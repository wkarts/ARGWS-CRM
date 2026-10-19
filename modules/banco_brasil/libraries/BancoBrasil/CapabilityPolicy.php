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
            'api.movements'=>['label'=>'Consultar página de movimentos API','implemented'=>true,'available'=>true,'reason'=>'Consulta oficial vinculada à conta/origem, janela/cursor explícitos, valores separados e histórico adverso; observação sem lançamento financeiro'],
            'api.percentage_discount'=>['label'=>'Registrar primeiro desconto percentual API','implemented'=>true,'available'=>true,'reason'=>'Perfil percentual e contrato distintos, api_discount, taxa/data exatas e confirmação pela conta; oferta sem cálculo ou extinção financeira'],
            'api.fixed_discount'=>['label'=>'Registrar primeiro desconto fixo API','implemented'=>true,'available'=>true,'reason'=>'Perfil e contrato distintos, capacidade api_discount, centavos/data exatos e confirmação do detalhamento; oferta não é recebimento ou extinção financeira'],
            'api.monthly_interest'=>['label'=>'Emitir com taxa percentual mensal API','implemented'=>true,'available'=>true,'reason'=>'Perfil mensal distinto, contrato declarado, capapi_charges, percentual exato e query coerente; sem cálculo local de juros ou escrituração de encargos API'],
            'api.daily_interest'=>['label'=>'Emitir com juros diários fixos API','implemented'=>true,'available'=>true,'reason'=>'Perfil distinto e declaração contratual, capapi_charges, valor diário exato e query coerente; encargos recebidos pela API ficam em revisão financeira'],
            'api.credit'=>['label'=>'Registrar crédito API confirmado','implemented'=>true,'available'=>true,'reason'=>'Ação explícita, consulta recente16 CREDITADO com prova de conta, integral sem ajustes, snapshot e writer canônicos; sem homologação externa'],
            'api.amend_due'=>['label'=>'Alterar vencimento bancário por API','implemented'=>true,'available'=>true,'reason'=>'PATCH oficial com intenção durável, consulta NORMAL recente e confirmação posterior pela conta; conserva snapshot/fatura; transporte simulado, sem homologação externa'],
            'api.remove'=>['label'=>'Solicitar baixa API por origem','implemented'=>true,'available'=>true,'reason'=>'Exige consulta NORMAL recente; pedido não cancela fatura local nem confirma estado final'],
            'cnab.generate'=>['label'=>'Gerar remessa CNAB 240','implemented'=>true,'available'=>true,'reason'=>'Geração local condicionada a fatura/origem, perfil240 e declaração de contrato; não transmite nem comprova aceite/homologação'],
            'cnab400.candidate'=>['label'=>'Preparar candidato de remessa CNAB 400','implemented'=>true,'available'=>false,'reason'=>'Codec candidato: faltam resolução primária BRL85–86/juros herdáveis/CBR64314vs15 e perfil contratado. Código liberável e homologação externa são etapas distintas'],
            'cnab.download'=>['label'=>'Baixar remessa registrada','implemented'=>true,'available'=>true,'reason'=>'Download próprio auditado; não equivale a envio ou aceite bancário'],
            'cnab.return'=>['label'=>'Importar retorno para revisão','implemented'=>true,'available'=>true,'reason'=>'CNAB 240/400 exigem perfil explícito e permissões do módulo/emitente; somente observações, sem baixa financeira automática'],
            'cnab.reconcile'=>['label'=>'Conciliar crédito240 nominal com evidência','implemented'=>true,'available'=>true,'reason'=>'Crédito simples sem ajustes: origem exata, confirmação independente e writer nativo; upload não autentica o banco'],
            'cnab.components'=>['label'=>'Conciliar composição240 declarada','implemented'=>true,'available'=>true,'reason'=>'Equação local explicitamente declarada; caixa do principal, desconto, abatimento e encargo agregado separados. IOF/tarifas/outros valores não suportados; parcial somente por crédito individual conferido, sem inferir acumulados API'],
            'cnab.external_reversal'=>['label'=>'Registrar estorno externo integral declarado','implemented'=>true,'available'=>true,'reason'=>'Somente recibo composto preservado: confirmação independente do operador, restituição e principal integrais, nova permissão, saldo e vetos adversos. Preserva original; não autentica banco, não usa44 como autorização e não executa transação BB'],
            'cnab.transmit'=>['label'=>'Transmitir arquivo ao banco','implemented'=>false,'available'=>false,'reason'=>'Transporte bancário não implementado'],
            'webhook'=>['label'=>'Receber webhook BB com mTLS','implemented'=>true,'available'=>false,'reason'=>'Receptor observacional implementado; habilitação exige ingresso TLS nativo, cadeia BB e fingerprint por perfil/ambiente. Sem homologação externa; não aplica pagamento'],
        ];
    }
}
