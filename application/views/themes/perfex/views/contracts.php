<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php if(isset($native_portal_contracts)){ ?>
<?php
$esc=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$labels=['active'=>'Ativo','cancelled'=>'Cancelado'];
$indexUrl=NativePortalPresentation::url('clients/contracts');
$hidden=static function(string$action,array$extra=[])use($esc,$native_portal_csrf):void{
    echo '<input type="hidden" name="_native_portal_csrf" value="'.$esc($native_portal_csrf).'">';
    echo '<input type="hidden" name="native_contract_action" value="'.$esc($action).'">';
    foreach($extra as$name=>$value)echo '<input type="hidden" name="'.$esc($name).'" value="'.$esc($value).'">';
};
?>
<div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
    <h3 class="tw-mt-0 tw-mb-0 tw-font-bold tw-text-lg tw-text-neutral-700"><?= $esc(_l('clients_contracts')); ?></h3>
    <?php if(is_array($native_portal_contract)){ ?><a class="btn btn-default" href="<?= $esc($indexUrl); ?>"><?= $esc(_l('go_back')); ?></a><?php } ?>
</div>

<?php if(is_array($native_portal_contract)){ $contract=$native_portal_contract;$detailUrl=NativePortalPresentation::url('clients/contracts/'.$contract['canonical_id']); ?>
<section class="panel_s" id="native-portal-contract-detail"><div class="panel-body">
    <div class="tw-flex tw-items-start tw-justify-between tw-gap-3">
        <div><h4 class="tw-mt-0 tw-font-semibold"><?= $esc($contract['name']); ?></h4><p class="text-muted">Contrato <?= (int)$contract['number']; ?> · <?= $esc($labels[$contract['status']]??$contract['status']); ?> · <?= $esc($contract['start_date'].' — '.$contract['due_date']); ?></p></div>
        <?= form_open($detailUrl,['class'=>'tw-shrink-0']); ?><?php $hidden('pdf'); ?><button class="btn btn-default" type="submit"><i class="fa-regular fa-file-pdf tw-mr-1"></i><?= $esc(_l('download')); ?></button><?= form_close(); ?>
    </div>
    <div class="well" style="white-space:pre-wrap"><?= $esc($contract['body']); ?></div>
    <p class="text-muted">Documento de registro. A disponibilização no portal não representa assinatura nem aceite eletrônico.</p>
</div></section>

<section class="panel_s" id="native-portal-contract-files"><div class="panel-body"><h4 class="tw-mt-0 tw-font-semibold"><?= $esc(_l('contract_attachments')); ?></h4>
    <?php if(!$contract['files']){ ?><p class="text-muted">Nenhum anexo publicado.</p><?php } ?>
    <?php foreach($contract['files']as$file){ ?>
        <?= form_open($detailUrl,['class'=>'tw-mb-2']); ?><?php $hidden('file',['file_key'=>$file['file_key']]); ?><button class="btn btn-default" type="submit"><i class="fa-solid fa-paperclip tw-mr-1"></i><?= $esc($file['filename']); ?> · <?= (int)$file['byte_size']; ?> bytes</button><?= form_close(); ?>
    <?php } ?>
</div></section>

<section class="panel_s" id="native-portal-contract-discussion"><div class="panel-body"><h4 class="tw-mt-0 tw-font-semibold">Conversa sobre este contrato</h4>
    <p class="text-muted">Comentários são registros operacionais. Eles não representam assinatura, aceite jurídico nem alteração do texto do contrato.</p>
    <?php foreach($contract['discussion']as$comment){ ?><article class="well"><p style="white-space:pre-wrap"><?= $esc($comment['body']); ?></p><small><?= $esc(($comment['author_type']==='contact'?'Contato: ':'Equipe: ').$comment['author_name']); ?> · <?= $esc($comment['created_at']); ?></small></article><?php } ?>
    <?= form_open($detailUrl,['id'=>'native-portal-contract-comment']); ?><?php $hidden('comment',['revision'=>$contract['revision'],'request_key'=>$native_portal_contract_request]); ?><div class="form-group"><label for="native-portal-contract-comment-body">Seu comentário em texto</label><textarea id="native-portal-contract-comment-body" class="form-control" name="discussion_body" maxlength="8000" rows="6" required></textarea></div><button class="btn btn-primary" type="submit">Registrar comentário</button><?= form_close(); ?>
</div></section>
<?php }else{ ?>
<section class="panel_s" id="native-portal-contract-list"><div class="panel-body">
    <p class="text-muted">Somente contratos publicados para seu cadastro e para esta empresa são exibidos.</p>
    <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Número</th><th>Contrato</th><th>Estado</th><th>Vigência</th></tr></thead><tbody>
    <?php foreach($native_portal_contracts['items']as$contract){ ?><tr><td><?= (int)$contract['number']; ?></td><td><a href="<?= $esc(NativePortalPresentation::url('clients/contracts/'.$contract['canonical_id'])); ?>"><?= $esc($contract['name']); ?></a></td><td><?= $esc($labels[$contract['status']]??$contract['status']); ?></td><td><?= $esc($contract['start_date'].' — '.$contract['due_date']); ?></td></tr><?php } ?>
    </tbody></table></div>
    <?php if(!$native_portal_contracts['items']){ ?><p class="text-muted">Nenhum contrato publicado disponível.</p><?php } ?>
    <?php if($native_portal_contracts['next']!==null){ ?><a class="btn btn-default" href="<?= $esc($indexUrl.'&after='.rawurlencode($native_portal_contracts['next'])); ?>">Próxima página</a><?php } ?>
</div></section>
<?php } ?>
<?php return; } ?>

<h3 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700 contracts-summary-heading">
    <?= _l('contract_summary_by_type'); ?>
</h3>

<div class="relative tw-mb-10" style="max-height:300px;">
    <canvas class="chart" height="300" id="contracts-by-type-chart"></canvas>
</div>

<h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700 section-heading section-heading-contracts">
    <?= _l('clients_contracts'); ?>
</h4>

<div class="panel_s">
    <div class="panel-body">
        <?php get_template_part('contracts_table'); ?>
    </div>
</div>
<script>
    var contracts_by_type = '<?= $contracts_by_type_chart; ?>';
</script>
