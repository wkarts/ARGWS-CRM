<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__ . '/Scope.php';
require_once __DIR__ . '/Fields.php';

final class Agreement
{
    public const LAYOUT_ID = 'bb-cobranca-240-2026-06-084-043';
    public const SOURCE_SHA256 = 'cbe4d93359eedd6d46c577d465423e05b6c8ea3accef415097cf9d5192c7af74';
    public readonly string $taxId;
    public readonly string $branch;
    public readonly string $branchDigit;
    public readonly string $account;
    public readonly string $accountDigit;
    public function __construct(
        public readonly Scope $scope,
        string $taxId,
        public readonly string $name,
        public readonly string $variation,
        public readonly string $wallet = '17',
        public readonly string $mode = 'simple'
    ) {
        if ($wallet !== '17' || $mode !== 'simple' || !preg_match('/^[0-9]{3}$/D', $variation)) {
            throw new \InvalidArgumentException('bb_agreement_profile_unsupported');
        }
        $this->taxId = Fields::taxId($taxId);
        if ($name === '' || strlen($name) > 160 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            throw new \InvalidArgumentException('bb_issuer_name_invalid');
        }
        preg_match('/^001:([0-9]{5})-([0-9X]):([0-9]{12})-([0-9X])$/D', $scope->accountId, $m);
        [$this->branch, $this->branchDigit, $this->account, $this->accountDigit] = array_slice($m, 1);
        if ((int) $this->branch === 0 || (int) $this->account === 0) {
            throw new \InvalidArgumentException('bb_account_invalid');
        }
    }
    public function wireAgreement(bool $test = false): string
    {
        return Fields::digits($this->scope->agreement, 9) . '0014' . $this->wallet . $this->variation . ($test ? 'TS' : '  ');
    }
    public function ourNumber(string $sequence): string
    {
        if ((int) $sequence === 0) throw new \InvalidArgumentException('bb_title_sequence_invalid');
        return $this->scope->agreement . Fields::digits($sequence, 10);
    }
}
