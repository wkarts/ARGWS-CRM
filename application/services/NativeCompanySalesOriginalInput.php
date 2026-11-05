<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyEstimateFormInput.php';
require_once __DIR__.'/CompanySales.php';

/** Original form wire mapped to one canonical full-document revision; line positions are not resource IDs. */
final class NativeCompanySalesOriginalInput
{
    public static function parse(string $kind,array $input,string $format):array
    {
        $type=match($kind){'estimates'=>'estimate','proposals'=>'proposal',default=>throw new DomainException('native_sales_editor_invalid')};
        $id=$input['_native_sales_id']??null;$revision=$input['_native_sales_revision']??null;
        if(!is_string($id)||!is_string($revision)||($id===''&&$revision!=='')||($id!==''&&!preg_match('/^[a-f0-9]{64}$/D',$revision)))throw new DomainException('native_sales_editor_invalid');
        if($id!==''){$ref=NativeCompanyResourceId::decode($type,$id);if($ref['source_kind']!=='company_'.$type)throw new DomainException('native_sales_editor_denied');$id=$ref['canonical_id'];}
        $review=['source_revision'=>$revision,'reason'=>$input['_native_sales_reason']??'','edit_confirmed'=>$input['_native_sales_confirmed']??''];
        $metadataRevision=$input['_native_sales_metadata_revision']??null;
        if($id!==''&&(!is_string($metadataRevision)||!preg_match('/^[a-f0-9]{64}$/D',$metadataRevision)))throw new DomainException('native_sales_metadata_stale');
        $number=$input['_native_sales_number']??null;$numberRevision=$input['_native_sales_number_revision']??null;$request=$input['_native_sales_request_key']??null;
        unset($input['_native_sales_id'],$input['_native_sales_revision'],$input['_native_sales_number'],$input['_native_sales_number_revision'],$input['_native_sales_request_key'],$input['_native_sales_reason'],$input['_native_sales_confirmed'],$input['_native_sales_metadata_revision'],$input['isedit']);
        $input['_native_estimate_id']='';$input['_native_estimate_revision']='';$input['_native_estimate_request_key']=$request;$input['_native_estimate_number_revision']=$numberRevision;
        $status=$input['status']??null;$proposal=null;
        if($kind==='proposals'){
            if(($input['rel_type']??null)!=='customer')throw new DomainException('native_proposal_customer_required');
            $proposal=array_intersect_key($input,array_flip(['subject','proposal_to','address','city','state','country','zip','email','phone','assigned','allow_comments','show_quantity_as','content']));
            if(!array_key_exists('allow_comments',$proposal))$proposal['allow_comments']=0;
            elseif($proposal['allow_comments']==='on')$proposal['allow_comments']=1;
            $proposal=CompanySales::proposalFields($proposal);
            $input['clientid']=$input['rel_id']??null;$input['expirydate']=$input['open_till']??'';$input['number']=$number;
            foreach(['subject','proposal_to','address','city','state','country','zip','email','phone','assigned','allow_comments','content','rel_type','rel_id','open_till']as$key)unset($input[$key]);
            $custom=$input['custom_fields']??[];
            if(!is_array($custom)||array_diff(array_keys($custom),['proposal']))throw new DomainException('native_proposal_form_invalid');
            $input['custom_fields']=['estimate'=>$custom['proposal']??[]];$input['status']='1';
        }
        $parsed=NativeCompanyEstimateFormInput::create($input,$format);$payload=$parsed['payload'];
        if(!is_string($status)||!in_array($status,$kind==='estimates'?['1','2','3','4','5']:['1','2','3','4','5','6'],true))throw new DomainException('native_sales_status_invalid');
        $payload['native_status']=(int)$status;
        if($proposal!==null){unset($payload['native_fields']);$payload['native_proposal']=$proposal;}
        $parsed['payload']=$payload;$parsed['id']=$id;$parsed['review']=$review;$parsed['metadata_revision']=$metadataRevision;
        return$parsed;
    }
}
