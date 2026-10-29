<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', __DIR__);

// Run the controller's real list gate, stopping before any query or rendering.
class AdminController { public object $session; }
class CompanyRequestScope { public static function nativeFor($owner): bool { return false; } }
final class ProposalListDenied extends RuntimeException {}
final class ProposalListAllowed extends RuntimeException {}
function close_setup_menu(): void {}
function staff_cant($capability, $feature): bool
{
    if ($feature !== 'proposals' || !in_array($capability, ['view', 'view_own'], true)) {
        throw new LogicException('Unexpected ACL query');
    }
    return !$GLOBALS['proposalListGrants'][$capability];
}
function get_option($name)
{
    $GLOBALS['proposalListOptionReads'][] = $name;
    if (!array_key_exists($name, $GLOBALS['proposalListOptions'])) {
        throw new LogicException('Unexpected option query');
    }
    return $GLOBALS['proposalListOptions'][$name];
}
function access_denied($feature): void
{
    if ($feature !== 'proposals') { throw new LogicException('Unexpected denied feature'); }
    throw new ProposalListDenied();
}
require __DIR__ . '/../../application/controllers/admin/Proposals.php';

$controller = (new ReflectionClass(Proposals::class))->newInstanceWithoutConstructor();
$controller->session = new class {
    public function userdata($name) { throw new ProposalListAllowed(); }
};
$cases = [
    // The estimates preference must not influence the proposals gate.
    [false, false, '0', '0', false],
    [false, false, '1', '0', false],
    [false, false, '0', '1', true],
    [false, false, '1', '1', true],
    // Explicit view/view-own grants still admit their normal downstream checks.
    [true, false, '0', '0', true],
    [false, true, '0', '0', true],
];
foreach ($cases as [$view, $viewOwn, $estimateAssigned, $proposalAssigned, $expectAllowed]) {
    $GLOBALS['proposalListGrants'] = ['view' => $view, 'view_own' => $viewOwn];
    $GLOBALS['proposalListOptions'] = [
        'allow_staff_view_estimates_assigned' => $estimateAssigned,
        'allow_staff_view_proposals_assigned' => $proposalAssigned,
    ];
    $GLOBALS['proposalListOptionReads'] = [];
    try {
        $controller->list_proposals();
        throw new LogicException('Controller did not reach expected gate');
    } catch (ProposalListAllowed $stop) {
        $allowed = true;
    } catch (ProposalListDenied $stop) {
        $allowed = false;
    }
    if ($allowed !== $expectAllowed) { throw new LogicException('Incorrect proposals list gate'); }
    $expectedReads = (!$view && !$viewOwn) ? ['allow_staff_view_proposals_assigned'] : [];
    if ($GLOBALS['proposalListOptionReads'] !== $expectedReads) {
        throw new LogicException('List gate queried an unrelated preference');
    }
}
echo 'Proposals list ACL: six gate cases passed; downstream ownership checks not exercised.' . PHP_EOL;
