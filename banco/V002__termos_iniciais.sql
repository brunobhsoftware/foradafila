-- =====================================================================
-- Fora da Fila — V002: termos iniciais (versão provisória 0.1)
-- Banco : u839930205_foradafila
-- Por quê: o cadastro do produtor registra o aceite dos termos vigentes
--          (termo_aceite) e o trigger trg_evento_bu só deixa publicar
--          evento com o termo de responsabilidade vigente aceito.
-- ATENÇÃO: textos PROVISÓRIOS para o projeto piloto. Devem ser revisados
--          por advogado; a versão revisada entra como 1.0 (novo INSERT
--          com vigente = 1 e UPDATE da 0.1 para vigente = 0), o que exige
--          novo aceite dos organizadores.
-- Idempotente: pode ser executado mais de uma vez.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

INSERT INTO termo (tipo, versao, titulo, conteudo_html, vigente_desde, vigente)
SELECT * FROM (
  SELECT 'uso_organizador' AS tipo, '0.1' AS versao, 'Termo de uso do organizador' AS titulo,
  '<p><strong>Versão provisória 0.1 — em revisão jurídica.</strong></p>
<p>1. O Fora da Fila é uma plataforma de tecnologia que permite ao organizador cadastrar eventos, vender ingressos e controlar o acesso do público.</p>
<p>2. O organizador é responsável pela veracidade dos dados cadastrados (CPF/CNPJ, razão social, contatos) e pela guarda das senhas da sua equipe.</p>
<p>3. O valor dos ingressos é pago pelo comprador diretamente na conta de recebimento do organizador no meio de pagamento escolhido. O Fora da Fila não recebe nem repassa o valor dos ingressos.</p>
<p>4. Pelo uso da plataforma, o organizador paga a comissão do plano contratado, por ingresso vendido, retida pela operadora ou cobrada em fatura. Faturas vencidas impedem a criação e a publicação de novos eventos até a quitação.</p>
<p>5. O Fora da Fila pode suspender o acesso em caso de fraude, uso indevido ou descumprimento destes termos.</p>' AS conteudo_html,
  UTC_TIMESTAMP() AS vigente_desde, 1 AS vigente
  UNION ALL
  SELECT 'responsabilidade_organizador', '0.1', 'Termo de responsabilidade do organizador',
  '<p><strong>Versão provisória 0.1 — em revisão jurídica.</strong></p>
<p>Ao aceitar este termo, o organizador declara que:</p>
<p>1. É o único responsável pela realização dos eventos que publicar: local, segurança, alvarás e licenças, acessibilidade, classificação etária, cumprimento da meia-entrada (Lei 12.933/2013) e demais obrigações legais.</p>
<p>2. É o responsável pelo atendimento ao comprador, por alterações, adiamentos e cancelamentos do evento e pelos reembolsos devidos, inclusive o direito de arrependimento previsto no Código de Defesa do Consumidor.</p>
<p>3. É o controlador dos dados pessoais dos participantes dos seus eventos, nos termos da LGPD, e o Fora da Fila atua como operador.</p>
<p>4. Reconhece que o Fora da Fila é apenas prestador de serviço de tecnologia e intermediário entre organizador e comprador, sem responsabilidade pela realização do evento.</p>',
  UTC_TIMESTAMP(), 1
  UNION ALL
  SELECT 'compra_comprador', '0.1', 'Termos de compra',
  '<p><strong>Versão provisória 0.1 — em revisão jurídica.</strong></p>
<p>O ingresso é vendido pelo organizador indicado na página do evento, que é o responsável pelo evento, por alterações, cancelamentos e reembolsos. O Fora da Fila fornece a tecnologia de venda e de check-in.</p>',
  UTC_TIMESTAMP(), 1
  UNION ALL
  SELECT 'privacidade', '0.1', 'Política de privacidade',
  '<p><strong>Versão provisória 0.1 — em revisão jurídica.</strong></p>
<p>Os dados pessoais são tratados para cadastro, venda de ingressos, check-in e atendimento, conforme a LGPD. O organizador do evento é o controlador dos dados dos seus participantes; o Fora da Fila atua como operador. O titular pode pedir acesso, correção ou anonimização pelos canais de contato do organizador.</p>',
  UTC_TIMESTAMP(), 1
) novos
WHERE NOT EXISTS (SELECT 1 FROM termo t WHERE t.tipo = novos.tipo AND t.versao = novos.versao);

-- Conferência (esperado: 4 linhas, todas vigente = 1)
SELECT tipo, versao, vigente FROM termo ORDER BY tipo;
