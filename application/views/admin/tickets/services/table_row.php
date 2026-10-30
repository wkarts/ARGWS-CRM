<?php defined('BASEPATH') or exit('No direct script access allowed');
                $row = [];

                for ($i = 0; $i < count($aColumns); $i++) {
                    $_data = $aRow[$aColumns[$i]];
                    if ($aColumns[$i] == 'name') {
                        $_data = '<a href="#" class="tw-font-medium" onclick="edit_service(this,' . $aRow['serviceid'] . ');return false" data-name="' . e($aRow['name']) . '"'.(!empty($native_ticket_catalog)?' data-native-revision="'.e($aRow['revision_token']).'"':'').'>' . e($_data) . '</a>';
                    }
                    $row[] = $_data;
                }

                $nativeAttrs=!empty($native_ticket_catalog)?' data-native-revision="'.e($aRow['revision_token']).'" data-native-return="'.e(admin_url('tickets/services')).'"':'';
                $options = '<a href="#" onclick="edit_service(this,' . $aRow['serviceid'] . '); return false;"  data-name="' . e($aRow['name']) . '"'.(!empty($native_ticket_catalog)?' data-native-revision="'.e($aRow['revision_token']).'"':'').' class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700 tw-mr-2">
                <i class="fa-regular fa-pen-to-square fa-lg"></i>
            </a>';

                $options .= '<a href="' . admin_url('tickets/delete_service/' . $aRow['serviceid']) . '" class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700 _delete"'.$nativeAttrs.'>
            <i class="fa-regular fa-trash-can fa-lg"></i>
        </a>';

                $row[]              = $options;
return $row;
