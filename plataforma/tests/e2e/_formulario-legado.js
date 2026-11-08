// Testes antigos: cria para cada organização (que ainda não tem) um formulário padrão igual ao comportamento anterior
// ao V015 — nome obrigatório, e-mail e CPF opcionais —, para que continuem preenchendo só nome/CPF.
module.exports = (sql) => {
  if (sql("SHOW COLUMNS FROM formulario LIKE 'padrao'") === '') return;
  for (const org of sql("SELECT o.id FROM organizador o WHERE NOT EXISTS (SELECT 1 FROM formulario f WHERE f.organizador_id = o.id)").split('\n').filter(Boolean)) {
    sql(`INSERT INTO formulario (organizador_id, nome, padrao) VALUES (${org}, 'Legado dos testes', 1)`);
    const f = sql(`SELECT MAX(id) FROM formulario WHERE organizador_id = ${org}`);
    sql(`INSERT INTO formulario_campo (formulario_id, campo_catalogo_id, rotulo, obrigatorio, ordem) SELECT ${f}, id, rotulo_padrao, codigo = 'nome', ordem FROM campo_catalogo WHERE codigo IN ('nome','email','cpf')`);
  }
};
