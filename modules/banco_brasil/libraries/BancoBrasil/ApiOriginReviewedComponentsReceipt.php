<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/ApiOriginReceiptTrait.php';
final class ApiOriginReviewedComponentsReceipt implements \CompanyReceiptSource{private const COMPONENTS=true;use ApiOriginReceiptTrait;}
