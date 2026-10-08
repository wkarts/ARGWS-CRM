window.pageManager = {
	tree: ".tree",
	pages: {},
	init: function () {
		this.tree = $(".tree");
		$(this.tree).on("click", ".delete", function (e) {
			let element = $(e.target).closest("li");
			pageManager.deletePage(element, e);
			e.preventDefault();
			return false;
		});

		$(this.tree).on("click", ".rename", function (e) {
			let element = $(e.target).closest("li");
			pageManager.renamePage(element, e, false);
			e.preventDefault();
			return false;
		});

		$(this.tree).on("click", ".duplicate", function (e) {
			let element = $(e.target).closest("li");
			pageManager.renamePage(element, e, true);
			e.preventDefault();
			return false;
		});

		$(this.tree).on("click", "label>.title,.build", function (e) {
			let element = $(e.target).closest("li");
			pageManager.buildPage(element, e);
			e.preventDefault();
			return false;
		});

		$("#new-file-btn").on("click", function () {
			pageManager.newPage();
		});

		//Upload
		$("#upload-template-form")
			.off("submit")
			.on("submit", function (e) {
				e.preventDefault(); // Prevent the default form submission
				pageManager.uploadTemplate($(this));
			});

		$("#settings-form")
			.off("submit")
			.on("submit", function (event) {
				event.preventDefault();
				pageManager.updateSettings($(this));
			});
	},

	deletePage: function (element, e) {
		let page = element[0].dataset;
		if (
			confirm(`Are you sure you want to delete "${page.file}" template?`)
		) {
			$.ajax({
				type: "POST",
				url: pageDeleteUrl, //set your server side save script url
				data: {
					file: page.file,
				},
				success: function (data, text) {
					let bg = "success";
					alert_float(bg, data.message ?? data);
					pageManager.refreshPageContent();
				},
				error: function (data) {
					alert_float("danger", data.responseText);
				},
			});

			element.remove();
		}
	},
	newPage: function () {
		var newPageModal = $("#new-page-modal");
		let submitBtn = newPageModal.find("button[type=submit]");

		newPageModal
			.modal("show")
			.find("form")
			.off("submit")
			.submit(function (e) {
				e.preventDefault();

				var data = {};
				$.each($(this).serializeArray(), function () {
					data[this.name] = this.value;
				});
				submitBtn.attr("disabled", "disabled");
				return $.ajax({
					type: "POST",
					url: this.action, //set your server side save script url
					data: data,
					cache: false,
				})
					.done(function (data) {
						let bg = "success";
						alert_float(bg, data.message ?? data);
						submitBtn.removeAttr("disabled");
						newPageModal.modal("hide");
						pageManager.refreshPageContent();
					})
					.fail(function (data) {
						alert_float("danger", data.responseText);
						submitBtn.removeAttr("disabled");
					});
			});
	},

	renameFilePath: function (filePath) {
		// Split the file path into directory path and filename
		const lastSlashIndex = filePath.lastIndexOf("/");
		const directoryPath = filePath.substring(0, lastSlashIndex + 1);
		const filename = filePath.substring(lastSlashIndex + 1);

		// Generate a random ID
		const randomId = Math.random().toString().substring(2, 10);

		// Extract the file extension
		const lastDotIndex = filename.lastIndexOf(".");
		const fileExtension =
			lastDotIndex === -1 ? "" : filename.substring(lastDotIndex);

		// Append the random ID to the filename
		const newFilename = filename.replace(
			fileExtension,
			`-copy-${randomId}${fileExtension}`
		);

		// Construct the new file path
		const newFilePath = directoryPath + newFilename;

		return newFilePath;
	},
	renamePage: function (element, e, duplicate = false) {
		var editPageModal = $("#edit-page-modal");
		let page = element[0].dataset;
		let pageMeta = pagesOptions[page.optionsKey];

		let _self = this;

		if (duplicate) {
			editPageModal.find("[data-for=duplicate]").show();
			editPageModal.find("[data-for=edit]").hide();
		} else {
			editPageModal.find("[data-for=duplicate]").hide();
			editPageModal.find("[data-for=edit]").show();
		}

		// Clear all modal fields
		editPageModal.find(`input,textarea,select`).val("");

		editPageModal
			.find('[name="options[landingpage]"]')
			.val(page.file === pagesOptions?.landingpage ? "yes" : "no");

		for (const key in pageMeta) {
			var input = editPageModal.find(`[data-metadata-key="${key}"]`);
			if (input) input.val(pageMeta[key] ?? "");
		}

		editPageModal.find("[name=file],[name=newfile]").val(page.file);
		editPageModal.find("[name=title]").val(page.title);

		if (duplicate) {
			let newFile = this.renameFilePath(page.file);
			editPageModal.find("[name=newfile]").val(newFile);
			editPageModal
				.find("[name=title]")
				.val(newFile.split("/").reverse()[0]);
		}

		editPageModal
			.modal("show")
			.find("form")
			.off("submit")
			.submit(function (e) {
				var data = {};
				$.each($(this).serializeArray(), function () {
					data[this.name] = this.value;
				});
				data.duplicate = duplicate;

				let newfile = data.newfile;
				let submitBtn = editPageModal.find("button[type=submit]");

				if (data.newfile?.length) {
					submitBtn.attr("disabled", "disabled");
					window.lastTouchedFile = data.newfile;
					$.ajax({
						type: "POST",
						url: pageUpdateUrl, //set your server side save script url
						data: data,
						dataType: "json",
						success: function (data, text) {
							let bg = "success";
							if (data.success) {
								$("#top-panel .save-btn").attr(
									"disabled",
									"true"
								);
							}

							alert_float(bg, data.message ?? data);
							editPageModal.modal("hide");
							submitBtn.removeAttr("disabled");
							if (data.pagesOptions) {
								pagesOptions = data.pagesOptions;
							}
							pageManager.refreshPageContent();
						},
						error: function (data) {
							alert_float("danger", data.responseText);
							submitBtn.removeAttr("disabled");
						},
					});
				}

				e.preventDefault();
				return false;
			});
		return;
	},

	buildPage: function (element, e) {
		var buildPageModal = $("#build-page-modal");
		let page = element[0].dataset;

		buildPageModal.find("[name=file]").val(page.file);
		buildPageModal.find("[name=title]").val(page.file);

		buildPageModal.modal("show");
		return;
	},

	uploadTemplate: function (form) {
		// Create FormData object to send file data
		var formData = new FormData(form[0]);
		let closeBtn = form.find('button[data-dismiss="modal"]');
		let submitBtn = form.find("button[type=submit]");
		submitBtn.attr("disabled", "disabled");

		// Send AJAX request yhcurnaL
		$.ajax({
			url: form.attr("action"),
			type: "POST",
			data: formData,
			cache: false,
			contentType: false,
			processData: false,
			success: function (response) {
				response = JSON.parse(response);
				alert_float(response.status, response.message);
				if (response.status === "success") {
					closeBtn.click();
				}
				pageManager.refreshPageContent();
			},
			error: function (data) {
				alert_float("danger", data.responseText);
			},
		}).always(function () {
			submitBtn.removeAttr("disabled");
		});
	},

	updateSettings: function (form) {
		let formData = form.serialize();
		$.ajax({
			type: "POST",
			url: form.attr("action"),
			data: formData,
			cache: false,
			dataType: "json",
		})
			.done(function (data) {
				let bg = "success";
				alert_float(bg, data.message ?? data);
				if (data.allowed_hosts.length) {
					window.allowedHosts = data.allowed_hosts;
				}
			})
			.fail(function (data) {
				alert_float("danger", data.responseText);
			});
		return false;
	},

	refreshPageContent: function () {
		$.ajax({
			url: window.location,
			dataType: "json",
		})
			.done(function (data) {
				if (data.html) {
					$("#page-manager-content").html(data.html);
					if (data.pagesOptions) {
						pagesOptions = data.pagesOptions;
					}

					pageManager.init();

					if (window.lastTouchedFile) {
						$(
							'[data-file="' + window.lastTouchedFile + '"]'
						)[0]?.scrollIntoView();
					}
				} else {
					window.location.reload();
				}
			})
			.fail(function (data) {
				alert_float("danger", data.responseText);
			});
	},
};
document.addEventListener("DOMContentLoaded", function () {
	window.pageManager.init();
});
