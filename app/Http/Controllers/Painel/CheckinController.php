<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Ingresso;
use App\Support\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Vendas → Check-in (portaria pelo navegador). Lê o QR (FF1.<codigo>.<hmac>, assinatura conferida antes do banco),
 * o número do ingresso (FF-0001-000123) ou a busca por nome. Toda leitura vai para checkin_log.
 */
class CheckinController extends Controller
{
    public const RESULTADOS = [
        'aceito' => 'Entrada liberada', 'ja_utilizado' => 'Ingresso já utilizado', 'cancelado' => 'Ingresso cancelado',
        'outro_evento' => 'Ingresso de outro evento', 'inexistente' => 'Ingresso não encontrado',
        'assinatura_invalida' => 'QR Code inválido (assinatura não confere)', 'fora_do_horario' => 'Check-in fora do horário do evento',
        'setor_nao_permitido' => 'Setor não permitido',
    ];

    public function index(Request $request)
    {
        Gate::authorize('checkin.realizar');
        [$eventos, $evento] = ParticipantesController::eventoEscolhido($request);
        $numeros = $evento ? self::numeros($evento) : null;
        $ultimos = $evento ? $this->ultimos($evento) : collect();

        return view('painel.checkin.index', compact('eventos', 'evento', 'numeros', 'ultimos'));
    }

    /** POST JSON: { codigo | ingresso (uuid), sentido: entrada|saida } */
    public function registrar(Request $request, Evento $evento)
    {
        Gate::authorize('checkin.realizar');
        $this->autorizar($evento);
        $d = $request->validate(['codigo' => ['nullable', 'string', 'max:120'], 'ingresso' => ['nullable', 'string', 'max:40'], 'sentido' => ['nullable', 'in:entrada,saida']]);
        $sentido = $d['sentido'] ?? 'entrada';
        $codigo = trim((string) ($d['codigo'] ?? ''));
        $metodo = 'busca_manual';
        $ingresso = null;
        $resultado = null;

        if (! empty($d['ingresso'])) {
            $ingresso = Ingresso::where('uuid', $d['ingresso'])->first();
        } elseif (preg_match('/^FF1\.([A-Za-z0-9_-]{43})\.([0-9a-f]{16})$/', $codigo, $m)) {
            $metodo = 'qr_code';
            $esperado = substr(hash_hmac('sha256', $m[1], (string) config('app.key')), 0, 16);
            if (! hash_equals($esperado, $m[2])) {
                $resultado = 'assinatura_invalida';
            } else {
                $ingresso = Ingresso::withoutGlobalScope('organizador')->where('codigo_qr', $m[1])->first();
            }
        } elseif (preg_match('/^(?:FF-?)?(?:(\d{1,6})-)?(\d{1,6})$/i', preg_replace('/\s+/', '', $codigo), $m)) {
            $metodo = 'numero_ingresso';
            if ($m[1] === '' || (int) $m[1] === $evento->id) {
                $ingresso = Ingresso::where('evento_id', $evento->id)->where('numero', (int) $m[2])->first();
            } else {
                $resultado = 'outro_evento';
            }
        } else {
            $resultado = 'inexistente';
        }

        if (! $resultado) {
            $resultado = $this->avaliar($evento, $ingresso, $sentido);
        }

        DB::transaction(function () use ($evento, $ingresso, $resultado, $sentido, $metodo, $codigo) {
            DB::table('checkin_log')->insert([
                'organizador_id' => $evento->organizador_id, 'evento_id' => $evento->id,
                'ingresso_id' => $ingresso && $ingresso->evento_id === $evento->id ? $ingresso->id : null,
                'usuario_id' => auth()->id(), 'sentido' => $sentido, 'metodo' => $metodo,
                'codigo_lido' => $codigo !== '' ? mb_substr($codigo, 0, 100) : null, 'resultado' => $resultado, 'lido_em' => now('UTC'),
            ]);
            if ($resultado === 'aceito') {
                if ($sentido === 'entrada') {
                    // UPDATE condicional: dois portões lendo o mesmo QR ao mesmo tempo não passam os dois.
                    $ok = DB::update("UPDATE ingresso SET status = 'utilizado', dentro_do_evento = 1, total_checkins = total_checkins + 1,
                        primeiro_checkin_em = COALESCE(primeiro_checkin_em, UTC_TIMESTAMP()), ultimo_checkin_em = UTC_TIMESTAMP()
                        WHERE id = ? AND (status = 'valido' OR (status = 'utilizado' AND dentro_do_evento = 0 AND ? = 1))",
                        [$ingresso->id, (int) $evento->permite_reentrada]);
                    abort_if(! $ok, 409, 'Ingresso acabou de ser lido em outro portão.');
                } else {
                    DB::update('UPDATE ingresso SET dentro_do_evento = 0 WHERE id = ?', [$ingresso->id]);
                }
            }
        });

        $ingresso?->loadMissing(['participante', 'tipo', 'lote']);

        return response()->json([
            'resultado' => $resultado,
            'aceito' => $resultado === 'aceito',
            'mensagem' => $resultado === 'aceito' && $sentido === 'saida' ? 'Saída registrada'
                : ($resultado === 'fora_do_horario' ? (\App\Support\JanelaCheckin::situacao($evento)['motivo'] ?? self::RESULTADOS[$resultado]) : (self::RESULTADOS[$resultado] ?? $resultado)),
            'ingresso' => $ingresso && $ingresso->evento_id === $evento->id ? [
                'numero' => $ingresso->numeroExibicao(), 'nome' => $ingresso->participante?->nome, 'tipo' => $ingresso->tipo?->nome,
                'lote' => $ingresso->lote?->nome, 'primeiro_checkin' => $ingresso->primeiro_checkin_em ? data_local($ingresso->fresh()->primeiro_checkin_em, 'd/m H:i') : null,
            ] : null,
            'numeros' => self::numeros($evento),
        ]);
    }

    /** GET JSON: busca por nome, CPF ou número para o check-in manual. */
    public function buscar(Request $request, Evento $evento)
    {
        Gate::authorize('checkin.realizar');
        $this->autorizar($evento);
        $b = trim((string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:60']])['q']);
        $digitos = preg_replace('/\D/', '', $b);
        $lista = Ingresso::with(['participante', 'tipo'])->where('evento_id', $evento->id)
            ->where(fn ($q) => $q->whereHas('participante', fn ($p) => $p->where('nome', 'like', "%{$b}%")
                    ->when(strlen($digitos) >= 4, fn ($x) => $x->orWhere('cpf', 'like', "%{$digitos}%")))
                ->when($digitos !== '' && strlen($digitos) <= 6, fn ($x) => $x->orWhere('numero', (int) $digitos)))
            ->orderBy('numero')->limit(20)->get();

        return response()->json($lista->map(fn ($i) => [
            'uuid' => $i->uuid, 'numero' => $i->numeroExibicao(), 'nome' => $i->participante?->nome, 'tipo' => $i->tipo?->nome,
            'status' => $i->status, 'status_texto' => Ingresso::STATUS[$i->status] ?? $i->status, 'dentro' => (bool) $i->dentro_do_evento,
        ]));
    }

    /** Desfaz a última entrada de um ingresso (leitura por engano). */
    public function desfazer(Request $request, Evento $evento)
    {
        Gate::authorize('checkin.configurar');
        $this->autorizar($evento);
        $ingresso = Ingresso::where('evento_id', $evento->id)->where('uuid', $request->validate(['ingresso' => ['required', 'string']])['ingresso'])->firstOrFail();
        abort_unless($ingresso->status === 'utilizado', 422);
        $antes = $ingresso->getAttributes();
        DB::update("UPDATE ingresso SET status = 'valido', dentro_do_evento = 0, primeiro_checkin_em = NULL, ultimo_checkin_em = NULL, total_checkins = 0 WHERE id = ?", [$ingresso->id]);
        Auditoria::registrar('checkin.desfazer', $ingresso->fresh(), $antes);

        return back()->with('ok', "Check-in de {$ingresso->numeroExibicao()} desfeito: o ingresso voltou a ser válido.");
    }

    private function avaliar(Evento $evento, ?Ingresso $ingresso, string $sentido): string
    {
        if (! $ingresso) {
            return 'inexistente';
        }
        if ($ingresso->evento_id !== $evento->id) {
            return 'outro_evento';
        }
        if (in_array($ingresso->status, ['cancelado', 'reembolsado', 'transferido'], true)) {
            return 'cancelado';
        }
        if ($sentido === 'saida') {
            return $ingresso->dentro_do_evento ? 'aceito' : 'ja_utilizado';
        }
        if (! \App\Support\JanelaCheckin::situacao($evento)['aberta']) {
            return 'fora_do_horario'; // só no dia do evento, da abertura dos portões ao término
        }
        if ($ingresso->status === 'utilizado' && ! ($evento->permite_reentrada && ! $ingresso->dentro_do_evento)) {
            return 'ja_utilizado';
        }

        return 'aceito';
    }

    public static function numeros(Evento $evento): array
    {
        $r = Ingresso::where('evento_id', $evento->id)->selectRaw("SUM(status IN ('valido','utilizado')) total, SUM(status = 'utilizado') entraram, SUM(dentro_do_evento = 1) dentro")->first();

        return ['total' => (int) $r->total, 'entraram' => (int) $r->entraram, 'dentro' => (int) $r->dentro,
            'pct' => $r->total ? round(100 * $r->entraram / $r->total) : 0, 'capacidade' => $evento->capacidade];
    }

    private function ultimos(Evento $evento)
    {
        return DB::table('checkin_log as c')->leftJoin('ingresso as i', 'i.id', '=', 'c.ingresso_id')->leftJoin('participante as p', 'p.ingresso_id', '=', 'i.id')
            ->leftJoin('usuario as u', 'u.id', '=', 'c.usuario_id')->where('c.evento_id', $evento->id)
            ->select('c.resultado', 'c.sentido', 'c.metodo', 'c.lido_em', 'c.codigo_lido', 'i.uuid', 'i.numero', 'i.evento_id', 'i.status', 'p.nome', 'u.nome as operador')
            ->orderByDesc('c.id')->limit(15)->get();
    }

    private function autorizar(Evento $evento): void
    {
        abort_unless(Evento::visiveis()->whereKey($evento->id)->exists(), 404);
    }
}
