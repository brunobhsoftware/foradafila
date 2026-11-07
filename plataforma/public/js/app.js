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

  // Copiar link
  $$('[data-copiar]').forEach((b) => b.addEventListener('click', async () => {
    const alvo = document.getElementById(b.dataset.copiar);
    try { await navigator.clipboard.writeText(alvo.value); } catch (e) { alvo.select(); document.execCommand('copy'); }
    const texto = b.textContent; b.textContent = 'Copiado!'; setTimeout(() => { b.textContent = texto; }, 1800);
  }));
})();
