<?php

declare(strict_types=1);

/** Single native projects table row markup. All data/provider callbacks are supplied by the authorized caller. */
final class NativeProjectRowPresentation
{
    public static function render(array $aRow,$clientid,array $permissions,array $renderers,array $customFieldsColumns=[]):array
    {
        foreach(['url','translate','escape','tags','date','avatar','status','brightness']as$key)
            if(!isset($renderers[$key])||!is_callable($renderers[$key]))throw new InvalidArgumentException('native_project_renderer_missing');
        foreach(['create','edit','delete']as$key)
            if(!array_key_exists($key,$permissions)||!is_bool($permissions[$key]))throw new InvalidArgumentException('native_project_permissions_invalid');
        $hasPermissionCreate=$permissions['create'];$hasPermissionEdit=$permissions['edit'];$hasPermissionDelete=$permissions['delete'];
        $row = [];

        $link = $renderers['url']('projects/view/' . $aRow['id']);

        $row[] = '<a href="' . $link . '" class="tw-font-medium">' . $aRow['id'] . '</a>';

        $name = '<a href="' . $link . '" class="tw-font-medium">' . $renderers['escape']($aRow['name']) . '</a>';

        $name .= '<div class="row-options">';

        $name .= '<a href="' . $link . '">' . $renderers['translate']('view') . '</a>';

        if ($hasPermissionCreate && !$clientid) {
            $name .= ' | <a href="#" data-name="' . $renderers['escape']($aRow['name']) . '" onclick="copy_project(' . $aRow['id'] . ', this);return false;">' . $renderers['translate']('copy_project') . '</a>';
        }

        if ($hasPermissionEdit) {
            $name .= ' | <a href="' . $renderers['url']('projects/project/' . $aRow['id']) . '">' . $renderers['translate']('edit') . '</a>';
        }

        if ($hasPermissionDelete) {
            $name .= ' | <a href="' . $renderers['url']('projects/delete/' . $aRow['id']) . '" class="_delete">' . $renderers['translate']('delete') . '</a>';
        }

        $name .= '</div>';

        $row[] = $name;

        $row[] = '<a href="' . $renderers['url']('clients/client/' . $aRow['clientid']) . '">' . $renderers['escape']($aRow['company']) . '</a>';

        $row[] = $renderers['tags']($aRow['tags']);

        $row[] = $renderers['escape']($renderers['date']($aRow['start_date']));

        $row[] = $renderers['escape']($renderers['date']($aRow['deadline']));

        $membersOutput = '<div class="tw-flex -tw-space-x-1">';
        $members       = explode(',', $aRow['members']);
        $exportMembers = '';
        foreach ($members as $key => $member) {
            if ($member != '') {
                $members_ids = explode(',', $aRow['members_ids']);
                $member_id   = $members_ids[$key];
                $membersOutput .= '<a href="' . $renderers['url']('profile/' . $member_id) . '">' .
                    $renderers['avatar']($member_id, [
                        'tw-inline-block tw-h-7 tw-w-7 tw-rounded-full tw-ring-2 tw-ring-white',
                    ], 'small', [
                        'data-toggle' => 'tooltip',
                        'data-title'  => $member,
                    ]) . '</a>';
                // For exporting
                $exportMembers .= $member . ', ';
            }
        }

        $membersOutput .= '<span class="hide">' . $renderers['escape'](trim($exportMembers, ', ')) . '</span>';
        $membersOutput .= '</div>';
        $row[] = $membersOutput;

        $status = $renderers['status']($aRow['status']);
        $row[]  = '<span class="label project-status-' . $aRow['status'] . '" style="color:' . $status['color'] . ';border:1px solid ' . $renderers['brightness']($status['color'], 0.4) . ';background: ' . $renderers['brightness']($status['color'], 0.04) . ';">' . $renderers['escape']($status['name']) . '</span>';

        // Custom fields add values
        foreach ($customFieldsColumns as $customFieldColumn) {
            $row[] = (strpos($customFieldColumn, 'date_picker_') !== false ? $renderers['date']($aRow[$customFieldColumn]) : $aRow[$customFieldColumn]);
        }

        $row['DT_RowClass'] = 'has-row-options';

        return $row;
    }
}
