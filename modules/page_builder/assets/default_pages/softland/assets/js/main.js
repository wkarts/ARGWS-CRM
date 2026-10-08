"use strict";
window.onload = function () {
	window.setTimeout(fadeout, 500);
};
function fadeout() {
	document.querySelector(".preloader").style.opacity = "0";
	document.querySelector(".preloader").style.display = "none";
}
window.onscroll = function () {
	var header_navbar = document.querySelector(".navbar-area");
	var sticky = header_navbar.offsetTop;

	if (window.pageYOffset > sticky) {
		header_navbar.classList.add("sticky");
	} else {
		header_navbar.classList.remove("sticky");
	}
	var backToTo = document.querySelector(".scroll-top");
	if (
		document.body.scrollTop > 50 ||
		document.documentElement.scrollTop > 50
	) {
		backToTo.style.display = "flex";
	} else {
		backToTo.style.display = "none";
	}
};
function onScroll(event) {
	var sections = document.querySelectorAll(".page-scroll");
	var scrollPos =
		window.pageYOffset ||
		document.documentElement.scrollTop ||
		document.body.scrollTop;
	for (var i = 0; i < sections.length; i++) {
		var currLink = sections[i];
		var val = currLink.getAttribute("href");
		var refElement = document.querySelector(val);
		var scrollTopMinus = scrollPos + 73;
		if (
			refElement.offsetTop <= scrollTopMinus &&
			refElement.offsetTop + refElement.offsetHeight > scrollTopMinus
		) {
			document.querySelector(".page-scroll").classList.remove("active");
			currLink.classList.add("active");
		} else {
			currLink.classList.remove("active");
		}
	}
}
window.document.addEventListener("scroll", onScroll);
let navbarToggler = document.querySelector(".navbar-toggler");
var navbarCollapse = document.querySelector(".navbar-collapse");
document.querySelectorAll(".page-scroll").forEach((e) =>
	e.addEventListener("click", () => {
		navbarToggler.classList.remove("active");
		navbarCollapse.classList.remove("show");
	})
);
navbarToggler.addEventListener("click", function () {
	navbarToggler.classList.toggle("active");
});
var cu = new counterUp({
	start: 0,
	duration: 2000,
	intvalues: true,
	interval: 100,
	append: "K",
});
cu.start();
const myGallery = GLightbox({
	href: "assets/video/video.mp4",
	type: "video",
	source: "local",
	width: 900,
	autoplayVideos: true,
});
var slider = new tns({
	container: ".testimonial-active",
	items: 2,
	slideBy: "page",
	autoplay: false,
	mouseDrag: true,
	gutter: 0,
	nav: true,
	controls: false,
	responsive: {0: {items: 1}, 992: {items: 2}},
});
new WOW().init();
