<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\OrganizadorUsuario;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;

/** Painel do produtor: indicadores somados de vw_dashboard_evento e próximos eventos. */
class DashboardController extends Controller
{
    public function __invoke(Tenant $tenant)
    {
        if (! $tenant->pode('dashboard.ver')) {
            // Perfis operacionais (portaria, promoter) caem direto no que podem usar.
            return $tenant->pode('evento.ver')
                ? redirect()->route('eventos.index')
                : view('painel.sem-painel');
        }

        $permitidos = $tenant->eventosPermitidos();
        $base = DB::table('vw_dashboard_evento')->where('organizador_id', $tenant->id())
            ->when($permitidos !== null, fn ($q) => $q->whereIn('evento_id', $permitidos ?: [0]));

        $totais = (clone $base)->selectRaw("
            COUNT(*) AS eventos,
            SUM(status = 'publicado') AS publicados,
            COALESCE(SUM(ingressos_vendidos), 0) AS vendidos,
            COALESCE(SUM(ingressos_ofertados), 0) AS ofertados,
            COALESCE(SUM(receita_bruta), 0) AS receita,
            COALESCE(SUM(checkins_realizados), 0) AS checkins,
            COALESCE(SUM(comissao_plataforma), 0) AS comissao
        ")->first();

        $proximos = (clone $base)->whereIn('status', ['rascunho', 'publicado', 'pausado'])
            ->where('data_inicio', '>=', now()->subDay())
            ->orderBy('data_inicio')->limit(6)->get();
        // A view não traz o uuid (usado nas rotas): busca para os links "Abrir" e "Editar".
        $uuids = DB::table('evento')->whereIn('id', $proximos->pluck('evento_id'))->pluck('uuid', 'id');
        $proximos->each(fn ($e) => $e->uuid = $uuids[$e->evento_id] ?? null);

        $org = $tenant->organizador();
        $passos = [
            ['ok' => $org->dadosCompletos(), 'texto' => 'Completar os dados da organização (representante legal)', 'rota' => 'organizacao.edit'],
            ['ok' => filled($org->logo_url) && filled($org->descricao), 'texto' => 'Enviar a logomarca e a descrição da página pública', 'rota' => 'organizacao.edit'],
            ['ok' => Evento::exists(), 'texto' => 'Criar o primeiro evento', 'rota' => 'eventos.create'],
            ['ok' => Evento::whereHas('lotes')->exists(), 'texto' => 'Cadastrar tipos de ingresso e lotes', 'rota' => 'eventos.index'],
            ['ok' => OrganizadorUsuario::where('status', '<>', 'removido')->count() > 1, 'texto' => 'Convidar a equipe (portaria, gerente, financeiro)', 'rota' => 'equipe.index'],
            ['ok' => false, 'texto' => 'Conectar a conta do Mercado Pago (próxima fase)', 'rota' => null],
        ];

        return view('painel.dashboard', compact('totais', 'proximos', 'passos'));
    }
}
