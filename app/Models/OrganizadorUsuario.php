<?php

namespace App\Models;

use App\Models\Concerns\PertenceAoOrganizador;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Vínculo de um usuário com um organizador: perfil de acesso e eventos permitidos. */
class OrganizadorUsuario extends Model
{
    use PertenceAoOrganizador;

    protected $table = 'organizador_usuario';

    public $timestamps = false;

    protected $fillable = [
        'organizador_id', 'usuario_id', 'perfil_id', 'todos_eventos', 'status',
        'convite_token_hash', 'convite_expira_em', 'convidado_por',
    ];

    protected function casts(): array
    {
        return ['todos_eventos' => 'boolean', 'convite_expira_em' => 'datetime'];
    }

    public const STATUS = [
        'convidado' => 'Convite enviado',
        'ativo' => 'Ativo',
        'suspenso' => 'Suspenso',
        'removido' => 'Removido',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(PerfilAcesso::class, 'perfil_id');
    }

    public function eventos(): BelongsToMany
    {
        return $this->belongsToMany(Evento::class, 'organizador_usuario_evento', 'organizador_usuario_id', 'evento_id');
    }

    public function ehDono(): bool
    {
        return $this->perfil?->codigo === 'dono' && $this->perfil?->organizador_id === null;
    }
}
