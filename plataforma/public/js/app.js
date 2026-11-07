/* Fora da Fila — comportamentos simples das telas (sem framework). */
(function () {
  'use strict';
  const $$ = (sel, raiz) => Array.from((raiz || document).querySelectorAll(sel));
  const digitos = (v) => (v || '').replace(/\D/g, '');

  // Menu lateral no celular
  $$('[data-menu-alternar]').forEach((b) => b.addEventListener('click', () => {
    const aberto = document.body.classList.toggle('menu-aberto');
    b.setAttribute('aria-expanded', aberto);
  }));
  document.addEventListener('click', (e) => {
    if (document.body.classList.contains('menu-aberto') && !e.target.closest('.menu-lateral, [data-menu-alternar]')) {
      document.body.classList.remove('menu-aberto');
    }
  });

  // Menu da conta (alto do painel): fecha ao clicar fora ou com Esc
  document.addEventListener('click', (e) => {
    $$('details.conta-menu[open]').forEach((d) => { if (!d.contains(e.target)) d.open = false; });
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') $$('details.conta-menu[open]').forEach((d) => { d.open = false; d.querySelector('summary').focus(); });
  });

  // Cadastro de evento: ao escolher a data de início, término e abertura dos portões
  // acompanham a data (mantendo a diferença de dias que já tinham); o produtor só ajusta o horário.
  const inicio = document.querySelector('input[type=datetime-local][name=data_inicio]');
  if (inicio) {
    const dia = (v) => (v || '').slice(0, 10);
    const hora = (v) => (v || '').slice(11, 16);
    const somaDias = (d, n) => { const x = new Date(d + 'T12:00:00Z'); x.setUTCDate(x.getUTCDate() + n); return x.toISOString().slice(0, 10); };
    const difDias = (a, b) => Math.round((new Date(a + 'T12:00:00Z') - new Date(b + 'T12:00:00Z')) / 864e5);
    let anterior = inicio.value;
    inicio.addEventListener('change', () => {
      const novo = inicio.value;
      if (!dia(novo)) return;
      ['data_fim', 'abertura_portoes'].forEach((nome) => {
        const campo = document.querySelector(`input[type=datetime-local][name=${nome}]`);
        if (!campo) return;
        const atual = campo.value;
        const desloc = atual && dia(anterior) ? difDias(dia(atual), dia(anterior)) : 0;
        const horario = hora(atual) || hora(novo);
        campo.value = somaDias(dia(novo), desloc) + 'T' + horario;
      });
      anterior = novo;
    });
  }

  // Máscaras
  const mascaras = {
    cpf: (d) => d.slice(0, 11).replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2'),
    cnpj: (d) => d.slice(0, 14).replace(/(\d{2})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1/$2').replace(/(\d{4})(\d{1,2})$/, '$1-$2'),
    telefone: (d) => { d = d.slice(0, 11); return d.length > 10 ? d.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3') : d.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3').replace(/[-\s(]+$/, ''); },
    cep: (d) => d.slice(0, 8).replace(/(\d{5})(\d)/, '$1-$2'),
  };
  function aplicarMascara(input) {
    let tipo = input.dataset.mascara;
    if (tipo === 'documento') {
      const pj = document.querySelector('[name="tipo_pessoa"]:checked');
      tipo = pj && pj.value === 'PJ' ? 'cnpj' : 'cpf';
    }
    const d = digitos(input.value);
    if (mascaras[tipo]) input.value = d ? mascaras[tipo](d) : '';
  }
  $$('[data-mascara]').forEach((input) => {
    input.addEventListener('input', () => aplicarMascara(input));
    aplicarMascara(input);
  });

  // Pessoa física x jurídica no cadastro: troca rótulos e a máscara do documento
  function atualizarTipoPessoa() {
    const pj = document.querySelector('[name="tipo_pessoa"]:checked');
    if (!pj) return;
    const ehPJ = pj.value === 'PJ';
    $$('[data-rotulo-pf]').forEach((el) => { el.textContent = ehPJ ? el.dataset.rotuloPj : el.dataset.rotuloPf; });
    $$('[data-so-pj]').forEach((el) => { el.hidden = !ehPJ; });
    $$('[data-mascara="documento"]').forEach(aplicarMascara);
  }
  $$('[name="tipo_pessoa"]').forEach((r) => r.addEventListener('change', atualizarTipoPessoa));
  atualizarTipoPessoa();

  // CEP: preenche o endereço pelo ViaCEP (consulta feita pelo navegador)
  $$('[data-cep]').forEach((input) => {
    input.addEventListener('blur', async () => {
      const cep = digitos(input.value);
      if (cep.length !== 8) return;
      const prefixo = input.dataset.cep || '';
      const campo = (nome) => document.querySelector(`[name="${prefixo}${nome}${prefixo.endsWith('[') ? ']' : ''}"]`);
      try {
        const r = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        const d = await r.json();
        if (d.erro) return;
        const preencher = (nome, valor) => { const c = campo(nome); if (c && !c.value) c.value = valor || ''; };
        preencher('logradouro', d.logradouro); preencher('bairro', d.bairro);
        preencher('cidade', d.localidade); preencher('uf', d.uf);
        const numero = campo('numero'); if (numero && !numero.value) numero.focus();
      } catch (e) { /* sem internet ou ViaCEP fora: o usuário preenche à mão */ }
    });
  });

  // Confirmação antes de ações destrutivas
  $$('form[data-confirmar]').forEach((f) => f.addEventListener('submit', (e) => {
    if (!window.confirm(f.dataset.confirmar)) e.preventDefault();
  }));

  // Mostrar/esconder blocos conforme uma escolha (ex.: "Todos os eventos" x "Só estes")
  $$('[data-mostra-se]').forEach((bloco) => {
    const [nome, valor] = bloco.dataset.mostraSe.split('=');
    const atualizar = () => {
      const marcado = bloco.closest('form').querySelector(`[name="${nome}"]:checked`) || bloco.closest('form').querySelector(`[name="${nome}"]`);
      bloco.hidden = !marcado || marcado.value !== valor;
    };
    $$(`[name="${nome}"]`, bloco.closest('form')).forEach((r) => r.addEventListener('change', atualizar));
    atualizar();
  });

  // Cortesia: preço sempre zero
  $$('[name="modalidade"]').forEach((sel) => {
    const preco = sel.form && sel.form.querySelector('[name="preco"]');
    const atualizar = () => { if (!preco) return; const c = sel.value === 'cortesia'; if (c) preco.value = '0'; preco.readOnly = c; };
    sel.addEventListener('change', atualizar); atualizar();
  });

  // Envio de imagem: clique ou arraste, com conferência no navegador (o servidor confere de novo)
  $$('[data-upload]').forEach((zona) => {
    const input = zona.querySelector('input[type=file]');
    const previa = zona.querySelector('.upload__previa');
    const status = zona.parentElement.querySelector('[data-upload-status]');
    const maxKb = Number(zona.dataset.maxKb), larg = Number(zona.dataset.largura), alt = Number(zona.dataset.altura);
    const tipos = ['image/jpeg', 'image/png', 'image/gif'];
    const avisar = (texto, erro) => { if (status) { status.textContent = texto; status.style.color = erro ? 'var(--vermelho)' : ''; } };
    const mostrar = (arquivo) => {
      if (!arquivo) return;
      if (!tipos.includes(arquivo.type)) { input.value = ''; return avisar('Formato não aceito. Envie JPEG, GIF ou PNG.', true); }
      if (arquivo.size > maxKb * 1024) { input.value = ''; return avisar(`A imagem tem ${(arquivo.size / 1048576).toFixed(1).replace('.', ',')} MB. O limite é ${maxKb / 1024} MB.`, true); }
      const url = URL.createObjectURL(arquivo);
      const img = new Image();
      img.onload = () => {
        previa.src = url; previa.hidden = false; zona.classList.add('upload--com-imagem');
        const dif = Math.abs(img.width / img.height - larg / alt) > 0.05;
        avisar(`${arquivo.name} · ${img.width} x ${img.height} px` + (dif ? ` — a proporção recomendada é ${larg} x ${alt}; a imagem pode ser cortada nas bordas.` : ' — pronta para enviar.'), false);
      };
      img.onerror = () => { input.value = ''; avisar('Não foi possível ler esta imagem.', true); };
      img.src = url;
    };
    input.addEventListener('change', () => mostrar(input.files[0]));
    ['dragenter', 'dragover'].forEach((ev) => zona.addEventListener(ev, (e) => { e.preventDefault(); zona.classList.add('upload--arrastando'); }));
    ['dragleave', 'drop'].forEach((ev) => zona.addEventListener(ev, (e) => { e.preventDefault(); zona.classList.remove('upload--arrastando'); }));
    zona.addEventListener('drop', (e) => {
      const arquivo = e.dataTransfer.files[0];
      if (!arquivo) return;
      const dt = new DataTransfer(); dt.items.add(arquivo); input.files = dt.files;
      mostrar(arquivo);
    });
  });

  // Copiar link
  $$('[data-copiar]').forEach((b) => b.addEventListener('click', async () => {
    const alvo = document.getElementById(b.dataset.copiar);
    try { await navigator.clipboard.writeText(alvo.value); } catch (e) { alvo.select(); document.execCommand('copy'); }
    const texto = b.textContent; b.textContent = 'Copiado!'; setTimeout(() => { b.textContent = texto; }, 1800);
  }));
})();
