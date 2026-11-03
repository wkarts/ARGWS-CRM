<?php
/** Integration of settings catalog with real invoice delivery/queue, no SMTP transport. */
require dirname(__DIR__).'/companies/invoice_delivery.php';
require_once APPPATH.'services/CompanySettingsCatalog.php';
require_once APPPATH.'services/CompanySettingsRuntime.php';
$presentationStart=$GLOBALS['essentialChecks'];
foreach([$a=>'A presentation',$b=>'B presentation'] as $issuer=>$marker) {
    CompanySettingsRuntime::run($db,$p,$issuer,7,function()use($marker){
        CompanySettingsRuntime::updateBatch(CompanySettingsCatalog::prepare('email_content',[
            'email_header'=>'<p><strong>'.$marker.' header</strong></p>',
            'email_signature'=>'<p>'.$marker.' signature</p><img src="https://synthetic.invalid/tracker"><script>alert(1)</script>',
            'email_footer'=>'<p>'.$marker.' footer</p>',
        ]));
    });
}
$invoiceA=$s->create($a,7,$input,hash('sha256','presentation-delivery-invoice-A'));
$invoiceB=$s->create($b,7,array_replace($input,['client_id'=>$cb]),hash('sha256','presentation-delivery-invoice-B'));
$prepared=[];
CompanySettingsRuntime::run($db,$p,$b,7,function()use($db,$p,$a,$b,$invoiceA,$invoiceB,$s,$delivery,$base,$decrypt,&$prepared){
    foreach([$a=>[$invoiceA,101,'A presentation','B presentation'],$b=>[$invoiceB,202,'B presentation','A presentation']] as $issuer=>[$invoice,$contact,$own,$foreign]) {
        $revision=$s->revision($issuer,7,$invoice);$key=hash('sha256','presentation-delivery-'.$issuer);
        $id=$delivery->queue($issuer,7,$invoice,$contact,$revision,$key,$base,$decrypt);
        $row=$db->query("SELECT * FROM {$p}mail_queue WHERE id=?",[$id])->row();
        essentialCheck((int)$row->issuer_id===$issuer,'queue persists explicit company while another company is selected');
        foreach(['header','signature','footer'] as $fragment)essentialCheck(str_contains($row->message,$own.' '.$fragment),'queued message contains own '.$fragment);
        essentialCheck(!str_contains($row->message,$foreign),'queued message has no other company presentation');
        essentialCheck(!str_contains($row->message,'<script')&&!str_contains($row->message,'<img'),'sanitized saved presentation reaches actual queue without active content');
        essentialCheck($row->cc===''&&$row->bcc==='','presentation setting cannot add external financial recipients');
        essentialCheck(!str_contains($row->message,'encrypted-')&&!str_contains($row->headers,'encrypted-'),'presentation output and headers never contain SMTP secrets');
        $delivery->assertDispatch($row,$key);
        $prepared[$issuer]=[$id,$row->message,$revision,$key,$invoice,$contact];
    }
    essentialCheck(CompanySettingsRuntime::issuerId()===$b,'queue preparation never switches interactive company scope');
});
foreach($prepared as $issuer=>[$id,$message,$revision,$key,$invoice,$contact]) {
    CompanySettingsRuntime::run($db,$p,$issuer,7,function(){CompanySettingsRuntime::updateBatch(CompanySettingsCatalog::prepare('email_content',['email_header'=>'<p>new header</p>','email_signature'=>'<p>new signature</p>','email_footer'=>'<p>new footer</p>']));});
    essentialCheck($db->query("SELECT message FROM {$p}mail_queue WHERE id=?",[$id])->row()->message===$message,'editing company presentation never rewrites queued message snapshot');
    essentialCheck($delivery->queue($issuer,7,$invoice,$contact,$revision,$key,$base,$decrypt)===$id,'retry after presentation change returns canonical existing queued message');
}
echo 'Company presentation delivery: '.($GLOBALS['essentialChecks']-$presentationStart).' added checks, '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().'). No SMTP delivery.'.PHP_EOL;
