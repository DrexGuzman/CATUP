(function ($) {
  $(document).on('submit', '.eef-emailjs-form', function (e) {
    e.preventDefault();
    const $form = $(this);
    const serviceID = $form.data('service');
    const templateID = $form.data('template');
    const publicKey = $form.data('public');
    const $status = $form.find('.eef-status');

    if (!serviceID || !templateID || !publicKey) {
      $status.text('Faltan credenciales de EmailJS');
      return;
    }

    emailjs.init(publicKey);

    emailjs.sendForm(serviceID, templateID, this)
      .then(() => {
        $status.text('✅ Mensaje enviado correctamente');
        $form[0].reset();
      }, (err) => {
        $status.text('❌ Error al enviar: ' + JSON.stringify(err));
      });
  });
})(jQuery);
