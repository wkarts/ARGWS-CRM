/* Masks only. External lookup now requires the explicit reviewed client-profile flow. */
(function ($) {
  'use strict';
  $(function () {
    if (!$.fn.mask) return;
    $('#zip').mask('00000-000');
    var phone = function (value) { return value.replace(/\D/g, '').length > 10 ? '(00) 00000-0000' : '(00) 0000-00009'; };
    $('#phonenumber').mask(phone, { onKeyPress: function (value, event, field, options) { field.mask(phone(value), options); } });
    // Do not force numeric masks on CPF/CNPJ: alphanumeric CNPJ is supported by the server.
  });
}(jQuery));
