<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="tw-mt-6" id="issuer-staff-access">
    <h4 class="tw-font-bold tw-text-lg"><?php echo _l('issuers_title'); ?></h4>
    <p class="text-muted"><?php echo _l($personal?'issuer_staff_own_default_explanation':'issuer_staff_access_explanation'); ?></p>
    <input type="hidden" name="issuers_access_present" value="1">
    <input type="hidden" name="issuers_access_csrf" value="<?php echo html_escape($issuers_staff_csrf); ?>">
    <?php if($master) { ?><p class="alert alert-info"><?php echo _l('issuer_master_access_automatic'); ?></p><?php } ?>
    <?php if(!$personal&&!$master) {
        $selectedGrants=[];
        foreach($grants as $grant) { $selectedGrants[(int)$grant['issuer_id']][$grant['feature']][$grant['capability']]=true; }
        foreach($catalog as $issuer) { $issuerId=(int)$issuer['id']; $active=(int)$issuer['active']===1; ?>
        <div class="panel panel-default"><div class="panel-heading">
            <div class="checkbox"><input type="checkbox" name="issuer_memberships[<?php echo $issuerId; ?>]" id="issuer-membership-<?php echo $issuerId; ?>" value="1" <?php echo !empty($members[$issuerId]['can_view'])?'checked ':''; ?><?php echo !$active?'disabled':''; ?>>
                <label for="issuer-membership-<?php echo $issuerId; ?>"><?php echo html_escape($issuer['legal_name']); ?></label>
                <?php if(!$active) { ?><span class="label label-default"><?php echo _l('issuer_inactive'); ?></span><?php } ?>
            </div>
        </div>
        <?php if($active) { ?><div class="panel-body"><details><summary><?php echo _l('issuer_staff_resource_permissions'); ?></summary>
            <p class="text-muted"><?php echo _l('issuer_staff_resource_explanation'); ?></p>
            <div class="table-responsive"><table class="table table-bordered issuer-resource-permissions"><thead><tr><th><?php echo _l('features'); ?></th><th><?php echo _l('capabilities'); ?></th></tr></thead><tbody>
            <?php foreach($resources as $feature=>$resource) { ?>
                <tr><td><?php echo html_escape($resource['name']); ?></td><td>
                <?php foreach($resource['capabilities'] as $capability=>$label) { $control='issuer-'.$issuerId.'-'.$feature.'-'.$capability; ?>
                    <div class="checkbox"><input type="checkbox" id="<?php echo html_escape($control); ?>" name="issuer_resource_permissions[<?php echo $issuerId; ?>][<?php echo html_escape($feature); ?>][]" value="<?php echo html_escape($capability); ?>" <?php echo isset($selectedGrants[$issuerId][$feature][$capability])?'checked':''; ?>>
                        <label for="<?php echo html_escape($control); ?>"><?php echo html_escape($label); ?></label></div>
                <?php } ?></td></tr>
            <?php } ?></tbody></table></div>
        </details></div><?php } ?></div>
    <?php } } ?>
    <label for="issuer_default"><?php echo _l('issuer_staff_default'); ?></label>
    <select id="issuer_default" name="issuer_default" class="form-control">
        <option value=""><?php echo _l('issuer_staff_no_default'); ?></option>
        <?php foreach($catalog as $issuer) { if((int)$issuer['active']!==1) { continue; } ?>
            <option value="<?php echo (int)$issuer['id']; ?>" <?php echo $default===(int)$issuer['id']?'selected':''; ?>><?php echo html_escape($issuer['legal_name']); ?></option>
        <?php } ?>
    </select>
    <p class="text-muted"><?php echo _l('issuer_staff_default_validation'); ?></p>
</div>
