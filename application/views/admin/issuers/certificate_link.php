<?php defined('BASEPATH') or exit('No direct script access allowed');
// Include after the company form closes, so the dedicated multipart form is never nested.
if (!empty($selected) && !empty($can_manage) && (int)$selected['active']===1) {
    $certificateContext=CompanyRequestScope::context();
    $certificateCurrent=$certificateContext && $certificateContext->issuerId()===(int)$selected['id'];
    $certificateUrl=CompanyRequestScope::adminUrl($this,$certificateCurrent?'settings?group=certificates':'');
?>
<p class="tw-mt-4"><a class="btn btn-default" href="<?= htmlspecialchars($certificateUrl,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); ?>"><?= $certificateCurrent?'Certificado A1':'Selecionar empresa para configurar certificado A1'; ?></a></p>
<?php } ?>
