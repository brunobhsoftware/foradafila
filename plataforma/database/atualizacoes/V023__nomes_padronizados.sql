-- Fora da Fila — V023: Padroniza nomes de pessoas (1ª letra maiúscula, resto minúsculo; de/da/do/das/dos/e minúsculos no meio)
-- Pedido do Bruno, 16/11/2026. Idempotente: só altera o que estiver diferente. Mesmo resultado de App\Support\NomePessoa
-- (exceto a maiúscula depois de hífen/apóstrofo, que o botão "Padronizar nomes" da Administração também corrige).
-- Separa as palavras com JSON_TABLE (MariaDB 10.6+); aspas duplas e barras invertidas são removidas só para montar a lista.

UPDATE usuario x
JOIN (
    SELECT t.id,
           GROUP_CONCAT(CASE WHEN j.ord > 1 AND LOWER(j.w) IN ('de','da','do','das','dos','e','di','du') THEN LOWER(j.w)
                             ELSE CONCAT(UPPER(LEFT(j.w, 1)), LOWER(SUBSTRING(j.w, 2))) END
                        ORDER BY j.ord SEPARATOR ' ') AS novo
      FROM usuario t,
           JSON_TABLE(CONCAT('["', REPLACE(REGEXP_REPLACE(TRIM(REPLACE(REPLACE(t.nome, '"', ''), CHAR(92), '')), '[[:space:]]+', ' '), ' ', '","'), '"]'),
                      '$[*]' COLUMNS (ord FOR ORDINALITY, w VARCHAR(255) CHARACTER SET utf8mb4 PATH '$')) j
     WHERE t.nome IS NOT NULL AND TRIM(t.nome) <> ''
     GROUP BY t.id
) n ON n.id = x.id
   SET x.nome = n.novo
 WHERE BINARY x.nome <> BINARY n.novo;

UPDATE comprador x
JOIN (
    SELECT t.id,
           GROUP_CONCAT(CASE WHEN j.ord > 1 AND LOWER(j.w) IN ('de','da','do','das','dos','e','di','du') THEN LOWER(j.w)
                             ELSE CONCAT(UPPER(LEFT(j.w, 1)), LOWER(SUBSTRING(j.w, 2))) END
                        ORDER BY j.ord SEPARATOR ' ') AS novo
      FROM comprador t,
           JSON_TABLE(CONCAT('["', REPLACE(REGEXP_REPLACE(TRIM(REPLACE(REPLACE(t.nome, '"', ''), CHAR(92), '')), '[[:space:]]+', ' '), ' ', '","'), '"]'),
                      '$[*]' COLUMNS (ord FOR ORDINALITY, w VARCHAR(255) CHARACTER SET utf8mb4 PATH '$')) j
     WHERE t.nome IS NOT NULL AND TRIM(t.nome) <> ''
     GROUP BY t.id
) n ON n.id = x.id
   SET x.nome = n.novo
 WHERE BINARY x.nome <> BINARY n.novo;

UPDATE participante x
JOIN (
    SELECT t.id,
           GROUP_CONCAT(CASE WHEN j.ord > 1 AND LOWER(j.w) IN ('de','da','do','das','dos','e','di','du') THEN LOWER(j.w)
                             ELSE CONCAT(UPPER(LEFT(j.w, 1)), LOWER(SUBSTRING(j.w, 2))) END
                        ORDER BY j.ord SEPARATOR ' ') AS novo
      FROM participante t,
           JSON_TABLE(CONCAT('["', REPLACE(REGEXP_REPLACE(TRIM(REPLACE(REPLACE(t.nome, '"', ''), CHAR(92), '')), '[[:space:]]+', ' '), ' ', '","'), '"]'),
                      '$[*]' COLUMNS (ord FOR ORDINALITY, w VARCHAR(255) CHARACTER SET utf8mb4 PATH '$')) j
     WHERE t.nome IS NOT NULL AND TRIM(t.nome) <> ''
     GROUP BY t.id
) n ON n.id = x.id
   SET x.nome = n.novo
 WHERE BINARY x.nome <> BINARY n.novo;

UPDATE pessoa x
JOIN (
    SELECT t.id,
           GROUP_CONCAT(CASE WHEN j.ord > 1 AND LOWER(j.w) IN ('de','da','do','das','dos','e','di','du') THEN LOWER(j.w)
                             ELSE CONCAT(UPPER(LEFT(j.w, 1)), LOWER(SUBSTRING(j.w, 2))) END
                        ORDER BY j.ord SEPARATOR ' ') AS novo
      FROM pessoa t,
           JSON_TABLE(CONCAT('["', REPLACE(REGEXP_REPLACE(TRIM(REPLACE(REPLACE(t.nome, '"', ''), CHAR(92), '')), '[[:space:]]+', ' '), ' ', '","'), '"]'),
                      '$[*]' COLUMNS (ord FOR ORDINALITY, w VARCHAR(255) CHARACTER SET utf8mb4 PATH '$')) j
     WHERE t.nome IS NOT NULL AND TRIM(t.nome) <> ''
     GROUP BY t.id
) n ON n.id = x.id
   SET x.nome = n.novo
 WHERE BINARY x.nome <> BINARY n.novo;

UPDATE convidado x
JOIN (
    SELECT t.id,
           GROUP_CONCAT(CASE WHEN j.ord > 1 AND LOWER(j.w) IN ('de','da','do','das','dos','e','di','du') THEN LOWER(j.w)
                             ELSE CONCAT(UPPER(LEFT(j.w, 1)), LOWER(SUBSTRING(j.w, 2))) END
                        ORDER BY j.ord SEPARATOR ' ') AS novo
      FROM convidado t,
           JSON_TABLE(CONCAT('["', REPLACE(REGEXP_REPLACE(TRIM(REPLACE(REPLACE(t.nome, '"', ''), CHAR(92), '')), '[[:space:]]+', ' '), ' ', '","'), '"]'),
                      '$[*]' COLUMNS (ord FOR ORDINALITY, w VARCHAR(255) CHARACTER SET utf8mb4 PATH '$')) j
     WHERE t.nome IS NOT NULL AND TRIM(t.nome) <> ''
     GROUP BY t.id
) n ON n.id = x.id
   SET x.nome = n.novo
 WHERE BINARY x.nome <> BINARY n.novo;

UPDATE lista_email_contato x
JOIN (
    SELECT t.id,
           GROUP_CONCAT(CASE WHEN j.ord > 1 AND LOWER(j.w) IN ('de','da','do','das','dos','e','di','du') THEN LOWER(j.w)
                             ELSE CONCAT(UPPER(LEFT(j.w, 1)), LOWER(SUBSTRING(j.w, 2))) END
                        ORDER BY j.ord SEPARATOR ' ') AS novo
      FROM lista_email_contato t,
           JSON_TABLE(CONCAT('["', REPLACE(REGEXP_REPLACE(TRIM(REPLACE(REPLACE(t.nome, '"', ''), CHAR(92), '')), '[[:space:]]+', ' '), ' ', '","'), '"]'),
                      '$[*]' COLUMNS (ord FOR ORDINALITY, w VARCHAR(255) CHARACTER SET utf8mb4 PATH '$')) j
     WHERE t.nome IS NOT NULL AND TRIM(t.nome) <> ''
     GROUP BY t.id
) n ON n.id = x.id
   SET x.nome = n.novo
 WHERE BINARY x.nome <> BINARY n.novo;

UPDATE organizador x
JOIN (
    SELECT t.id,
           GROUP_CONCAT(CASE WHEN j.ord > 1 AND LOWER(j.w) IN ('de','da','do','das','dos','e','di','du') THEN LOWER(j.w)
                             ELSE CONCAT(UPPER(LEFT(j.w, 1)), LOWER(SUBSTRING(j.w, 2))) END
                        ORDER BY j.ord SEPARATOR ' ') AS novo
      FROM organizador t,
           JSON_TABLE(CONCAT('["', REPLACE(REGEXP_REPLACE(TRIM(REPLACE(REPLACE(t.representante_nome, '"', ''), CHAR(92), '')), '[[:space:]]+', ' '), ' ', '","'), '"]'),
                      '$[*]' COLUMNS (ord FOR ORDINALITY, w VARCHAR(255) CHARACTER SET utf8mb4 PATH '$')) j
     WHERE t.representante_nome IS NOT NULL AND TRIM(t.representante_nome) <> ''
     GROUP BY t.id
) n ON n.id = x.id
   SET x.representante_nome = n.novo
 WHERE BINARY x.representante_nome <> BINARY n.novo;

SELECT 'V023 ok' AS resultado;
