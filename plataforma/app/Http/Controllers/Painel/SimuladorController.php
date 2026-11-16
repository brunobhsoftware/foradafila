<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Organizador;
use App\Pagamentos\MercadoPago;
use App\Support\Esquema;
use App\Support\MotorTaxas;
use App\Support\Recebimento;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Gestão → Simulador de vendas: quanto a empresa recebe por ingresso, no Pix e no cartão, com a configuração atual. */
class SimuladorController extends Controller
{
    public const VALORES = [20, 50, 100, 200, 500];

    public function index(Request $request)
    {
        Gate::authorize('financeiro.ver');
        $org = Organizador::findOrFail(app(Tenant::class)->id());
        $d = $request->validate(['valor' => ['nullable', 'numeric', 'min:1', 'max:100000'], 'taxa' => ['nullable', 'in:embutida,repassada']]);
        $valor = round((float) str_replace(',', '.', (string) ($d['valor'] ?? 50)), 2);
        $repassada = isset($d['taxa']) ? $d['taxa'] === 'repassada' : (Esquema::temV010() && (bool) $org->taxa_comprador);
        // Onde as vendas caem hoje: conta conectada (direta), aplicação manual da empresa (propria) ou conta da plataforma.
        $modo = Recebimento::contaAtual($org->id)?->modo ?? 'plataforma';
        $pct = ['pix' => MercadoPago::tarifaPct('pix'), 'cartao' => MercadoPago::tarifaPct('cartao')];

        return view('painel.simulador', [
            'org' => $org, 'valor' => $valor, 'repassada' => $repassada, 'modo' => $modo, 'pct' => $pct,
            'simulacao' => self::simular($org, $valor, $repassada, $modo, $pct),
            'tabela' => collect(self::VALORES)->map(fn ($v) => self::simular($org, $v, $repassada, $modo, $pct)),
        ]);
    }

    /**
     * Conta de um ingresso. Por forma de pagamento: comprador paga, taxa da plataforma, tarifa estimada do Mercado Pago,
     * quanto dela a plataforma cobre e quanto a empresa recebe.
     */
    public static function simular(Organizador $org, float $valor, bool $repassada, string $modo, array $pct): array
    {
        $taxa = Esquema::temV008() ? (float) MotorTaxas::calcular($org, 'ingresso_pago', $valor)['taxa'] : 0.0;
        $total = round($repassada ? $valor + $taxa : $valor, 2);
        $r = ['valor' => $valor, 'taxa' => $taxa, 'total' => $total];
        foreach ($pct as $forma => $p) {
            $tarifa = round($total * $p / 100, 2);
            if ($modo === 'propria') {
                // Aplicação manual: a tarifa do Mercado Pago é da empresa e a taxa da plataforma vem em fatura.
                $coberta = 0.0;
                $recebe = round($total - $tarifa - $taxa, 2);
            } elseif ($modo === 'direta' && $taxa > 0) {
                // Conta conectada: a plataforma cobre a tarifa até o valor da própria taxa.
                $coberta = min($tarifa, $taxa);
                $recebe = round($total - $taxa - ($tarifa - $coberta), 2);
            } elseif ($modo === 'direta') {
                $coberta = 0.0; // sem taxa da plataforma não há o que abater
                $recebe = round($total - $tarifa, 2);
            } else {
                // Recebimento pela plataforma: a tarifa é toda da plataforma; a empresa recebe o repasse.
                $coberta = $tarifa;
                $recebe = round($total - $taxa, 2);
            }
            $r[$forma] = ['pct' => $p, 'tarifa' => $tarifa, 'coberta' => round($coberta, 2), 'da_empresa' => round($tarifa - $coberta, 2), 'recebe' => max($recebe, 0)];
        }

        return $r;
    }
}
