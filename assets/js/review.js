/* globals ajaxurl */
jQuery(document).ready(function ($) {
  $(document).on('click', '.mp-rating-notice button', function () {
      $.post( ajaxurl, { action: 'mercadopago_review_dismiss' } );
    }
  );
});
