<?php
/** Bounded SQL and route arguments; no SDK/client is loaded in this fixture. */
declare(strict_types=1);
require dirname(__DIR__) . '/application/services/EfiLegacyCheckoutPreflight.php';
function db_prefix() { return 'tbl'; }
$checks = 0;
function efiPreflightCheck($ok,$name) { ++$GLOBALS['checks']; if (!$ok) throw new RuntimeException($name); }
function efiKernel($issuer, $method='invoice', $active=1, $version='5.1.5', $module='gerencianet_gateway') {
    return (object) [
        'router'=>new class($method,$module) {
            function __construct(private $method,private $module){}
            function fetch_class(){return 'gerencia_net';} function fetch_module(){return $this->module;}
            function fetch_directory(){return $this->module==='gerencianet_gateway'?'../../modules/gerencianet_gateway/controllers':'gateways';}
            function fetch_method(){return $this->method;}
        },
        'uri'=>new class { public array $args=[3=>'17',4=>'synthetic_hash']; function rsegment($n){return $this->args[$n]??null;} },
        'input'=>new class { function get($key){return $key==='invoiceid'?'17':'synthetic_hash';} },
        'load'=>new class { function database(){} },
        'db'=>new class($issuer,$active,$version) {
            public array $queries=[]; public bool $fail=false;
            function __construct(private $issuer,private $active,private $version){}
            function query($sql,$args){
                $this->queries[]=[$sql,$args]; if($this->fail)throw new RuntimeException('synthetic');
                $rows=str_contains($sql,'modules')?[['active'=>$this->active,'installed_version'=>$this->version]]:($this->issuer==='missing'?[]:[['id'=>17,'issuer_id'=>$this->issuer]]);
                return new class($rows){function __construct(private $rows){}function result_array(){return $this->rows;}};
            }
        }
    ];
}
foreach(['invoice','gerar_cartao_credito','gerar_pix_por_javascript','callback_boleto','callback_cartao'] as $method) {
    efiPreflightCheck(EfiLegacyCheckoutPreflight::allowsBootstrap(efiKernel(null,$method)), 'legacy allowed '.$method);
    foreach([1,2,'missing'] as $issuer)efiPreflightCheck(!EfiLegacyCheckoutPreflight::allowsBootstrap(efiKernel($issuer,$method)), 'foreign/missing denied '.$method);
}
$ci=efiKernel(null,'callback_check_pix_pago');$ci->uri->args=[3=>'synthetic_txid',4=>'17',5=>'synthetic_hash'];
efiPreflightCheck(EfiLegacyCheckoutPreflight::allowsBootstrap($ci),'pix hash required');unset($ci->uri->args[5]);
efiPreflightCheck(!EfiLegacyCheckoutPreflight::allowsBootstrap($ci),'old pix URL without hash denied');
foreach(['fetch_payment','constructor','unknown'] as $method)efiPreflightCheck(!EfiLegacyCheckoutPreflight::allowsBootstrap(efiKernel(null,$method)),'internal route denied');
foreach([[0,'5.1.5'],[1,'5.1.4 sdk f'],[1,'5.1.6']] as [$active,$version])efiPreflightCheck(!EfiLegacyCheckoutPreflight::allowsBootstrap(efiKernel(null,'invoice',$active,$version)),'inactive/unadopted denied');
efiPreflightCheck(!EfiLegacyCheckoutPreflight::allowsBootstrap(efiKernel(null,'invoice',1,'5.1.5','')),'old copied controller denied');
$ci=efiKernel(null);$ci->db->fail=true;efiPreflightCheck(!EfiLegacyCheckoutPreflight::allowsBootstrap($ci),'SQL failure denied');
echo "Efí checkout bounded preflight: {$checks} checks PASS (no SDK/network)\n";
