<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
// The original preview supplies the already verified, issuer-owned document.
// These amounts describe settlement history; they never recalculate the invoice.
$positionDocument = $native_invoice_preview_data['document'];
$position = $positionDocument['position'];
$positionEscape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$hasSettlementHistory = false;
foreach (['noncash_reductions', 'received_external_reversed', 'settlement_discount_reversed', 'settlement_abatement_reversed'] as $field) {
    if (($position[$field] ?? '0.00') !== '0.00') $hasSettlementHistory = true;
}
?>
<?php if ($hasSettlementHistory) { ?>
<div class="table-responsive mtop15" data-native-financial-position>
    <table class="table table-bordered">
        <caption>Posição do principal e ajustes na liquidação</caption>
        <tbody>
        <?php foreach ([
            'received_original' => 'Principal recebido originalmente',
            'received_reversed' => 'Principal revertido',
            'received' => 'Principal recebido vigente',
            'applied_credit' => 'Crédito aplicado',
            'settlement_discount' => 'Desconto na liquidação vigente (sem recebimento)',
            'settlement_abatement' => 'Abatimento na liquidação vigente (sem recebimento)',
            'settlement_discount_reversed' => 'Desconto restabelecido por reversão',
            'settlement_abatement_reversed' => 'Abatimento restabelecido por reversão',
            'receivable' => 'Principal em aberto',
        ] as $field => $label) {
            if ($field === 'receivable' && (int) $positionDocument['status'] === 5) {
                $label = 'Principal residual histórico (fatura cancelada; sem cobrança vigente)';
            }
        ?>
            <tr><th scope="row"><?= $positionEscape($label); ?></th><td><?= $positionEscape($positionDocument['currency'] . ' ' . $position[$field]); ?></td></tr>
        <?php } ?>
        </tbody>
    </table>
    <?php if (($position['received_external_reversed'] ?? '0.00') !== '0.00') { ?>
    <p>Reversão externa declarada com evidência conferida; esta declaração não executa estorno no banco.</p>
    <?php } ?>
</div>
<?php } ?>
