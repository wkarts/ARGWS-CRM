(function ($) {
    'use strict';
    $(function () {
        var data = JSON.parse(document.getElementById('pige-settings-data').textContent);
        var modal = $('#pige-editor');
        function value(form, name, v) {
            var field = form.find('[name="' + name + '"]');
            if (field.is(':checkbox')) field.prop('checked', v === true);
            else field.val(v == null ? '' : String(v));
        }
        var current = null;
        function options(selector, entries, selected) {
            var field = $(selector).empty().append($('<option>', {value:'',text:'Selecione'}));
            (entries || []).forEach(function (entry) { field.append($('<option>', {value:String(entry.id),text:entry.label})); });
            if (selected != null && !field.find('option').toArray().some(function (o) { return o.value === String(selected); })) {
                field.append($('<option>', {value:String(selected),text:'Indisponível nesta configuração (#' + selected + ')'}));
            }
            field.val(selected == null ? '' : String(selected));
        }
        function profiles(selected) {
            var account = $('#pige-ext-account_row').val(), bb = $('#pige-ext-provider').val() === 'banco_brasil';
            options('#pige-ext-profile', ((current && current.choices && current.choices.profiles) || []).filter(function (p) {return bb && String(p.account_id) === account;}), selected);
            $('#pige-ext-profile').prop('required', bb).prop('disabled', !bb);
        }
        function accounts(selected, profile) {
            var provider = $('#pige-ext-provider').val();
            options('#pige-ext-account_row', ((current && current.choices && current.choices.accounts) || []).filter(function (a) {return a.provider === provider;}), selected);
            profiles(profile);
        }
        $('#pige-ext-provider').on('change', function () {accounts(null, null);});
        $('#pige-ext-account_row').on('change', function () {profiles(null);});
        function open(index) {
            var record = index == null ? null : data[index];
            if (index != null && !record) return;
            current = record;
            $('#pige-choices-warning').toggle(!!record && !record.choices);
            modal.find('form').each(function () { this.reset(); });
            var connection = $('#pige-connection-form'), mapping = $('#pige-mapping-form'), external = $('#pige-external-form');
            modal.find('input[type="password"]').val('');
            value(connection, 'revision', record ? record.revision : 0);
            value(connection, 'connection_key', record ? record.connection_key : '');
            connection.find('[name="connection_key"], [name="school_id"], [name="account_id"]').prop('readOnly', !!record);
            // Disabled selects are represented by hidden successful controls on existing records.
            connection.find('.pige-fixed-scope').remove();
            connection.find('[name="issuer_id"], [name="environment"]').prop('disabled', false);
            if (record) {
                Object.keys(record.scope).forEach(function (name) { value(connection, name, record.scope[name]); });
                ['issuer_id', 'environment'].forEach(function (name) {
                    connection.find('[name="' + name + '"]').prop('disabled', true);
                    $('<input>', {type:'hidden', name:name, 'class':'pige-fixed-scope'}).val(record.scope[name]).appendTo(connection);
                });
                value(connection, 'enabled', record.enabled);
            }
            connection.find('[name="secret"]').prop('required', !record);
            connection.find('[name="confirm_rotation"]').closest('.checkbox').toggle(!!record);
            $('.pige-existing-tab').toggle(!!record);
            [mapping, external].forEach(function (form) { value(form, 'connection_key', record ? record.connection_key : ''); });
            value(mapping, 'revision', record ? record.revision : '');
            value(external, 'connection_revision', record ? record.revision : '');
            value(external, 'external_revision', record && record.external ? record.external.revision : 0);
            options('#pige-map-actor_id', record && record.choices ? record.choices.actors : [], record && record.mapping ? record.mapping.actor_id : null);
            options('#pige-map-currency_id', record && record.choices ? record.choices.currencies : [], record && record.mapping ? record.mapping.currency_id : null);
            options('#pige-ext-actor_id', record && record.choices ? record.choices.actors : [], record && record.external ? record.external.mapping.actor_id : null);
            if (record && record.mapping) {
                value(mapping, 'actor_id', record.mapping.actor_id); value(mapping, 'currency_id', record.mapping.currency_id);
                value(mapping, 'mapping_enabled', record.mapping.enabled);
            }
            if (record && record.external) {
                Object.keys(record.external.mapping).forEach(function (name) {
                    value(external, name === 'enabled' ? 'external_enabled' : name, name === 'enabled' ? record.external.mapping.enabled && record.external.connection_revision === record.revision : record.external.mapping[name]);
                });
            }
            accounts(record && record.external ? record.external.mapping.account_row : null, record && record.external ? record.external.mapping.profile_row : null);
            $('#pige-editor-title').text(record ? 'Editar integração: ' + record.connection_key : 'Nova integração PIGE');
            modal.find('a[href="#pige-tab-connection"]').tab('show');
            modal.modal('show');
        }
        $('#pige-new').on('click', function () { open(null); });
        $('#pige-connections').on('click', '.pige-edit', function () { open(Number(this.getAttribute('data-index'))); });
        $('#pige-connections').on('submit', '.pige-disable', function (event) {
            if (!window.confirm('Desativar esta integração e invalidar sua autorização de fatura local? O histórico será preservado.')) event.preventDefault();
        });
        if (typeof window.initDataTableInline === 'function') window.initDataTableInline($('#pige-connections'));
        modal.on('hidden.bs.modal', function () {
            current = null;
            modal.find('select[name=actor_id], select[name=currency_id], select[name=account_row], select[name=profile_row]').empty();
            modal.find('input[type="password"]').val('');
            modal.find('form').each(function () { this.reset(); });
        });
    });
}(jQuery));
