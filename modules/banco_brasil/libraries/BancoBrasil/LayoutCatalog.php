<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Agreement.php';

final class LayoutCatalog
{
    public const CNAB400 = 'bb-cbr641-2026-07-cbr643-2023-07-7pos';
    public const CNAB400_AUX = 'bb-cbr643-2023-07-7pos-aux-observation-v1';
    /** CBR641 July/2026 positions 85-86: foreign-currency codes are 02/14; BRL carries no foreign-currency code. */
    public const CNAB400_BRL_CURRENCY_FIELD = '  ';
    /** CBR641 July/2026 positions 161-173. Zero is safe only with an independently confirmed interest-free bank profile. */
    public const CNAB400_DAILY_INTEREST_FIELD = '0000000000000';
    public static function all(): array
    {
        return [
            Agreement::LAYOUT_ID => ['label'=>'CNAB 240 — BB junho/2026, 084/043', 'channel'=>'cnab240',
                'remittance_sha256'=>Agreement::SOURCE_SHA256, 'return_sha256'=>Agreement::SOURCE_SHA256,
                'date_window'=>'2000-2099', 'currency'=>'BRL'],
            self::CNAB400 => ['label'=>'CNAB 400 — CBR641 julho/2026 / CBR643 julho/2023, convênio 7 posições', 'channel'=>'cnab400',
                'remittance_sha256'=>'ef77ecbf067ef1eb3d29da52a2d2cc8501a181cdfa141ccca62fb4c6290252a8',
                'return_sha256'=>'da3a81b60df9ddfaaad22d3fa1889c45133a9d849fc950c8a78ce08d542303a1',
                'date_window'=>'2000-2099', 'currency'=>'BRL'],
            self::CNAB400_AUX => ['label'=>'Retorno CNAB400 — auxiliares 5/01 e 5/10 brutos, sem operação financeira', 'channel'=>'cnab400_aux_observation',
                'remittance_sha256'=>'ef77ecbf067ef1eb3d29da52a2d2cc8501a181cdfa141ccca62fb4c6290252a8',
                'return_sha256'=>'da3a81b60df9ddfaaad22d3fa1889c45133a9d849fc950c8a78ce08d542303a1',
                'date_window'=>'2000-2099', 'currency'=>'BRL','return_only'=>true,'parent_layout'=>self::CNAB400,
                'auxiliary_contract'=>'bb_cbr643_raw_aux_v1'],
        ];
    }
    public static function get(string $id): array
    {
        return self::all()[$id] ?? throw new \InvalidArgumentException('bb_layout_unsupported');
    }
    public static function fingerprint(string $id): string
    {
        return hash('sha256', json_encode([$id,self::get($id)], JSON_THROW_ON_ERROR));
    }
}
