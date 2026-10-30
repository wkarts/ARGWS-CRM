
<?php if(isset($contract_addendum)){ ?>
	<!-- init table tasks -->
	<script>
		var contract_id = '<?php echo new_html_entity_decode($contract_addendum->id); ?>';
	</script>
<?php } ?>
<script>
	Dropzone.autoDiscover = false;
	$(function () {
		"use strict";

		init_ajax_project_search_by_customer_id();
		if ($('#contract-attachments-form').length > 0) {
			new Dropzone("#contract-attachments-form",appCreateDropzoneOptions({
				success: function (file) {
					if (this.getUploadingFiles().length === 0 && this.getQueuedFiles().length === 0) {
						var location = window.location.href;
						window.location.href = location.split('?')[0] + '?tab=attachments';
					}
				}
			}));
		}

	// In case user expect the submit btn to save the contract content

		if (typeof (Dropbox) != 'undefined' && $('#dropbox-chooser').length > 0) {
			document.getElementById("dropbox-chooser").appendChild(Dropbox.createChooseButton({
				success: function (files) {
					$.post(admin_url + 'service_management/add_external_attachment', {
						files: files,
						contract_id: contract_id,
						external: 'dropbox'
					}).done(function () {
						var location = window.location.href;
						window.location.href = location.split('?')[0] + '?tab=attachments';
					});
				},
				linkType: "preview",
				extensions: app.options.allowed_files.split(','),
			}));
		}

		appValidateForm($('#contract-form'), {
			contract_id: 'required',
			datestart: 'required',
			subject: 'required'
		});

		appValidateForm($('#renew-contract-form'), {
			new_start_date: 'required'
		});

		var _templates = [];
		$.each(contractsTemplates, function (i, template) {
			_templates.push({
				url: admin_url + 'service_management/get_template?name=' + template,
				title: template
			});
		});

		var editor_settings = {
			selector: 'div.editable',
			inline: true,
			theme: 'inlite',
			relative_urls: false,
			remove_script_host: false,
			inline_styles: true,
			verify_html: false,
			cleanup: false,
			apply_source_formatting: false,
			valid_elements: '+*[*]',
			valid_children: "+body[style], +style[type]",
			file_browser_callback: elFinderBrowser,
			table_default_styles: {
				width: '100%'
			},
			fontsize_formats: '8pt 10pt 12pt 14pt 18pt 24pt 36pt',
			pagebreak_separator: '<p pagebreak="true"></p>',
			plugins: [
				'advlist pagebreak autolink autoresize lists link image charmap hr',
				'searchreplace visualblocks visualchars code',
				'media nonbreaking table contextmenu',
				'paste textcolor colorpicker'
				],
			autoresize_bottom_margin: 50,
			insert_toolbar: 'image media quicktable | bullist numlist | h2 h3 | hr',
			selection_toolbar: 'save_button bold italic underline superscript | forecolor backcolor link | alignleft aligncenter alignright alignjustify | fontselect fontsizeselect h2 h3',
			contextmenu: "image media inserttable | cell row column deletetable | paste pastetext searchreplace | visualblocks pagebreak charmap | code",
			setup: function (editor) {

				editor.addCommand('mceSave', function () {
					save_contract_content(true);
				});

				editor.addShortcut('Meta+S', '', 'mceSave');

				editor.on('MouseLeave blur', function () {
					if (tinymce.activeEditor.isDirty()) {
						save_contract_content();
					}
				});

				editor.on('MouseDown ContextMenu', function () {
					if (!is_mobile() && !$('.left-column').hasClass('hide')) {
						contract_full_view();
					}
				});

				editor.on('blur', function () {
					$.Shortcuts.start();
				});

				editor.on('focus', function () {
					$.Shortcuts.stop();
				});

			}
		}

		if (_templates.length > 0) {
			editor_settings.templates = _templates;
			editor_settings.plugins[3] = 'template ' + editor_settings.plugins[3];
			editor_settings.contextmenu = editor_settings.contextmenu.replace('inserttable', 'inserttable template');
		}

		if(is_mobile()) {

			editor_settings.theme = 'modern';
			editor_settings.mobile    = {};
			editor_settings.mobile.theme = 'mobile';
			editor_settings.mobile.toolbar = _tinymce_mobile_toolbar();

			editor_settings.inline = false;
			window.addEventListener("beforeunload", function (event) {
				if (tinymce.activeEditor.isDirty()) {
					save_contract_content();
				}
			});
		}

		if (typeof init_tinymce_inline_editor !== "undefined") { 
			init_tinymce_inline_editor({
				saveUsing: save_contract_content,
				onSetup: function(editor) {
					editor.on('MouseDown ContextMenu', function() {
						if (!is_mobile() && !$('.left-column').hasClass('hide')) {
							contract_full_view();
						}
					});
				}
			});

		}else{
			if(tinymce.majorVersion + '.' + tinymce.minorVersion == '6.8.3'){
				tinymce.init({
					selector: 'div.editable',
					promotion: false,
					inline: true,
					browser_spellcheck: true,
					branding: false,
					plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
					toolbar: 'undo redo | formatselect | bold italic forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | table | charmap | fullscreen | help',
					insert_toolbar: 'image media quicktable | bullist numlist | h2 h3 | hr',
					selection_toolbar: 'save_button bold italic underline superscript | forecolor backcolor link | alignleft aligncenter alignright alignjustify | fontselect fontsizeselect h2 h3',
					contextmenu: "image media inserttable | cell row column deletetable | paste pastetext searchreplace | visualblocks pagebreak charmap | code",
					setup: function (editor) {

						editor.addCommand('mceSave', function () {
							save_contract_content(true);
						});

						editor.addShortcut('Meta+S', '', 'mceSave');

						editor.on('MouseLeave blur', function () {
							if (tinymce.activeEditor.isDirty()) {
								save_contract_content();
							}
						});

						editor.on('MouseDown ContextMenu', function () {
							if (!is_mobile() && !$('.left-column').hasClass('hide')) {
								contract_full_view();
							}
						});

						editor.on('blur', function () {
							$.Shortcuts.start();
						});

						editor.on('focus', function () {
							$.Shortcuts.stop();
						});

					}
				});

				$('.tox-promotion').css('display', 'none');


			}else{
				tinymce.init(editor_settings);
			}
		}


	});

function save_contract_content(manual) {
	"use strict";

	var editor = tinyMCE.activeEditor;
	var data = {};
	data.contract_id = contract_id;
	data.content = editor.getContent();
	$.post(admin_url + 'service_management/save_contract_addendum_data', data).done(function (response) {
		response = JSON.parse(response);
		if (typeof (manual) != 'undefined') {
		  // Show some message to the user if saved via CTRL + S
			alert_float('success', response.message);
		}
	   // Invokes to set dirty to false
		editor.save();
	}).fail(function (error) {
		var response = JSON.parse(error.responseText);
		alert_float('danger', response.message);
	});
}

function delete_contract_attachment(wrapper, id) {
	"use strict";

	if (confirm_delete()) {
		$.get(admin_url + 'service_management/delete_contract_addendum_attachment/' + id, function (response) {
			if (response.success == true) {
				$(wrapper).parents('.contract-attachment-wrapper').remove();

				var totalAttachmentsIndicator = $('.attachments-indicator');
				var totalAttachments = totalAttachmentsIndicator.text().trim();
				if(totalAttachments == 1) {
					totalAttachmentsIndicator.remove();
				} else {
					totalAttachmentsIndicator.text(totalAttachments-1);
				}
			} else {
				alert_float('danger', response.message);
			}
		}, 'json');
	}
	return false;
}

function insert_merge_field(field) {
	"use strict";

	var key = $(field).text();
	tinymce.activeEditor.execCommand('mceInsertContent', false, key);
}

function contract_full_view() {
	"use strict";

	$('.left-column').toggleClass('hide');
	$('.right-column').toggleClass('col-md-7');
	$('.right-column').toggleClass('col-md-12');
	$(window).trigger('resize');
}

function add_contract_comment() {
	"use strict";

	var comment = $('#comment').val();
	if (comment == '') {
		return;
	}
	var data = {};
	data.content = comment;
	data.contract_id = contract_id;
	$('body').append('<div class="dt-loader"></div>');
	$.post(admin_url + 'service_management/add_comment', data).done(function (response) {
		response = JSON.parse(response);
		$('body').find('.dt-loader').remove();
		if (response.success == true) {
			$('#comment').val('');
			get_contract_comments();
		}
	});
}

function get_contract_comments() {
	"use strict";

	if (typeof (contract_id) == 'undefined') {
		return;
	}
	requestGet('service_management/get_comments/' + contract_id).done(function (response) {
		$('#contract-comments').html(response);
		var totalComments = $('[data-commentid]').length;
		var commentsIndicator = $('.comments-indicator');
		if(totalComments == 0) {
			commentsIndicator.addClass('hide');
		} else {
			commentsIndicator.removeClass('hide');
			commentsIndicator.text(totalComments);
		}
	});
}

function remove_contract_comment(commentid) {
	"use strict";

	if (confirm_delete()) {
		requestGetJSON('service_management/remove_comment/' + commentid).done(function (response) {
			if (response.success == true) {

				var totalComments = $('[data-commentid]').length;

				$('[data-commentid="' + commentid + '"]').remove();

				var commentsIndicator = $('.comments-indicator');
				if(totalComments-1 == 0) {
					commentsIndicator.addClass('hide');
				} else {
					commentsIndicator.removeClass('hide');
					commentsIndicator.text(totalComments-1);
				}
			}
		});
	}
}

function edit_contract_comment(id) {
	"use strict";

	var content = $('body').find('[data-contract-comment-edit-textarea="' + id + '"] textarea').val();
	if (content != '') {
		$.post(admin_url + 'service_management/edit_comment/' + id, {
			content: content
		}).done(function (response) {
			response = JSON.parse(response);
			if (response.success == true) {
				alert_float('success', response.message);
				$('body').find('[data-contract-comment="' + id + '"]').html(nl2br(content));
			}
		});
		toggle_contract_comment_edit(id);
	}
}

function toggle_contract_comment_edit(id) {
	"use strict";

	$('body').find('[data-contract-comment="' + id + '"]').toggleClass('hide');
	$('body').find('[data-contract-comment-edit-textarea="' + id + '"]').toggleClass('hide');
}

function contractGoogleDriveSave(pickData) {
	"use strict";
	
	var data = {};
	data.contract_id = contract_id;
	data.external = 'gdrive';
	data.files = pickData;
	$.post(admin_url + 'service_management/add_external_attachment', data).done(function () {
		var location = window.location.href;
		window.location.href = location.split('?')[0] + '?tab=attachments';
	});
}

</script>