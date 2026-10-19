<?php
declare(strict_types=1);
namespace BancoBrasil;
final class CapabilityPolicy
{
    public static function matrix(): array
    {
        return [
            'api.issue'=>['label'=>'Registrar cobrança por API','implemented'=>true,'available'=>true,'reason'=>'Origem/fatura, perfil e cofre nativo; OAuth/especificação3.2.2; testes simulados, sem homologação externa'],
            'api.query'=>['label'=>'Consultar cobrança API por origem','implemented'=>true,'available'=>true,'reason'=>'Consulta vinculada e projeção validada; não cria pagamento automático'],
            'api.credit'=>['label'=>'Registrar crédito API confirmado','implemented'=>true,'available'=>true,'reason'=>'Ação explícita, consulta recente16 CREDITADO com prova de conta, integral sem ajustes, snapshot e writer canônicos; sem homologação externa'],
            'api.amend_due'=>['label'=>'Alterar vencimento bancário por API','implemented'=>true,'available'=>true,'reason'=>'PATCH oficial com intenção durável, consulta NORMAL recente e confirmação posterior pela conta; conserva snapshot/fatura; transporte simulado, sem homologação externa'],
            'api.remove'=>['label'=>'Solicitar baixa API por origem','implemented'=>true,'available'=>true,'reason'=>'Exige consulta NORMAL recente; pedido não cancela fatura local nem confirma estado final'],
            'cnab.generate'=>['label'=>'Gerar remessa CNAB 240','implemented'=>true,'available'=>true,'reason'=>'Geração local condicionada a fatura/origem, perfil240 e declaração de contrato; não transmite nem comprova aceite/homologação'],
            'cnab400.candidate'=>['label'=>'Preparar candidato de remessa CNAB 400','implemented'=>true,'available'=>false,'reason'=>'Codec de testes; campo BRL, contrato e homologação pendentes'],
            'cnab.download'=>['label'=>'Baixar remessa registrada','implemented'=>true,'available'=>true,'reason'=>'Download próprio auditado; não equivale a envio ou aceite bancário'],
            'cnab.return'=>['label'=>'Importar retorno para revisão','implemented'=>true,'available'=>true,'reason'=>'CNAB 240/400 exigem perfil explícito e permissões do módulo/emitente; somente observações, sem baixa financeira automática'],
            'cnab.reconcile'=>['label'=>'Conciliar crédito240 com evidência','implemented'=>true,'available'=>true,'reason'=>'Assistido: origem exata + confirmação independente do operador + writer nativo; encargos/estorno/eventos adversos exigem revisão; não autentica o arquivo'],
            'cnab.transmit'=>['label'=>'Transmitir arquivo ao banco','implemented'=>false,'available'=>false,'reason'=>'Transporte bancário não implementado'],
            'webhook'=>['label'=>'Receber webhook BB com mTLS','implemented'=>true,'available'=>false,'reason'=>'Receptor observacional implementado; habilitação exige ingresso TLS nativo, cadeia BB e fingerprint por perfil/ambiente. Sem homologação externa; não aplica pagamento'],
        ];
    }
}
