<?php
/** The actual PHP renderer must keep the native opt-in checkbox addressable and submittable. */
require __DIR__.'/invoice_recurring_modes_controller.php';
$previous=libxml_use_internal_errors(true);$dom=new DOMDocument();$dom->loadHTML($html);libxml_clear_errors();libxml_use_internal_errors($previous);
$send=$dom->getElementById('schedule-send');essentialCheck($send instanceof DOMElement&&$send->tagName==='input'&&$send->getAttribute('type')==='checkbox'&&$send->getAttribute('name')==='send'&&$send->getAttribute('value')==='1','actual DOM retains stable native notification opt-in ID/name/type/value');
$label=(new DOMXPath($dom))->query('//label[@for="schedule-send"]');essentialCheck($label->length===1,'notification checkbox retains its accessible label');
essentialCheck(!$send->hasAttribute('checked'),'notification remains explicit opt-in on newly rendered form');
echo 'Invoice recurring modes native DOM notification opt-in: '.$GLOBALS['essentialChecks'].' cumulative ('.$db->driver().'). Actual HTTP/Chromium remains separate.'.PHP_EOL;
