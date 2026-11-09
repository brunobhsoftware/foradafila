<?php

namespace App\Models;

use App\Models\Concerns\PertenceAoOrganizador;
use App\Models\Concerns\TemUuid;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Evento extends Model
{
    use PertenceAoOrganizador, TemUuid;

    protected $table = 'evento';

    public $timestamps = false;

    protected $fillable = [
        'local_id', 'categoria_id', 'conta_pagamento_id', 'titulo', 'slug', 'resumo', 'descricao', 'imagem_capa_url',
        'formato', 'link_online', 'classificacao', 'data_inicio', 'data_fim', 'abertura_portoes', 'capacidade',
        'visibilidade', 'max_ingressos_pedido', 'max_ingressos_cpf', 'exige_cpf_participante', 'nominal',
        'permite_transferencia', 'transferencia_ate_h', 'permite_reentrada', 'politica_cancelamento', 'criado_por',
        'taxa_comprador', // V010
        'formulario_id', // V015
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'datetime', 'data_fim' => 'datetime', 'abertura_portoes' => 'datetime',
            'publicado_em' => 'datetime', 'cancelado_em' => 'datetime',
            'exige_cpf_participante' => 'boolean', 'nominal' => 'boolean',
            'permite_transferencia' => 'boolean', 'permite_reentrada' => 'boolean',
        ];
    }

    public const STATUS = [
        'rascunho' => 'Rascunho', 'publicado' => 'Publicado', 'pausado' => 'Vendas pausadas',
        'encerrado' => 'Encerrado', 'cancelado' => 'Cancelado',
    ];

    public const FORMATOS = ['presencial' => 'Presencial', 'online' => 'Online', 'hibrido' => 'Híbrido'];

    public const VISIBILIDADES = [
        'publico' => 'Público — aparece na vitrine e na busca',
        'nao_listado' => 'Não listado — só quem tem o link',
        'privado' => 'Privado — exige link de acesso privado',
    ];

    public const CLASSIFICACOES = ['livre' => 'Livre', '10' => '10 anos', '12' => '12 anos', '14' => '14 anos', '16' => '16 anos', '18' => '18 anos'];

    /** Mudanças de status possíveis: status atual => [novo status => permissão exigida]. */
    public const TRANSICOES = [
        'rascunho' => ['publicado' => 'evento.publicar', 'cancelado' => 'evento.cancelar'],
        // "rascunho" = ocultar: sai do site (página, vitrine e página da organização); ingressos já emitidos continuam valendo
        'publicado' => ['pausado' => 'evento.publicar', 'rascunho' => 'evento.publicar', 'encerrado' => 'evento.publicar', 'cancelado' => 'evento.cancelar'],
        'pausado' => ['publicado' => 'evento.publicar', 'rascunho' => 'evento.publicar', 'encerrado' => 'evento.publicar', 'cancelado' => 'evento.cancelar'],
        'encerrado' => [],
        'cancelado' => [],
    ];

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(EventoCategoria::class, 'categoria_id');
    }

    public function contaPagamento(): BelongsTo
    {
        return $this->belongsTo(ContaPagamento::class);
    }

    public function tipos(): HasMany
    {
        return $this->hasMany(IngressoTipo::class)->orderBy('ordem')->orderBy('id');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class)->orderBy('ordem')->orderBy('id');
    }

    /** Respeita o vínculo "só estes eventos" (organizador_usuario_evento). */
    public function scopeVisiveis(Builder $query): Builder
    {
        $ids = app(Tenant::class)->eventosPermitidos();

        return $ids === null ? $query : $query->whereIn('evento.id', $ids ?: [0]);
    }

    public function indicadores(): object
    {
        return DB::table('vw_dashboard_evento')->where('evento_id', $this->id)->first()
            ?? (object) ['ingressos_vendidos' => 0, 'ingressos_reservados' => 0, 'ingressos_ofertados' => 0,
                'pedidos_pagos' => 0, 'receita_bruta' => 0, 'descontos' => 0, 'checkins_realizados' => 0, 'comissao_plataforma' => 0];
    }

    /**
     * O que falta para publicar — as mesmas regras do trigger trg_evento_bu,
     * mostradas antes para o produtor não ser surpreendido pelo erro do banco.
     *
     * @return array<int, array{ok: bool, texto: string}>
     */
    public function checklistPublicacao(): array
    {
        $org = $this->organizador;
        $somaLotes = (int) $this->lotes()->sum('quantidade');
        $temPago = $this->lotes()->where('preco', '>', 0)->exists();
        $termoAceito = DB::table('termo_aceite as ta')->join('termo as t', 't.id', '=', 'ta.termo_id')
            ->where('ta.organizador_id', $this->organizador_id)
            ->where('t.tipo', 'responsabilidade_organizador')->where('t.vigente', 1)->exists();
        $contaId = \App\Support\Recebimento::contaAtual($this->organizador_id)?->id ?: $this->conta_pagamento_id;
        $contaAtiva = $contaId && ContaPagamento::withoutGlobalScopes()->whereKey($contaId)
            ->where('status', 'ativa')->where('ambiente', 'producao')->exists();

        $itens = [
            ['ok' => $org->situacao === 'ativo', 'texto' => 'Organização ativa (sem débito em aberto)'],
            ['ok' => $termoAceito, 'texto' => 'Termo de responsabilidade aceito'],
            ['ok' => $this->lotes()->exists(), 'texto' => 'Pelo menos um lote de ingressos'],
            $this->capacidade === null
                ? ['ok' => true, 'texto' => 'Sem capacidade máxima definida: '.numero($somaLotes).' ingressos nos lotes (responsabilidade da organização)']
                : ['ok' => $somaLotes <= $this->capacidade, 'texto' => 'Soma dos lotes ('.numero($somaLotes).') dentro da capacidade ('.numero($this->capacidade).')'],
            ['ok' => $this->data_inicio?->isFuture() ?? false, 'texto' => 'Data de início no futuro'],
        ];
        if ($temPago) {
            $itens[] = ['ok' => $contaAtiva, 'texto' => 'Dados para o repasse cadastrados em Financeiro (obrigatório para ingresso pago)'];
        }

        return $itens;
    }

    public function capacidadeTexto(): string
    {
        return $this->capacidade === null ? 'sem limite definido' : numero($this->capacidade);
    }

    public function podePublicar(): bool
    {
        return collect($this->checklistPublicacao())->every('ok');
    }

    public static function slugDisponivel(string $titulo, ?int $ignorarId = null): string
    {
        $base = Str::limit(Str::slug($titulo) ?: 'evento', 150, '');
        $slug = $base;
        $n = 2;
        // slug é único na plataforma inteira (URL pública), então a busca ignora o organizador.
        while (static::withoutGlobalScope('organizador')->where('slug', $slug)
            ->when($ignorarId, fn ($q) => $q->where('id', '<>', $ignorarId))->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    /**
     * Código fixo do link público (/e/{codigo}): 10 caracteres do uuid, que nunca muda — o nome (slug) pode mudar
     * e quebrar links já compartilhados (decisão do Bruno, 08/11/2026). Links antigos com o nome continuam valendo.
     */
    public function codigoPublico(): string
    {
        return substr(str_replace('-', '', (string) $this->uuid), 0, 10);
    }

    public function linkPublico(array $query = []): string
    {
        return route('evento.publico', ['slug' => $this->codigoPublico()] + $query);
    }
}
