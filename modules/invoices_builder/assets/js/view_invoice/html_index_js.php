<script>
   (function($) {
      "use strict";
      new Sticky('[data-sticky]');
      var $payNowTop = $('.pay-now-top');
      if ($payNowTop.length && !$('#pay_now').isInViewport()) {
         $payNowTop.removeClass('hide');
         $('.pay-now-top').on('click', function(e) {
            e.preventDefault();
            $('html,body').animate({
                  scrollTop: $("#online_payment_form").offset().top
               },
               'slow');
         });
      }

      $('#online_payment_form').appFormValidator();

      var online_payments = $('.online-payment-radio');
      if (online_payments.length == 1) {
         online_payments.find('input').prop('checked', true);
      }
   })(jQuery);
</script>