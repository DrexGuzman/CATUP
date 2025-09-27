jQuery(document).ready(function($){
    $('#bus-booking-form').on('submit', function(e){
        e.preventDefault();
        const data = $(this).serialize() + "&action=add_bus_booking&nonce=" + BusBookingAjax.nonce;
        $.post(BusBookingAjax.ajaxurl, data, function(response){
            if(response.success){
                alert(response.data.message);
                window.location.href = "/cart"; // redirigir al carrito
            } else {
                alert(response.data.message);
            }
        });
    });
});
