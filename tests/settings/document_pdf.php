<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$pdfFields=['pdf_format_invoice','pdf_format_estimate','pdf_format_proposal','pdf_format_credit_note','pdf_format_contract','pdf_format_payment','pdf_format_statement'];
require __DIR__.'/native_pdf_formats.php';

