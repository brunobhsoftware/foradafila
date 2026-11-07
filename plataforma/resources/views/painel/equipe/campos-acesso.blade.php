{{-- Perfil + alcance (todos os eventos ou só alguns). Variáveis: $perfis, $eventos, $perfilAtual, $todos, $marcados, $sufixo --}}
<div class="campo">
    <label for="perfil_{{ $sufixo }}">Perfil de acesso</label>
    <select id="perfil_{{ $sufixo }}" name="perfil_id" required>
        @foreach ($perfis as $p)
            <option value="{{ $p->id }}" @selected($perfilAtual ? (string) $perfilAtual === (string) $p->id : ($p->codigo === 'gerente' && $p->ehDoSistema()))>{{ $p->nome }}{{ $p->ehDoSistema() ? '' : ' (personalizado)' }}</option>
        @endforeach
    </select>
</div>
<div class="campo">
    <span class="rotulo">Eventos</span>
    <div class="opcoes">
        <label class="opcao"><input type="radio" name="alcance" value="todos" @checked($todos)> Todos os eventos</label>
        <label class="opcao"><input type="radio" name="alcance" value="alguns" @checked(! $todos)> Só os eventos escolhidos</label>
    </div>
</div>
<div class="campo" data-mostra-se="alcance=alguns">
    @if ($eventos->isEmpty())
        <small>Nenhum evento cadastrado ainda.</small>
    @else
        <div class="opcoes" style="max-height:180px;overflow:auto">
            @foreach ($eventos as $id => $titulo)
                <label class="opcao"><input type="checkbox" name="eventos[]" value="{{ $id }}" @checked(in_array($id, $marcados))> {{ $titulo }}</label>
            @endforeach
        </div>
    @endif
</div>
