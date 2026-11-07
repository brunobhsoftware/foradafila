-- =====================================================================
-- Fora da Fila — V007: administrador da plataforma (equipe BH Software)
-- Só dados. Idempotente: se o e-mail já existir (ex.: já é produtor), só marca como administrador.
-- A senha NÃO vai no script: no primeiro acesso, use "Esqueci minha senha" na tela de entrar
-- (área do produtor) com este e-mail; chega um link para criar a senha (vale 60 minutos).
-- Importar no phpMyAdmin com o banco u839930205_foradafila selecionado.
-- =====================================================================
INSERT INTO usuario (uuid, nome, email, telefone, admin_plataforma, ativo)
VALUES (UUID(), 'Bruno Duarte de Magalhães', 'bruno@bhsoftware.com.br', '31992161610', 1, 1)
ON DUPLICATE KEY UPDATE admin_plataforma = 1, ativo = 1;

-- Conferência (deve mostrar "V007 ok")
SELECT IF(COUNT(*) = 1, 'V007 ok', 'V007 FALHOU') AS resultado
  FROM usuario WHERE email = 'bruno@bhsoftware.com.br' AND admin_plataforma = 1;
