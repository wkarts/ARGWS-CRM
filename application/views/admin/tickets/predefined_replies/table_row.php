<?php defined('BASEPATH') or exit('No direct script access allowed');
                $row = [];

                for ($i = 0; $i < count($aColumns); $i++) {
                    $_data = $aRow[$aColumns[$i]];
                    if ($aColumns[$i] == 'name') {
                        $_data = '<a href="' . admin_url('tickets/predefined_reply/' . $aRow['id']) . '" class="tw-font-medium">' . e($_data) . '</a>';
                    }
                    $row[] = $_data;
                }

                $nativeAttrs=!empty($native_ticket_catalog)?' data-native-revision="'.e($aRow['revision_token']).'" data-native-return="'.e(admin_url('tickets/predefined_replies')).'"':'';
                $options = '<div class="tw-flex tw-items-center tw-space-x-2">';
                $options .= '<a href="' . admin_url('tickets/predefined_reply/' . $aRow['id']) . '" class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700">
                    <i class="fa-regular fa-pen-to-square fa-lg"></i>
                </a>';

                $options .= '<a href="' . admin_url('tickets/delete_predefined_reply/' . $aRow['id']) . '"
                class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700 _delete"'.$nativeAttrs.'>
                    <i class="fa-regular fa-trash-can fa-lg"></i>
                </a>';
                $options .= '</div>';
                $row[]              = $options;
return $row;
