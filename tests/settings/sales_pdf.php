<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$pdfFields=['pdf_format_estimate','pdf_format_proposal'];
require __DIR__.'/native_pdf_formats.php';
