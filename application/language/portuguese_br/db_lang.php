<?php
/**
 * System messages translation for CodeIgniter(tm)
 *
 * @author	CodeIgniter community
 * @copyright	Copyright (c) 2014-2018, British Columbia Institute of Technology (http://bcit.ca/)
 * @license	http://opensource.org/licenses/MIT	MIT License
 * @link	https://codeigniter.com
 */
defined('BASEPATH') OR exit('No direct script access allowed');

$lang['db_invalid_connection_str']     = 'Não foi possível determinar as configurações do banco de dados pela string de conexão informada.';
$lang['db_unable_to_connect']          = 'Não foi possível conectar ao servidor de banco de dados com as configurações fornecidas.';
$lang['db_unable_to_select']           = 'Não foi possível selecionar o banco de dados: %s';
$lang['db_unable_to_create']           = 'Não foi possível criar o banco de dados: %s';
$lang['db_invalid_query']              = 'A consulta que você enviou não é válida.';
$lang['db_must_set_table']             = 'Informe a tabela do banco de dados a ser usada na consulta.';
$lang['db_must_use_set']               = 'Você precisa usar o método "set" para atualizar um registro.';
$lang['db_must_use_index']             = 'Você precisa especificar um índice na busca de registros para atualizações em lote.';
$lang['db_batch_missing_index']        = 'Uma ou mais linhas apresentadas para atualização em lote está faltando o índice especificado.';
$lang['db_must_use_where']             = 'Não são permitidas atualizações sem conter ao menos uma cláusula "where".';
$lang['db_del_must_use_where']         = 'Exclusões exigem uma cláusula "where" ou "like".';
$lang['db_field_param_missing']        = 'Para buscar campos é necessário o parâmetro com o nome da tabela.';
$lang['db_unsupported_function']      = 'Este recurso não está disponível para o banco de dados em uso.';
$lang['db_transaction_failure']        = 'Falha na transação: Reversão realizada.';
$lang['db_unable_to_drop']            = 'Não foi possível excluir o banco de dados especificado.';
$lang['db_unsupported_feature']       = 'Este recurso não é suportado pelo banco de dados em uso.';
$lang['db_unsupported_compression']   = 'O servidor não suporta o formato de compressão escolhido.';
$lang['db_filepath_error']             = 'Não foi possível gravar os dados no arquivo indicado.';
$lang['db_invalid_cache_path']         = 'O caminho da pasta de "cache" informado é inválido ou não tem permissão de escrita.';
$lang['db_table_name_required']        = 'É necessário informar o nome da tabela para executar esta operação.';
$lang['db_column_name_required']       = 'É necessário informar o nome da coluna para executar esta operação.';
$lang['db_column_definition_required'] = 'É necessário definir a coluna para executar esta operação.';
$lang['db_unable_to_set_charset']      = 'Não foi possível definir o conjunto de caracteres da conexão do cliente: %s';
$lang['db_error_heading']              = 'Ocorreu um erro no banco de dados';
