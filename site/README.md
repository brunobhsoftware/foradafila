# Site — página inicial (fase 1, estática)

HTML e CSS puros, sem build. Os links ainda não levam a lugar nenhum (`href="#"`); a única interação é o menu no celular.

```
site/
├── index.html
└── assets/
    ├── css/site.css          # paleta e fontes em variáveis no topo (:root)
    └── img/
        ├── simbolo.svg       # símbolo da marca (fundo claro), redesenhado em vetor
        ├── simbolo-branco.svg# símbolo para fundo escuro
        ├── favicon.svg       # ícone do app / aba do navegador
        ├── hero-show.jpg     # foto do show (+ .webp) — recortada do layout
        └── ilustracao-fila.png # ilustração "saindo da fila", fundo transparente
```

O painel (dashboard) e a paleta de cores foram feitos em HTML/CSS, não são imagens.

## Ver localmente

```bash
cd site && python3 -m http.server 8000
```

## Imagens provisórias

`hero-show.jpg` e `ilustracao-fila.png` foram recortadas do layout de referência
(`docs/identidade-visual/`), que é de baixa resolução. Substituir pelos arquivos originais
em alta resolução quando existirem, mantendo os mesmos nomes.
