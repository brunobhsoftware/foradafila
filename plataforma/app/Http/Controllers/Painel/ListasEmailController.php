<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Organizador;
use App\Support\Auditoria;
use App\Support\Esquema;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Divulgação → Listas de e-mail (V017): listas da empresa, montadas à mão, por arquivo CSV ou com os compradores e
 * participantes de eventos anteriores. Quem se descadastrou (email_descadastro) fica na lista, marcado, e não recebe envios.
 */
class ListasEmailController extends Controller
{
    public const MAX_CSV_KB = 10240;

    public function index(Tenant $tenant)
    {
        Gate::authorize('campanha.gerenciar');
        abort_unless(Esquema::temV017(), 404);
        $org = $tenant->id();
        $listas = DB::table('lista_email as l')->where('l.organizador_id', $org)
            ->select('l.*', DB::raw('(SELECT COUNT(*) FROM lista_email_contato c WHERE c.lista_id = l.id) as total'))
            ->orderByDesc('l.id')->get();

        return view('painel.listas-email.index', [
            'listas' => $listas,
            'descadastrados' => DB::table('email_descadastro')->where('organizador_id', $org)->count(),
        ]);
    }

    public function criar(Request $request, Tenant $tenant)
    {
        Gate::authorize('campanha.gerenciar');
        abort_unless(Esquema::temV017(), 404);
        $d = $request->validate(['nome' => ['required', 'string', 'max:100'], 'descricao' => ['nullable', 'string', 'max:255']]);
        $uuid = (string) Str::uuid();
        DB::table('lista_email')->insert($d + ['uuid' => $uuid, 'organizador_id' => $tenant->id(), 'criado_por' => auth()->id()]);
        Auditoria::registrar('lista_email.criar', Organizador::findOrFail($tenant->id()), null, $d);

        return redirect()->route('listas-email.show', $uuid)->with('ok', "Lista “{$d['nome']}” criada. Agora adicione os e-mails.");
    }

    public function show(Request $request, Tenant $tenant, string $uuid)
    {
        Gate::authorize('campanha.gerenciar');
        $lista = $this->lista($tenant, $uuid);
        $busca = trim((string) $request->query('busca'));
        $contatos = DB::table('lista_email_contato as c')->where('c.lista_id', $lista->id)
            ->leftJoin('email_descadastro as d', fn ($j) => $j->on('d.email', '=', 'c.email')->where('d.organizador_id', $tenant->id()))
            ->when($busca !== '', fn ($q) => $q->where(fn ($q) => $q->where('c.email', 'like', "%$busca%")->orWhere('c.nome', 'like', "%$busca%")))
            ->select('c.*', 'd.criado_em as descadastrado_em')->orderBy('c.email')->paginate(50)->withQueryString();

        return view('painel.listas-email.show', [
            'lista' => $lista, 'contatos' => $contatos, 'busca' => $busca,
            'total' => DB::table('lista_email_contato')->where('lista_id', $lista->id)->count(),
            'eventos' => Evento::visiveis()->orderByDesc('data_inicio')->get(['id', 'uuid', 'titulo', 'data_inicio']),
        ]);
    }

    public function renomear(Request $request, Tenant $tenant, string $uuid)
    {
        Gate::authorize('campanha.gerenciar');
        $lista = $this->lista($tenant, $uuid);
        $d = $request->validate(['nome' => ['required', 'string', 'max:100'], 'descricao' => ['nullable', 'string', 'max:255']]);
        DB::table('lista_email')->where('id', $lista->id)->update($d);

        return back()->with('ok', 'Lista salva.');
    }

    /** Colar e-mails: um por linha, "e-mail" ou "Nome; e-mail" (ou com vírgula/tab). */
    public function colar(Request $request, Tenant $tenant, string $uuid)
    {
        Gate::authorize('campanha.gerenciar');
        $lista = $this->lista($tenant, $uuid);
        $texto = (string) $request->validate(['texto' => ['required', 'string', 'max:500000']])['texto'];
        $linhas = array_map(fn ($l) => preg_split('/[;,\t]/', $l), preg_split('/\R/', $texto));

        return $this->gravar($tenant, $lista, $this->lerLinhas($linhas, false), 'manual', 'colados');
    }

    /** Importa um CSV (separador ; ou , — com ou sem cabeçalho; reconhece as colunas email, nome e telefone). */
    public function importar(Request $request, Tenant $tenant, string $uuid)
    {
        Gate::authorize('campanha.gerenciar');
        $lista = $this->lista($tenant, $uuid);
        $request->validate(['arquivo' => ['required', 'file', 'max:'.self::MAX_CSV_KB, 'mimes:csv,txt']],
            ['arquivo.max' => 'O arquivo pode ter no máximo 10 MB.', 'arquivo.mimes' => 'Envie um arquivo .csv ou .txt.'], ['arquivo' => 'arquivo']);
        $conteudo = file_get_contents($request->file('arquivo')->getRealPath());
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);
        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252'); // CSV salvo pelo Excel
        }
        $primeira = strtok($conteudo, "\n");
        $sep = substr_count($primeira, ';') >= substr_count($primeira, ',') ? ';' : ',';
        $linhas = array_map(fn ($l) => str_getcsv($l, $sep), preg_split('/\R/', $conteudo));

        return $this->gravar($tenant, $lista, $this->lerLinhas($linhas, true), 'csv', 'importados do arquivo');
    }

    /** Traz compradores e/ou participantes (com e-mail) de eventos da empresa. */
    public function deEventos(Request $request, Tenant $tenant, string $uuid)
    {
        Gate::authorize('campanha.gerenciar');
        $lista = $this->lista($tenant, $uuid);
        $d = $request->validate(['eventos' => ['required', 'array', 'min:1'], 'eventos.*' => ['string'],
            'quem' => ['required', 'in:compradores,participantes,todos']], ['eventos.required' => 'Escolha ao menos um evento.']);
        $ids = Evento::visiveis()->whereIn('uuid', $d['eventos'])->pluck('id')->all();
        abort_if(! $ids, 422);
        $contatos = [];
        if ($d['quem'] !== 'participantes') {
            DB::table('pedido as p')->join('comprador as c', 'c.id', '=', 'p.comprador_id')
                ->whereIn('p.evento_id', $ids)->whereIn('p.status', ['pago', 'reembolso_parcial'])
                ->select('c.email', 'c.nome', 'c.telefone', 'p.evento_id')->orderBy('p.id')
                ->each(function ($r) use (&$contatos) { $contatos[] = [$r->email, $r->nome, $r->telefone, 'evento:'.$r->evento_id]; });
        }
        if ($d['quem'] !== 'compradores') {
            DB::table('participante as pa')->join('ingresso as i', 'i.id', '=', 'pa.ingresso_id')
                ->whereIn('i.evento_id', $ids)->whereIn('i.status', ['valido', 'utilizado'])->whereNotNull('pa.email')
                ->select('pa.email', 'pa.nome', 'pa.telefone', 'i.evento_id')->orderBy('pa.id')
                ->each(function ($r) use (&$contatos) { $contatos[] = [$r->email, $r->nome, $r->telefone, 'evento:'.$r->evento_id]; });
        }
        $lidos = ['validos' => [], 'invalidos' => 0];
        foreach ($contatos as [$email, $nome, $tel, $origem]) {
            $email = mb_strtolower(trim((string) $email));
            if (self::emailValido($email)) {
                $lidos['validos'][$email] ??= ['email' => $email, 'nome' => $nome ? mb_substr($nome, 0, 150) : null, 'telefone' => $tel ? mb_substr($tel, 0, 20) : null, 'origem' => $origem];
            } else {
                $lidos['invalidos']++;
            }
        }

        return $this->gravar($tenant, $lista, $lidos, null, 'trazidos dos eventos');
    }

    public function removerContato(Tenant $tenant, string $uuid, int $contato)
    {
        Gate::authorize('campanha.gerenciar');
        $lista = $this->lista($tenant, $uuid);
        DB::table('lista_email_contato')->where('lista_id', $lista->id)->where('id', $contato)->delete();

        return back()->with('ok', 'E-mail removido da lista.');
    }

    public function excluir(Tenant $tenant, string $uuid)
    {
        Gate::authorize('campanha.gerenciar');
        $lista = $this->lista($tenant, $uuid);
        DB::table('lista_email')->where('id', $lista->id)->delete();
        Auditoria::registrar('lista_email.excluir', Organizador::findOrFail($tenant->id()), null, ['nome' => $lista->nome]);

        return redirect()->route('listas-email.index')->with('ok', "Lista “{$lista->nome}” excluída.");
    }

    public function exportar(Tenant $tenant, string $uuid)
    {
        Gate::authorize('campanha.gerenciar');
        $lista = $this->lista($tenant, $uuid);

        return response()->streamDownload(function () use ($lista, $tenant) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['email', 'nome', 'telefone', 'origem', 'descadastrado'], ';');
            DB::table('lista_email_contato as c')->where('c.lista_id', $lista->id)
                ->leftJoin('email_descadastro as d', fn ($j) => $j->on('d.email', '=', 'c.email')->where('d.organizador_id', $tenant->id()))
                ->select('c.*', 'd.id as desc')->orderBy('c.id')
                ->each(fn ($c) => fputcsv($out, [$c->email, $c->nome, $c->telefone, $c->origem, $c->desc ? 'sim' : ''], ';'));
            fclose($out);
        }, Str::slug($lista->nome).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function emailValido(string $email): bool
    {
        return mb_strlen($email) <= 150 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** @return array{validos: array<string, array>, invalidos: int} */
    private function lerLinhas(array $linhas, bool $cabecalho): array
    {
        $colEmail = null; $colNome = null; $colTel = null;
        $validos = []; $invalidos = 0;
        foreach ($linhas as $n => $cols) {
            $cols = array_map(fn ($c) => trim((string) $c, " \t\"'"), $cols);
            if (count(array_filter($cols, fn ($c) => $c !== '')) === 0) {
                continue;
            }
            if ($n === 0 && $cabecalho) {
                foreach ($cols as $i => $c) {
                    $c = Str::of($c)->ascii()->lower()->value();
                    if ($colEmail === null && str_contains($c, 'mail')) { $colEmail = $i; }
                    elseif ($colNome === null && str_contains($c, 'nome') || $c === 'name') { $colNome ??= $i; }
                    elseif ($colTel === null && (str_contains($c, 'telefone') || str_contains($c, 'celular') || str_contains($c, 'whats') || $c === 'phone')) { $colTel = $i; }
                }
                if ($colEmail !== null) {
                    continue;
                }
            }
            // sem cabeçalho: a coluna do e-mail é a que tem "@"; o nome é a primeira outra com texto
            $iEmail = $colEmail ?? collect($cols)->search(fn ($c) => str_contains($c, '@'));
            $email = $iEmail === false ? '' : mb_strtolower($cols[$iEmail] ?? '');
            if (! self::emailValido($email)) {
                $invalidos++;
                continue;
            }
            $nome = $colNome !== null ? ($cols[$colNome] ?? '') : (collect($cols)->except($iEmail)->first(fn ($c) => $c !== '' && ! preg_match('/^[\d\s()+-]+$/', $c)) ?? '');
            $tel = $colTel !== null ? preg_replace('/\D/', '', $cols[$colTel] ?? '') : '';
            $validos[$email] ??= ['email' => $email, 'nome' => $nome !== '' ? mb_substr($nome, 0, 150) : null, 'telefone' => $tel !== '' ? mb_substr($tel, 0, 20) : null];
        }

        return ['validos' => $validos, 'invalidos' => $invalidos];
    }

    private function gravar(Tenant $tenant, object $lista, array $lidos, ?string $origem, string $verbo)
    {
        $antes = DB::table('lista_email_contato')->where('lista_id', $lista->id)->count();
        foreach (array_chunk(array_values($lidos['validos']), 500) as $lote) {
            DB::table('lista_email_contato')->insertOrIgnore(array_map(fn ($c) => [
                'lista_id' => $lista->id, 'organizador_id' => $tenant->id(), 'email' => $c['email'], 'nome' => $c['nome'],
                'telefone' => $c['telefone'], 'origem' => $c['origem'] ?? $origem ?? 'manual',
            ], $lote));
        }
        $novos = DB::table('lista_email_contato')->where('lista_id', $lista->id)->count() - $antes;
        $repetidos = count($lidos['validos']) - $novos;
        Auditoria::registrar('lista_email.adicionar', Organizador::findOrFail($tenant->id()), null,
            ['lista' => $lista->nome, 'novos' => $novos, 'repetidos' => $repetidos, 'invalidos' => $lidos['invalidos'], 'origem' => $origem ?? 'eventos']);
        $msg = "$novos e-mail(s) $verbo.";
        if ($repetidos) { $msg .= " $repetidos já estavam na lista."; }
        if ($lidos['invalidos']) { $msg .= " {$lidos['invalidos']} linha(s) sem e-mail válido foram ignoradas."; }

        return redirect()->route('listas-email.show', $lista->uuid)->with('ok', $msg);
    }

    private function lista(Tenant $tenant, string $uuid): object
    {
        abort_unless(Esquema::temV017(), 404);

        return DB::table('lista_email')->where('organizador_id', $tenant->id())->where('uuid', $uuid)->first() ?? abort(404);
    }
}
