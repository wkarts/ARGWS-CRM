<?php
declare(strict_types=1);
namespace Efi;
require_once __DIR__.'/CompanyAccounts.php';
require_once __DIR__.'/EfiSdkClient.php';
require_once __DIR__.'/EfiCertificateStore.php';
/** Explicit scoped credentials only. Never constructs the legacy gateway or reads global options. */
final class CompanyTransport
{
    private array $options;
    private string $pixKey;
    private ?string $certificate=null;
    public function __construct(array $account,$encryption)
    {
        $credentials=CompanyAccounts::credentials($account,$encryption);
        $this->options=['client_id'=>$credentials['client_id'],'client_secret'=>$credentials['client_secret'],'sandbox'=>$account['environment']==='sandbox'];
        $this->pixKey=$credentials['pix_key'];
        if(!empty($account['certificate_cipher'])){
            $this->certificate=\EfiCertificateStore::temporary($account['certificate_cipher'],$encryption);
            $this->options['pix_cert']=$this->certificate;
            $path=$this->certificate;register_shutdown_function(static function()use($path){if(is_file($path))@unlink($path);});
        }
    }
    public function __destruct(){if($this->certificate!==null&&is_file($this->certificate))@unlink($this->certificate);}
    public function request(string $operation,array $params=[],array $body=[]):array
    {
        if(!in_array($operation,['pixCreateImmediateCharge','pixDetailCharge','pixGenerateQRCode','createCharge','definePayMethod','createOneStepCharge','detailCharge'],true))throw new \DomainException('efi_company_operation_denied');
        if(str_starts_with($operation,'pix')){
            if($this->certificate===null)throw new \DomainException('efi_company_certificate_required');
            if($operation==='pixCreateImmediateCharge')$body['chave']=$this->pixKey;
        }
        $client=new \EfiSdkClient($this->options);
        $response=$client->$operation($params,$body);
        if(!is_array($response))throw new \RuntimeException('efi_company_response_invalid');
        return $response;
    }
}
