<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/ApiOriginReceiptTrait.php';
final class ApiOriginReviewedReceipt implements \CompanyReceiptSource{private const COMPONENTS=false;use ApiOriginReceiptTrait;}
