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
                action : 'wc_return_form',
                type : 'post',
                dataType : 'json',
                order: $(this).find('[name=order]').val(),
                customer: $(this).find('[name=customer]').val(),
                wc_products: $(this).find('.wc_products').val()
      };
      $.post(
        wc_return_ajaxurl, // ERROR, HAY QUE SABER SI EXISTE Y COMO SE LLAMA
        datos,
        function(data, textStatus, xhr) {
          data = jQuery.parseJSON(data);
          console.log( data );
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

  $.fn.serializeObject = function(){
    var self = this,
        json = {},
        push_counters = {},
        patterns = {
            "validate": /^[a-zA-Z][a-zA-Z0-9_]*(?:\[(?:\d*|[a-zA-Z0-9_]+)\])*$/,
            "key":      /[a-zA-Z0-9_]+|(?=\[\])/g,
            "push":     /^$/,
            "fixed":    /^\d+$/,
            "named":    /^[a-zA-Z0-9_]+$/
        };


    this.build = function(base, key, value){
        base[key] = value;
        return base;
    };

    this.push_counter = function(key){
        if(push_counters[key] === undefined){
            push_counters[key] = 0;
        }
        return push_counters[key]++;
    };

    $.each($(this).serializeArray(), function(){

        // skip invalid keys
        if(!patterns.validate.test(this.name)){
            return;
        }

        var k,
            keys = this.name.match(patterns.key),
            merge = this.value,
            reverse_key = this.name;

        while((k = keys.pop()) !== undefined){
            // adjust reverse_key
            reverse_key = reverse_key.replace(new RegExp("\\[" + k + "\\]$"), '');

            // push
            if(k.match(patterns.push)){
                merge = self.build([], self.push_counter(reverse_key), merge);
            }

            // fixed
            else if(k.match(patterns.fixed)){
                merge = self.build([], k, merge);
            }

            // named
            else if(k.match(patterns.named)){
                merge = self.build({}, k, merge);
            }
        }
        json = $.extend(true, json, merge);
    });
    return json;
  };
});