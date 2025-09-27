// filepath: [scpt-galeria.js](http://_vscodecontentref_/0)
(function($){
  $(function(){

    // Selector de LOGO (una sola imagen)
    var logoFrame;
    $('#scpt-logo-btn').on('click', function(e){
      e.preventDefault();

      if (logoFrame) {
        logoFrame.open();
        return;
      }

      logoFrame = wp.media({
        title: 'Seleccionar logo',
        button: { text: 'Usar este logo' },
        multiple: false,
        library: { type: 'image' }
      });

      logoFrame.on('select', function(){
        var attachment = logoFrame.state().get('selection').first().toJSON();
        $('#scpt_logo_id').val(attachment.id);

        var thumb = (attachment.sizes && (attachment.sizes.thumbnail || attachment.sizes.medium || attachment.sizes.full)) || attachment;
        $('#scpt-logo-preview').html('<img src="'+ thumb.url +'" style="max-width:120px; display:block; margin-bottom:8px;">');
      });

      logoFrame.open();
    });

    // Helpers
    function parseIds(str){
      return (str || '').split(',').map(function(v){ return parseInt(v,10); }).filter(Boolean);
    }
    function idsToString(ids){
      return ids.filter(Boolean).map(function(v){ return parseInt(v,10); }).filter(function(v,i,a){ return a.indexOf(v)===i; }).join(',');
    }
    function appendThumb(id, url){
      var $wrap = $('<div/>', {
        'class': 'scpt-thumb',
        'data-id': id,
        'style': 'position:relative;display:inline-block;margin:5px;'
      });
      var $btn = $('<button/>', {
        'type': 'button',
        'class': 'scpt-remove',
        'title': 'Eliminar',
        'style': 'position:absolute;top:-8px;right:-8px;background:#dc3232;color:#fff;border-radius:50%;width:22px;height:22px;line-height:20px;text-align:center;border:0;cursor:pointer;',
        'text': '×'
      });
      var $img = $('<img/>', {
        'src': url,
        'style': 'display:block;max-width:120px;height:auto;border:1px solid #ddd;border-radius:4px;'
      });
      $wrap.append($btn).append($img);
      $('#scpt-galeria-preview').append($wrap);
    }

    // Eliminar una miniatura
    $('#scpt-galeria-preview').on('click', '.scpt-remove', function(){
      var $thumb = $(this).closest('.scpt-thumb');
      var id = parseInt($thumb.data('id'), 10);
      var ids = parseIds($('#scpt_galeria').val());
      ids = ids.filter(function(v){ return v !== id; });
      $('#scpt_galeria').val(idsToString(ids));
      $thumb.remove();
    });

    // Selector de GALERÍA (múltiples imágenes)
    var galeriaFrame;
    $('#scpt-galeria-btn').on('click', function(e){
      e.preventDefault();

      if (!galeriaFrame) {
        galeriaFrame = wp.media({
          title: 'Seleccionar imágenes de la galería',
          button: { text: 'Añadir a la galería' },
          multiple: true,
          library: { type: 'image' }
        });

        // Preseleccionar imágenes guardadas SIEMPRE que se abre
        galeriaFrame.on('open', function(){
          var selection = galeriaFrame.state().get('selection');
          selection.reset();
          var currentIds = parseIds($('#scpt_galeria').val());
          currentIds.forEach(function(id){
            var attachment = wp.media.attachment(id);
            if (attachment) {
              attachment.fetch();
              selection.add(attachment);
            }
          });
        });

        galeriaFrame.on('select', function(){
          var selection = galeriaFrame.state().get('selection');

          // IDs actuales (no se pierden)
          var current = parseIds($('#scpt_galeria').val());

          // IDs seleccionados en este turno
          var picked = [];
          var pickedMap = {}; // guardar JSON para URL rápida
          selection.each(function(att){
            var a = att.toJSON();
            picked.push(a.id);
            pickedMap[a.id] = a;
          });

          // Solo añadir los que no existen aún
          var onlyNew = picked.filter(function(id){ return current.indexOf(id) === -1; });

          // Actualizar input oculto
          var updated = current.concat(onlyNew);
          $('#scpt_galeria').val(idsToString(updated));

          // Añadir previews solo de los nuevos
          onlyNew.forEach(function(id){
            var a = pickedMap[id];
            var thumb = (a && a.sizes && (a.sizes.thumbnail || a.sizes.medium || a.sizes.full)) || a;
            if (thumb && thumb.url) {
              appendThumb(id, thumb.url);
            } else {
              // Fallback: intentar recuperar
              var att = wp.media.attachment(id);
              att.fetch().then(function(){
                var j = att.toJSON();
                var t = (j.sizes && (j.sizes.thumbnail || j.sizes.medium || j.sizes.full)) || j;
                appendThumb(id, t.url);
              });
            }
          });
        });
      }

      galeriaFrame.open();
    });

  });
})(jQuery);