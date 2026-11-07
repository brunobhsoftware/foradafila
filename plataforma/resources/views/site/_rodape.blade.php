<footer class="rodape">
  <div class="container rodape__linha">
    <a href="{{ route('inicio') }}" class="marca marca--grande" aria-label="fora da fila — início">
      <img src="{{ asset('assets/img/simbolo.svg') }}" alt="" class="marca__simbolo" width="70" height="41">
      <span class="marca__nome">fora da fila<span class="ponto">.</span></span>
    </a>
    <nav class="rodape__colunas" aria-label="Rodapé">
      <div>
        <h3>Para produtores</h3>
        <a href="{{ route('solucao') }}">Solução</a>
        <a href="{{ route('precos') }}">Preços</a>
        <a href="{{ route('cadastro') }}">Criar conta</a>
        <a href="{{ route('entrar') }}">Área do produtor</a>
      </div>
      <div>
        <h3>Para o público</h3>
        <a href="{{ route('vitrine') }}">Eventos</a>
        <a href="{{ route('comprador.ingressos') }}">Meus ingressos</a>
        <a href="{{ route('ajuda') }}">Ajuda e dúvidas frequentes</a>
      </div>
      <div>
        <h3>Termos</h3>
        <a href="{{ route('termo', 'compra_comprador') }}">Termos de compra</a>
        <a href="{{ route('termo', 'privacidade') }}">Política de privacidade</a>
        <a href="{{ route('termo', 'uso_organizador') }}">Termo de uso do organizador</a>
        <a href="{{ route('termo', 'responsabilidade_organizador') }}">Responsabilidade do organizador</a>
      </div>
    </nav>
    <div class="rodape__social">
      <b>foradafila.com.br</b>
      <small>Uma plataforma de tecnologia da BH Software.</small>
    </div>
  </div>
  <div class="container rodape__base">
    © {{ date('Y') }} fora da fila. Os eventos são realizados pelos organizadores indicados em cada página de evento,
    responsáveis pelo evento e pelo atendimento ao comprador.
  </div>
</footer>
