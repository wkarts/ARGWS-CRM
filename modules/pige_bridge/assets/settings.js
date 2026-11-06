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
        var current = null, discoveryEpoch = 0, discoveryRequest = null;
        function clearDiscovery() {
            discoveryEpoch++;
            if (discoveryRequest) discoveryRequest.abort();
            discoveryRequest = null;
            $('#pige-discovery-url, #pige-discovery-credential').val('');
            $('#pige-discovery-school').empty().append($('<option>', {value:'',text:'Consulte as instituições'}));
            $('#pige-discovery-status').text('');
            $('#pige-discovery button').prop('disabled', false);
        }
        function discover(action) {
            if (current || discoveryRequest) return;
            if (window.location.protocol !== 'https:') {$('#pige-discovery-status').text('Abra esta configuração por HTTPS antes de informar a credencial administrativa.');return;}
            var issuer = String($('#pige-issuer').val() || ''), origin = $('#pige-discovery-url').val(), epoch = ++discoveryEpoch;
            var credential = $('#pige-discovery-credential').val(), school = $('#pige-discovery-school').val();
            if (!origin || !credential || (action === 'configuration' && !school)) {
                $('#pige-discovery-status').text('Informe URL, credencial administrativa e, para preencher, selecione uma instituição consultada.'); return;
            }
            var payload = {};
            $('#pige-connection-form input[type=hidden]').each(function () {if (this.name && this.name !== 'revision') payload[this.name] = this.value;});
            Object.assign(payload, {discovery_action:action,issuer_id:issuer,pige_url:origin,admin_credential:credential,school_id:school || ''});
            $('#pige-discovery button').prop('disabled', true);
            $('#pige-discovery-status').text('Consultando a API do PIGE…');
            discoveryRequest = $.ajax({url:$('#pige-discovery').attr('data-url'),method:'POST',data:payload,dataType:'json',timeout:20000});
            discoveryRequest.done(function (reply) {
                if (epoch !== discoveryEpoch || current || String($('#pige-issuer').val()) !== issuer || $('#pige-discovery-url').val() !== origin) return;
                if (!reply || reply.success !== true) {$('#pige-discovery-status').text('Consulta não confirmada. Nenhum dado foi preenchido.');return;}
                if (action === 'schools' && Array.isArray(reply.schools)) {
                    var choices = $('#pige-discovery-school').empty().append($('<option>', {value:'',text:'Selecione a instituição'}));
                    reply.schools.forEach(function (row) {choices.append($('<option>', {value:row.id,text:row.name}));});
                    $('#pige-discovery-status').text('Instituições consultadas. Selecione uma para ler a configuração da ponte.');
                } else if (action === 'configuration' && reply.configuration && reply.configuration.issuer_id === issuer) {
                    var connection = $('#pige-connection-form'), config = reply.configuration;
                    ['school_id','account_id','environment'].forEach(function (name) {value(connection, name, config[name]);});
                    $('#pige-discovery-credential').val('');
                    $('#pige-discovery-status').text('Escopo preenchido pela revisão ' + config.remote_revision + ' do PIGE. Revise identificador local e HMAC; nenhuma conexão ou autorização financeira foi habilitada.');
                }
            }).fail(function () {
                if (epoch !== discoveryEpoch) return;
                $('#pige-discovery-credential').val('');
                $('#pige-discovery-status').text('Consulta não confirmada. Confira URL, credencial, permissões e configuração da ponte para esta empresa.');
            }).always(function () {
                if (epoch === discoveryEpoch) {discoveryRequest = null;$('#pige-discovery button').prop('disabled', false);}
            });
        }
        $('#pige-discovery-schools').on('click', function () {discover('schools');});
        $('#pige-discovery-read').on('click', function () {discover('configuration');});
        $('#pige-discovery-url, #pige-discovery-credential, #pige-issuer').on('change', function () {
            discoveryEpoch++;
            if (discoveryRequest) discoveryRequest.abort();
            discoveryRequest = null;
            $('#pige-discovery button').prop('disabled', false);
            $('#pige-discovery-school').empty().append($('<option>', {value:'',text:'Consulte as instituições'}));
            $('#pige-discovery-status').text('');
        });
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
            clearDiscovery();
            current = record;
            $('#pige-discovery').toggle(!record);
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
            value($('#pige-prepare-form'), 'connection_key', record ? record.connection_key : '');
            value($('#pige-prepare-form'), 'revision', record ? record.revision : '');
            $('#pige-prepare-form button[type=submit]').prop('disabled', !record || record.enabled);
            value($('#pige-diagnostic-form'), 'connection_key', record ? record.connection_key : '');
            value($('#pige-diagnostic-form'), 'revision', record ? record.revision : '');
            $('#pige-diagnostic-form button[type=submit]').prop('disabled', !record || !record.enabled);
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
            clearDiscovery();
            current = null;
            modal.find('select[name=actor_id], select[name=currency_id], select[name=account_row], select[name=profile_row]').empty();
            modal.find('input[type="password"]').val('');
            modal.find('form').each(function () { this.reset(); });
        });
    });
}(jQuery));
