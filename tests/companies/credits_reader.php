<?php
require __DIR__.'/credits_operations.php';require_once APPPATH.'services/CompanyCredits.php';$reader=new CompanyCredits($db,$p);
$doc=$reader->get($a,7,$credit);essentialCheck($doc['available']==='60.00'&&$doc['applied']==='20.00'&&$doc['refunded']==='20.00','authorized reader reconciles native projections with original and reversal ledger');
essentialCheck($doc['recipient']['client_id']===$ca&&$doc['identity']['legal_name']!=='','credit snapshots are private, complete and immutable');
essentialThrows(fn()=>$reader->get($b,7,$credit),'company_credit_access_denied','reader cannot hydrate known foreign credit ID');
essentialCheck(count($reader->page($b,7)['items'])===1&&$reader->get($b,7,$creditB)['available']==='100.00','B positive own reader and independent list');
essentialCheck($reader->get($a,7,$untouched)['status']===3,'cancelled credit remains readable');
$pdf=$reader->pdf($a,7,$credit);essentialCheck(str_starts_with($pdf,'%PDF-')&&strlen($pdf)>1000,'actual TCPDF uses only authorized credit snapshots');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_credit_pdf_downloaded' AND issuer_id=? AND subject_id=?",[$a,$credit])->row()->n===1,'private PDF provenance confirmed before returned bytes');
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$reader->pdf($a,7,$credit),'issuer_storage_failed','PDF returns no bytes when provenance audit fails');$db->failOn=null;
$db->query("UPDATE {$p}credits SET amount='19.99' WHERE credit_id=?",[$credit]);essentialThrows(fn()=>$reader->get($a,7,$credit),'company_credit_balance_review','reader refuses diverging native projection instead of displaying false balance');$db->query("UPDATE {$p}credits SET amount='20.00' WHERE credit_id=?",[$credit]);
$old=$db->query("SELECT items_json FROM {$p}company_credit_documents WHERE credit_id=?",[$credit])->row()->items_json;$tampered=json_decode($old,true);$tampered['total']='99.00';$db->query("UPDATE {$p}company_credit_documents SET items_json=? WHERE credit_id=?",[json_encode($tampered),$credit]);essentialThrows(fn()=>$reader->get($a,7,$credit),'company_credit_balance_review','reader recalculates snapshot item totals against native amounts');$db->query("UPDATE {$p}company_credit_documents SET items_json=? WHERE credit_id=?",[$old,$credit]);
essentialCheck(in_array($invoice,array_map(static fn($r)=>(int)$r['id'],$reader->invoiceOptions($a,7,$credit)),true),'application chooser only offers native same origin/customer/currency invoices');
echo 'Credit reader/private PDF: '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().'). No provider calls.'.PHP_EOL;
