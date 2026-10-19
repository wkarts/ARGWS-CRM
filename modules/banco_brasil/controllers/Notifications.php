<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once dirname(__DIR__).'/libraries/BancoBrasil/MtlsEvidence.php';
/** Public mTLS callback: deliberately avoids staff/client session and global application models. */
class Notifications extends CI_Controller
{
    public function index()
    {
        if(!class_exists('CompanyBbCallbackScope',false)||!\CompanyBbCallbackScope::active()){show_404();return;}
        $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json');
        if($this->input->method(true)!=='POST'){$this->output->set_status_header(405)->set_output('{"accepted":false}');return;}
        try{$tls=\BancoBrasil\MtlsEvidence::fromNativeTls($_SERVER,getenv('BB_WEBHOOK_NATIVE_TLS')==='1');}
        catch(Throwable $error){$this->output->set_status_header(401)->set_output('{"accepted":false}');return;}
        try{
            if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')throw new DomainException('bb_webhook_payload_invalid');
            $length=$_SERVER['CONTENT_LENGTH']??'';if($length!==''&&(!ctype_digit((string)$length)||(int)$length>65536))throw new DomainException('bb_webhook_payload_invalid');
            $body=file_get_contents('php://input',false,null,0,65537);if(!is_string($body)||strlen($body)>65536)throw new DomainException('bb_webhook_payload_invalid');
            $this->load->database();require_once APPPATH.'services/InvoicePaymentWriter.php';require_once dirname(__DIR__).'/libraries/BancoBrasil/ApiWebhook.php';
            (new \BancoBrasil\ApiWebhook($this->db,db_prefix()))->receive($tls,$body);$this->output->set_output('{"accepted":true}');
        }catch(DomainException $error){$this->output->set_status_header(400)->set_output('{"accepted":false}');}
        catch(Throwable $error){$this->output->set_status_header(503)->set_output('{"accepted":false}');}
    }
}
