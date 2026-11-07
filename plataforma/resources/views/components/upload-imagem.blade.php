{{--
  Campo de imagem com "clique ou arraste". O formulário precisa de enctype="multipart/form-data".
  <x-upload-imagem nome="imagem" rotulo="Imagem de divulgação do evento" :atual="$evento->imagem_capa_url" />
--}}
@props(['nome', 'rotulo', 'atual' => null, 'largura' => 1600, 'altura' => 838, 'maxKb' => 2048, 'opcional' => true, 'texto' => null])
@php $erro = $errors->first($nome); $id = 'up_'.$nome; @endphp
<div class="campo col-12 {{ $erro ? 'com-erro' : '' }}">
    <span class="rotulo" id="{{ $id }}_rotulo">{{ $rotulo }} @if ($opcional)<small class="suave" style="font-weight:400">(opcional)</small>@endif</span>
    <label class="upload" for="{{ $id }}" data-upload data-max-kb="{{ $maxKb }}" data-largura="{{ $largura }}" data-altura="{{ $altura }}"
           aria-describedby="{{ $id }}_ajuda" @class(['upload--com-imagem' => $atual])>
        <input type="file" id="{{ $id }}" name="{{ $nome }}" accept="image/jpeg,image/png,image/gif" class="oculto-visual" aria-labelledby="{{ $id }}_rotulo">
        <img class="upload__previa" src="{{ $atual ?: '' }}" alt="" style="aspect-ratio: {{ $largura }} / {{ $altura }}; {{ $largura === $altura ? 'max-width:'.max(136, $largura).'px;object-fit:contain' : '' }}" @if (! $atual) hidden @endif>
        <span class="upload__texto">
            <x-icone nome="mais" />
            <b>Clique ou arraste a imagem aqui</b>
            <small>{{ $texto ?? 'Formatos aceitos: JPEG, GIF ou PNG de até '.($maxKb / 1024).' MB. Dimensão recomendada: '.$largura.' x '.$altura.' pixels.' }}</small>
        </span>
    </label>
    <span class="ajuda" id="{{ $id }}_ajuda" data-upload-status aria-live="polite">
        @if ($atual) Imagem atual mostrada acima. Envie outra para trocar. @endif
    </span>
    @if ($atual)
        <label class="opcao" style="margin-top:8px"><input type="checkbox" name="remover_{{ $nome }}" value="1"> Remover a imagem atual</label>
    @endif
    @if ($erro)
        <span class="erro" role="alert">{{ $erro }} Selecione a imagem de novo.</span>
    @endif
</div>
