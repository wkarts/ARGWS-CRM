<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php foreach($events as$rawEvent){if(!isset($rawEvent['auxiliary']))continue;$rawAux=$rawEvent['auxiliary']; ?>
<details class="tw-mt-4" data-bb400-aux-event="<?= (int)$rawEvent['id']; ?>"><summary>Retorno CNAB400 auxiliar 5/<?= html_escape($rawAux['service']); ?> — Nosso Número <?= html_escape($rawEvent['remote_id']); ?> — somente observação bruta</summary>
<p>Evento pai 7 <code><?= html_escape($rawAux['parent_event_key']); ?></code>; comando do pai <?= html_escape($rawAux['parent_occurrence']); ?>. Não autentica o banco, não muda endereço, cadastro ou chave, não confirma pagamento e não autoriza envio ou conciliação financeira.</p>
<?php if($rawAux['service']==='01'){ ?><p>Campo bruto de e-mail (posições 6–142; 137 bytes). O manual informa picture135: divergência documental, sem validação de endereços nem interpretação da lista.</p><pre data-bb400-email-raw><?= html_escape($rawAux['fields']['email_slot_raw']); ?></pre>
<?php }else{ ?><p>Ocorrência bruta (posições 4–5; 2 bytes): <code><?= html_escape($rawAux['fields']['occurrence_raw']); ?></code>. A nota27 lista códigos de3 dígitos; nenhum mapeamento ou confirmação de QR Code é inferido.</p>
<p>Campo bruto URL/chave (77 bytes; texto não verificado):</p><pre data-bb400-pix-key-raw><?= html_escape($rawAux['fields']['url_or_key_raw']); ?></pre><p>TXID bruto (35 bytes):</p><pre data-bb400-pix-txid-raw><?= html_escape($rawAux['fields']['txid_raw']); ?></pre>
<?php } ?><p>Hash dos bytes1–394 do auxiliar <code><?= html_escape($rawAux['raw_body_hash']); ?></code>. Sequência física e hash integral pertencem à proveniência do arquivo. Nenhum link é aberto ou consultado.</p></details>
<?php } ?>
