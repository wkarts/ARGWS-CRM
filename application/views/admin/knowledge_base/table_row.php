<?php
defined('BASEPATH') or exit('No direct script access allowed');
    $row = [];

    for ($i = 0; $i < count($aColumns); $i++) {
        $_data = $aRow[$aColumns[$i]];
        if ($aColumns[$i] == 'articlegroup') {
            $_data = e($aRow['name']);
        } elseif ($aColumns[$i] == 'subject') {
            $link = admin_url('knowledge_base/view/' . $aRow['slug']);
            if ($aRow['staff_article'] == 0) {
                $link = isset($native_knowledge_issuer) ? NativeCompanyKnowledgePresentation::publicUrl($aRow['slug'], $native_knowledge_issuer) : site_url('knowledge-base/article/' . $aRow['slug']);
            }

            if (staff_can('edit', 'knowledge_base')) {
                $_data = '<a href="' . admin_url('knowledge_base/article/' . $aRow['articleid']) . '" class="tw-font-medium">' . e($_data) . '</a>';
            } else {
                $_data = '<a href="' . $link . '" target="_blank" class="tw-font-medium">' . e($_data) . '</a>';
            }

            if ($aRow['staff_article'] == 1) {
                $_data .= '<span class="label label-default pull-right">' . _l('internal_article') . '</span>';
            }

            $_data .= '<div class="row-options">';

            $_data .= '<a href="' . $link . '" target="_blank">' . _l('view') . '</a>';

            if (staff_can('edit', 'knowledge_base')) {
                $_data .= ' | <a href="' . admin_url('knowledge_base/article/' . $aRow['articleid']) . '">' . _l('edit') . '</a>';
            }

            if (staff_can('delete', 'knowledge_base')) {
                $_data .= ' | <a href="' . admin_url('knowledge_base/delete_article/' . $aRow['articleid']) . '" class="_delete text-danger"' . (isset($native_knowledge_issuer) ? ' data-native-revision="' . e($aRow['revision_token']) . '"' : '') . '>' . _l('delete') . '</a>';
            }

            $_data .= '</div>';
        } elseif ($aColumns[$i] == 'datecreated') {
            $_data = e(_dt($_data));
        }

        $row[]              = $_data;
        $row['DT_RowClass'] = 'has-row-options';
    }


return $row;
