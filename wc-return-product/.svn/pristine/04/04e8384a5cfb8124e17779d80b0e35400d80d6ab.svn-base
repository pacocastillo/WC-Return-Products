jQuery(document).ready(function($) {
  if ( $('#wc-form-return').length ) {
    $('#wc-form-return').hide();
    // oculto o muestro el formulario para devolución
    $('a.return-form-product').click(function(event) {
      if ( $('#wc-form-return').is(':visible') ) {
        $('#wc-form-return').hide(200);
      }
      else {
        $('#wc-form-return').show(200);
      }
      return false;
    });

    // submit form return
    $('#wc-form-return').submit(function(event) {
      //validate select some product
      form = $(this);
      form.find('+ .message').hide();

      var datos = {
      'action': 'wc_return_form',
      'type': 'post',
      'products': form.find('select[name="products"]').val(),
      'order': form.find('input[name="order"]').val(),
      'customer': form.find('input[name="customer"]').val(),
      };
      $.post(
        wc_return_ajaxurl, // ERROR, HAY QUE SABER SI EXISTE Y COMO SE LLAMA
        datos,
        function(data, textStatus, xhr) {
          data = jQuery.parseJSON(data);
          // console.log( data );
          if( data.result == false ) {
            // form.append('Su devolución se ha procesado con éxito. Nos pondremos en contacto con usted para la devolución.');
            form.find('+ .message').addClass('error').html(data.response).show(200);
          }
          else {
            form.find('+ .message').addClass('ok').html(data.response).show(200);
          }
        }
      );

      return false;
    });
  }
});