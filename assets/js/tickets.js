$(function () {
    var nativeTicketAdd = $("#new_ticket_form").attr("data-native-ticket") === "1";
    var ticketAiEnabled = !nativeTicketAdd && !!app.options.is_ai_provider_enabled;
    init_editor("#message", {
        quickbars_selection_toolbar: `bold link ${ticketAiEnabled ? 'ai' : ''}`,
        append_plugins: 'quickbars',
        setup: function (editor) {
            if(ticketAiEnabled) {
                configure_ai_editor(editor);
            }
        }
    });

    $("#tickets_bulk_actions").on("show.bs.modal", function () {
        $("#primary_ticket_id")
            .find("option")
            .remove()
            .end()
            .append("<option></option>");
        $("#merge_tickets").prop("checked", false);
        $("#merge_tickets").trigger("change");
    });

    $("#merge_tickets").on("change", function () {
        var $mergeCheckbox = $(this);
        var merge_tickets = $mergeCheckbox.prop("checked");
        var $bulkChange = $("#bulk_change");
        var $ticketsSelect = $("#primary_ticket_id");
        var rows = $(".table-tickets").find("tbody tr");

        $ticketsSelect.find("option").remove().end().append("<option></option>");
        if (merge_tickets) {
            $("#bulk_change").addClass("hide");
            $("#merge_tickets_wrapper").removeClass("hide");
            $(".mass_delete_checkbox").addClass("hide");
            $("#mass_delete").prop("checked", false);
            $bulkChange.addClass("hide");

            $.each(rows, function () {
                var checkbox = $($(this).find("td").eq(0)).find("input");
                if (checkbox.prop("checked") == true) {
                    $ticketsSelect.append(
                        '<option value="' +
                        checkbox.val() +
                        '" data-status="' +
                        checkbox.data("status") +
                        '">' +
                        checkbox.data("name") +
                        "</option"
                    );
                }
            });
            $ticketsSelect.selectpicker("refresh");
        } else {
            $("#merge_tickets_wrapper").addClass("hide");
            $bulkChange.removeClass("hide");
            $(".mass_delete_checkbox").removeClass("hide");
        }
    });

    $("#primary_ticket_id").on("change", function () {
        var status = $(this).find("option:selected").data("status");
        $("#primary_ticket_status").selectpicker("val", status);
    });

    // Add predefined reply click
    $("#insert_predefined_reply").on("change", function (e) {
        e.preventDefault();
        var selectpicker = $(this);
        var id = selectpicker.val();
        if (id != "") {
            requestGetJSON("tickets/get_predefined_reply_ajax/" + id).done(function (
                response
            ) {
                tinymce.activeEditor.execCommand(
                    "mceInsertContent",
                    false,
                    response.message
                );
                selectpicker.selectpicker("val", "");
            });
        }
    });

    $("#ticket_no_contact").on("click", function (e) {
        e.preventDefault();
        validate_new_ticket_form();
        $("#name, #email").prop("disabled", false);
        $("#name").val("").rules("add", {required: true});
        $("#email").val("").rules("add", {required: true});

        $(this).addClass("hide");

        $("#contactid").removeAttr("required");
        $("#contactid").selectpicker("val", "");
        $('input[name="userid"]').val("");

        $("#ticket_to_contact").removeClass("hide");
        $("#ticket_contact_w").addClass("hide");
    });

    $("#ticket_to_contact").on("click", function (e) {
        e.preventDefault();
        $("#name, #email").prop("disabled", true);
        $("#ticket_no_contact").removeClass("hide");
        $("#contactid").attr("required", true);
        $("#name").rules("remove", "required");
        $("#email").rules("remove", "required");
        $("#ticket_no_contact, #ticket_contact_w").removeClass("hide");
        $(this).addClass("hide");
    });

    $(".block-sender").on("click", function () {
        var sender = $(this).data("sender");
        if (sender == "") {
            alert("No Sender Found");
            return false;
        }
        if (document.body.classList.contains('native-company-admin')) {
            ticket_block_sender_request().done(function (response) {
                if (!response || response.success !== true) throw new Error('native_ticket_receipt_invalid');
                window.location.reload();
            });
            return false;
        }
        $.post(admin_url + "tickets/block_sender", {
            sender: sender,
        }).done(function () {
            window.location.reload();
        });
    });

    // Admin ticket note add
    $(".add_note_ticket").on("click", function (e) {
        e.preventDefault();
        var note_description = $('textarea[name="note_description"]').val();
        var ticketid = $('input[name="ticketid"]').val();
        if (note_description == "") {
            return;
        }
        $(e.target).addClass("disabled");
        $.post(admin_url + "misc/add_note/" + ticketid + "/ticket", {
            description: note_description,
        }).done(function () {
            window.location.reload();
        });
    });

    // Update ticket settings from settings tab
    $(".save_changes_settings_single_ticket").on("click", function (e) {
        e.preventDefault();
        var data = {};

        var $settingsArea = $("#settings");
        var errors = false;

        if ($settingsArea.find('input[name="subject"]').val() == "") {
            errors = true;
            $settingsArea
                .find('input[name="subject"]')
                .parents(".form-group")
                .addClass("has-error");
        } else {
            $settingsArea
                .find('input[name="subject"]')
                .parents(".form-group")
                .removeClass("has-error");
        }

        var selectRequired = ["department", "priority"];

        if ($("#contactid").data("no-contact") != true) {
            selectRequired.push("contactid");
        }

        for (var i = 0; i < selectRequired.length; i++) {
            var $select = $settingsArea.find(
                'select[name="' + selectRequired[i] + '"]'
            );
            if ($select.selectpicker("val") == "") {
                errors = true;
                $select.parents(".form-group").addClass("has-error");
            } else {
                $select.parents(".form-group").removeClass("has-error");
            }
        }

        var cf_required = $settingsArea.find('[data-custom-field-required="1"]');

        $.each(cf_required, function () {
            var cf_field = $(this);
            var parent = cf_field.parents(".form-group");
            if (cf_field.is(":checkbox")) {
                var checked = parent.find('input[type="checkbox"]:checked');
                if (checked.length == 0) {
                    errors = true;
                    parent.addClass("has-error");
                } else {
                    parent.removeClass("has-error");
                }
            } else if (cf_field.is("input") || cf_field.is("textarea")) {
                if (cf_field.val() === "") {
                    errors = true;
                    parent.addClass("has-error");
                } else {
                    parent.removeClass("has-error");
                }
            } else if (cf_field.is("select")) {
                if (cf_field.selectpicker("val") == "") {
                    errors = true;
                    parent.addClass("has-error");
                } else {
                    parent.removeClass("has-error");
                }
            }
        });

        if (errors == true) {
            return;
        }

        data = $("#settings *").serialize();
        data += "&ticketid=" + $('input[name="ticketid"]').val();
        if (typeof csrfData !== "undefined") {
            data += "&" + csrfData["token_name"] + "=" + csrfData["hash"];
        }
        $.post(admin_url + "tickets/update_single_ticket_settings", data).done(
            function (response) {
                response = JSON.parse(response);
                if (response.success == true) {
                    if (typeof response.department_reassigned !== "undefined") {
                        window.location.href = admin_url + "tickets/";
                    } else {
                        window.location.reload();
                    }
                } else if (typeof response.message !== "undefined") {
                    alert_float("warning", response.message);
                }
            }
        );
    });

    $("#new_ticket_form").submit(function () {
        $("#project_id").prop("disabled", false);
        return true;
    });

    // Change ticket status without replying (new)
    $('.change-ticket-status').on("click", function () {
        var status = $(this).data('status');
        var ticketid = $('input[name="ticketid"]').val();
        ticket_status_request(ticketid, status).done(function (response) {
            alert_float(response.alert, response.message);
            setTimeout(function () {
                window.location.reload()
            }, 1500)
        });
    });

    // Change ticket status without replying (old)
    $('select[name="status_top"]').on("change", function () {
        var status = $(this).val();
        var ticketid = $('input[name="ticketid"]').val();
        ticket_status_request(ticketid, status).done(function (response) {
            alert_float(response.alert, response.message);
        });
    });

    // Select ticket user id
    $('body.ticket select[name="contactid"]').on("change", function () {
        var contactid = $(this).val();

        var projectAjax = $('select[name="project_id"]');
        var projectAutoSelected = projectAjax.attr("data-auto-project");
        var projectsWrapper = $(".projects-wrapper");
        if (!projectAjax.attr("disabled")) {
            var clonedProjectsAjaxSearchSelect;
            if (!projectAutoSelected) {
                clonedProjectsAjaxSearchSelect = projectAjax.html("").clone();
            } else {
                clonedProjectsAjaxSearchSelect = projectAjax.clone();
                clonedProjectsAjaxSearchSelect.prop("disabled", true);
            }
            projectAjax.selectpicker("destroy").remove();
            projectAjax = clonedProjectsAjaxSearchSelect;
            $("#project_ajax_search_wrapper").append(clonedProjectsAjaxSearchSelect);
            init_ajax_search("project", projectAjax, {
                customer_id: function () {
                    return $('input[name="userid"]').val();
                },
            });
        }
        if (contactid != "") {
            $.post(admin_url + "tickets/ticket_change_data/", {
                contact_id: contactid,
                _company_context: $('#new_ticket_form input[name="_company_context"]').val(),
                _native_company_csrf: $('#new_ticket_form input[name="_native_company_csrf"]').val(),
            }).done(function (response) {
                if (typeof response === "string") response = JSON.parse(response);
                // A late response must not overwrite a newer contact selection.
                if ($('select[name="contactid"]').val() !== contactid) return;
                if (response.contact_data) {
                    $('input[name="name"]').val(
                        response.contact_data.firstname +
                        " " +
                        response.contact_data.lastname
                    );
                    $('input[name="email"]').val(response.contact_data.email);
                    $('input[name="userid"]').val(response.contact_data.userid);
                    if (response.contact_data.ticket_emails == "0") {
                        show_ticket_no_contact_email_warning(
                            response.contact_data.userid,
                            response.contact_data.id
                        );
                    } else {
                        clear_ticket_no_contact_email_warning();
                    }
                }
                if (!projectAutoSelected) {
                    if (response.customer_has_projects) {
                        projectsWrapper.removeClass("hide");
                    } else {
                        projectsWrapper.addClass("hide");
                    }
                } else {
                    projectsWrapper.removeClass("hide");
                }
            });
        } else {
            $('input[name="name"]').val("");
            $('input[name="email"]').val("");
            $('input[name="contactid"]').val("");
            if (!projectAutoSelected) {
                projectsWrapper.addClass("hide");
            } else {
                projectsWrapper.removeClass("hide");
            }
            clear_ticket_no_contact_email_warning();
        }
    });
    if (!nativeTicketAdd) init_ai_features();
});

// Insert ticket knowledge base link modal
function insert_ticket_knowledgebase_link(e) {
    var id = $(e).val();
    if (id == "") {
        return;
    }
    function insertLink(url, subject) {
        var link = document.createElement("a");
        link.href = url;
        link.textContent = subject;
        tinymce.activeEditor.execCommand("mceInsertContent", false, link.outerHTML);
        $(e).selectpicker("val", "");
    }
    if ($("#new_ticket_form").attr("data-native-ticket") === "1") {
        var option = $(e).find("option:selected");
        var url = option.attr("data-public-url");
        if (url && /^https?:\/\//i.test(url)) insertLink(url, option.text());
        return;
    }
    requestGetJSON("knowledge_base/get_article_by_id_ajax/" + id).done(function (response) {
        insertLink(site_url + "knowledge_base/" + encodeURIComponent(response.slug), response.subject);
    });
}

function tickets_bulk_action(event) {
    if (confirm_delete()) {
        var mass_delete = $("#mass_delete").prop("checked");
        var merge_tickets = $("#merge_tickets").prop("checked");
        var ids = [];
        var data = {};

        if (typeof merge_tickets != "undefined" && merge_tickets == true) {
            data.merge_tickets = true;
            data.primary_ticket = $("#primary_ticket_id").val();
            data.primary_ticket_status = $("#primary_ticket_status").val();

            if (data.primary_ticket == "") {
                console.log("empty");

                return;
            }
        } else if (mass_delete == false || typeof mass_delete == "undefined") {
            data.status = $("#move_to_status_tickets_bulk").val();
            data.department = $("#move_to_department_tickets_bulk").val();
            data.priority = $("#move_to_priority_tickets_bulk").val();
            data.service = $("#move_to_service_tickets_bulk").val();
            data.tags = $("#tags_bulk").tagit("assignedTags");
            if (
                data.status == "" &&
                data.department == "" &&
                data.priority == "" &&
                data.service == "" &&
                data.tags == ""
            ) {
                return;
            }
        } else {
            data.mass_delete = true;
        }
        var rows = $(".table-tickets").find("tbody tr");
        $.each(rows, function () {
            var checkbox = $($(this).find("td").eq(0)).find("input");
            if (checkbox.prop("checked") == true) {
                ids.push(checkbox.val());
            }
        });
        data.ids = ids;
        $(event).addClass("disabled");
        setTimeout(function () {
            $.post(admin_url + "tickets/bulk_action", data).done(function () {
                window.location.reload();
            });
        }, 50);
    }
}

function show_ticket_no_contact_email_warning(userid, contactid) {
    if ($("#contact_email_notifications_warning").length == 0) {
        $("#new_ticket_form, #single-ticket-form").prepend(
            '<div class="alert alert-warning" id="contact_email_notifications_warning">Email notifications for tickets is disabled for this contact, if you want the contact to receive ticket emails you must enable by clicking <a href="' +
            admin_url +
            "clients/client/" +
            userid +
            "?contactid=" +
            contactid +
            '" target="_blank" class="alert-link">here</a>.</div>'
        );
    }
}

function clear_ticket_no_contact_email_warning() {
    $("#contact_email_notifications_warning").remove();
}

function validate_new_ticket_form() {
    $("#new_ticket_form").appFormValidator();

    setTimeout(function () {
        $.each(
            $("#new_ticket_form").find('[data-custom-field-required="1"]'),
            function () {
                $(this).rules("add", "required");
            }
        );
    }, 10);
}

function init_ai_features() {
    const threadLength = $('.ticket-thread').text().length;
    const suggestBtn = $('#btn-ai-suggest');
    suggestBtn.on('click', function () {
        const ticketId = $('input[name="ticketid"]').val();
        if (threadLength > 1500) {
            if (!confirm(app.lang.warn_ticket_thread_too_long)) {
                setTimeout(() => suggestBtn.button('reset'), 10);
                return;
            }
        }

        $.post(admin_url + 'ai_tickets/suggest_reply/' + ticketId)
            .done(function (response) {
                const result = JSON.parse(response);
                if (result.success) {
                    tinymce.activeEditor.execCommand(
                        "mceSetContent",
                        false,
                        result.message
                    );
                    suggestBtn
                        .button('reset')
                        .button('rephrase')
                } else {
                    suggestBtn.button('reset');
                }
            })
            .catch(function (error) {
                const result = JSON.parse(error.responseText);
                alert_float('danger', result.error ? result.error : app.lang.something_went_wrong);
                summarizeBtn.button('reset');
            });
    });

    const summarizeBtn = $('.btn-ai-summarize');
    summarizeBtn.on('click', function () {
        const ticketId = $('input[name="ticketid"]').val();
        if (threadLength > 1500) {
            if (!confirm(app.lang.warn_ticket_thread_too_long)) {
                setTimeout(() => summarizeBtn.button('reset'), 10);
                return;
            }
        }
        $.post(admin_url + 'ai_tickets/summarize_ticket/' + ticketId)
            .done(function (response) {
                const result = JSON.parse(response);
                if (result.success) {
                    $('#ai-summary-modal .modal-body').html(result.message);
                    $('#ai-summary-modal').modal('show');
                    summarizeBtn.button('reset');
                } else {
                    summarizeBtn.button('reset');
                }
            })
            .catch(function (error) {
                const result = JSON.parse(error.responseText);
                alert_float('danger', result.error ? result.error : app.lang.something_went_wrong);
                summarizeBtn.button('reset');
            });
    });
}


// Existing status controls share one request path; named mutations never fall back to GET.
function ticket_status_request(ticketid, status) {
    if (!document.body.classList.contains('native-company-admin')) {
        return requestGetJSON("tickets/change_status_ajax/" + ticketid + "/" + status);
    }
    if (!window.NativeCompanyTransport || !/^[1-9][0-9]*$/.test(String(ticketid)) || !/^[1-9][0-9]*$/.test(String(status))) throw new Error('native_ticket_status_invalid');
    var form = $('#single-ticket-form');
    var revision = form.find('input[name="revision"]').val();
    var nonce = form.find('input[name="request_nonce"]').val();
    if (!/^[a-f0-9]{64}$/.test(revision || '') || !/^[a-f0-9]{64}$/.test(nonce || '')) throw new Error('native_ticket_revision_required');
    return window.NativeCompanyTransport.request("tickets/change_status_ajax/" + ticketid + "/" + status, 'POST', { revision: revision, request_nonce: nonce }, { dataType: 'json' }).done(function (response) {
        if (!response || response.success !== true || String(response.ticketid) !== String(ticketid) || !/^[a-f0-9]{64}$/.test(response.revision || '')) throw new Error('native_ticket_receipt_invalid');
        // Reload is already the native click handler. The older select control needs a new command identity after a confirmed response.
        var random = new Uint8Array(32); window.crypto.getRandomValues(random);
        form.find('input[name="revision"]').val(response.revision);
        form.find('input[name="request_nonce"]').val(Array.from(random, function (b) { return b.toString(16).padStart(2, '0'); }).join(''));
    });
}

function ticket_block_sender_request() {
    if (!document.body.classList.contains('native-company-admin') || !window.NativeCompanyTransport) throw new Error('native_ticket_context_required');
    var form = $('#single-ticket-form');
    var ticket = form.find('input[name="ticketid"]').val();
    var revision = form.find('input[name="revision"]').val();
    var nonce = form.find('input[name="request_nonce"]').val();
    if (!/^[1-9][0-9]*$/.test(ticket || '') || !/^[a-f0-9]{64}$/.test(revision || '') || !/^[a-f0-9]{64}$/.test(nonce || '')) throw new Error('native_ticket_revision_required');
    return window.NativeCompanyTransport.request('tickets/block_sender', 'POST', {ticketid: ticket, revision: revision, request_nonce: nonce}, {dataType: 'json'});
}

// Same native typing lifecycle; named writes carry content CAS and authenticated context.
function ticket_replying_request(ticket, actor) {
    var path = 'tickets/update_staff_replying/' + ticket + (actor === '' ? '' : '/' + actor);
    if (!document.body.classList.contains('native-company-admin')) return $.post(admin_url + path);
    if (!window.NativeCompanyTransport || !/^[1-9][0-9]*$/.test(ticket) || (actor !== '' && !/^[1-9][0-9]*$/.test(actor))) throw new Error('native_ticket_context_required');
    var revision = $('#single-ticket-form').find('input[name="revision"]').val();
    if (!/^[a-f0-9]{64}$/.test(revision || '')) throw new Error('native_ticket_revision_required');
    return window.NativeCompanyTransport.request(path, 'POST', {revision: revision}, {dataType: 'json'}).done(function (result) {
        if (!result || result.success !== true || result.revision !== revision) throw new Error('native_ticket_lease_unconfirmed');
    });
}
