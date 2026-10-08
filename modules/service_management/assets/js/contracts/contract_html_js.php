<script type="text/javascript">
	$(function(){
		"use strict"; 
		
		new Sticky('[data-sticky]');
		$(".contract-left table").wrap("<div class='table-responsive'></div>");
		 // Create lightbox for contract content images
		 $('.contract-html-content img').wrap( function(){ return '<a href="' + $(this).attr('src') + '" data-lightbox="contract"></a>'; });
	 })
 </script>