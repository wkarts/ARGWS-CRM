<?php
declare(strict_types=1);
require dirname(__DIR__).'/application/services/CompanyShellPresentation.php';
$checks=0;
function shellCheck($ok,$label){++$GLOBALS['checks'];if(!$ok)throw new RuntimeException($label);}
$css=CompanyShellPresentation::css('[{"id":"btn-primary","color":"#32c977"},{"id":"admin-menu-active-item","color":"#32c977"}]');
shellCheck(str_contains($css,'.company-context-shell .btn-primary{background-color:#32c977;border-color:#32c977;}'),'native primary token projected exactly');
shellCheck(str_contains($css,'background-color:#009745;border-color:#009745'),'native hover uses the same minus50 channels');
shellCheck(str_contains($css,'details[open] > summary'),'native active menu token applies to isolated disclosure');
foreach(['not-json','{}','[{"id":"unknown","color":"#ffffff"}]','[{"id":"btn-primary","color":"red;}</style><script>alert(1)</script>"}]','[{"id":"btn-primary","color":"url(https://invalid.example)"}]',str_repeat('a',65537)]as$bad){shellCheck(CompanyShellPresentation::css($bad)==='','malformed or untrusted styles rejected');}
shellCheck(!str_contains($css,'hooks')&&!str_contains($css,'url('),'no hook or remote asset injection');
$head=file_get_contents(dirname(__DIR__).'/application/views/admin/includes/native_company_layout_head.php');
shellCheck(!preg_match('/\b(init_head|app_admin_head|app_compile_css|get_admin_header_logo_url)\s*\(/',$head),'no global hydration');
shellCheck(str_contains($head,"assets/images/argws/logo-dark.png")&&str_contains($head,'assets/builds/vendor-admin.css'),'same native public logo and vendor CSS');
require dirname(__DIR__).'/application/services/issuers/IssuerStorage.php';
final class ShellThemeDb {
    public array $queries=[]; public bool $active=true; public bool $missing=false;
    public function query($sql,$params){
        $this->queries[]=[$sql,$params];
        if($this->missing)throw new RuntimeException('missing_storage');
        if($sql==='SELECT active FROM fixture_modules WHERE module_name=?'&&$params===['theme_style'])$row=['active'=>(int)$this->active];
        elseif($sql==='SELECT value FROM fixture_options WHERE name=?'&&$params===['theme_style'])$row=['value'=>'[{"id":"btn-primary","color":"#32c977"}]'];
        else throw new LogicException('unexpected_read');
        return new class($row){public function __construct(private array $row){}public function row_array(){return $this->row;}};
    }
}
$db=new ShellThemeDb();shellCheck(str_contains(CompanyShellPresentation::read($db,'fixture_'),'#32c977')&&count($db->queries)===2,'only active module and cosmetic option read');
$db=new ShellThemeDb();$db->active=false;shellCheck(CompanyShellPresentation::read($db,'fixture_')===''&&count($db->queries)===1,'disabled theme never exposes saved style');
$db=new ShellThemeDb();$db->missing=true;shellCheck(CompanyShellPresentation::read($db,'fixture_')==='','missing cosmetic storage uses native baseline');
echo "PASS: $checks isolated shell presentation checks\n";
