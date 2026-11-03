<?php
/** Real scoped Settings rendering over the existing disposable controller fixture. */
ob_start();
require __DIR__.'/settings/settings_model_controller.php';
ob_end_clean();
$renderData['company_identity']['legal_name'] = 'Empresa sintética';
$renderData['values']['smtp_host'] = 'smtp.example.invalid';
$renderData['can_edit'] = true;
$html = $viewHarness->render($renderData);
$renderLevel = ob_get_level();
$incompleteHarness = clone $viewHarness;
unset($incompleteHarness->router);
try { $incompleteHarness->render($renderData); throw new RuntimeException('Missing router was not rejected'); }
catch (ErrorException $error) { essentialCheck(str_contains($error->getMessage(), 'router'), 'missing renderer dependency fails before HTML'); }
essentialCheck(ob_get_level() === $renderLevel, 'failed renderer restores output buffer boundary');
try { strictFixtureRender(static function (): void { echo 'must not leak'; throw new Error('synthetic-render-fatal'); }); }
catch (Error $error) { essentialCheck($error->getMessage() === 'synthetic-render-fatal', 'fatal renderer error remains fatal'); }
essentialCheck(ob_get_level() === $renderLevel, 'fatal renderer restores output buffer boundary');

essentialCheck(str_contains($html, 'company-native-settings'), 'native Settings composition is rendered');
essentialCheck(str_contains($html, 'panel-footer text-right'), 'save action is in native panel footer');
essentialCheck(!str_contains($html, 'class="company-settings-nav"'), 'no parallel horizontal settings navigation');
// Only routes with an explicit URL are admitted by this compatibility renderer.
// The shared native Settings route has its own independently tested group catalog.
foreach (array_intersect_key($renderData['groups'], $renderData['company_urls']) as $key => $label) {
    essentialCheck(substr_count($html, 'class="settings-group-'.$key.'"') === 1, 'each admitted settings group occurs once: '.$key);
}
foreach (array_diff_key($renderData['groups'], $renderData['company_urls']) as $key => $label) {
    essentialCheck(!str_contains($html, 'class="settings-group-'.$key.'"'), 'unrouted compatibility group is not advertised: '.$key);
}
essentialCheck(substr_count($html, 'aria-current="page"') === 1, 'exactly the active settings group is selected');
essentialCheck(substr_count($html, '<form id="company-settings-form"') === 1, 'one settings form');
$form = substr($html, strpos($html, '<form id="company-settings-form"'));
$form = substr($form, 0, strpos($form, '</form>'));
essentialCheck(str_contains($form, 'name="_company_context"') && str_contains($form, 'name="synthetic_csrf"'), 'context and CSRF stay within the form');
essentialCheck(str_contains($form, 'type="submit">Salvar definições'), 'save stays inside its form for keyboard and existing flow');
$readOnly = $renderData;
$readOnly['can_edit'] = false;
$readOnlyHtml = $viewHarness->render($readOnly);
essentialCheck(str_contains($readOnlyHtml, '<fieldset disabled>') && !str_contains($readOnlyHtml, '>Salvar definições</button>'), 'read-only settings retain disabled controls and no submit action');
$future = $renderData;
$future['groups']['future_safe'] = 'Nova seção <script>unsafe</script>';
$future['company_urls']['future_safe'] = '/admin/settings?group=future_safe';
$futureHtml = $viewHarness->render($future);
essentialCheck(str_contains($futureHtml, 'settings-group-future_safe') && !str_contains($futureHtml, '<script>unsafe</script>'), 'future admitted groups are not silently hidden and labels remain escaped');
if (in_array('--html-json', $argv ?? [], true)) {
    echo json_encode(['editable' => $html, 'readOnly' => $readOnlyHtml], JSON_THROW_ON_ERROR);
} else {
    echo 'PASS: native Settings render, complete categorized navigation, form/CSRF scope, readonly and future groups ('.$GLOBALS['essentialChecks'].' cumulative checks).'.PHP_EOL;
}
