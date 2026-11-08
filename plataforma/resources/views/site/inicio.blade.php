@extends('layouts.publico')
@section('sem_alertas', true)

@section('conteudo')
<!-- ============ HERO ============ -->
<section class="hero">
  <picture class="hero__foto">
    <source srcset="{{ asset('assets/img/hero-show.webp') }}" type="image/webp">
    <img src="{{ asset('assets/img/hero-show.jpg') }}" alt="Público com as mãos para o alto em um show com luzes no palco" width="1170" height="570">
  </picture>
  <div class="container hero__conteudo">
    <h1>Venda ingressos com taxa <span class="sublinhado">justa</span><span class="ponto">.</span></h1>
    <p>Taxas competitivas e transparentes, o dinheiro direto na sua conta e você decide: absorve a taxa ou repassa ao comprador.</p>
    <div class="hero__acoes">
      <a href="{{ route('cadastro') }}" class="botao botao--amarelo">Criar evento grátis <svg class="i" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
      <a href="{{ route('solucao') }}" class="botao botao--contorno">Conhecer a plataforma</a>
    </div>
  </div>
</section>

<!-- ============ DIFERENCIAIS ============ -->
<section class="diferenciais container" aria-label="Diferenciais">
  <div class="diferencial">
    <svg class="i i--laranja" viewBox="0 0 24 24"><ellipse cx="9" cy="6" rx="6" ry="2.5"/><path d="M3 6v4c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5V6M3 10v4c0 1.4 2.7 2.5 6 2.5"/><ellipse cx="15" cy="14" rx="6" ry="2.5"/><path d="M9 14v4c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5v-4"/></svg>
    <span>Taxas baixas<br>e transparentes</span>
  </div>
  <div class="diferencial">
    <svg class="i i--laranja" viewBox="0 0 24 24"><path d="M3 8V5a2 2 0 0 1 2-2h3M16 3h3a2 2 0 0 1 2 2v3M21 16v3a2 2 0 0 1-2 2h-3M8 21H5a2 2 0 0 1-2-2v-3M7 12h10"/></svg>
    <span>Você escolhe<br>quem paga a taxa</span>
  </div>
  <div class="diferencial">
    <svg class="i i--laranja i--cheio" viewBox="0 0 24 24"><rect x="3" y="13" width="4" height="8" rx="1"/><rect x="10" y="8" width="4" height="13" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/></svg>
    <span>Dinheiro direto<br>na sua conta</span>
  </div>
  <div class="diferencial">
    <svg class="i i--laranja" viewBox="0 0 24 24"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="2.5" y="13" width="4" height="6" rx="1.5"/><rect x="17.5" y="13" width="4" height="6" rx="1.5"/><path d="M19.5 19c0 1.5-2 2.5-5.5 2.5"/></svg>
    <span>Check-in rápido<br>por QR Code</span>
  </div>
</section>

<!-- ============ RECURSOS ============ -->
<section class="recursos container">
  <div class="recursos__titulo">
    <span class="traco"></span>
    <h2>Tudo o que você precisa para vender ingressos.</h2>
    <p>Recursos completos e fáceis de usar para planejar, vender, gerenciar e realizar o seu evento do começo ao fim.</p>
  </div>
  <div class="recursos__grade">
    <article class="cartao">
      <svg class="i i--laranja" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/><path d="M7 14h2M11 14h2M15 14h2M7 17h2M11 17h2"/></svg>
      <h3>Criação de eventos</h3>
      <p>Cadastre o evento em minutos e publique quando estiver pronto.</p>
    </article>
    <article class="cartao">
      <svg class="i" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/></svg>
      <h3>Página de vendas</h3>
      <p>Uma página personalizada, pronta para vender.</p>
    </article>
    <article class="cartao">
      <svg class="i i--laranja" viewBox="0 0 24 24"><path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/></svg>
      <h3>Lotes, cupons e divulgação</h3>
      <p>Lotes por data ou em sequência, cupons de desconto e links de divulgadores com contagem de vendas.</p>
    </article>
    <article class="cartao">
      <svg class="i i--laranja" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M14 14h3v3M21 14v.01M14 21h.01M17.5 17.5 21 21M21 17v.01"/></svg>
      <h3>Check-in por QR Code</h3>
      <p>Leitor, câmera do celular ou busca por nome, com bloqueio de ingresso repetido.</p>
    </article>
    <article class="cartao">
      <svg class="i i--laranja i--cheio" viewBox="0 0 24 24"><rect x="3" y="13" width="4" height="8" rx="1"/><rect x="10" y="8" width="4" height="13" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/></svg>
      <h3>Pedidos e participantes</h3>
      <p>Acompanhe vendas, reenvie ingressos, cancele e exporte a lista.</p>
    </article>
    <article class="cartao">
      <svg class="i i--laranja i--cheio" viewBox="0 0 24 24"><circle cx="12" cy="7" r="3.2"/><circle cx="5" cy="9" r="2.4"/><circle cx="19" cy="9" r="2.4"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0zM0.5 20a4.5 4.5 0 0 1 6-4.2A8 8 0 0 0 4.5 20zM23.5 20a4.5 4.5 0 0 0-6-4.2 8 8 0 0 1 2 4.2z"/></svg>
      <h3>Equipe e convidados</h3>
      <p>Permissões por pessoa, listas de convidados e e-mails personalizados.</p>
    </article>
  </div>
</section>

<!-- ============ SOLUÇÃO + PAINEL ============ -->
<section class="solucao">
  <div class="container solucao__linha">
    <div class="solucao__texto">
      <span class="traco"></span>
      <h2>Uma solução completa para organizadores de todos os portes.</h2>
      <p>Do evento local ao grande festival, o <strong>fora da fila.</strong> se adapta à sua realidade, com ferramentas que simplificam a gestão, aumentam as vendas e oferecem uma experiência incrível para o público.</p>
      <ul class="lista-check">
        <li>Flexível para pequenos, médios e grandes eventos.</li>
        <li>Mais autonomia e controle na sua operação.</li>
        <li>Tecnologia confiável e sempre em evolução.</li>
      </ul>
    </div>

    <!-- Ilustração do painel (estática, dados de exemplo) -->
    <div class="painel" aria-hidden="true">
      <aside class="painel__lado">
        <div class="painel__marca"><img src="{{ asset('assets/img/simbolo-branco.svg') }}" alt="" width="34" height="20"> fora da fila<span class="ponto">.</span></div>
        <ul>
          <li class="ativo">Painel</li>
          <li>Eventos</li>
          <li>Ingressos</li>
          <li>Vendas</li>
          <li>Relatórios</li>
          <li>Equipe</li>
          <li>Configurações</li>
        </ul>
      </aside>
      <div class="painel__corpo">
        <div class="painel__cabeca">
          <div>
            <small>Meu evento</small>
            <strong>Festival Fora da Fila</strong>
            <span class="selo">Publicado</span>
          </div>
          <span class="painel__link">Ver página do evento ↗</span>
        </div>
        <div class="painel__kpis">
          <div class="kpi"><small>Ingressos vendidos</small><b>3.482</b><em>↑ +28%</em></div>
          <div class="kpi"><small>Receita</small><b>R$ 174.100</b><em>↑ +32%</em></div>
          <div class="kpi"><small>Taxa de ocupação</small><b>87%</b><span class="barra"><span style="width:87%"></span></span></div>
        </div>
        <div class="painel__graficos">
          <div class="grafico">
            <small>Vendas por dia</small>
            <div class="barras">
              <span style="height:16%"></span><span style="height:22%"></span><span style="height:45%"></span><span style="height:28%"></span><span style="height:55%"></span><span style="height:50%"></span><span style="height:70%"></span><span style="height:68%"></span><span style="height:100%"></span>
            </div>
          </div>
          <div class="grafico">
            <small>Ingressos por lote</small>
            <div class="rosca-linha">
              <div class="rosca"><span><b>3.482</b>vendidos</span></div>
              <ul class="legenda">
                <li><i style="background:var(--tinta)"></i>1º lote<b>52%</b></li>
                <li><i style="background:var(--laranja)"></i>2º lote<b>28%</b></li>
                <li><i style="background:var(--amarelo)"></i>3º lote<b>15%</b></li>
                <li><i style="background:#d0d5dd"></i>Outros<b>5%</b></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ TAXA ============ -->
<section class="recursos container" id="taxa">
  <div class="recursos__titulo">
    <span class="traco"></span>
    <h2>Taxa justa, e quem decide quem paga é você.</h2>
    <p>Criar evento é grátis e evento gratuito não tem taxa. Nos ingressos pagos, cobramos uma taxa pequena por ingresso, sem mensalidade e sem letra miúda.</p>
  </div>
  <div class="recursos__grade">
    <article class="cartao">
      <h3>Taxa embutida</h3>
      <p>O comprador paga o preço do ingresso e a taxa sai do valor que você recebe. Ótimo para quem quer preço “redondo”.</p>
    </article>
    <article class="cartao">
      <h3>Taxa repassada ao comprador</h3>
      <p>O comprador vê o preço e a taxa separados e você recebe o valor cheio do ingresso.</p>
    </article>
    <article class="cartao">
      <h3>Escolha por evento</h3>
      <p>Defina o padrão da sua organização e mude em cada evento quando quiser. O dinheiro das vendas cai direto na sua conta.</p>
    </article>
  </div>
  <p style="margin-top:20px"><a href="{{ route('precos') }}" class="botao botao--contorno">Ver preços e taxas</a></p>
</section>

<!-- ============ CONCEITO ============ -->
<section class="conceito">
  <div class="container conceito__linha">
    <img src="{{ asset('assets/img/ilustracao-fila.png') }}" alt="Pessoas esperando em fila enquanto uma delas sai da fila e sobe, animada" class="conceito__ilustracao" width="1080" height="414" loading="lazy">
    <div class="conceito__texto">
      <h2>Coloque seu evento fora da fila<span class="ponto">.</span></h2>
      <p>Venda ingressos com mais liberdade, praticidade e profissionalismo, e ofereça uma experiência incrível do primeiro clique ao check-in.</p>
      <ul class="lista-check">
        <li>Taxa justa e transparente, sem surpresa.</li>
        <li>Experiência simples para você e para o seu público.</li>
      </ul>
    </div>
  </div>
</section>

<!-- ============ CHAMADA FINAL ============ -->
<section class="chamada">
  <div class="container chamada__linha">
    <div>
      <h2>Pronto para começar a vender?</h2>
      <p>Crie seu evento agora, é grátis, rápido e sem complicação.</p>
    </div>
    <a href="{{ route('cadastro') }}" class="botao botao--amarelo">Criar evento grátis <svg class="i" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
  </div>
</section>
@endsection
