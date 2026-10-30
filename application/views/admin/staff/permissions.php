<div class="table-responsive">
    <table class="table table-bordered roles no-margin">
        <thead>
            <tr>
                <th><?= _l('features') ?></th>
                <th><?= _l('capabilities') ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php
            $nativePermissions = isset($native_staff_permission_catalog);
            if (isset($member)) {
                $is_admin = $nativePermissions ? !empty($native_staff_target_master) : is_admin($member->staffid);
            }
            $native_staff_permissions = $native_staff_permissions ?? [];
            $member = $member ?? null; $role = $role ?? null;
            $permissionHas = static function ($capability, $feature) use ($nativePermissions, $native_staff_permissions, $member, $role) {
                if ($nativePermissions) return in_array($capability, $native_staff_permissions[$feature] ?? [], true);
                return (isset($member) && staff_can($capability, $feature, $member->staffid))
                    || (isset($role) && has_role_permission($role->roleid, $capability, $feature));
            };
                foreach (($nativePermissions ? $native_staff_permission_catalog : get_available_staff_permissions($funcData)) as $feature => $permission) { ?>
            <tr data-name="<?= e($feature); ?>">
                <td>
                    <b><?= e($permission['name']); ?></b>
                </td>
                <td>
                    <?php
                         if (isset($permission['before'])) {
                             echo $permission['before'];
                         }
                    ?>
                    <?php foreach ($permission['capabilities'] as $capability => $name) {
                        $checked  = '';
                        $disabled = '';
                        if ((isset($is_admin) && $is_admin)
                   || (is_array($name) && isset($name['not_applicable']) && $name['not_applicable'])
                   || (
                       ($capability == 'view_own' || $capability == 'view'
                          && array_key_exists('view_own', $permission['capabilities']) && array_key_exists('view', $permission['capabilities']))
                        && (
                            $permissionHas(($capability == 'view' ? 'view_own' : 'view'), $feature)
                        )
                   )
                        ) {
                            $disabled = ' disabled ';
                        } elseif ($permissionHas($capability, $feature)) {
                            $checked = ' checked ';
                        } ?>
                    <?php if ($nativePermissions && !in_array($capability, $native_staff_assignable[$feature] ?? [], true) && !$permissionHas($capability, $feature)) { $disabled = ' disabled '; } ?>
                    <div class="tw-ml-2">
                        <div class="checkbox last:tw-mb-0">
                            <input
                                <?php if ($capability == 'view') { ?>
                            data-can-view <?php } ?>
                            <?php if ($capability == 'view_own') { ?>
                            data-can-view-own <?php } ?>
                            <?php if (is_array($name) && isset($name['not_applicable']) && $name['not_applicable']) { ?>
                            data-not-applicable="true" <?php } ?>
                            type="checkbox"
                            <?= e($checked); ?>
                            class="capability"
                            id="<?= $feature . '_' . $capability; ?>"
                            name="permissions[<?= e($feature); ?>][]"
                            value="<?= e($capability); ?>"
                            <?= e($disabled); ?>>
                            <label
                                for="<?= $feature . '_' . $capability; ?>">
                                <?= ! is_array($name) ? $name : $name['name']; ?>
                            </label>
                            <?php
                        if (isset($permission['help']) && array_key_exists($capability, $permission['help'])) {
                            echo '<i class="fa-regular fa-circle-question" data-toggle="tooltip" data-title="' . e($permission['help'][$capability]) . '"></i>';
                        } ?>
                        </div>
                    </div>
                    <?php
                    } ?>
                    <?php
                    if (isset($permission['after'])) {
                        echo $permission['after'];
                    }
                    ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>