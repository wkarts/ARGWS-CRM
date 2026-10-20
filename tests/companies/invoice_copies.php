<?php
/**
 * Compatibility fixture recreated for the recovered 440 draft tests.
 * The original 439 invoice_copies.php fixture was not recovered. Reuse the
 * independently validated canonical-copy fixture and explicitly supply its
 * public service alias and a real, locally cancelled B source invoice.
 */
require __DIR__.'/finance_resume_439_copies.php';
$copy=$copies;
// invoices.php supplies the original explicit A input; retain every item field.
$legacyInput=array_replace($legacyInput,['client_id'=>$ca]);
$cancelled=$s->create($b,7,array_replace($legacyInput,['client_id'=>$cb]),hash('sha256','resume440-drafts-cancelled-copy-source'));
$s->change($b,7,$cancelled,$s->revision($b,7,$cancelled));
essentialCheck($s->document($b,7,$cancelled)->data()['status']===5,'recreated copy bootstrap supplies an actual cancelled B invoice');
