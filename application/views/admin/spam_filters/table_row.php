<?php
                $row = [];
                $aColumns = ['value'];
                if(isset($native_spam_row))$aRow['id']=$aRow['native_id'];

                for ($i = 0; $i < count($aColumns); $i++) {
                    $_data = $aRow[$aColumns[$i]];
                    $row[] = e($_data);
                }

                $options = '<div class="tw-flex tw-items-center tw-space-x-2">';
                $options .= '<a href="#" class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700"' . (isset($native_spam_row)?' data-native-revision="'.e($aRow['revision_token']).'"':'') . ' data-type="' . $filter_type . '" data-value="' . e($aRow['value']) . '" onclick="edit_spam_filter(this,' . $aRow['id'] . '); return false;">
                    <i class="fa-regular fa-pen-to-square fa-lg"></i>
                </a>';

                $options .= '<a href="' . admin_url('spam_filters/delete/' . $aRow['id'] . '/' . $rel_type) . '"
                class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700 _delete"' . (isset($native_spam_row)?' data-native-revision="'.e($aRow['revision_token']).'" data-native-request-nonce="'.bin2hex(random_bytes(32)).'"':'') . '>
                    <i class="fa-regular fa-trash-can fa-lg"></i>
                </a>';
                $options .= '</div>';

                $row[] = $options;
                return $row;
