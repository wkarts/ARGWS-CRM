$(window).on('load', function() {  
	count_product_cart();
});
function count_product_cart(){
	"use strict";
	var cart_qty_list = getCookie('service_qty_list'),count = 0;
	if(cart_qty_list.trim()){
		var qty_list = JSON.parse('['+cart_qty_list+']');
		$.each(qty_list, function( key, value ) {
			count+=value;
		});   
	}
	if(count > 0){
		$('.service_qty_total').text(count).fadeIn(500);
	}
	else{
		$('.service_qty_total').text('').fadeOut(500);
	}
}

function getCookie(cname) {
	"use strict";
	var name = cname + "=";
	var decodedCookie = decodeURIComponent(document.cookie);
	var ca = decodedCookie.split(';');
	for(var i = 0; i <ca.length; i++) {
		var c = ca[i];
		while (c.charAt(0) == ' ') {
			c = c.substring(1);
		}
		if (c.indexOf(name) == 0) {
			return c.substring(name.length, c.length);
		}
	}
	return "";
}