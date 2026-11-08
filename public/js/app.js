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

  // Formulário de lote: mostra os campos conforme gratuito/pago e por data/por lote
  $$('[data-lote-form]').forEach((form) => {
    const atualizar = () => form.querySelectorAll('[data-mostrar-se]').forEach((el) => {
      const [nome, valor] = el.dataset.mostrarSe.split('=');
      const marcado = form.querySelector(`input[name=${nome}]:checked`);
      el.hidden = !marcado || marcado.value !== valor;
    });
    form.addEventListener('change', (e) => { if (['cobranca', 'liberacao'].includes(e.target.name)) atualizar(); });
    atualizar();
  });

  // Cadastro único: ao completar o CPF, preenche nome/e-mail/telefone vazios do mesmo formulário
  $$('[data-busca-cpf]').forEach((cpf) => {
    let ultimo = '';
    cpf.addEventListener('input', async () => {
      const d = cpf.value.replace(/\D/g, '');
      if (d.length !== 11 || d === ultimo) return;
      ultimo = d;
      try {
        const r = await fetch('/cadastro-unico/' + d, { headers: { Accept: 'application/json' } });
        if (!r.ok) return;
        const p = await r.json();
        if (!p.encontrado) return;
        const form = cpf.form || document;
        let preencheu = false;
        ['nome', 'email', 'telefone'].forEach((k) => {
          const el = form.querySelector('[data-pessoa="' + k + '"]');
          if (el && p[k] && !el.value.trim()) { el.value = p[k]; el.dispatchEvent(new Event('input', { bubbles: true })); preencheu = true; }
        });
        if (preencheu) {
          let aviso = cpf.parentElement.querySelector('.cpf-encontrado');
          if (!aviso) { aviso = document.createElement('small'); aviso.className = 'cpf-encontrado suave'; cpf.parentElement.appendChild(aviso); }
          aviso.textContent = 'Dados preenchidos pelo cadastro único.';
        }
      } catch (e) { /* sem conexão: segue manual */ }
    });
  });

  // Botões que mostram/escondem um bloco (ex.: ações do participante)
  $$('[data-alternar]').forEach((b) => b.addEventListener('click', () => {
    const alvo = document.getElementById(b.dataset.alternar);
    if (!alvo) return;
    alvo.hidden = !alvo.hidden;
    b.setAttribute('aria-expanded', String(!alvo.hidden));
  }));

  // Check-in pelo navegador: leitor (teclado), câmera (BarcodeDetector) e busca manual
  $$('[data-checkin]').forEach((raiz) => {
    const token = document.querySelector('meta[name=csrf-token]')?.content;
    const caixa = raiz.querySelector('[data-resultado]');
    const campo = raiz.querySelector('#checkin-codigo');
    const sentido = () => raiz.querySelector('input[name=sentido]:checked')?.value || 'entrada';
    let ocupado = false;
    const mostrar = (r) => {
      caixa.hidden = false;
      caixa.className = 'checkin__resultado ' + (r.aceito ? 'ok' : 'erro');
      const i = r.ingresso;
      caixa.innerHTML = '';
      const t = document.createElement('b'); t.textContent = r.mensagem; caixa.appendChild(t);
      if (i) { const d = document.createElement('span'); d.textContent = `${i.nome || ''} · ${i.numero} · ${i.tipo || ''}${i.primeiro_checkin && !r.aceito ? ' · entrou ' + i.primeiro_checkin : ''}`; caixa.appendChild(d); }
      if (r.numeros) {
        Object.entries(r.numeros).forEach(([k, v]) => { const el = raiz.querySelector(`[data-n="${k}"]`); if (el) el.textContent = Number(v).toLocaleString('pt-BR'); });
        const barra = raiz.querySelector('[data-barra]'); if (barra) barra.style.width = r.numeros.pct + '%';
      }
      if (navigator.vibrate) navigator.vibrate(r.aceito ? 80 : [80, 60, 80]);
    };
    const enviar = async (dados) => {
      if (ocupado) return; ocupado = true;
      try {
        const resp = await fetch(raiz.dataset.registrar, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
          body: JSON.stringify({ ...dados, sentido: sentido() }) });
        const r = await resp.json().catch(() => ({ aceito: false, mensagem: 'Erro ao validar. Tente de novo.' }));
        mostrar(resp.ok ? r : { aceito: false, mensagem: r.message || 'Erro ao validar. Tente de novo.' });
      } catch (e) { mostrar({ aceito: false, mensagem: 'Sem conexão. Tente de novo.' }); }
      finally { ocupado = false; campo.value = ''; campo.focus(); }
    };
    raiz.querySelector('[data-checkin-form]').addEventListener('submit', (e) => { e.preventDefault(); if (campo.value.trim()) enviar({ codigo: campo.value.trim() }); });

    // câmera
    const botaoCam = raiz.querySelector('[data-camera]'); const video = raiz.querySelector('[data-video]');
    let fluxo = null; let ultimo = ''; let ultimoEm = 0;
    botaoCam.addEventListener('click', async () => {
      if (fluxo) { fluxo.getTracks().forEach((t) => t.stop()); fluxo = null; video.hidden = true; botaoCam.textContent = 'Ler com a câmera'; return; }
      if (!('BarcodeDetector' in window) || !navigator.mediaDevices) { raiz.querySelector('[data-camera-aviso]').hidden = false; return; }
      try {
        const det = new window.BarcodeDetector({ formats: ['qr_code'] });
        fluxo = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        video.srcObject = fluxo; video.hidden = false; await video.play(); botaoCam.textContent = 'Parar câmera';
        const loop = async () => {
          if (!fluxo) return;
          try {
            const achados = await det.detect(video);
            const v = achados[0]?.rawValue;
            if (v && (v !== ultimo || Date.now() - ultimoEm > 4000)) { ultimo = v; ultimoEm = Date.now(); await enviar({ codigo: v }); }
          } catch (e) { /* quadro sem leitura */ }
          setTimeout(loop, 250);
        };
        loop();
      } catch (e) { raiz.querySelector('[data-camera-aviso]').hidden = false; raiz.querySelector('[data-camera-aviso]').textContent = 'Não foi possível abrir a câmera (permissão negada?).'; }
    });

    // busca manual
    const busca = raiz.querySelector('[data-busca]'); const lista = raiz.querySelector('[data-lista]'); let atraso;
    busca.addEventListener('input', () => {
      clearTimeout(atraso);
      const q = busca.value.trim();
      if (q.length < 2) { lista.innerHTML = ''; return; }
      atraso = setTimeout(async () => {
        const resp = await fetch(raiz.dataset.buscar + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
        const itens = resp.ok ? await resp.json() : [];
        lista.innerHTML = '';
        if (!itens.length) { lista.textContent = 'Ninguém encontrado.'; return; }
        itens.forEach((i) => {
          const linha = document.createElement('div'); linha.className = 'checkin__item';
          const info = document.createElement('div');
          const nome = document.createElement('b'); nome.textContent = i.nome || '—'; info.appendChild(nome);
          info.appendChild(document.createElement('br'));
          const sub = document.createElement('small'); sub.textContent = `${i.numero} · ${i.tipo || ''} · ${i.status_texto}`; info.appendChild(sub);
          const b = document.createElement('button'); b.type = 'button'; b.className = 'botao botao--primario botao--pequeno';
          b.textContent = sentido() === 'saida' ? 'Registrar saída' : 'Registrar entrada';
          b.disabled = i.status !== 'valido' && !(i.status === 'utilizado' && sentido() === 'saida');
          b.addEventListener('click', async () => { await enviar({ ingresso: i.uuid }); busca.dispatchEvent(new Event('input')); });
          linha.append(info, b); lista.appendChild(linha);
        });
      }, 250);
    });
  });

  // Cupom: lotes do evento escolhido
  $$('[data-cupom-form]').forEach((form) => {
    const sel = form.querySelector('[data-cupom-evento]');
    const atualizar = () => {
      form.querySelectorAll('[data-lotes-evento]').forEach((g) => {
        const ativo = g.dataset.lotesEvento === sel.value;
        g.hidden = !ativo;
        g.querySelectorAll('input').forEach((i) => { i.disabled = !ativo; });
      });
      const aviso = form.querySelector('[data-sem-evento]'); if (aviso) aviso.hidden = !!sel.value;
    };
    sel.addEventListener('change', atualizar);
    // Divulgação: esconde desconto, limites, pedido mínimo e validade
    const tipo = form.querySelector('[name=tipo_desconto]');
    const tipoAtualizar = () => {
      const div = tipo.value === 'divulgacao';
      form.querySelectorAll('[data-nao-divulgacao]').forEach((el) => { el.hidden = div; el.querySelectorAll('input').forEach((i) => { i.disabled = div; }); });
      form.querySelectorAll('[data-so-divulgacao]').forEach((el) => { el.hidden = !div; });
    };
    tipo.addEventListener('change', tipoAtualizar); tipoAtualizar();
  });

  // Mensagens: inserir variável e pré-visualizar
  $$('[data-modelo-form]').forEach((form) => {
    const texto = form.querySelector('textarea[name=corpo_texto]');
    form.querySelectorAll('[data-inserir]').forEach((b) => b.addEventListener('click', () => {
      const ini = texto.selectionStart ?? texto.value.length;
      texto.setRangeText(b.dataset.inserir, ini, texto.selectionEnd ?? ini, 'end'); texto.focus();
    }));
    form.querySelector('[data-ver-previa]').addEventListener('click', async () => {
      const token = document.querySelector('meta[name=csrf-token]')?.content;
      const resp = await fetch(form.dataset.previa, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify({ assunto: form.querySelector('[name=assunto]').value, corpo_texto: texto.value }) });
      if (!resp.ok) return;
      const r = await resp.json();
      form.querySelector('[data-previa-assunto]').textContent = r.assunto;
      form.querySelector('[data-previa-texto]').textContent = r.texto;
      form.querySelector('[data-previa-caixa]').hidden = false;
    });
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
