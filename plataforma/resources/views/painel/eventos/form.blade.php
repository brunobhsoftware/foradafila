@extends('layouts.painel')
@php $editando = $evento->exists; $local = $evento->local; @endphp
@section('titulo', $editando ? 'Editar evento' : 'Novo evento')

@section('conteudo')
<div class="cabecalho">
    <div>
        <div class="trilha"><a href="{{ route('eventos.index') }}">Eventos</a> › {{ $editando ? $evento->titulo : 'Novo' }}</div>
        <h1>{{ $editando ? 'Editar evento' : 'Novo evento' }}</h1>
        <p>{{ $editando ? 'As mudanças valem na hora para a página do evento.' : 'O evento nasce como rascunho. Você publica depois de criar os ingressos.' }}</p>
    </div>
</div>

<form method="post" action="{{ $editando ? route('eventos.update', $evento) : route('eventos.store') }}" class="cartao" enctype="multipart/form-data">
    @csrf
    @if ($editando) @method('PUT') @endif

    <section class="secao-form">
        <h2>Informações do evento</h2>
        <p>O que aparece para o público.</p>
        <div class="form-grade">
            <x-campo nome="titulo" rotulo="Nome do evento" :valor="$evento->titulo" obrigatorio classe="col-8" maxlength="150" />
            <x-campo nome="categoria_id" rotulo="Categoria" tipo="select" :opcoes="$categorias" :valor="$evento->categoria_id" vazio="Escolha" classe="col-4" />
            <x-campo nome="resumo" rotulo="Resumo" :valor="$evento->resumo" maxlength="300" ajuda="Uma frase que aparece na vitrine e no compartilhamento (até 300 caracteres)." />
            <x-campo nome="descricao" rotulo="Descrição completa" tipo="textarea" :valor="$evento->descricao" rows="6" ajuda="Atrações, programação, o que está incluso, regras do local." />
            <x-upload-imagem nome="imagem" rotulo="Imagem de divulgação do evento" :atual="$evento->imagem_capa_url" />
            <x-campo nome="classificacao" rotulo="Classificação etária" tipo="select" :opcoes="\App\Models\Evento::CLASSIFICACOES" :valor="$evento->classificacao" obrigatorio classe="col-4" />
            @if (\App\Support\Esquema::capacidadeOpcional())
                <x-campo nome="capacidade" rotulo="Capacidade máxima" tipo="number" min="1" :valor="$evento->capacidade" classe="col-4"
                    ajuda="Opcional. Em branco, você cadastra quantos ingressos quiser; a lotação passa a ser responsabilidade da empresa." />
            @else
                <x-campo nome="capacidade" rotulo="Capacidade máxima" tipo="number" min="1" :valor="$evento->capacidade" obrigatorio classe="col-4"
                    ajuda="Lotação máxima. A soma dos lotes não pode passar disso. (Ficará opcional após a atualização V005 do banco.)" />
            @endif
            <x-campo nome="visibilidade" rotulo="Visibilidade" tipo="select" :opcoes="\App\Models\Evento::VISIBILIDADES" :valor="$evento->visibilidade" obrigatorio classe="col-4" />
        </div>
    </section>

    <section class="secao-form">
        <h2>Data e horário</h2>
        <p>Horário de Brasília.</p>
        <div class="form-grade">
            <x-campo nome="data_inicio" rotulo="Início" tipo="datetime-local" :valor="para_input_data($evento->data_inicio)" obrigatorio classe="col-4" />
            <x-campo nome="data_fim" rotulo="Término" tipo="datetime-local" :valor="para_input_data($evento->data_fim)" obrigatorio classe="col-4" />
            <x-campo nome="abertura_portoes" rotulo="Abertura dos portões" tipo="datetime-local" :valor="para_input_data($evento->abertura_portoes)" classe="col-4" ajuda="O check-in só é aceito a partir daqui." />
        </div>
    </section>

    <section class="secao-form">
        <h2>Onde vai ser</h2>
        <div class="form-grade">
            <div class="campo col-12">
                <span class="rotulo">Formato</span>
                <div class="segmentado">
                    @foreach (\App\Models\Evento::FORMATOS as $valor => $texto)
                        <label><input type="radio" name="formato" value="{{ $valor }}" @checked(old('formato', $evento->formato) === $valor)><span>{{ $texto }}</span></label>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="form-grade" id="bloco-online" style="margin-top:16px">
            <x-campo nome="link_online" rotulo="Link da transmissão" tipo="url" :valor="$evento->link_online" ajuda="Enviado só para quem tem ingresso." />
        </div>
        <div class="form-grade" id="bloco-local" style="margin-top:16px">
            <x-campo nome="local[nome]" rotulo="Nome do local" :valor="$local?->nome" classe="col-8" placeholder="Ex.: Arena Central" />
            <x-campo nome="local[cep]" rotulo="CEP" :valor="$local?->cep" classe="col-4" data-mascara="cep" data-cep="local[" inputmode="numeric" ajuda="Preenche o endereço." />
            <x-campo nome="local[logradouro]" rotulo="Endereço" :valor="$local?->logradouro" classe="col-6" />
            <x-campo nome="local[numero]" rotulo="Número" :valor="$local?->numero" classe="col-2" />
            <x-campo nome="local[complemento]" rotulo="Complemento" :valor="$local?->complemento" classe="col-4" />
            <x-campo nome="local[bairro]" rotulo="Bairro" :valor="$local?->bairro" classe="col-4" />
            <x-campo nome="local[cidade]" rotulo="Cidade" :valor="$local?->cidade" classe="col-6" />
            <x-campo nome="local[uf]" rotulo="UF" :valor="$local?->uf" classe="col-2" maxlength="2" />
        </div>
    </section>

    <section class="secao-form">
        <h2>Regras da venda e do ingresso</h2>
        <div class="form-grade">
            <x-campo nome="max_ingressos_pedido" rotulo="Máximo de ingressos por pedido" tipo="number" min="1" :valor="$evento->max_ingressos_pedido" obrigatorio classe="col-4" />
            <x-campo nome="max_ingressos_cpf" rotulo="Máximo por CPF" tipo="number" min="1" :valor="$evento->max_ingressos_cpf" classe="col-4" ajuda="Em branco = sem limite." />
            <x-campo nome="transferencia_ate_h" rotulo="Transferência até (horas antes)" tipo="number" min="0" :valor="$evento->transferencia_ate_h" obrigatorio classe="col-4" />
            <div class="campo col-12">
                <div class="opcoes-linha">
                    @foreach (['nominal' => 'Ingresso nominal (cada ingresso tem um titular)', 'exige_cpf_participante' => 'Exigir CPF do participante', 'permite_transferencia' => 'Permitir transferência de titularidade', 'permite_reentrada' => 'Permitir reentrada (sair e voltar)'] as $campo => $texto)
                        <input type="hidden" name="{{ $campo }}" value="0">
                        <label class="opcao"><input type="checkbox" name="{{ $campo }}" value="1" @checked(old($campo, $evento->{$campo}))> {{ $texto }}</label>
                    @endforeach
                </div>
            </div>
            <x-campo nome="politica_cancelamento" rotulo="Política de cancelamento e reembolso" tipo="textarea" :valor="$evento->politica_cancelamento" rows="3" ajuda="Aparece na página do evento. O direito de arrependimento (7 dias, CDC) vale sempre." />
            <div class="col-12 alerta alerta--info" style="margin:0">
                <div>
                    <b>Política de cancelamento de eventos</b>
                    <p style="margin:6px 0 10px">Em caso de cancelamento do evento, é responsabilidade do produtor (organizador) devolver todos os valores pagos aos clientes,
                    sob as penas das leis de defesa do consumidor. Este aviso aparece também na página pública do evento.</p>
                    @php $errAceite = $errors->first('aceite_cancelamento'); @endphp
                    <label class="opcao"><input type="checkbox" name="aceite_cancelamento" value="1" required @checked(old('aceite_cancelamento'))>
                        <span><b>{{ \App\Http\Controllers\Painel\EventoController::DECLARACAO_CANCELAMENTO }}</b></span></label>
                    @if ($errAceite)<span class="erro" style="display:block;margin-top:6px;color:var(--vermelho)">{{ $errAceite }}</span>@endif
                </div>
            </div>
            @if ($contas->isNotEmpty())
                <x-campo nome="conta_pagamento_id" rotulo="Conta que recebe as vendas" tipo="select" :opcoes="$contas" :valor="$evento->conta_pagamento_id" vazio="Escolha" classe="col-6" />
            @else
                <div class="col-12 alerta alerta--info" style="margin:0"><p><b>Ingressos pagos:</b> para vender, a conta do Mercado Pago precisa estar conectada e homologada (módulo Financeiro, próxima fase). Eventos com ingressos gratuitos ou cortesias já podem ser publicados.</p></div>
            @endif
        </div>
    </section>

    <div class="acoes-form">
        <a href="{{ $editando ? route('eventos.show', $evento) : route('eventos.index') }}" class="botao botao--contorno">Cancelar</a>
        <button type="submit" class="botao botao--primario">{{ $editando ? 'Salvar alterações' : 'Criar evento' }}</button>
    </div>
</form>

<script>
    // Local só para eventos presenciais ou híbridos
    document.addEventListener('DOMContentLoaded', () => {
        const bloco = document.getElementById('bloco-local');
        const radios = document.querySelectorAll('[name="formato"]');
        const atualizar = () => { const v = document.querySelector('[name="formato"]:checked')?.value; bloco.hidden = v === 'online'; };
        radios.forEach(r => r.addEventListener('change', atualizar)); atualizar();
        const online = document.getElementById('bloco-online');
        const atualizarOnline = () => { const v = document.querySelector('[name="formato"]:checked')?.value; online.hidden = v === 'presencial'; };
        radios.forEach(r => r.addEventListener('change', atualizarOnline)); atualizarOnline();
    });
</script>
@endsection
