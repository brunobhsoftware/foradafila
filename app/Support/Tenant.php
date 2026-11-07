<?php

namespace App\Support;

use App\Models\Organizador;
use App\Models\OrganizadorUsuario;
use Illuminate\Support\Collection;

/**
 * Organizador em uso na requisição e o vínculo do usuário logado com ele.
 *
 * Toda tabela de negócio é filtrada por organizador_id a partir daqui
 * (trait PertenceAoOrganizador). Sem organizador definido, as consultas
 * dessas tabelas não retornam nada (falha fechada).
 */
class Tenant
{
    private ?Organizador $organizador = null;

    private ?OrganizadorUsuario $vinculo = null;

    /** @var Collection<int,string>|null */
    private ?Collection $permissoes = null;

    public function definir(Organizador $organizador, ?OrganizadorUsuario $vinculo = null): void
    {
        $this->organizador = $organizador;
        $this->vinculo = $vinculo;
        $this->permissoes = null;
    }

    public function limpar(): void
    {
        $this->organizador = $this->vinculo = $this->permissoes = null;
    }

    public function id(): ?int
    {
        return $this->organizador?->id;
    }

    public function organizador(): ?Organizador
    {
        return $this->organizador;
    }

    public function vinculo(): ?OrganizadorUsuario
    {
        return $this->vinculo;
    }

    public function ehDono(): bool
    {
        return $this->vinculo?->perfil?->codigo === 'dono' && $this->vinculo->perfil->organizador_id === null;
    }

    /** Códigos de permissão (modulo.acao) do perfil do usuário neste organizador. */
    public function permissoes(): Collection
    {
        if ($this->permissoes === null) {
            $this->permissoes = ($this->vinculo && $this->vinculo->status === 'ativo')
                ? $this->vinculo->perfil->permissoes()->pluck('codigo')
                : collect();
        }

        return $this->permissoes;
    }

    public function pode(string ...$codigos): bool
    {
        return $this->permissoes()->intersect($codigos)->isNotEmpty();
    }

    /** IDs de eventos permitidos, ou null quando o usuário vê todos os eventos. */
    public function eventosPermitidos(): ?array
    {
        if (! $this->vinculo || $this->vinculo->todos_eventos) {
            return null;
        }

        return $this->vinculo->eventos()->pluck('evento.id')->all();
    }
}
