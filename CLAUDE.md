# Fora da Fila — contexto do projeto

Plataforma SaaS de venda de ingressos e check-in para eventos (foradafila.com.br).
Dono do projeto: Bruno (BH Software), desenvolvedor com experiência em ASP.NET MVC.
Idioma do projeto, da documentação e das conversas: **português do Brasil**.

## Onde está cada coisa

| Arquivo | Conteúdo |
| --- | --- |
| `docs/regras-de-negocio.md` | Regras de negócio completas (17 seções). **Fonte da verdade** para modelagem e implementação. |
| `docs/requisitos-originais.md` | Orientação original do Bruno, na íntegra |
| `banco/V001__criacao_foradafila.sql` | Script de criação do banco (revisão 2, 1.462 linhas): 54 tabelas, 3 views, 4 triggers, dados iniciais |

O documento vivo (editável e comentável) continua em
https://claude.ai/artifact/SdhmCrhNMcPWmupBJZKv6b. `docs/regras-de-negocio.md` é uma exportação de 07/11/2026
(o diagrama dos 12 módulos não foi exportado).

## Modelo de negócio (resumo)

- O Fora da Fila é **só prestador de tecnologia**: o dinheiro do ingresso cai direto na conta do organizador no
  gateway (Mercado Pago na fase 1; PayPal e PagBank previstos). A plataforma recebe só a comissão.
- **Sem taxa para o comprador.** Comissão por ingresso: `max(valor_pago × % + fixo, mínimo)`.
- Dois modos de cobrança ainda em estudo: `split` (operadora retém e repassa) ou `fatura` mensal.
  Fatura vencida → organizador `bloqueado_debito` → não cria nem publica evento (trigger).
- O organizador é o responsável legal pelo evento; aceite de termos versionados obrigatório antes de publicar.
- LGPD: organizador = controlador, Fora da Fila = operador.

## Banco de dados (produção)

- MariaDB 11.8.9 na hospedagem web Hostinger. Banco `u839930205_foradafila`, usuário `u839930205_bhsoftware`.
- **V001 já importado em produção em 07/11/2026** e conferido (54 tabelas, 3 views, 4 triggers, seeds).
- A aplicação conecta em `127.0.0.1:3306`. O host `*.hstgr.io` é só para acesso remoto liberado por IP.
- Senha nunca no código nem no Git: fica no `.env` do servidor, fora do `public_html`.
- Toda mudança de estrutura entra como nova migração versionada (`V002__...`), nunca por edição manual no phpMyAdmin.
- Convenções: InnoDB, utf8mb4, datas em UTC, dinheiro `DECIMAL(12,2)`, `id BIGINT` interno + `uuid` público,
  multi-tenant por `organizador_id` em toda tabela de negócio, exclusão lógica para dados financeiros.
- Triggers: `trg_conta_bu`, `trg_evento_bi`, `trg_evento_bu`, `trg_lote_bu`.

## Decisões tomadas

- **Backend recomendado: Laravel (PHP 8.3+)** servindo site público, painel do produtor (Filament) e API.
  Ainda falta o Bruno confirmar.
- App de check-in como **PWA** (QR Code pela câmera, funciona offline), consumindo a mesma API.
- Rotinas automáticas (expirar reservas, virar lote, fila de e-mail, conciliação com o Mercado Pago) pelo
  agendador do Laravel, chamado por um cron a cada minuto no hPanel.
- Os models do Laravel devem mapear as tabelas que **já existem** (não recriar via migrations do Laravel);
  escopo global por `organizador_id`.

## Pendências

- [ ] Confirmar o backend (Laravel) e o plano da Hostinger (precisa de SSH e cron, a partir do Premium)
- [ ] Decidir o modo de cobrança (split ou fatura) e os valores: percentual, fixo e mínimo por ingresso
- [ ] Confirmar com o Mercado Pago se dá para reter a comissão na venda (split / marketplace)
- [ ] Redigir com advogado os 4 termos (uso, responsabilidade do organizador, compra, privacidade)
- [ ] Validar a política de cancelamento, o direito de arrependimento e a cota de meia-entrada
- [ ] Escolher o provedor de e-mail transacional (fila `mensagem_envio`)
- [ ] Criar um banco de homologação

## Próximo passo combinado

Montar o esqueleto do projeto Laravel: estrutura de pastas, models ligados às 54 tabelas existentes,
login com perfis e permissões (6 perfis, 30 permissões `modulo.acao`) e o agendador configurado.
