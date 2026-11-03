<?php
declare(strict_types=1);
namespace BancoBrasil;

/** Only parser failures cross this boundary; never include wire values or exception text. */
final class ReturnValidationFailure extends \UnexpectedValueException
{
    public static function from(\Throwable $cause):self
    {
        $code=$cause->getMessage();
        if(!isset(self::labels()[$code]))$code='bb_return_validation_unclassified';
        return new self($code);
    }
    public static function labels():array
    {
        return [
            'bb_alpha_field_invalid'=>'Texto fora do formato aceito pelo perfil.',
            'bb_tax_id_invalid'=>'Documento do emitente fora do formato suportado.',
            'bb400_account_width_unsupported'=>'Agência ou conta fora das larguras suportadas no CNAB400.',
            'bb400_auxiliary_raw_invalid'=>'Conteúdo auxiliar CNAB400 fora do formato suportado.',
            'bb400_auxiliary_hash_invalid'=>'Identificação do conteúdo auxiliar inválida.',
            'bb400_auxiliary_fields_invalid'=>'Campos auxiliares CNAB400 incompatíveis com o perfil.',
            'bb400_auxiliary_service_unsupported'=>'Serviço auxiliar CNAB400 não suportado.',
            'bb400_auxiliary_reserved_invalid'=>'Área reservada do registro auxiliar incompatível.',
            'bb400_auxiliary_contract_invalid'=>'Registro auxiliar incompatível com o contrato observacional.',
            'bb400_auxiliary_parent_invalid'=>'Vínculo do auxiliar ao título inválido.',
            'bb400_auxiliary_corrupt'=>'Integridade do registro auxiliar não confirmada.',
            'bb_return_validation_unclassified'=>'Arquivo recusado; diagnóstico específico indisponível.',
            'bb_return_encoding_invalid'=>'Formato inválido: confira ASCII, CRLF e o tamanho do arquivo.',
            'bb_return_incomplete'=>'Arquivo incompleto.',
            'bb_return_record_invalid'=>'Registro com tamanho ou banco incompatível.',
            'bb_return_record_limit'=>'Quantidade de linhas incompatível com o limite do arquivo.',
            'bb_return_header_mismatch'=>'Cabeçalho incompatível com a empresa ou o perfil selecionado.',
            'bb_return_agreement_mismatch'=>'Convênio incompatível com a conexão selecionada.',
            'bb_return_account_mismatch'=>'Agência ou conta incompatível com a conexão selecionada.',
            'bb_return_lot_mismatch'=>'Identificação do lote incompatível com o perfil.',
            'bb_return_unpaired'=>'Par de segmentos incompleto.',
            'bb_return_occurrence_mismatch'=>'Ocorrências dos segmentos não correspondem.',
            'bb_return_currency_unsupported'=>'Moeda não suportada pelo perfil.',
            'bb_return_occurrence_unsupported'=>'Ocorrência não suportada pelo perfil contratado.',
            'bb_return_title_scope_mismatch'=>'Identificação do título incompatível com o convênio.',
            'bb_return_control_missing'=>'Referência de controle ausente ou inválida.',
            'bb_return_duplicate_event'=>'Evento repetido dentro do arquivo.',
            'bb_return_lot_incomplete'=>'Lote incompleto.',
            'bb_return_lot_count_mismatch'=>'Quantidade de registros do lote divergente.',
            'bb_return_file_count_mismatch'=>'Quantidade de registros do arquivo divergente.',
            'bb_return_sequence_invalid'=>'Sequência ou segmento inválido.',
            'bb_return_numeric_invalid'=>'Campo numérico inválido.',
            'bb_date_invalid'=>'Data inválida ou fora da janela do perfil.',
            'bb_amount_invalid'=>'Valor inválido para o perfil.',
            'bb_numeric_field_invalid'=>'Campo numérico fora do formato suportado.',
            'bb400_date_invalid'=>'Data CNAB400 inválida.',
            'bb400_numeric_invalid'=>'Campo numérico CNAB400 inválido.',
            'bb400_amount_overflow'=>'Valor CNAB400 fora do limite suportado.',
            'bb400_layout_unsupported'=>'Perfil CNAB400 não suportado.',
            'bb400_record_sequence_invalid'=>'Tamanho ou sequência física CNAB400 inválida.',
            'bb400_header_mismatch'=>'Cabeçalho CNAB400 incompatível com a conexão.',
            'bb400_return_sequence_invalid'=>'Sequência de retorno CNAB400 inválida.',
            'bb400_auxiliary_parent_required'=>'Registro auxiliar sem título anterior.',
            'bb400_duplicate_auxiliary'=>'Registro auxiliar repetido para o título.',
            'bb400_auxiliary_record_unsupported'=>'Registro auxiliar não aceito neste perfil.',
            'bb400_title_scope_mismatch'=>'Título CNAB400 incompatível com a conexão.',
            'bb400_credit_date_command_mismatch'=>'Data de crédito incompatível com a ocorrência.',
            'bb400_overloaded_amount_unsupported'=>'Ocorrência exige outro significado de valor e outro perfil.',
            'bb400_collection_profile_unsupported'=>'Modalidade CNAB400 não suportada pelo perfil.',
            'bb400_trailer_invalid'=>'Trailer CNAB400 inválido.',
            'bb400_trailer_profile_unsupported'=>'Totais de modalidade não suportada no trailer.',
        ];
    }
}
