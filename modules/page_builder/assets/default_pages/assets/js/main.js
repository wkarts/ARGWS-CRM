window.addEventListener("DOMContentLoaded", function () {
	//===== Prealoder

	function fadeout() {
		document.querySelector(".preloader").style.opacity = "0";
		document.querySelector(".preloader").style.display = "none";
	}
	if (document.querySelector(".preloader")) window.setTimeout(fadeout, 500);

	/*=====================================
        Sticky
        ======================================= */
	window.onscroll = function () {
		const header_navbar = document.querySelector(".navbar-area");
		if (header_navbar) {
			const sticky = header_navbar.offsetTop;

			if (window.pageYOffset > sticky) {
				header_navbar.classList.add("sticky");
			} else {
				header_navbar.classList.remove("sticky");
			}
		}

		if (document.querySelector("nav.fixed-top")) navbarSticky();

		// show or hide the back-top-top button
		var backToTo = document.querySelector(".scroll-top");
		if (backToTo) {
			if (
				document.body.scrollTop > 50 ||
				document.documentElement.scrollTop > 50
			) {
				backToTo.style.display = "flex";
			} else {
				backToTo.style.display = "none";
			}
		}
	};

	function toggleNavbarTheme() {
		// Get the navbar
		var navbar = document.getElementsByClassName("navbar")[0];

		if (navbar.classList.contains("navbar-dark")) {
			navbar.classList.add("navbar-light");
			navbar.classList.remove("navbar-dark");
		} else if (navbar.classList.contains("navbar-light")) {
			navbar.classList.add("navbar-dark");
			navbar.classList.remove("navbar-light");
		}
	}

	// Add the sticky class to the navbar when you reach its scroll position. Remove "sticky" when you leave the scroll position
	function navbarSticky() {
		// Get the navbar
		var navbar = document.getElementsByClassName("navbar")[0];

		// Get the offset position of the navbar
		var sticky = navbar.offsetTop ? navbar.offsetTop : navbar.offsetHeight;

		let isSticky = window.pageYOffset >= sticky;

		if (isSticky) {
			if (!navbar.classList.contains("sticky")) {
				navbar.classList.add("sticky");
				toggleNavbarTheme();
			}
		} else {
			if (navbar.classList.contains("sticky")) {
				navbar.classList.remove("sticky");
				toggleNavbarTheme();
			}
		}
	}

	// for menu scroll
	var pageLink = document.querySelectorAll(".page-scroll");

	pageLink.forEach((elem) => {
		elem.addEventListener("click", (e) => {
			e.preventDefault();
			document.querySelector(elem.getAttribute("href")).scrollIntoView({
				behavior: "smooth",
				offsetTop: 1 - 60,
			});
		});
	});

	// section menu active
	function onScroll(event) {
		var sections = document.querySelectorAll(".page-scroll");
		if (sections.length) {
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
					refElement.offsetTop + refElement.offsetHeight >
						scrollTopMinus
				) {
					document
						.querySelector(".page-scroll")
						.classList.remove("active");
					currLink.classList.add("active");
				} else {
					currLink.classList.remove("active");
				}
			}
		}
	}

	if (document.querySelectorAll(".page-scroll").length)
		window.document.addEventListener("scroll", onScroll);

	//===== close navbar-collapse when a  clicked
	let navbarToggler = document.querySelector(".navbar-toggler");
	var navbarCollapse = document.querySelector(".navbar-collapse");

	if (navbarCollapse && navbarToggler) {
		document.querySelectorAll(".page-scroll").forEach((e) =>
			e.addEventListener("click", () => {
				navbarToggler.classList.remove("active");
				navbarCollapse.classList.remove("show");
			})
		);
		navbarToggler.addEventListener("click", function () {
			navbarToggler.classList.toggle("active");
		});
	}

	// WOW active
	new WOW().init();

	//======== tiny slider for clients
	if (document.querySelector("testimonial-active-wrapper"))
		tns({
			container: ".testimonial-active",
			autoplay: true,
			autoplayTimeout: 5000,
			autoplayButtonOutput: false,
			mouseDrag: true,
			gutter: 0,
			nav: false,
			navPosition: "bottom",
			controls: true,
			controlsText: [
				'<i class="lni lni-chevron-left"></i>',
				'<i class="lni lni-chevron-right"></i>',
			],
			responsive: {
				0: {
					items: 1,
				},
			},
		});

	// ====== scroll top js
	function scrollTo(element, to = 0, duration = 1000) {
		const start = element.scrollTop;
		const change = to - start;
		const increment = 20;
		let currentTime = 0;

		const animateScroll = () => {
			currentTime += increment;

			const val = Math.easeInOutQuad(
				currentTime,
				start,
				change,
				duration
			);

			element.scrollTop = val;

			if (currentTime < duration) {
				setTimeout(animateScroll, increment);
			}
		};

		animateScroll();
	}

	Math.easeInOutQuad = function (t, b, c, d) {
		t /= d / 2;
		if (t < 1) return (c / 2) * t * t + b;
		t--;
		return (-c / 2) * (t * (t - 2) - 1) + b;
	};

	if (document.querySelector(".scroll-top"))
		document.querySelector(".scroll-top").onclick = function () {
			scrollTo(document.documentElement);
		};

	if (!this.document.querySelector(".extended-bs-layout"))
		document.querySelector("#extended-bs-style").remove();
});
