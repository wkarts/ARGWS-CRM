$(function() {
"use strict";		   
	$.each(si_pickers, function() {
		$(this).colorpicker({
		format: "hex"
		});

		$(this).colorpicker().on('changeColor', function(e) {
			var color = e.color.toHex();
			var _class = 'si_ct_loaded si_custom_theme_style_' + $(this).find('input').data('id');
			var val = $(this).find('input').val();
			if (val == '') {
				$('.' + _class).remove();
				return false;
			}
			var append_data = '';
			var additional = $(this).data('additional');
			additional = additional.split('+');
			if (additional.length > 0 && additional[0] != '') {
				$.each(additional, function(i, add) {
					add = add.split('|');
					append_data += add[0] + '{' + add[1] + ':' + color + ' !important;}';
				});
			}
			append_data += $(this).data('target') + '{' + $(this).data('css') + ':' + color + ' !important;}';
			if ($('head').find('.' + _class).length > 0) {
				$('head').find('.' + _class).html(append_data);
			} else {
				$("<style />", {
					class: _class,
					type: 'text/css',
					html: append_data
				}).appendTo("head");
			}
		});
	});
	
	$('input[name="default_theme"]').on('change', function() {
		$.post(admin_url + 'si_custom_theme/get_theme', {
			theme:$('input[name="default_theme"]:checked').val(),
		}).done(function(append_data) {
			$('.si_ct_temp_loaded').remove();
			$('.si_ct_loaded').remove();
			$("<style />", {
					class: 'si_ct_temp_loaded',
					type: 'text/css',
					html: append_data
				}).appendTo("head");
		});
	});
	
	$('#copy_theme').on('change', function() {
		$.post(admin_url + 'si_custom_theme/get_copy_theme', {
			theme:$('#copy_theme').val(),
		}).done(function(data) {
			data = JSON.parse(data);
			$('.si_ct_temp_loaded').remove();
			$('.si_ct_loaded').remove();
			si_pickers.find('input').val('');
			si_pickers.find('span.input-group-addon i').css('background-color','');
			$.each(data, function(key,value) {
				$('input[data-id="'+value.id+'"]').val(value.color);
				$('input[data-id="'+value.id+'"]').trigger('change');
			});
		});
	});
	
	$('input[name="new_theme"]').on('change', function() {
		$("#theme_name_wrapper").toggleClass('hide');
	});
	
	$("#si_ct_save_theme").on('click', function() {
		var data = [];
		$.each(si_pickers, function() {
			var color = $(this).find('input').val();
			if (color != '') {
				var _data = {};
				_data.id = $(this).find('input').data('id');
				_data.color = color;
				data.push(_data);
			}
		});
		$.post(admin_url + 'si_custom_theme/save', {
			data: JSON.stringify(data),
			default_theme:$('input[name="default_theme"]:checked').val(),
			default_clients_theme:$('input[name="default_clients_theme"]:checked').val(),
			edit_theme:$('#copy_theme').val(),
			new_theme:$('input[name="new_theme"]:checked').val(),
			theme_name:$('#theme_name').val(),
			admin_area: $('#si_ct_custom_admin_area').val(),
			clients_area: $('#si_ct_custom_clients_area').val(),
			clients_and_admin: $('#si_ct_custom_clients_and_admin_area').val(),
		}).done(function() {
			var tab = $('#si_ct_theme_tabs').find('li.active > a:eq(0)').attr('href');
			tab = tab.substring(1, tab.length)
			window.location = admin_url+'si_custom_theme?tab='+tab;
		});
	});
});