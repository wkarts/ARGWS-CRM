<?php defined('BASEPATH') or exit('No direct script access allowed');
if(isset($native_settings_tags)){ ?>
<ul class="list-unstyled">
<?php foreach($native_settings_tags as$tag){ ?>
<li class="settings-tag-wrapper tw-mb-4">
<p><strong><?= e($tag['name']); ?></strong> <span class="badge"><?= (int)$tag['count']; ?></span></p>
<?php if($tag['can_edit']){echo form_open(admin_url('settings?group=tags'),['class'=>'tw-inline-block']);echo form_hidden('_company_context',CompanyRequestScope::token());echo form_hidden('_native_company_csrf',CompanyRequestScope::nativeCsrfToken(get_instance()));echo form_hidden('id',$tag['id']);echo form_hidden('revision',$tag['expected']);echo form_hidden('request_key',bin2hex(random_bytes(32))); ?>
<label class="sr-only" for="tag-<?= e($tag['id']); ?>"><?= _l('name'); ?></label><input id="tag-<?= e($tag['id']); ?>" name="name" value="<?= e($tag['name']); ?>" maxlength="100" required><button class="btn btn-default" type="submit"><?= _l('save'); ?></button>
<?php echo form_close();} ?>
<?php if($tag['can_delete']){echo form_open(admin_url('settings/delete_tag/'.$tag['id']),['class'=>'tw-inline-block']);echo form_hidden('_company_context',CompanyRequestScope::token());echo form_hidden('_native_company_csrf',CompanyRequestScope::nativeCsrfToken(get_instance()));echo form_hidden('revision',$tag['expected']);echo form_hidden('request_key',bin2hex(random_bytes(32))); ?>
<button class="btn btn-danger" type="submit"><?= _l('delete'); ?></button><?php echo form_close();} ?>
</li><?php } if(!$native_settings_tags){ ?><li><?= _l('no_tags_used'); ?></li><?php } ?>
</ul>
<?php return; }
$tags      = get_tags();
$totalTags = count($tags);
?>
<div class="row">
    <div class="col-md-<?php if ($totalTags > 0) {
    echo '12';
} else {
    echo '12 text-center';
}?>">
        <ul class="no-mbot">
            <?php
          foreach ($tags as $tag) { ?>
            <li class="settings-tag-wrapper tw-mb-3 last:tw-mb-0">
                <span class="settings-tag-name"></span>
                <div class="form-group no-mbot settings-tag-input">
                    <div class="input-group">
                        <div class="input-group-addon">
                            <?php echo total_rows(db_prefix() . 'taggables', ['tag_id' => $tag['id']]); ?>
                        </div>
                        <input type="text" name="tags[<?php echo e($tag['id']); ?>]" value="<?php echo e($tag['name']); ?>"
                            class="form-control">
                        <div class="input-group-btn">
                            <a class="btn btn-danger _delete"
                                href="<?php echo admin_url('settings/delete_tag/' . $tag['id']); ?>">
                                <i class="fa fa-remove"></i></a>
                        </div>
                    </div>
                </div>
            </li>
            <?php }
      if ($totalTags == 0) { ?>
            <li class="list-group-item no-mbot"><?php echo _l('no_tags_used'); ?></li>
            <?php } ?>
        </ul>
    </div>
</div>
