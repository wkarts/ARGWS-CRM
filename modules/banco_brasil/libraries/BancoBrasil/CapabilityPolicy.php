<?php
declare(strict_types=1);
namespace BancoBrasil;
final class CapabilityPolicy
{
    public static function matrix(): array
    {
        return [
            'api.issue'=>['label'=>'Emitir cobrança por API','implemented'=>false,'available'=>false,'reason'=>'OpenAPI primária atual pendente de validação'],
            'api.query'=>['label'=>'Consultar e conciliar cobrança por API','implemented'=>false,'available'=>false,'reason'=>'OpenAPI primária atual pendente de validação'],
            'api.remove'=>['label'=>'Solicitar baixa por API','implemented'=>false,'available'=>false,'reason'=>'OpenAPI primária atual pendente de validação'],
            'cnab.generate'=>['label'=>'Gerar remessa CNAB 240','implemented'=>true,'available'=>true,'reason'=>'Geração local condicionada a fatura/origem, perfil240 e declaração de contrato; não transmite nem comprova aceite/homologação'],
            'cnab400.candidate'=>['label'=>'Preparar candidato de remessa CNAB 400','implemented'=>true,'available'=>false,'reason'=>'Codec de testes; campo BRL, contrato e homologação pendentes'],
            'cnab.download'=>['label'=>'Baixar remessa registrada','implemented'=>true,'available'=>true,'reason'=>'Download próprio auditado; não equivale a envio ou aceite bancário'],
            'cnab.return'=>['label'=>'Importar retorno para revisão','implemented'=>true,'available'=>true,'reason'=>'CNAB 240/400 exigem perfil explícito e permissões do módulo/emitente; somente observações, sem baixa financeira automática'],
            'cnab.transmit'=>['label'=>'Transmitir arquivo ao banco','implemented'=>false,'available'=>false,'reason'=>'Transporte bancário não implementado'],
            'webhook'=>['label'=>'Receber webhook BB','implemented'=>false,'available'=>false,'reason'=>'Contrato e autenticação de transporte pendentes'],
        ];
    }
}
