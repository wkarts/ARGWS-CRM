<?php
            $row = [];

            $link = admin_url('projects/view/' . $aRow['id']);

            $row[] = '<a href="' . $link . '" class="tw-font-medium">' . $aRow['id'] . '</a>';

            $name = '<a href="' . $link . '" class="tw-font-medium">' . e($aRow['name']) . '</a>';

            $name .= '<div class="row-options">';

            $name .= '<a href="' . $link . '">' . _l('view') . '</a>';

            if ($hasPermissionCreate && !$clientid) {
                $name .= ' | <a href="#" data-name="' . e($aRow['name']) . '" onclick="copy_project(' . $aRow['id'] . ', this);return false;">' . _l('copy_project') . '</a>';
            }

            if ($hasPermissionEdit) {
                $name .= ' | <a href="' . admin_url('projects/project/' . $aRow['id']) . '">' . _l('edit') . '</a>';
            }

            if ($hasPermissionDelete) {
                $name .= ' | <a href="' . admin_url('projects/delete/' . $aRow['id']) . '" class="_delete">' . _l('delete') . '</a>';
            }

            $name .= '</div>';

            $row[] = $name;

            $row[] = '<a href="' . admin_url('clients/client/' . $aRow['clientid']) . '">' . e($aRow['company']) . '</a>';

            $row[] = render_tags($aRow['tags']);

            $row[] = e(_d($aRow['start_date']));

            $row[] = e(_d($aRow['deadline']));

            $membersOutput = '<div class="tw-flex -tw-space-x-1">';
            $members       = explode(',', $aRow['members']);
            $exportMembers = '';
            foreach ($members as $key => $member) {
                if ($member != '') {
                    $members_ids = explode(',', $aRow['members_ids']);
                    $member_id   = $members_ids[$key];
                    $membersOutput .= '<a href="' . admin_url('profile/' . $member_id) . '">' .
                        staff_profile_image($member_id, [
                            'tw-inline-block tw-h-7 tw-w-7 tw-rounded-full tw-ring-2 tw-ring-white',
                        ], 'small', [
                            'data-toggle' => 'tooltip',
                            'data-title'  => $member,
                        ]) . '</a>';
                    // For exporting
                    $exportMembers .= $member . ', ';
                }
            }

            $membersOutput .= '<span class="hide">' . trim($exportMembers, ', ') . '</span>';
            $membersOutput .= '</div>';
            $row[] = $membersOutput;

            $status = get_project_status_by_id($aRow['status']);
            $row[]  = '<span class="label project-status-' . $aRow['status'] . '" style="color:' . $status['color'] . ';border:1px solid ' . adjust_hex_brightness($status['color'], 0.4) . ';background: ' . adjust_hex_brightness($status['color'], 0.04) . ';">' . e($status['name']) . '</span>';

            // Custom fields add values
            foreach ($customFieldsColumns as $customFieldColumn) {
                $row[] = (strpos($customFieldColumn, 'date_picker_') !== false ? _d($aRow[$customFieldColumn]) : $aRow[$customFieldColumn]);
            }

            $row['DT_RowClass'] = 'has-row-options';

