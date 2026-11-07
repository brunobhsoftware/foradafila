# Fora da Fila — Regras de Negócio do Banco de Dados

Nov 7, 2026 · @Bruno Duarte de Magalhães

## 1. Dados técnicos do banco (Hostinger)

O banco de produção é o MySQL `u839930205_foradafila`, vazio (1 MB), vinculado ao site foradafila.com.br.

| Item | Valor |
| --- | --- |
| SGBD | MariaDB 11.8.9 (Hostinger, hospedagem web) |
| Banco | `u839930205_foradafila` |
| Usuário | `u839930205_bhsoftware` |
| Criado em | 06/11/2026 |
| Site | foradafila.com.br |
| Host a partir da aplicação | `127.0.0.1`, porta `3306` (PHP também aceita `localhost`) |
| Host externo | `srvNNNN.hstgr.io` — somente para acesso remoto, após liberar o IP no hPanel |
| Engine | InnoDB em todas as tabelas (transações e chaves estrangeiras) |
| Charset / collation | `utf8mb4` / `utf8mb4_unicode_ci` |
| Fuso horário | Gravação em UTC; exibição em America/Sao\_Paulo |

Regras de conexão e segurança:

1. A senha nunca fica no código-fonte, no Git ou em JavaScript do navegador. Em PHP, fica em arquivo de configuração fora do `public_html`; em Node.js, em variável de ambiente do hPanel.
2. A aplicação usa somente consultas parametrizadas (prepared statements). SQL concatenado com dado do usuário é proibido.
3. O acesso remoto (`hstgr.io`) fica desligado em produção e é liberado só por IP fixo e por tempo limitado, para manutenção.
4. Toda alteração de estrutura passa por script de migração versionado (`V001__criacao.sql`, `V002__…`), nunca por edição manual no phpMyAdmin.
5. Além do backup automático da Hostinger, um dump diário (`mysqldump --single-transaction`) é guardado fora do servidor.
6. Confirmar a versão exata com `SELECT VERSION();` — se for MySQL 8, usar `utf8mb4_0900_ai_ci`.

Pendência: definir a linguagem do backend (PHP/Laravel ou Node.js), que decide onde ficam as credenciais.

## 2. Princípios gerais de modelagem

Toda linha de negócio pertence a um organizador, e nenhuma consulta pode ler ou alterar dados de outro organizador.

| Regra | Como se aplica no banco |
| --- | --- |
| Multi-tenant por coluna | Toda tabela de negócio tem `organizador_id` NOT NULL, indexado; toda consulta da aplicação filtra por ele |
| Chave primária | `BIGINT UNSIGNED AUTO_INCREMENT` interno + `uuid` `CHAR(36)` único, que é o único identificador exposto em URL e API |
| Dinheiro | Sempre `DECIMAL(12,2)` em reais; nunca `FLOAT`/`DOUBLE`. Percentuais em `DECIMAL(5,2)` |
| Preço congelado | Pedido e ingresso gravam o preço e a taxa vigentes no momento da compra; mudanças posteriores no lote não os alteram |
| Datas | `DATETIME` em UTC; colunas `criado_em` e `atualizado_em` em todas as tabelas |
| Exclusão lógica | Eventos, lotes, pedidos e ingressos nunca são apagados: usam `status` e `cancelado_em`. Exclusão física só para dados sem valor financeiro |
| Status como ENUM | Valores fechados (`rascunho`, `publicado`…) em `ENUM` ou tabela de domínio, nunca texto livre |
| Auditoria | Tabela `auditoria` registra quem, quando, ação, tabela, registro e valores antes/depois em JSON, para dados financeiros e de permissão |
| Senhas | Somente hash (`password_hash` / Argon2id ou bcrypt); nunca senha em texto ou com MD5/SHA1 |
| Segredos de pagamento | Tokens do Mercado Pago, PayPal etc. gravados criptografados (AES-256-GCM) com chave fora do banco |
| LGPD | CPF, telefone e e-mail do comprador são dados pessoais: coleta mínima, finalidade registrada, consentimento com data, anonimização a pedido do titular sem apagar o registro financeiro |
| Integridade | Chaves estrangeiras com `ON DELETE RESTRICT` em tudo que é financeiro; valores validados com `CHECK` (preço ≥ 0, quantidade > 0) |

## 3. Papel da plataforma e responsabilidade

O Fora da Fila é prestador de serviço de tecnologia: liga organizador e comprador, nunca recebe o dinheiro do ingresso e só recebe sua comissão, repassada pela operadora ou cobrada em fatura.

- O organizador é o único responsável pelo evento: realização, segurança, alvarás, meia-entrada, cancelamento e reembolso ao comprador.
- Essa condição fica em **termos versionados** (`termo`) com aceite registrado (`termo_aceite`: quem, versão, data, IP, navegador).
- Termos previstos: *Termo de uso do organizador*, *Termo de responsabilidade do organizador*, *Termos de compra do comprador*, *Política de privacidade*.
- Sem aceite da versão vigente do termo do organizador, o banco recusa a publicação de evento (trigger).
- Nova versão de termo exige novo aceite; o aceite antigo nunca é apagado (prova jurídica).
- A página do evento e o e-mail de confirmação exibem a razão social e o CNPJ/CPF do organizador como responsável pelo evento.
- LGPD: o organizador é o **controlador** dos dados dos seus participantes; o Fora da Fila é o **operador**. Cada organizador só enxerga quem comprou para os eventos dele.

## 4. Mapa dos módulos

&#91;embedded content: módulos do banco · 12 módulos, 54 tabelas\]

A base (organizador e equipe) vem primeiro; o evento, com seus lotes, é o centro do qual dependem formulário, cupons, convidados, vendas e check-in; mensagens, recebimentos e comissão fecham o ciclo. Toda tabela de negócio carrega `organizador_id`.

## 5. Área do produtor e dashboard

Cada organizador tem uma área própria com página pública (`foradafila.com.br/p/<slug>`) e um painel que mostra, por evento, vendas, receita, lotação e check-ins.

- **Cadastro do organizador** (`organizador`): PF ou PJ, CPF/CNPJ único, razão social, nome fantasia, endereço, e-mail de SAC (exibido ao comprador) e e-mail financeiro.
- **Página pública**: `slug` único, logo, descrição, site, Instagram e WhatsApp; lista os eventos publicados e públicos.
- **Situação**: `pendente` → `ativo` → `bloqueado_debito` / `suspenso` / `encerrado`, com motivo registrado. O cadastro só fica `ativo` após e-mail verificado, documento validado e aceite dos termos.
- **Dashboard** lê três views, sempre filtradas por `organizador_id` e pelos eventos que o usuário pode ver:

| View | Mostra |
| --- | --- |
| `vw_dashboard_evento` | Por evento: ingressos vendidos, reservados e ofertados, pedidos pagos, receita bruta, descontos, check-ins e comissão da plataforma |
| `vw_vendas_lote` | Por lote: preço, vendidos, disponíveis e % vendido |
| `vw_participantes` | Lista completa de participantes (seção 9) |

- Valores financeiros do dashboard só aparecem para quem tem a permissão `financeiro.ver` ou `dashboard.ver` com acesso ao evento.
- Quando o volume crescer, as views viram tabelas de resumo atualizadas por job (sem mudar a tela).

## 6. Níveis de acesso

O acesso é por perfil: cada perfil é um conjunto de 30 permissões (`modulo.acao`), e cada usuário da equipe recebe um perfil por organizador, podendo ser limitado a eventos específicos.

| Área / permissões | Dono | Gerente | Financeiro | Check-in | Segurança | Promoter |
| --- | --- | --- | --- | --- | --- | --- |
| Dashboard | ✓ | ✓ | ✓ | — | — | — |
| Eventos, lotes e formulário | ✓ | ✓ | ver | — | ver | — |
| Publicar evento | ✓ | ✓ | — | — | — | — |
| Cancelar evento | ✓ | — | — | — | — | — |
| Cupons, promoters, mensagens, campanhas | ✓ | ✓ | — | — | — | só as próprias vendas |
| Convidados e acesso privado | ✓ | ✓ | — | — | — | — |
| Pedidos e participantes (ver) | ✓ | ✓ | ✓ | — | só nomes | — |
| Dados completos e exportação | ✓ | ✓ | — | — | — | — |
| Emitir cortesia manual | ✓ | ✓ | — | — | — | — |
| Reembolsar | ✓ | — | ✓ | — | — | — |
| Fazer check-in | ✓ | ✓ | — | ✓ | — | — |
| Acompanhar entradas e lotação | ✓ | ✓ | — | — | ✓ | — |
| Configurar portões e dispositivos | ✓ | ✓ | — | — | — | — |
| Financeiro, contas e faturas | ✓ | — | ✓ | — | — | — |
| Equipe e auditoria | ✓ | — | — | — | — | — |

Regras:

1. Os 6 perfis acima são padrão do sistema (`organizador_id` NULL). O organizador pode criar perfis próprios, combinando as permissões que quiser.
2. O perfil **Dono** não é editável, tem todas as permissões, e o último dono ativo não pode ser removido nem rebaixado.
3. Usuário com `todos_eventos = 0` só enxerga os eventos listados em `organizador_usuario_evento` (ex.: equipe de portaria contratada para um evento).
4. Membro entra por convite: e-mail + token com validade de 72 h; status `convidado` → `ativo`.
5. A mesma pessoa pode trabalhar para vários organizadores, com perfil diferente em cada um.
6. Toda mudança de perfil, permissão ou membro grava em `auditoria`.
7. 2FA obrigatório para perfis com `financeiro.contas` ou `equipe.gerenciar`; 5 senhas erradas bloqueiam o login por 15 minutos.

## 7. Eventos, tipos de ingresso e lotes

O evento se divide em tipos de ingresso (setores como Pista, VIP, Camarote), e cada tipo em lotes com preço, quantidade e janela de venda próprios.

**Evento** (`evento`)

- Status: `rascunho` → `publicado` ⇄ `pausado` → `encerrado`; ou `cancelado` a qualquer momento (com motivo e aviso aos compradores).
- Visibilidade: `publico` (vitrine e busca), `nao_listado` (só quem tem o link) ou `privado` (exige link de acesso privado, seção 11).
- Formato `presencial`, `online` ou `hibrido`; o link do evento online só vai para quem tem ingresso.
- Limites configuráveis: máximo de ingressos por pedido (padrão 10) e por CPF (opcional).
- Opções: ingresso nominal, CPF obrigatório do participante, transferência permitida até X horas antes, reentrada permitida, política de cancelamento em texto.
- Cada evento escolhe a conta de recebimento (`conta_pagamento_id`) que receberá as vendas.

**Publicação** — o trigger `trg_evento_bu` só deixa publicar se:

1. o organizador estiver `ativo` (sem débito vencido);
2. houver aceite do termo de responsabilidade vigente;
3. houver ao menos um lote e a soma dos lotes não passar da capacidade;
4. com ingresso pago, a conta de recebimento estiver `ativa` em produção;
5. a data de início estiver no futuro.

**Tipo de ingresso** (`ingresso_tipo`): nome, descrição, capacidade opcional do setor e ordem de exibição. Define também em quais portões o ingresso entra (seção 13).

**Lote** (`lote`)

- Modalidade: `inteira`, `meia` ou `cortesia` (cortesia sempre com preço 0).
- Mínimo e máximo por pedido; janela `venda_inicio`–`venda_fim`, nunca depois do início do evento.
- Virada: `manual`, `ao_esgotar`, `por_data` ou `esgotar_ou_data`. Com `lote_anterior_id`, o lote abre sozinho quando o anterior esgota ou encerra (job a cada minuto).
- Visibilidade: `publico`, `oculto` (só por link de acesso privado) ou `convidados` (só por lista de convidados).
- Status: `agendado` → `ativo` → `esgotado` / `encerrado`, ou `pausado`. O banco muda para `esgotado` sozinho quando reservado + vendido = quantidade.
- Lote com venda ou reserva não muda de preço (trigger); para novo preço, cria-se o lote seguinte.
- Meia-entrada: lote `meia` exige categoria e documento no formulário, conferidos na portaria. A cota legal de meia-entrada (Lei 12.933/2013) fica a cargo do organizador — a validar.

## 8. Formulário de inscrição configurável

Todos os campos possíveis já existem como colunas na tabela `participante`; o organizador só escolhe, por evento, quais pedir e quais são obrigatórios. Campos que não estão no catálogo viram campos personalizados.

| Grupo | Campos do catálogo (`campo_catalogo` = coluna em `participante`) |
| --- | --- |
| Identificação | nome (sempre obrigatório), nome social, CPF, RG, órgão emissor/UF, passaporte, data de nascimento, gênero, nacionalidade |
| Contato | e-mail, telefone, WhatsApp, Instagram |
| Endereço | CEP, endereço, número, complemento, bairro, cidade, estado, país |
| Perfil | empresa, cargo, escolaridade, tamanho da camiseta |
| Evento | categoria e documento da meia-entrada, necessidade de acessibilidade\*, restrição alimentar\*, contato e telefone de emergência, como conheceu o evento |
| Aceites | termo de responsabilidade, uso de imagem, regulamento (grava data/hora do aceite) |

\* Dado pessoal sensível (LGPD): marcado `sensivel = 1`, só visível com `participante.ver_dados_completos`.

Regras:

1. Cada evento tem um formulário (`formulario`); o organizador pode salvar formulários-modelo (`evento_id` NULL) e copiá-los para novos eventos.
2. `formulario_campo` liga o formulário a um campo do catálogo **ou** a um campo personalizado — nunca os dois (CHECK). Define rótulo, texto de ajuda, obrigatório e ordem.
3. Um campo pode valer só para um tipo de ingresso (ex.: tamanho da camiseta só no VIP).
4. Campo personalizado tem tipo (`texto`, `texto_longo`, `numero`, `data`, `selecao`, `multipla`, `checkbox`) e a resposta vai para `participante_resposta`.
5. O formulário é preenchido por **participante** (um por ingresso). O comprador informa sempre nome, e-mail, CPF e telefone e pode marcar “sou eu o participante”.
6. O termo de responsabilidade do evento tem texto próprio no formulário; se for obrigatório, sem marcar não há compra.
7. Com vendas iniciadas, campos podem ser adicionados como opcionais, mas não tornados obrigatórios (quem já comprou não tem o dado).
8. Peça só o necessário: a tela de configuração avisa quando um campo sensível é ativado (minimização, LGPD).

## 9. Pedidos, compradores e participantes

Comprador é quem paga (um por pedido); participante é o titular de cada ingresso. Um pedido de 3 ingressos tem 1 comprador e 3 participantes.

| Conceito | Tabela | Identificação exibida |
| --- | --- | --- |
| Comprador | `comprador` | nome, e-mail (único), CPF, telefone |
| Pedido | `pedido` + `pedido_item` | número do pedido (`codigo`, ex.: FF7K2M9Q), data da compra |
| Ingresso | `ingresso` | número sequencial por evento (ex.: FF-0042-000123) + QR Code |
| Participante | `participante` (+ `participante_resposta`) | nome e campos do formulário |

**Lista de participantes** (`vw_participantes`), filtrável e exportável: número do ingresso, participante, tipo de ingresso, lote, valor pago, status, número do pedido, data da compra, comprador, cupom, promoter e se fez check-in (sim/não, com horário).

Regras:

1. Status do pedido: `reservado` → `aguardando_pagamento` → `pago`; ou `expirado`, `recusado`, `cancelado`, `reembolsado`, `reembolso_parcial`, `contestado`.
2. Valores: `valor_total = valor_bruto − valor_desconto` (CHECK). Sem taxa ao comprador: não existe coluna de taxa de conveniência.
3. Origem do pedido registrada: `site`, `link_promoter`, `acesso_privado`, `lista_convidados` ou `emissao_manual`, com UTMs.
4. Ingresso só é emitido com pedido `pago` confirmado pelo gateway (ou na hora, se gratuito).
5. Número do ingresso: contador `evento.ultimo_numero_ingresso`, incrementado na mesma transação; único por evento.
6. QR Code: 256 bits aleatórios + assinatura HMAC; não é sequencial nem derivável do número.
7. Status do ingresso: `valido`, `utilizado`, `cancelado`, `reembolsado`, `transferido`.
8. Transferência de titularidade (se o evento permitir e dentro do prazo): cria ingresso novo com QR novo, marca o antigo `transferido` e grava `ingresso_transferencia`.
9. Comprador pode criar senha para ver todos os ingressos em “Meus ingressos”; sem senha, acessa por link enviado ao e-mail.
10. Anonimização a pedido do titular (LGPD): apaga dados pessoais e mantém números, valores e datas para a contabilidade.

## 10. Cupons e links de divulgação

O organizador cria cupons de desconto e links rastreáveis para promoters; um link pode aplicar um cupom automaticamente, e cada venda fica atribuída ao link e ao cupom usados.

**Cupom** (`cupom`, `cupom_lote`, `cupom_uso`)

- Código único por organizador, gravado em maiúsculas (ex.: `SLOD10`).
- Desconto `percentual` (até 100%) ou `valor_fixo`, aplicado por ingresso ou no pedido.
- Escopo: todos os eventos do organizador, um evento ou lotes específicos.
- Limites: total de usos, usos por CPF, valor mínimo do pedido, validade de/até.
- Um cupom por pedido; desconto nunca maior que o preço (CHECK em `pedido_item`).
- O uso é reservado junto com o estoque e confirmado no pagamento; pedido expirado ou reembolsado devolve o uso. O contador sobe com UPDATE condicional (`usos < max_usos`), como no estoque.
- Cupom de 100% gera ingresso gratuito, sem passar pelo gateway.

**Promoter / link de divulgação** (`promoter`)

- Link `foradafila.com.br/e/<evento>?ref=<codigo>`, com UTM opcional e cupom automático opcional.
- Contador de cliques; vendas por promoter calculadas a partir de `pedido.promoter_id`.
- Promoter pode ter usuário com perfil Promoter para ver só as próprias vendas.
- Atribuição: vale o último link clicado, guardado por 30 dias no navegador (regra a confirmar).
- Comissão de promoter é assunto entre organizador e promoter; a plataforma só fornece o relatório.

## 11. Convidados e acesso privado

Há dois mecanismos: **listas de convidados** (pessoas nomeadas que recebem ingresso) e **links de acesso privado** (um canal, como um grupo de WhatsApp, que libera lotes ocultos para quem tem o link).

|  | Lista de convidados | Link de acesso privado |
| --- | --- | --- |
| Tabelas | `lista_convidados`, `convidado` | `acesso_privado`, `acesso_privado_lote` |
| Quem acessa | Cada convidado, por link pessoal (token) | Qualquer pessoa com o link do canal |
| Uso típico | VIP, imprensa, aniversariantes, parceiros | Sócios, clientes fiéis, pré-venda para um grupo |
| Ingresso | Cortesia ou lote pago específico | Compra normal dos lotes liberados |
| Limites | Total de convidados, acompanhantes por convidado, data para confirmar | Máximo de usos, validade, senha opcional |

Regras:

1. Convidado: `pendente` → `enviado` → `confirmado` → `emitido`; ou `recusado` / `cancelado`. Na emissão, gera um pedido de origem `lista_convidados` e os ingressos (convidado + acompanhantes).
2. Mesmo CPF não entra duas vezes na mesma lista.
3. Cortesias consomem estoque do lote como qualquer ingresso e aparecem na lista de participantes e no check-in.
4. Lote `convidados` só é emitido por lista; lote `oculto` só aparece com token de acesso privado válido.
5. Evento `privado` só abre a página com token válido; cada uso do link conta em `usos`.
6. Links e tokens podem ser desativados a qualquer momento sem afetar ingressos já emitidos.
7. Taxa da plataforma sobre cortesia segue o plano (`valor_ingresso_gratuito`, padrão R$ 0).

## 12. Mensagens e divulgação

Toda mensagem sai de um modelo editável e passa por uma fila única de envio; campanhas de divulgação (fase futura) usam a mesma fila e só alcançam quem deu consentimento.

**Modelos** (`modelo_mensagem`)

- Tipos: confirmação da compra, ingresso emitido, pagamento pendente, lembrete do evento, alteração, cancelamento, reembolso, convite, transferência, pós-evento.
- Canais: e-mail (fase 1); WhatsApp e SMS previstos.
- Hierarquia: modelo do **evento** → modelo do **organizador** → modelo **padrão do sistema**. Vale o mais específico que estiver ativo.
- Variáveis: `{{comprador_nome}}`, `{{participante_nome}}`, `{{pedido_codigo}}`, `{{ingresso_numero}}`, `{{evento_titulo}}`, `{{evento_data}}`, `{{local_nome}}`, `{{link_meus_ingressos}}`, `{{organizador_razao_social}}`, `{{organizador_documento}}`, `{{organizador_email_contato}}`.
- O e-mail de confirmação sempre traz o organizador como responsável pelo evento e o contato de SAC dele — mesmo que o organizador edite o texto, esse rodapé é fixo.
- Lembrete do evento: enviado X horas antes (`antecedencia_h`).

**Fila de envio** (`mensagem_envio`)

- Status: `pendente` → `enviando` → `enviado` → `entregue` / `aberto`; ou `erro` (até 5 tentativas) / `descartado`.
- Prioridade 1 para transacionais (confirmação, ingresso); campanhas sempre atrás.
- Grava o corpo já renderizado: o que foi enviado fica como prova.

**Campanhas** (`campanha`, `consentimento_marketing`) — fase futura

- O organizador monta a campanha (texto, evento divulgado, segmento: quem foi a eventos anteriores, cidade, faixa etária) e agenda o envio.
- Só recebe quem marcou o aceite de marketing **daquele organizador** e não revogou (LGPD). Todo e-mail tem link de descadastro, que preenche `revogado_em`.
- Um destinatário não recebe a mesma campanha duas vezes (índice único).
- Limite diário de envios por organizador, para proteger a reputação do domínio (valor a definir).

## 13. Check-in

O check-in é feito pela leitura do QR Code no navegador (web) ou no aplicativo, inclusive sem internet; cada leitura, aceita ou recusada, fica registrada.

- **Pontos de acesso** (`checkin_ponto`, `checkin_ponto_tipo`): portões do evento (Portão A, Camarote) e quais tipos de ingresso cada um aceita. VIP lido no portão da Pista → `setor_nao_permitido`.
- **Dispositivos** (`dispositivo_checkin`): cada celular/app é registrado para um evento e um operador, com token próprio; pode ser revogado na hora (celular perdido).
- **Métodos**: QR Code, busca por nome/CPF ou número do ingresso (busca manual exige perfil com `checkin.realizar`).
- **Resultado** gravado em `checkin_log`: `aceito`, `ja_utilizado`, `cancelado`, `outro_evento`, `setor_nao_permitido`, `inexistente`, `assinatura_invalida`, `fora_do_horario`.
- **Online**: o check-in é um UPDATE atômico (`... WHERE status = 'valido'`); 0 linhas afetadas = já utilizado. Dois portões lendo o mesmo QR ao mesmo tempo → só um aceita.
- **Offline**: o app baixa a lista do evento (QR + nome + tipo), valida a assinatura localmente e guarda as leituras. Ao reconectar, sincroniza; `id_local_app` impede duplicar a mesma leitura e, havendo conflito, vale a leitura com `lido_em` mais antigo.
- **Reentrada**: se o evento permitir, a leitura alterna `entrada`/`saida` (`dentro_do_evento`); se não permitir, a segunda leitura é recusada.
- **Acompanhamento** (perfil Segurança): entradas por hora, por portão e lotação atual (entradas − saídas) frente à capacidade.
- O check-in só é aceito entre a abertura dos portões e o fim do evento (fora disso: `fora_do_horario`).

## 14. Financeiro do organizador

O organizador cadastra e homologa as próprias contas de recebimento; o dinheiro de cada venda cai direto nelas, e o Fora da Fila só registra pagamentos, reembolsos e avisos do gateway.

**Contas de recebimento** (`gateway`, `conta_pagamento`)

- Gateways cadastrados: Mercado Pago (ativo na fase 1), PayPal e PagBank (previstos, inativos).
- O organizador pode ter várias contas (ex.: uma por CNPJ) e escolhe uma por evento; uma é a padrão.
- Configurações por conta: meios aceitos (Pix, cartão, boleto), parcelas máximas (1 a 12), quem paga os juros do parcelamento, validade do Pix (padrão 15 min) e nome na fatura do cartão.
- Credenciais via OAuth do gateway, guardadas criptografadas; o organizador nunca digita senha do gateway no Fora da Fila.

**Homologação** (`homologacao_teste`) — status da conta:

1. `pendente` → `conectada`: OAuth concluído em sandbox.
2. `em_homologacao`: testes executados e registrados — conexão, Pix aprovado, cartão aprovado, cartão recusado, aviso (webhook) recebido, reembolso e, se houver split, retenção da comissão.
3. `homologada`: o trigger só permite com os 5 testes obrigatórios em `ok` (conexão, Pix, cartão aprovado, webhook, reembolso).
4. `ativa`: conta de produção conectada; só depois de homologada. Apenas conta `ativa` em produção vende ingresso pago.
5. `suspensa` (token expirado, falha recorrente) ou `revogada` (organizador desconectou): vendas pagas param até regularizar.

**Pagamentos e reembolsos** (`pagamento`, `reembolso`, `reembolso_ingresso`, `webhook_evento`)

- Cada tentativa de pagamento é uma linha em `pagamento` (um pedido pode ter Pix expirado e depois cartão aprovado). Guarda valor, tarifa do gateway, comissão retida, líquido do organizador, final do cartão e bandeira — nunca o número do cartão.
- Status: `criado`, `pendente`, `em_analise`, `aprovado`, `recusado`, `cancelado`, `expirado`, `estornado`, `estorno_parcial`, `contestado`.
- Avisos do gateway: gravados brutos, assinatura validada, status reconsultado na API, idempotência por (`gateway`, `id_evento_externo`), conciliação diária para avisos perdidos.
- Reembolso total ou por ingresso, com motivo (`arrependimento`, `evento_cancelado`, `evento_alterado`, `decisao_organizador`, `contestacao`, `pagamento_tardio_sem_estoque`); quem decide é o organizador, a plataforma só executa no gateway.
- Pagamento aprovado após a reserva expirar: aceito se ainda houver estoque; senão, reembolso automático (`solicitado_por = sistema`).
- Relatório do organizador: vendas brutas, descontos, tarifas do gateway, comissão da plataforma e líquido, por evento e por período.

## 15. Comissão da plataforma e inadimplência

A comissão é calculada por ingresso vendido, com percentual, valor fixo e uma taxa mínima; o banco aceita os dois modos de cobrança ainda em estudo, configurados por plano.

Taxa de cada ingresso pago (valor pago = preço − desconto):

```latex
\text{taxa} = \max\left(\text{valor pago} \times \frac{\text{percentual}}{100} + \text{valor fixo},\ \text{valor mínimo}\right)
```

Ingresso gratuito ou cortesia paga `valor_ingresso_gratuito` (padrão R$ 0). Exemplo com 3% + R$ 0,00 e mínimo R$ 1,00: ingresso de R$ 20 → R$ 1,00; ingresso de R$ 80 → R$ 2,40.

|  | Modo `split` | Modo `fatura` |
| --- | --- | --- |
| Como a plataforma recebe | A operadora retém a comissão na própria venda e repassa ao Fora da Fila | Fatura mensal (Pix/boleto) emitida ao organizador |
| Risco de inadimplência | Nenhum | Existe → bloqueio por débito |
| Status do lançamento | `retida_split` | `pendente` → `faturada` |
| Depende de | Gateway com split (`gateway.suporta_split`); Mercado Pago a confirmar na homologação | Nada além da plataforma |

Regras:

1. `plano`: percentual, valor fixo por ingresso, valor mínimo por ingresso, valor por ingresso gratuito, modo de cobrança, dia de fechamento e prazo de vencimento. Plano “Lançamento” com tudo zerado já vem cadastrado.
2. Organizador pode ter plano negociado (`publico = 0`); o histórico fica em `organizador_plano`, sem períodos sobrepostos.
3. `taxa_lancamento`: uma linha por ingresso pago, com base, percentual e fixo **congelados** na data do pagamento. Reembolso gera linha de estorno com valor negativo; a original nunca é apagada.
4. Fatura (`fatura`) fecha por competência (AAAA-MM), soma cobranças e estornos e vence em N dias. Fatura fechada não muda; estorno tardio vira crédito na próxima. Total zero → `isenta`.
5. Fatura vencida → organizador `bloqueado_debito` → o banco recusa criar e publicar eventos (trigger). Eventos já publicados continuam vendendo e fazendo check-in, para não prejudicar o comprador.
6. Pagamento da fatura confirmado (`fatura_pagamento`) → organizador volta a `ativo` automaticamente.
7. O valor da comissão aparece ao organizador antes de publicar o evento e no relatório por evento (`vw_dashboard_evento.comissao_plataforma`).

## 16. Estoque e concorrência

Nenhum lote pode vender mais que sua quantidade, mesmo com centenas de compras simultâneas na abertura de vendas.

- O lote mantém contadores `quantidade`, `reservado` e `vendido`, com `CHECK (reservado + vendido <= quantidade)`.
- A reserva é um único UPDATE condicional, sem ler antes e gravar depois:

```sql
UPDATE lote
   SET reservado = reservado + :qtd
 WHERE id = :lote_id
   AND status = 'ativo'
   AND UTC_TIMESTAMP() BETWEEN venda_inicio AND venda_fim
   AND quantidade - reservado - vendido >= :qtd;
-- 0 linhas afetadas = esgotado ou fora da janela
```

- Reserva, criação do pedido e dos itens ocorrem na mesma transação (`START TRANSACTION … COMMIT`).
- Pagamento confirmado: `reservado = reservado - qtd` e `vendido = vendido + qtd`, na mesma transação que emite os ingressos.
- Um job a cada minuto expira pedidos com `expira_em < UTC_TIMESTAMP()` e devolve o reservado ao lote.
- Pedidos com mais de um lote travam as linhas sempre em ordem crescente de `lote.id`, para evitar deadlock.
- Formulário de compra envia uma `chave_idempotencia` (UUID); índice único impede pedido duplicado por duplo clique.
- Isolamento padrão do InnoDB (`REPEATABLE READ`); as regras acima não dependem de mudar o nível.

## 17. Script V001 e próximos passos

O script `V001__criacao_foradafila.sql` (revisão 2, 1.462 linhas) cria 54 tabelas, 3 views do dashboard, 4 triggers e os dados iniciais. Como o banco ainda está vazio, ele substitui a primeira versão — não é preciso migração.

Testado em MariaDB 10.11 (07/11/2026). Resultados:

| Teste | Resultado |
| --- | --- |
| Criação completa | 54 tabelas, 3 views, 4 triggers, sem erro |
| Publicar sem termo de responsabilidade aceito | Recusado |
| Publicar evento pago sem conta ativa em produção | Recusado |
| Homologar conta sem os 5 testes obrigatórios | Recusado |
| Homologar → ativar → publicar | Aceito; `publicado_em` preenchido |
| Reservar além do estoque | 0 linhas afetadas; lote vira `esgotado` sozinho |
| Mudar preço de lote com reserva | Recusado |

Dados iniciais: 30 permissões, 6 perfis padrão, 35 campos do catálogo do formulário, 3 gateways (Mercado Pago ativo), 10 categorias de evento, plano “Lançamento” (0%), 2 modelos de e-mail padrão e 3 parâmetros do sistema.

Como aplicar na Hostinger: hPanel → Bancos de dados → phpMyAdmin → `u839930205_foradafila` → Importar. Se triggers ou views forem recusados por falta de privilégio, a regra correspondente passa para a camada de serviço da aplicação.

**Aplicado em produção (07/11/2026):** importado via phpMyAdmin no banco `u839930205_foradafila` (MariaDB 11.8.9). Conferência: 54 tabelas, 3 views, 4 triggers, 30 permissões, 6 perfis, 35 campos, 3 gateways, 10 categorias, 1 plano e 2 modelos de mensagem.

- [x] Confirmar a versão do banco na Hostinger (`SELECT VERSION();`)
- [ ] Definir o backend (PHP/Laravel ou Node.js)
- [ ] Decidir o modo de cobrança (split ou fatura) e os valores: percentual, fixo e mínimo por ingresso
- [ ] Confirmar com o Mercado Pago se a conta permite reter a comissão na venda (split / marketplace)
- [ ] Redigir os 4 termos (uso, responsabilidade do organizador, compra, privacidade) com advogado
- [ ] Validar política de cancelamento, arrependimento e cota de meia-entrada
- [ ] Escolher o provedor de e-mail transacional (fila `mensagem_envio`)
- [ ] Importar primeiro num banco de homologação e só depois em produção
