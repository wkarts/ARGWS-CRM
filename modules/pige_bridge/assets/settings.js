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
        function open(index) {
            var record = index == null ? null : data[index];
            if (index != null && !record) return;
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
            if (record && record.mapping) {
                value(mapping, 'actor_id', record.mapping.actor_id); value(mapping, 'currency_id', record.mapping.currency_id);
                value(mapping, 'mapping_enabled', record.mapping.enabled);
            }
            if (record && record.external) {
                Object.keys(record.external.mapping).forEach(function (name) {
                    value(external, name === 'enabled' ? 'external_enabled' : name, name === 'enabled' ? record.external.mapping.enabled && record.external.connection_revision === record.revision : record.external.mapping[name]);
                });
            }
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
            modal.find('input[type="password"]').val('');
            modal.find('form').each(function () { this.reset(); });
        });
    });
}(jQuery));
