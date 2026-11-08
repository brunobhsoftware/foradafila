<?php

namespace App\Support;

use App\Models\Evento;
use App\Rules\DocumentoValido;
use Illuminate\Support\Facades\DB;

/**
 * Formulários de inscrição (V015): campos pedidos para cada participante na compra. Os campos do catálogo
 * (campo_catalogo) gravam na coluna de mesmo nome em `participante`; os personalizados, em `participante_resposta`.
 * Toda organização tem um formulário padrão (criado na primeira vez): Nome completo + E-mail.
 */
class Formularios
{
    public const TIPOS_PERSONALIZADOS = ['texto' => 'Texto curto', 'texto_longo' => 'Texto longo', 'numero' => 'Número', 'data' => 'Data',
        'selecao' => 'Lista de opções', 'checkbox' => 'Caixa de marcar (sim/não)'];

    public static function ativo(): bool
    {
        return Esquema::temV015();
    }

    /** Formulário padrão da organização; cria "Nome completo e e-mail" se ainda não houver nenhum. */
    public static function padrao(int $organizadorId): ?object
    {
        if (! self::ativo()) {
            return null;
        }
        $f = DB::table('formulario')->where('organizador_id', $organizadorId)->whereNull('evento_id')->orderByDesc('padrao')->orderBy('id')->first();
        if ($f && ! $f->padrao) {
            DB::table('formulario')->where('id', $f->id)->update(['padrao' => 1]);
            $f->padrao = 1;
        }
        if (! $f) {
            $id = DB::table('formulario')->insertGetId(['organizador_id' => $organizadorId, 'nome' => 'Padrão — Nome completo e e-mail', 'padrao' => 1]);
            $cat = DB::table('campo_catalogo')->whereIn('codigo', ['nome', 'email'])->pluck('id', 'codigo');
            DB::table('formulario_campo')->insert([
                ['formulario_id' => $id, 'campo_catalogo_id' => $cat['nome'], 'rotulo' => 'Nome completo', 'obrigatorio' => 1, 'ordem' => 1],
                ['formulario_id' => $id, 'campo_catalogo_id' => $cat['email'], 'rotulo' => 'E-mail', 'obrigatorio' => 1, 'ordem' => 2],
            ]);
            $f = DB::table('formulario')->find($id);
        }

        return $f;
    }

    public static function doEvento(Evento $evento): ?object
    {
        if (! self::ativo()) {
            return null;
        }
        $f = $evento->formulario_id ? DB::table('formulario')->where('id', $evento->formulario_id)->where('organizador_id', $evento->organizador_id)->first() : null;

        return $f ?: self::padrao($evento->organizador_id);
    }

    /**
     * Campos do formulário, em ordem. O nome completo vem sempre (é obrigatório para o ingresso).
     * @return array<int, array{id:int, chave:string, coluna:?string, rotulo:string, tipo:string, obrigatorio:bool, opcoes:array, ajuda:?string}>
     */
    public static function campos(?object $formulario): array
    {
        if (! $formulario) {
            return [['id' => 0, 'chave' => 'nome', 'coluna' => 'nome', 'rotulo' => 'Nome completo', 'tipo' => 'texto', 'obrigatorio' => true, 'opcoes' => [], 'ajuda' => null]];
        }
        $linhas = DB::table('formulario_campo as fc')->leftJoin('campo_catalogo as cc', 'cc.id', '=', 'fc.campo_catalogo_id')
            ->where('fc.formulario_id', $formulario->id)->where('fc.ativo', 1)->orderBy('fc.ordem')->orderBy('fc.id')
            ->get(['fc.*', 'cc.codigo', 'cc.tipo as tipo_catalogo', 'cc.opcoes as opcoes_catalogo']);
        $campos = $linhas->map(fn ($l) => [
            'id' => $l->id, 'chave' => $l->codigo ?: 'c'.$l->id, 'coluna' => $l->codigo,
            'rotulo' => $l->rotulo, 'tipo' => $l->codigo ? $l->tipo_catalogo : $l->tipo_personalizado,
            'obrigatorio' => (bool) $l->obrigatorio || $l->codigo === 'nome',
            'opcoes' => json_decode((string) ($l->codigo ? $l->opcoes_catalogo : $l->opcoes), true) ?: [], 'ajuda' => $l->ajuda,
        ])->values()->all();
        if (! collect($campos)->contains('chave', 'nome')) {
            array_unshift($campos, ['id' => 0, 'chave' => 'nome', 'coluna' => 'nome', 'rotulo' => 'Nome completo', 'tipo' => 'texto', 'obrigatorio' => true, 'opcoes' => [], 'ajuda' => null]);
        }

        return $campos;
    }

    /** Regras de validação de participantes.*.<chave>. */
    public static function regras(array $campos): array
    {
        $r = [];
        foreach ($campos as $c) {
            $base = [$c['obrigatorio'] ? ($c['tipo'] === 'aceite' || $c['tipo'] === 'checkbox' ? 'accepted' : 'required') : 'nullable'];
            $tipo = match ($c['tipo']) {
                'email' => ['email:filter', 'max:150'],
                'cpf' => [new DocumentoValido('PF')],
                'telefone' => ['regex:/^\D*(\d\D*){10,11}$/'],
                'data' => ['date'],
                'numero' => ['numeric'],
                'cep' => ['regex:/^\D*(\d\D*){8}$/'],
                'uf' => ['size:2'],
                'selecao' => $c['opcoes'] ? ['in:'.implode(',', array_map(fn ($o) => str_replace(',', '', (string) $o), $c['opcoes']))] : ['string'],
                'aceite', 'checkbox' => [],
                'texto_longo' => ['string', 'max:2000'],
                default => ['string', 'max:150'],
            };
            $r['participantes.*.'.$c['chave']] = array_merge($base, $tipo);
        }

        return $r;
    }

    /** Nomes amigáveis dos campos para as mensagens de erro. */
    public static function nomes(array $campos): array
    {
        return collect($campos)->mapWithKeys(fn ($c) => ['participantes.*.'.$c['chave'] => mb_strtolower($c['rotulo'])])->all();
    }

    /** Normaliza os dados de um participante (só dígitos em CPF/telefone/CEP, maiúsculas na UF). */
    public static function normalizar(array $campos, array $p): array
    {
        $saida = [];
        foreach ($campos as $c) {
            $v = $p[$c['chave']] ?? null;
            $v = is_string($v) ? trim($v) : $v;
            $v = match ($c['tipo']) {
                'cpf', 'telefone', 'cep' => preg_replace('/\D/', '', (string) $v) ?: null,
                'uf' => $v ? strtoupper($v) : null,
                'email' => $v ? mb_strtolower($v) : null,
                'aceite', 'checkbox' => $v ? '1' : null,
                default => $v === '' ? null : $v,
            };
            $saida[$c['chave']] = $v;
        }

        return $saida;
    }

    /** Colunas de `participante` preenchidas a partir dos dados (campos do catálogo). */
    public static function colunas(array $campos, array $p): array
    {
        $col = [];
        foreach ($campos as $c) {
            if (! $c['coluna'] || ! array_key_exists($c['chave'], $p) || $p[$c['chave']] === null) {
                continue;
            }
            $col[$c['coluna']] = $c['tipo'] === 'aceite' ? now() : $p[$c['chave']];
        }

        return $col;
    }

    /** Respostas dos campos personalizados (participante_resposta). */
    public static function gravarRespostas(int $participanteId, array $campos, array $p): void
    {
        foreach ($campos as $c) {
            if ($c['coluna'] || ! $c['id'] || ($p[$c['chave']] ?? null) === null) {
                continue;
            }
            DB::table('participante_resposta')->updateOrInsert(['participante_id' => $participanteId, 'formulario_campo_id' => $c['id']],
                ['valor' => mb_substr((string) $p[$c['chave']], 0, 2000)]);
        }
    }

    /** O formulário pede CPF obrigatório? (limite de ingressos por CPF continua funcionando). */
    public static function exigeCpf(array $campos): bool
    {
        return collect($campos)->contains(fn ($c) => $c['chave'] === 'cpf' && $c['obrigatorio']);
    }

    /**
     * Cria o participante do ingresso com os dados do formulário (colunas + respostas personalizadas) e registra
     * no cadastro único. Sem e-mail no formulário, o 1º participante recebe o e-mail do comprador.
     */
    public static function criarParticipante(int $organizadorId, int $eventoId, int $ingressoId, array $p, array $campos, ?\App\Models\Comprador $comprador, int $i, string $origem): \App\Models\Participante
    {
        $eleMesmo = $i === 0 && $comprador && mb_strtolower((string) ($p['nome'] ?? '')) === mb_strtolower($comprador->nome);
        $colunas = $campos ? self::colunas($campos, $p) : array_filter(['nome' => $p['nome'] ?? null, 'cpf' => $p['cpf'] ?? null]);
        $colunas['nome'] = $colunas['nome'] ?? ($p['nome'] ?? $comprador?->nome ?? 'Participante');
        if (empty($colunas['email']) && $i === 0) {
            $colunas['email'] = $comprador?->email;
        }
        $part = new \App\Models\Participante();
        $part->forceFill($colunas + ['ingresso_id' => $ingressoId, 'organizador_id' => $organizadorId, 'evento_id' => $eventoId, 'mesmo_que_comprador' => $eleMesmo])->save();
        if ($campos) {
            self::gravarRespostas($part->id, $campos, $p);
        }
        CadastroPessoa::registrar($colunas['cpf'] ?? null, $colunas['nome'], $colunas['email'] ?? ($eleMesmo ? $comprador?->email : null),
            $colunas['telefone'] ?? $colunas['whatsapp'] ?? ($eleMesmo ? $comprador?->telefone : null), $origem);

        return $part;
    }
}
