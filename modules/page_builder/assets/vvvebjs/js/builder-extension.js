// Get FrameDocument body inner content exluding the head
Vvveb.Builder.getHtml = function (keepHelperAttributes = true) {
	var doc = window.FrameDocument;
	var html = "";

	$("[contenteditable]", doc).removeAttr("contenteditable");
	$("[spellcheckker]", doc).removeAttr("spellcheckker");

	$(window).triggerHandler("vvveb.getHtml.before", doc);

	Vvveb.FontsManager.cleanUnusedFonts();

	html = doc.body.innerHTML.replace(/\n{2,}/g, "\n").trim();
	html = this.removeHelpers(html, keepHelperAttributes);

	$(window).triggerHandler("vvveb.getHtml.after", doc);

	var filter = $(window).triggerHandler("vvveb.getHtml.filter", html);
	if (filter) return filter;

	return html;
};

// Sanitize and inject into Frame
Vvveb.Builder.setHtml = function (html) {
	window.FrameDocument.body.innerHTML = DOMPurify.sanitize(html, {
		ADD_TAGS: ["iframe"],
		ADD_ATTR: [
			"allow",
			"allowfullscreen",
			"frameborder",
			"scrolling",
			"width",
			"height",
			"class",
			"id",
		],
	});
};

// Extend page saving for meta data and sanitization
Vvveb.Builder.saveAjax = function (file, saveUrl, callback, errorCallback) {
	var data = {};
	var fileName = file.file;
	var startTemplateFile = file.startTemplateFile;
	data["file"] =
		fileName && fileName != ""
			? fileName
			: Vvveb.FileManager.getCurrentFileName();
	data["startTemplateFile"] = startTemplateFile;
	if (!startTemplateFile || startTemplateFile == null) {
		data["html"] = this.getHtml();
	}

	data["css"] = Vvveb.StyleManager.getCss();

	return $.ajax({
		type: "POST",
		url: saveUrl, //set your server side save script url
		data: data,
		cache: false,
	})
		.done(function (data) {
			if (callback) callback(data);
			Vvveb.Undo.reset();
			$("#top-panel .save-btn").attr("disabled", "true");
		})
		.fail(function (data) {
			alert(data.responseText);
			if (errorCallback) errorCallback(data);
		});
};

/**
 * Get host from url i.e base.domain.com from http://base.domain.com/something
 * @param {string} str The url
 * @returns
 */
function getHostNameFromString(str) {
	try {
		const url = new URL(str);
		return url.hostname;
	} catch (error) {
		return null; // If the string is not a valid URL
	}
}

/**
 * Get the base root domain from a url string i.e domain.com from http://base.domain.com/something
 * @param {string} urlString
 * @returns
 */
function getRootDomainFromUrl(urlString) {
	try {
		const url = new URL(urlString);
		const hostnameParts = getHostNameFromString(urlString)
			.split(".")
			.reverse();

		// Check if the hostname has at least two parts (e.g., example.com)
		if (hostnameParts.length >= 2) {
			return hostnameParts[1] + "." + hostnameParts[0];
		} else {
			return url.hostname;
		}
	} catch (error) {
		return null; // If the string is not a valid URL
	}
}

/**
 * Check is a url host/domain is whitelabelled
 * @param {string} url The url
 * @returns bool
 */
function urlHostAllowed(url) {
	const host = getHostNameFromString(url);
	const domain = getRootDomainFromUrl(url);
	if (allowedHosts.includes(host) || allowedHosts.includes(domain))
		return true;

	return false;
}

// Add hook for filtering iframe sources.
if (typeof DOMPurify !== "undefined") {
	DOMPurify.addHook("uponSanitizeElement", (node, data) => {
		if (
			[
				"img",
				"script",
				"iframe",
				"audio",
				"video",
				"source",
				"track",
			].includes(data.tagName)
		) {
			const url = node.getAttribute("src") || "";
			if (!urlHostAllowed(url)) {
				return node.parentNode?.removeChild(node);
			}
		}
	});

	// Purify inputs
	$("input, select, textarea").on("blur, change", function (event) {
		event.target.value = DOMPurify.sanitize(event.target.value, {
			ALLOWED_TAGS: [], // dont allow any tag here
		});
	});
}
