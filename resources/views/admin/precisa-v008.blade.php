@extends('layouts.admin')
@section('titulo', 'Atualização do banco')
@section('conteudo')
<div class="cabecalho"><div><h1>Falta atualizar o banco de dados</h1></div></div>
<div class="alerta alerta--info"><p>Esta tela fica disponível depois que o script <b>banco/V008__administracao_taxas_impersonacao.sql</b> for importado no phpMyAdmin
    (o final deve mostrar "V008 ok").</p></div>
@endsection
