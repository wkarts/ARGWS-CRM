<?php
declare(strict_types=1);
require __DIR__.'/../../application/services/NativeSettingsCompatibility.php';
foreach (['email','invoices'] as $group) {
    if (!NativeSettingsCompatibility::usesInstallationForm(true,$group)) throw new RuntimeException('Primary '.$group.' must retain its original writer');
    if (NativeSettingsCompatibility::usesInstallationForm(false,$group)) throw new RuntimeException('Secondary '.$group.' must remain isolated');
}
foreach (['google','company','general',null,[], 'email/../invoices'] as $group) {
    if (NativeSettingsCompatibility::usesInstallationForm(true,$group)) throw new RuntimeException('Unexpected installation fallback');
}
echo "Primary email/invoices dispatch and secondary isolation passed.\n";
