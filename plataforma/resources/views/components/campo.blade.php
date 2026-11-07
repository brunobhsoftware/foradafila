{{--
  Campo de formulário padrão.
  <x-campo nome="titulo" rotulo="Nome do evento" obrigatorio />
  <x-campo nome="uf" rotulo="UF" tipo="select" :opcoes="$ufs" />
  <x-campo nome="descricao" rotulo="Descrição" tipo="textarea" ajuda="Aparece na página do evento." />
  Nomes com colchetes (local[cidade]) usam a notação com ponto para old() e erros.
--}}
@props([
    'nome', 'rotulo' => null, 'tipo' => 'text', 'valor' => null, 'opcoes' => [], 'ajuda' => null,
    'obrigatorio' => false, 'vazio' => null, 'classe' => 'col-12',
])
@php
    $chave = str_replace(['[', ']'], ['.', ''], $nome);
    $id = 'c_'.str_replace('.', '_', $chave);
    $atual = old($chave, $valor);
    $erro = $errors->first($chave);
@endphp
<div class="campo {{ $classe }} {{ $erro ? 'com-erro' : '' }}">
    @if ($rotulo)
        <label for="{{ $id }}">{{ $rotulo }} @if ($obrigatorio)<span class="obrigatorio" aria-hidden="true">*</span>@endif</label>
    @endif

    @if ($tipo === 'select')
        <select id="{{ $id }}" name="{{ $nome }}" @required($obrigatorio) {{ $attributes }}>
            @if ($vazio !== null)<option value="">{{ $vazio }}</option>@endif
            @foreach ($opcoes as $valorOpcao => $texto)
                <option value="{{ $valorOpcao }}" @selected((string) $atual === (string) $valorOpcao)>{{ $texto }}</option>
            @endforeach
        </select>
    @elseif ($tipo === 'textarea')
        <textarea id="{{ $id }}" name="{{ $nome }}" @required($obrigatorio) {{ $attributes }}>{{ $atual }}</textarea>
    @else
        <input id="{{ $id }}" type="{{ $tipo }}" name="{{ $nome }}" value="{{ $tipo === 'password' ? '' : $atual }}" @required($obrigatorio) {{ $attributes }}>
    @endif

    @if ($erro)
        <span class="erro" role="alert">{{ $erro }}</span>
    @elseif ($ajuda)
        <span class="ajuda">{{ $ajuda }}</span>
    @endif
</div>
