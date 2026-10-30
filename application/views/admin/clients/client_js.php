<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Included in application/views/admin/clients/client.php
 */
?>
<script>
Dropzone.options.clientAttachmentsUpload = false;
var customer_id = $('input[name="userid"]').val();
var customer_tab_active = get_url_param('tab');
<?php if(!empty($native_customers)){ ?>
// The embedded administrators table is a browsing control, not profile data.
// Keep pagination usable without submitting its generated select to the strict writer.
function nativeCustomerAdminTableControls() {
    $('#customer_admins .dataTables_length select').removeAttr('name');
}
$('#customer_admins table').on('init.dt draw.dt', nativeCustomerAdminTableControls);
<?php } ?>
$(function() {
    <?php if(!empty($native_customers)){ ?>
    nativeCustomerAdminTableControls();
    <?php } ?>
    $(document).on('click', '[data-native-admin-remove]', function(event) {
        event.preventDefault();
        var form=$('<form>', {method:'POST', action:$(this).attr('href')});
        form.append($('<input>', {type:'hidden', name:'revision', value:$(this).attr('data-revision')}));
        form.append($('<input>', {type:'hidden', name:'_native_company_csrf', value:$('input[name="_native_company_csrf"]').first().val()}));
        if(typeof csrfData !== 'undefined') form.append($('<input>', {type:'hidden', name:csrfData.token_name, value:csrfData.hash}));
        form.appendTo(document.body).trigger('submit');
    });


    if ($('#client-attachments-upload').length > 0) {
        new Dropzone('#client-attachments-upload', appCreateDropzoneOptions({
            paramName: "file",
            <?php if(!empty($native_customers)){ ?>
            sending: function(file,xhr,data){if(!file.nativeRequestKey){var bytes=new Uint8Array(32);window.crypto.getRandomValues(bytes);file.nativeRequestKey=Array.from(bytes).map(function(v){return v.toString(16).padStart(2,'0');}).join('');}data.append('request_key',file.nativeRequestKey);data.append('_native_company_csrf',<?= json_encode($company_clients_csrf); ?>);},
            <?php } ?>
            accept: function(file, done) {
                done();
            },
            success: function(file, response) {
                if (this.getUploadingFiles().length === 0 && this.getQueuedFiles().length ===
                    0) {
                    window.location.reload();
                }
            }
        }));
    }

    // Save button not hidden if passed from url ?tab= we need to re-click again
    if (customer_tab_active) {
        $('body').find('.nav-tabs [href="#' + customer_tab_active + '"]').click();
    }

    if(customer_tab_active === 'customer_admins') {
        $('#profile-save-section').addClass('hide');
    }

    $('a[href="#customer_admins"]').on('click', function() {
        $('#profile-save-section').addClass('hide');
    });

    $('.customer-profile-tabs  a').not('a[href="#customer_admins"]').on('click', function() {
        $('#profile-save-section').removeClass('hide');
    });

    $("input[name='tasks_related_to[]']").on('change', function() {
        var tasks_related_values = []
        $('#tasks_related_filter :checkbox:checked').each(function(i) {
            tasks_related_values[i] = $(this).val();
        });
        $('input[name="tasks_related_to"]').val(tasks_related_values.join());
        $('.table-rel-tasks').DataTable().ajax.reload();
    });

    var contact_id = get_url_param('contactid');
    if (contact_id) {
        contact(customer_id, contact_id);
    }

    // consents=CONTACT_ID
    var consents = get_url_param('consents');
    if (consents) {
        view_contact_consent(consents);
    }

    // If user clicked save and add new contact
    if (get_url_param('new_contact')) {
        contact(customer_id);
    }

    <?php if(!empty($native_customers)){ ?>
    $('body').on('submit','.native-customer-activate',function(event){
        event.preventDefault();var form=$(this),button=form.find('button[type="submit"]');
        if(form.data('nativeBusy'))return false;form.data('nativeBusy',true);button.prop('disabled',true);
        $.post(form.attr('action'),form.serialize()).done(function(result){
            if(typeof result==='string'){try{result=JSON.parse(result);}catch(error){result=null;}}
            if(result&&result.success===true)window.location.reload();
            else alert_float('danger',<?= json_encode(_l('access_denied')); ?>);
        }).fail(function(){alert_float('danger',<?= json_encode(_l('access_denied')); ?>);})
          .always(function(){form.data('nativeBusy',false);button.prop('disabled',false);});
        return false;
    });
    $('#send_file').on('show.bs.modal',function(event){var el=$(event.relatedTarget),modal=$(this),bytes=new Uint8Array(32);window.crypto.getRandomValues(bytes);modal.find('[name="native_file_id"]').val(el.attr('data-native-file-id'));modal.find('[name="native_file_revision"]').val(el.attr('data-native-file-revision'));modal.find('[name="request_key"]').val(Array.from(bytes).map(function(v){return v.toString(16).padStart(2,'0');}).join(''));});
    // Native file submission returns a durable queue receipt, not a delivery claim.
    $('#send_file form').on('submit.nativeFileQueue',function(event){
        event.preventDefault();var form=$(this),button=form.find('button[type="submit"]');
        if(form.data('nativeFileBusy'))return false;form.data('nativeFileBusy',true);button.prop('disabled',true);
        $.post(form.attr('action'),form.serialize()).done(function(result){
            if(typeof result==='string'){try{result=JSON.parse(result);}catch(error){result=null;}}
            if(result&&result.success===true&&result.queued===true&&Number.isSafeInteger(result.queue_id)&&result.queue_id>0){
                alert_float('success','Arquivo adicionado à fila de envio.');$('#send_file').modal('hide');
            }else alert_float('danger','Não foi possível confirmar a fila de envio.');
        }).fail(function(){alert_float('danger','Não foi possível confirmar a fila de envio.');})
          .always(function(){form.data('nativeFileBusy',false);button.prop('disabled',false);});
        return false;
    });
    $('body').on('click','.native-customer-file-delete',function(event){event.preventDefault();var el=$(this);$.post(el.attr('href'),{revision:el.closest('tr').attr('data-native-file-revision'),_native_company_csrf:<?= json_encode($company_clients_csrf); ?>}).done(function(){window.location.reload();}).fail(function(){alert_float('danger',<?= json_encode(_l('access_denied')); ?>);});});
    <?php } ?>
    $('body').on('change', '.onoffswitch input.customer_file', function(event, state) {
        var invoker = $(this);
        var checked_visibility = invoker.prop('checked');
        var share_file_modal = $('#customer_file_share_file_with');
        setTimeout(function() {
            $('input[name="file_id"]').val(invoker.attr('data-id'));
            if (checked_visibility && share_file_modal.attr('data-total-contacts') > 1) {
                share_file_modal.modal('show');
            } else {
                do_share_file_contacts();
            }
        }, 200);
    });

    $('.customer-form-submiter').on('click', function() {
        var form = $('.client-form');
        if (form.valid()) {
            if ($(this).hasClass('save-and-add-contact')) {
                form.find('.additional').html(hidden_input('save_and_add_contact', 'true'));
            } else {
                form.find('.additional').html('');
            }
            form.submit();
        }
    });

    if (typeof(Dropbox) != 'undefined' && $('#dropbox-chooser').length > 0) {
        document.getElementById("dropbox-chooser").appendChild(Dropbox.createChooseButton({
            success: function(files) {
                saveCustomerProfileExternalFile(files, 'dropbox');
            },
            linkType: "preview",
            extensions: app.options.allowed_files.split(','),
        }));
    }

    /* Customer profile tickets table */
    $('.table-tickets-single').find('#th-submitter').removeClass('toggleable');

    <?php if(!empty($native_customers)){ ?>
    initDataTable('.table-tickets-single', admin_url + 'tickets/index', undefined,
        undefined, {userid:'input[name="userid"]'}, [$('table thead .ticket_created_column').index(), 'desc']);
    <?php }else{ ?>
    initDataTable('.table-tickets-single', admin_url + 'tickets/index/false/' + customer_id, undefined,
        undefined, 'undefined', [$('table thead .ticket_created_column').index(), 'desc']);
    <?php } ?>

    /* Customer profile contracts table */
    initDataTable('.table-contracts-single-client', admin_url + 'contracts/table/' + customer_id, undefined,
        undefined, 'undefined', [6, 'desc']);

    /* Custome profile contacts table */
    var contactsNotSortable = [];
    <?php if (!empty($native_customers)?!empty($native_contact_consent_enabled):(is_gdpr() && get_option('gdpr_enable_consent_for_contacts') == '1')) { ?>
    contactsNotSortable.push($('#th-consent').index());
    <?php } ?>
    _table_api = initDataTable('.table-contacts', admin_url + 'clients/contacts/' + customer_id,
        contactsNotSortable, contactsNotSortable);
    if (_table_api) {
        <?php if (!empty($native_customers)?!empty($native_contact_consent_enabled):(is_gdpr() && get_option('gdpr_enable_consent_for_contacts') == '1')) { ?>
        _table_api.on('draw', function() {
            var tableData = $('.table-contacts').find('tbody tr');
            $.each(tableData, function() {
                $(this).find('td:eq(1)').addClass('bg-neutral');
            });
        });
        <?php } ?>
    }
    /* Customer profile invoices table */
    initDataTable('.table-invoices-single-client',
        admin_url + 'invoices/table/' + customer_id,
        'undefined',
        'undefined',
        'undefined', [
            [3, 'desc'],
            [0, 'desc']
        ]);

    initDataTable('.table-credit-notes', admin_url + 'credit_notes/table/' + customer_id, ['undefined'], [
        'undefined'
    ], undefined, [0, 'desc']);

    /* Customer profile Estimates table */
    initDataTable('.table-estimates-single-client',
        admin_url + 'estimates/table/' + customer_id,
        'undefined',
        'undefined',
        'undefined', [
            [3, 'desc'],
            [0, 'desc']
        ]);

    /* Customer profile payments table */
    initDataTable('.table-payments-single-client',
        admin_url + 'payments/table/' + customer_id, undefined, undefined,
        'undefined', [0, 'desc']);

    /* Customer profile reminders table */
    initDataTable('.table-reminders', admin_url + 'misc/get_reminders/' + customer_id + '/' + 'customer',
        undefined, undefined, undefined, [1, 'asc']);

    /* Customer profile expenses table */
    initDataTable('.table-expenses-single-client',
        admin_url + 'expenses/table/' + customer_id,
        'undefined',
        'undefined',
        'undefined', [6, 'desc']);

    /* Customer profile proposals table */
    initDataTable('.table-proposals-client-profile',
        admin_url + 'proposals/proposal_relations/' + customer_id + '/customer',
        'undefined',
        'undefined',
        'undefined', [6, 'desc']);

    /* Custome profile projects table */
    initDataTable('.table-projects-single-client', admin_url + 'projects/table/' + customer_id, undefined,
        undefined, 'undefined',
        <?php echo !empty($native_customers)?json_encode([5,'asc']):hooks()->apply_filters('projects_table_default_order', json_encode([5, 'asc'])); ?>);

    var vRules = {};
    if (<?= !empty($native_customers)?json_encode($native_customer_company_required??'1'): 'app.options.company_is_required'; ?> == 1) {
        vRules = {
            company: 'required',
        }
    }

    appValidateForm($('.client-form'), vRules);

    if (typeof(customer_id) == 'undefined') {
        $('#company').on('blur', function() {
            var company = $(this).val();
            var $companyExistsDiv = $('#company_exists_info');

            if (company == '') {
                $companyExistsDiv.addClass('hide');
                return;
            }

            $.post(admin_url + 'clients/check_duplicate_customer_name', {
                    company: company
                })
                .done(function(response) {
                    if (response) {
                        if(typeof response === 'string') response = JSON.parse(response);
                        if (response.exists == true) {
                            $companyExistsDiv.removeClass('hide');
                            $companyExistsDiv.html('<div class="alert alert-info">' + response
                                .message + '</div>');
                        } else {
                            $companyExistsDiv.addClass('hide');
                        }
                    }
                });
        });
    }

    $('.billing-same-as-customer').on('click', function(e) {
        e.preventDefault();
        $('textarea[name="billing_street"]').val($('textarea[name="address"]').val());
        $('input[name="billing_city"]').val($('input[name="city"]').val());
        $('input[name="billing_state"]').val($('input[name="state"]').val());
        $('input[name="billing_zip"]').val($('input[name="zip"]').val());
        $('select[name="billing_country"]').selectpicker('val', $('select[name="country"]')
            .selectpicker('val'));
    });

    $('.customer-copy-billing-address').on('click', function(e) {
        e.preventDefault();
        $('textarea[name="shipping_street"]').val($('textarea[name="billing_street"]').val());
        $('input[name="shipping_city"]').val($('input[name="billing_city"]').val());
        $('input[name="shipping_state"]').val($('input[name="billing_state"]').val());
        $('input[name="shipping_zip"]').val($('input[name="billing_zip"]').val());
        $('select[name="shipping_country"]').selectpicker('val', $('select[name="billing_country"]')
            .selectpicker('val'));
    });

    $('body').on('hidden.bs.modal', '#contact', function() {
        $('#contact_data').empty();
    });

    $('.client-form').on('submit', function() {
        $('select[name="default_currency"]').prop('disabled', false);
    });

});

function delete_contact_profile_image(contact_id) {
    <?php if (!empty($native_customers)) { ?>
    $.post(admin_url+'clients/delete_contact_profile_image/'+contact_id,{
        client_id:$('#contact input[name="native_customer_id"]').val(),
        revision:$('#contact input[name="native_contact_revision"]').val(),
        _native_company_csrf:$('#contact input[name="_native_company_csrf"]').val()
    }).done(function(response){
        if(typeof response==='string')response=JSON.parse(response);
        $('#contact input[name="native_contact_revision"]').val(response.revision);
        $('#contact-profile-image').removeClass('hide');$('#contact-remove-img').addClass('hide');
        $('#contact-img').attr('src',<?= json_encode(base_url('assets/images/user-placeholder.jpg')); ?>);
    }).fail(function(error){alert_float('danger',(error.responseJSON||{}).message||'Não foi possível alterar este contato.');});
    return;
    <?php } ?>
    requestGet('clients/delete_contact_profile_image/' + contact_id).done(function() {
        $('body').find('#contact-profile-image').removeClass('hide');
        $('body').find('#contact-remove-img').addClass('hide');
        $('body').find('#contact-img').attr('src',
            '<?php echo base_url('assets/images/user-placeholder.jpg'); ?>');
    });
}

function customerGoogleDriveSave(pickData) {
    saveCustomerProfileExternalFile(pickData, 'gdrive');
}

function saveCustomerProfileExternalFile(files, externalType) {
    <?php if(!empty($native_customers)){ ?>
    var requests=files.map(function(file){if(!file.nativeRequestKey){var bytes=new Uint8Array(32);window.crypto.getRandomValues(bytes);file.nativeRequestKey=Array.from(bytes).map(function(v){return v.toString(16).padStart(2,'0');}).join('');}return $.post(admin_url+'clients/add_external_attachment',{files:[file],clientid:customer_id,external:externalType,request_key:file.nativeRequestKey,_native_company_csrf:<?= json_encode($company_clients_csrf); ?>});});$.when.apply($,requests).done(function(){window.location.reload();}).fail(function(){alert_float('danger',<?= json_encode(_l('access_denied')); ?>);});return;
    <?php } ?>

    $.post(admin_url + 'clients/add_external_attachment', {
        files: files,
        clientid: customer_id,
        external: externalType
    }).done(function() {
        window.location.reload();
    });
}

function validate_contact_form() {
    appValidateForm('#contact-form', {
        firstname: 'required',
        lastname: 'required',
        password: {
            required: {
                depends: function(element) {

                    var $sentSetPassword = $('input[name="send_set_password_email"]');

                    if ($('#contact input[name="contactid"]').val() == '' && $sentSetPassword.prop(
                            'checked') == false) {
                        return true;
                    }
                }
            }
        },
        email: {
            <?php if (!empty($native_customers) || hooks()->apply_filters('contact_email_required', 'true') === 'true') { ?>
            required: true,
            <?php } ?>
            email: true,
            // Use this hook only if the contacts are not logging into the customers area and you are not using support tickets piping.
            <?php if (!empty($native_customers) || hooks()->apply_filters('contact_email_unique', 'true') === 'true') { ?>
            remote: {
                url: admin_url + <?= json_encode(!empty($native_customers)?'clients/contact_email_exists':'misc/contact_email_exists'); ?>,
                type: 'post',
                data: {
                    email: function() {
                        return $('#contact input[name="email"]').val();
                    },
                    <?php if (!empty($native_customers)) { ?>
                    client_id: function() { return $('#contact input[name="native_customer_id"]').val(); },
                    _native_company_csrf: function() { return $('#contact input[name="_native_company_csrf"]').val(); },
                    <?php } ?>
                    userid: function() {
                        return $('body').find('input[name="contactid"]').val();
                    }
                }
            }
            <?php } ?>
        }
    }, contactFormHandler);
}

function contactFormHandler(form) {
    $('#contact input[name="is_primary"]').prop('disabled', false);

    $("#contact input[type=file]").each(function() {
        if ($(this).val() === "") {
            $(this).prop('disabled', true);
        }
    });

    var formURL = $(form).attr("action");
    var formData = new FormData($(form)[0]);

    $.ajax({
        type: 'POST',
        data: formData,
        mimeType: "multipart/form-data",
        contentType: false,
        cache: false,
        processData: false,
        url: formURL
    }).done(function(response) {
        if(typeof response === 'string') response = JSON.parse(response);
        if (response.success) {
            alert_float('success', response.message);
            if (typeof(response.is_individual) != 'undefined' && response.is_individual) {
                $('.new-contact').addClass('disabled');
                if (!$('.new-contact-wrapper')[0].hasAttribute('data-toggle')) {
                    $('.new-contact-wrapper').attr('data-toggle', 'tooltip');
                }
            }
        }

        if ($.fn.DataTable.isDataTable('.table-contacts')) {
            $('.table-contacts').DataTable().ajax.reload(null, false);
        } else if ($.fn.DataTable.isDataTable('.table-all-contacts')) {
            $('.table-all-contacts').DataTable().ajax.reload(null, false);
        }

        if (response.proposal_warning && response.proposal_warning != false) {
            $('body').find('#contact_proposal_warning').removeClass('hide');
            $('body').find('#contact_update_proposals_emails').attr('data-original-email', response
                .original_email);
            $('#contact').animate({
                scrollTop: 0
            }, 800);
        } else {
            $('#contact').modal('hide');
        }
    }).fail(function(error) {
        alert_float('danger', JSON.parse(error.responseText));
    });
    return false;
}

function contact(client_id, contact_id) {
    if (typeof(contact_id) == 'undefined') {
        contact_id = '';
    }
    requestGet('clients/form_contact/' + client_id + '/' + contact_id).done(function(response) {
        $('#contact_data').html(response);
        $('#contact').modal({
            show: true,
            backdrop: 'static'
        });
        $('body').off('shown.bs.modal', '#contact');
        $('body').on('shown.bs.modal', '#contact', function() {
            if (contact_id == '') {
                $('#contact').find('input[name="firstname"]').focus();
            }
        });
        init_selectpicker();
        init_datepicker();
        custom_fields_hyperlink();
        validate_contact_form();
    }).fail(function(error) {
        var response = JSON.parse(error.responseText);
        alert_float('danger', response.message);
    });
}

<?php if(!empty($native_customers)){ ?>
// Same native modal action, made available without loading the global main.js.
function view_contact_consent(id) {
    requestGet('clients/consents/' + id).done(function(response) {
        $('#consent_data').html(response);
        initDataTableInline($('#consentHistoryTable'));
        $('#consentModal').modal('show');
    });
}
<?php } ?>


function update_all_proposal_emails_linked_to_contact(contact_id) {
    var data = {};
    data.update = true;
    data.original_email = $('body').find('#contact_update_proposals_emails').data('original-email');
    $.post(admin_url + 'clients/update_all_proposal_emails_linked_to_customer/' + contact_id, data).done(function(
        response) {
        if(typeof response === 'string') response = JSON.parse(response);
        if (response.success) {
            alert_float('success', response.message);
        }
        $('#contact').modal('hide');
    });
}

function do_share_file_contacts(edit_contacts, file_id) {
    var contacts_shared_ids = $('select[name="share_contacts_id[]"]');
    if (typeof(edit_contacts) == 'undefined' && typeof(file_id) == 'undefined') {
        var contacts_shared_ids_selected = $('select[name="share_contacts_id[]"]').val();
    } else {
        var _temp = edit_contacts.toString().split(',');
        for (var cshare_id in _temp) {
            contacts_shared_ids.find('option[value="' + _temp[cshare_id] + '"]').attr('selected', true);
        }
        contacts_shared_ids.selectpicker('refresh');
        $('input[name="file_id"]').val(file_id);
        $('#customer_file_share_file_with').modal('show');
        return;
    }
    var file_id = $('input[name="file_id"]').val();
    $.post(admin_url + 'clients/update_file_share_visibility', {
        file_id: file_id,
        <?php if(!empty($native_customers)){ ?>revision:$('#tr_file_'+file_id).attr('data-native-file-revision'),visible:$('#tr_file_'+file_id+' input.customer_file').prop('checked')?'1':'0',_native_company_csrf:<?= json_encode($company_clients_csrf); ?>,<?php } ?>
        share_contacts_id: contacts_shared_ids_selected,
        customer_id: $('input[name="userid"]').val()
    }).done(function() {
        window.location.reload();
    });
}

function save_longitude_and_latitude(clientid) {
    var data = {};
    data.latitude = $('#latitude').val();
    data.longitude = $('#longitude').val();
    <?php if(!empty($native_customers)){ ?>data._native_company_csrf=<?= json_encode($company_clients_csrf); ?>;data.native_coordinates_revision=<?= json_encode($native_map_revision??''); ?>;<?php } ?>
    $.post(admin_url + 'clients/save_longitude_and_latitude/' + clientid, data).done(function(response) {
        if (response == 'success') {
            alert_float('success', "<?php echo e(_l('updated_successfully', _l('client'))); ?>");
        }
        setTimeout(function() {
            window.location.reload();
        }, 1200);
    }).fail(function(error) {
        alert_float('danger', error.responseText);
    });
}

function fetch_lat_long_from_google_cprofile() {
    var data = {};
    data.address = $('#long_lat_wrapper').data('address');
    <?php if(!empty($native_customers)){ ?>data._native_company_csrf=<?= json_encode($company_clients_csrf); ?>;<?php } ?>
    data.city = $('#long_lat_wrapper').data('city');
    data.country = $('#long_lat_wrapper').data('country');
    $('#gmaps-search-icon').removeClass('fa-google').addClass('fa-spinner fa-spin');
    $.post(admin_url + 'misc/fetch_address_info_gmaps', data).done(function(data) {
        data = JSON.parse(data);
        $('#gmaps-search-icon').removeClass('fa-spinner fa-spin').addClass('fa-google');
        if (data.response.status == 'OK') {
            $('input[name="latitude"]').val(data.lat);
            $('input[name="longitude"]').val(data.lng);
        } else {
            if (data.response.status == 'ZERO_RESULTS') {
                alert_float('warning', "<?php echo _l('g_search_address_not_found'); ?>");
            } else {
                alert_float('danger', data.response.status + ' - ' + data.response.error_message);
            }
        }
    });
}
</script>
<?php if(!empty($native_customers)){ ?>
<script>
$(document).on('click','.native-contact-delete',function(event){
    event.preventDefault();var link=$(this);if(!confirm(<?= json_encode(_l('confirm_action_prompt')); ?>))return;
    $.post(link.attr('href'),{revision:link.attr('data-native-revision'),_native_company_csrf:<?= json_encode($company_clients_csrf); ?>})
      .done(function(){['.table-contacts','.table-all-contacts'].forEach(function(selector){if($.fn.DataTable.isDataTable(selector))$(selector).DataTable().ajax.reload(null,false);});})
      .fail(function(){alert_float('danger',<?= json_encode(_l('problem_deleting')); ?>);});
});
$(document).on('change','.native-contact-status',function(){
    var control=$(this), desired=control.prop('checked'), contactId=control.attr('data-native-contact');
    control.prop('disabled',true);
    $.post(admin_url+'clients/change_contact_status/'+contactId+'/'+(desired?'1':'0'),{
        client_id:control.attr('data-native-client'),revision:control.attr('data-native-revision'),
        _native_company_csrf:<?= json_encode($company_clients_csrf); ?>
    }).done(function(result){if(typeof result==='string')result=JSON.parse(result);if(!result.success)control.prop('checked',!desired);})
      .fail(function(){control.prop('checked',!desired);})
      .always(function(){['.table-contacts','.table-all-contacts'].forEach(function(selector){if($.fn.DataTable.isDataTable(selector))$(selector).DataTable().ajax.reload(null,false);});});
});
</script>
<?php } ?>
