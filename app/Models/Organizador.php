<?php

namespace App\Models;

use App\Models\Concerns\TemUuid;
use App\Support\Documento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organizador extends Model
{
    use TemUuid;

    protected $table = 'organizador';

    public $timestamps = false;

    protected $fillable = [
        'tipo_pessoa', 'documento', 'razao_social', 'nome_fantasia', 'slug', 'logo_url', 'descricao',
        'site', 'instagram', 'whatsapp', 'email_contato', 'email_financeiro',
        'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'situacao', 'situacao_motivo',
    ];

    public const SITUACOES = [
        'pendente' => 'Pendente',
        'ativo' => 'Ativo',
        'bloqueado_debito' => 'Bloqueado por débito',
        'suspenso' => 'Suspenso',
        'encerrado' => 'Encerrado',
    ];

    public function nomeExibicao(): string
    {
        return $this->nome_fantasia ?: $this->razao_social;
    }

    public function documentoFormatado(): string
    {
        return Documento::formatar($this->documento);
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(OrganizadorUsuario::class)->withoutGlobalScope('organizador');
    }

    public static function slugDisponivel(string $base, ?int $ignorarId = null): string
    {
        $base = Str::slug($base) ?: 'organizador';
        $base = Str::limit($base, 70, '');
        $slug = $base;
        $n = 2;
        while (static::where('slug', $slug)->when($ignorarId, fn ($q) => $q->where('id', '<>', $ignorarId))->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
