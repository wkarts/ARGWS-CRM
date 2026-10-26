<?php
defined('BASEPATH') or exit('No direct script access allowed');
// Shared ORIGINAL ticket table row. The legacy provider supplies its existing variables.
$nativeTicketRow = isset($native_ticket_row);
if ($nativeTicketRow && (!class_exists('CompanyRequestScope', false) || !CompanyRequestScope::nativeFor(get_instance()) || !CompanyRequestScope::sealedFor(get_instance()))) throw new DomainException('native_ticket_projection_denied');
$row = [];

for ($i = 0; $i < count($aColumns); $i++) {
    if (strpos($aColumns[$i], 'as') !== false && ! isset($aRow[$aColumns[$i]])) {
        $_data = $aRow[strafter($aColumns[$i], 'as ')];
    } else {
        $_data = $aRow[$aColumns[$i]];
    }

    if ($aColumns[$i] == '1') {
        $_data = (!$nativeTicketRow || $native_ticket_row['can_edit']) ? '<div class="checkbox"><input type="checkbox" value="' . $aRow['ticketid'] . '" data-name="' . e($aRow['subject']) . '" data-status="' . $aRow['status'] . '"' . ($nativeTicketRow ? ' data-ticket-revision="'.e($native_ticket_row['revision']).'" data-tags-revision="'.e($native_ticket_row['tags_revision']).'"' : '') . '><label></label></div>' : '';
    } elseif ($aColumns[$i] == 'lastreply') {
        if ($aRow[$aColumns[$i]] == null) {
            $_data = _l('ticket_no_reply_yet');
        } else {
            $_data = e($nativeTicketRow ? $native_ticket_row['lastreply_label'] : _dt($aRow[$aColumns[$i]]));
        }
    } elseif ($aColumns[$i] == 'subject' || $aColumns[$i] == 'ticketid') {
        if ($nativeTicketRow) $_data = e($_data);
        // Ticket is assigned
        if ($aRow['assigned'] != 0) {
            if ($aColumns[$i] != 'ticketid') {
                $_data .= '<a href="' . admin_url('profile/' . $aRow['assigned']) . '" data-toggle="tooltip" title="' . e($nativeTicketRow ? $native_ticket_row['assignee_name'] : get_staff_full_name($aRow['assigned'])) . '" class="pull-left mright5">' . ($nativeTicketRow ? $native_ticket_row['assignee_image'] : staff_profile_image($aRow['assigned'], [
                    'staff-profile-image-xs',
                ])) . '</a>';
            } else {
                $_data = $nativeTicketRow ? $_data : e($_data);
            }
        } else {
            $_data = $nativeTicketRow ? $_data : e($_data);
        }

        $url   = admin_url('tickets/ticket/' . $aRow['ticketid']);
        $_data = '<a href="' . $url . '" class="valign tw-truncate tw-max-w-xs tw-block tw-min-w-0 tw-font-medium" title="' . e($_data) . '">' . $_data . '</a>';
        if ($aColumns[$i] == 'subject') {
            $_data .= '<div class="row-options">';
            $_data .= '<a href="' . $url . '">' . _l('view') . '</a>';
            if (!$nativeTicketRow || $native_ticket_row['can_edit']) $_data .= ' | <a href="' . $url . '?tab=settings">' . _l('edit') . '</a>';
            if ($nativeTicketRow ? $native_ticket_row['public_url'] !== null : get_option('disable_ticket_public_url') == '0') {
                $_data .= ' | <a href="' . ($nativeTicketRow ? e($native_ticket_row['public_url']) : get_ticket_public_url($aRow)) . '" target="_blank">' . _l('view_public_form') . '</a>';
            }
            if ($nativeTicketRow ? $native_ticket_row['can_delete'] : can_staff_delete_ticket()) {
                $_data .= ' | <a href="' . admin_url('tickets/delete/' . $aRow['ticketid']) . '"' . ($nativeTicketRow ? ' data-ticket-revision="'.e($native_ticket_row['revision']).'"' : '') . ' class="_delete">' . _l('delete') . '</a>';
            }
            $_data .= '</div>';
        }
    } elseif ($i == $tagsColumns) {
        $_data = ($nativeTicketRow ? $native_ticket_row['tags_html'] : render_tags($_data));
    } elseif ($i == $contactColumn) {
        if ($aRow['userid'] != 0 && (!$nativeTicketRow || $native_ticket_row['can_view_customer'])) {
            $_data = '<a href="' . admin_url('clients/client/' . $aRow['userid'] . '?group=contacts') . '">' . e($aRow['contact_full_name']);
            if (! empty($aRow['company'])) {
                $_data .= ' (' . e($aRow['company']) . ')';
            }
            $_data .= '</a>';
        } else {
            $_data = e($nativeTicketRow && $aRow['userid'] != 0 ? $aRow['contact_full_name'] : $aRow['ticket_opened_by_name']);
        }
    } elseif ($aColumns[$i] == 'status') {
        $_data = '<span class="label ticket-status-' . $aRow['status'] . '" style="border:1px solid ' . adjust_hex_brightness($aRow['statuscolor'], 0.4) . '; color:' . $aRow['statuscolor'] . ';background: ' . adjust_hex_brightness($aRow['statuscolor'], 0.04) . ';">' . e($nativeTicketRow ? $aRow['status_name'] : ticket_status_translate($aRow['status'])) . '</span>';
    } elseif ($aColumns[$i] == db_prefix() . 'tickets.date') {
        $_data = e($nativeTicketRow ? $native_ticket_row['date_label'] : _dt($_data));
    } elseif (strpos($aColumns[$i], 'service_name') !== false) {
        $_data = e($_data);
    } elseif ($aColumns[$i] == 'priority') {
        $_data = e($nativeTicketRow ? $aRow['priority_name'] : ticket_priority_translate($aRow['priority']));
    } else {
        if ($nativeTicketRow && isset($native_ticket_row['custom_labels'][$aColumns[$i]])) {
            $_data = e($native_ticket_row['custom_labels'][$aColumns[$i]]);
        } elseif ($nativeTicketRow) {
            $_data = e($_data);
        } elseif (strpos($aColumns[$i], 'date_picker_') !== false) {
            $_data = (strpos($_data, ' ') !== false ? _dt($_data) : _d($_data));
        }
    }

    $row[] = $_data;

    if ($aRow['adminread'] == 0) {
        $row['DT_RowClass'] = 'text-danger';
    }
}

if (isset($row['DT_RowClass'])) {
    $row['DT_RowClass'] .= ' has-row-options';
} else {
    $row['DT_RowClass'] = 'has-row-options';
}

if (!$nativeTicketRow) $row = hooks()->apply_filters('admin_tickets_table_row_data', $row, $aRow);
