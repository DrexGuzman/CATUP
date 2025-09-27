<?php
/**
 * Plugin Name: Bus Booking for WooCommerce (Skeleton)
 * Description: Reserva de espacios en buses (mañana/tarde) integrada a WooCommerce. Esqueleto con CPT, shortcode, integración básica y administración.
 * Version: 0.1
 * Author: Tu Nombre
 * Text Domain: bus-booking
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Bus_Booking_Plugin {

    public function __construct() {
        // Init hooks
        add_action( 'init', array( $this, 'register_cpts' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_service_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_service_meta' ), 10, 2 );

        // Shortcode
        add_shortcode( 'bus_booking_form', array( $this, 'render_booking_form_shortcode' ) );

        // Enqueue scripts/styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );

        // WooCommerce integration: validation, cart, order creation
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 5 );
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
        add_action( 'woocommerce_get_item_data', array( $this, 'show_cart_item_meta' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order', array( $this, 'create_reservations_on_order' ), 10, 2 );

        // Update capacities on order status changes
        add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_finalize_reservations' ) );
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'maybe_revert_reservations' ) );
        add_action( 'woocommerce_order_status_refunded', array( $this, 'maybe_revert_reservations' ) );

        // Admin menu for reservations
        add_action( 'admin_menu', array( $this, 'register_admin_pages' ) );

        // AJAX endpoint for remaining seats (public)
        add_action( 'wp_ajax_nopriv_bus_remaining_seats', array( $this, 'ajax_remaining_seats' ) );
        add_action( 'wp_ajax_bus_remaining_seats', array( $this, 'ajax_remaining_seats' ) );
    }

    /* ----------------------------
       CPTs: Service days & Bookings
    ---------------------------- */
    public function register_cpts() {
        // Service days
        register_post_type( 'bus_service_day', array(
            'labels' => array(
                'name' => __( 'Días de Servicio', 'bus-booking' ),
                'singular_name' => __( 'Día de Servicio', 'bus-booking' ),
            ),
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => array( 'title' ),
        ) );

        // Booking records
        register_post_type( 'bus_booking', array(
            'labels' => array(
                'name' => __( 'Reservas Bus', 'bus-booking' ),
                'singular_name' => __( 'Reserva Bus', 'bus-booking' ),
            ),
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-tickets',
            'supports' => array( 'title' ),
        ) );
    }

    /* ----------------------------
       Metaboxes for service day
    ---------------------------- */
    public function add_service_meta_boxes() {
        add_meta_box( 'bus_service_day_meta', __( 'Detalles del Servicio', 'bus-booking' ), array( $this, 'render_service_meta_box' ), 'bus_service_day', 'normal', 'high' );
    }

    public function render_service_meta_box( $post ) {
        wp_nonce_field( 'save_bus_service_day', 'bus_service_day_nonce' );
        $date = get_post_meta( $post->ID, '_service_date', true );
        $capacity = get_post_meta( $post->ID, '_capacity', true );
        $morning = get_post_meta( $post->ID, '_morning_available', true );
        $afternoon = get_post_meta( $post->ID, '_afternoon_available', true );
        $morning_reserved = get_post_meta( $post->ID, '_morning_reserved', true );
        $afternoon_reserved = get_post_meta( $post->ID, '_afternoon_reserved', true );

        if ( ! $capacity ) $capacity = 40;
        if ( '' === $morning_reserved ) $morning_reserved = 0;
        if ( '' === $afternoon_reserved ) $afternoon_reserved = 0;

        ?>
        <p>
            <label><?php _e( 'Fecha del servicio', 'bus-booking' ); ?></label><br/>
            <input type="date" name="service_date" value="<?php echo esc_attr( $date ); ?>" />
        </p>
        <p>
            <label><?php _e( 'Capacidad por bus (asientos)', 'bus-booking' ); ?></label><br/>
            <input type="number" name="capacity" value="<?php echo esc_attr( $capacity ); ?>" min="1" />
        </p>
        <p>
            <label><input type="checkbox" name="morning_available" value="1" <?php checked( $morning, '1' ); ?> /> <?php _e( 'Disponible por la mañana', 'bus-booking' ); ?></label><br/>
            <label><input type="checkbox" name="afternoon_available" value="1" <?php checked( $afternoon, '1' ); ?> /> <?php _e( 'Disponible por la tarde', 'bus-booking' ); ?></label>
        </p>
        <p>
            <strong><?php _e( 'Reservados (solo info administrativa)', 'bus-booking' ); ?></strong><br/>
            Mañana: <input type="number" name="morning_reserved" value="<?php echo esc_attr( $morning_reserved ); ?>" min="0" /> &nbsp;
            Tarde: <input type="number" name="afternoon_reserved" value="<?php echo esc_attr( $afternoon_reserved ); ?>" min="0" />
        </p>
        <?php
    }

    public function save_service_meta( $post_id, $post ) {
        if ( $post->post_type !== 'bus_service_day' ) return;
        if ( ! isset( $_POST['bus_service_day_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['bus_service_day_nonce'], 'save_bus_service_day' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        $date = isset( $_POST['service_date'] ) ? sanitize_text_field( $_POST['service_date'] ) : '';
        $capacity = isset( $_POST['capacity'] ) ? intval( $_POST['capacity'] ) : 40;
        $morning = isset( $_POST['morning_available'] ) ? '1' : '';
        $afternoon = isset( $_POST['afternoon_available'] ) ? '1' : '';
        $morning_reserved = isset( $_POST['morning_reserved'] ) ? intval( $_POST['morning_reserved'] ) : 0;
        $afternoon_reserved = isset( $_POST['afternoon_reserved'] ) ? intval( $_POST['afternoon_reserved'] ) : 0;

        update_post_meta( $post_id, '_service_date', $date );
        update_post_meta( $post_id, '_capacity', $capacity );
        update_post_meta( $post_id, '_morning_available', $morning );
        update_post_meta( $post_id, '_afternoon_available', $afternoon );
        update_post_meta( $post_id, '_morning_reserved', $morning_reserved );
        update_post_meta( $post_id, '_afternoon_reserved', $afternoon_reserved );
    }

    /* ----------------------------
       Front assets
    ---------------------------- */
    public function enqueue_front_assets() {
        wp_enqueue_script( 'bus-booking-frontend', plugin_dir_url( __FILE__ ) . 'assets/frontend.js', array( 'jquery' ), '0.1', true );
        wp_localize_script( 'bus-booking-frontend', 'BusBooking', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'bus_booking_nonce' ),
        ) );
        wp_enqueue_style( 'bus-booking-style', plugin_dir_url( __FILE__ ) . 'assets/frontend.css' );
    }

    /* ----------------------------
       Shortcode: Booking form
       - Outputs a form where user picks: fecha, horario, cantidad, y llena datos.
       - On submit usa el proceso estándar para "add to cart" (via POST a same page) — aquí simplificamos:
    ---------------------------- */
    public function render_booking_form_shortcode( $atts ) {
        // Get upcoming service days that are enabled
        $today = date( 'Y-m-d' );
        $args = array(
            'post_type' => 'bus_service_day',
            'meta_key' => '_service_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_service_date',
                    'value' => $today,
                    'compare' => '>=',
                ),
            ),
            'posts_per_page' => 50,
        );
        $days = get_posts( $args );

        ob_start();
        ?>
        <form class="bus-booking-form" method="post">
            <input type="hidden" name="bus_booking_action" value="add_to_cart" />
            <p>
                <label>Fecha</label><br/>
                <select name="service_day_id" id="service_day_id" required>
                    <option value=""><?php _e( 'Selecciona una fecha', 'bus-booking' ); ?></option>
                    <?php foreach ( $days as $d ) :
                        $date = get_post_meta( $d->ID, '_service_date', true );
                        $morning = get_post_meta( $d->ID, '_morning_available', true );
                        $afternoon = get_post_meta( $d->ID, '_afternoon_available', true );
                        $capacity = get_post_meta( $d->ID, '_capacity', true ); ?>
                        <option value="<?php echo esc_attr( $d->ID ); ?>"
                            data-morning="<?php echo esc_attr( $morning ); ?>"
                            data-afternoon="<?php echo esc_attr( $afternoon ); ?>"
                            data-capacity="<?php echo esc_attr( $capacity ); ?>"
                        >
                            <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $date ) ) . ' — ' . $d->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label>Horario</label><br/>
                <select name="schedule" id="schedule" required>
                    <option value=""><?php _e( 'Selecciona horario', 'bus-booking' ); ?></option>
                    <option value="morning" data-available="0"><?php _e( 'Mañana', 'bus-booking' ); ?></option>
                    <option value="afternoon" data-available="0"><?php _e( 'Tarde', 'bus-booking' ); ?></option>
                </select>
            </p>

            <p>
                <label>Cantidad de espacios</label><br/>
                <input type="number" name="seats" id="seats" min="1" value="1" required />
                <small id="seats_info"></small>
            </p>

            <hr/>

            <p><label>Nombre completo</label><br/><input type="text" name="customer_name" required /></p>
            <p><label>Identificación</label><br/><input type="text" name="customer_id" required /></p>
            <p><label>Correo electrónico</label><br/><input type="email" name="customer_email" required /></p>
            <p><label>Teléfono (incluye extensión si aplica)</label><br/><input type="text" name="customer_phone" required /></p>

            <p>
                <label>Precio por espacio (CRC)</label><br/>
                <input type="number" name="price_per_seat" id="price_per_seat" min="0" value="5000" required />
            </p>

            <p>
                <button type="submit" class="button"><?php _e( 'Añadir al carrito', 'bus-booking' ); ?></button>
            </p>
        </form>

        <script>
        (function($){
            $(function(){
                function updateScheduleOptions(){
                    var selected = $('#service_day_id').find('option:selected');
                    var morning = selected.data('morning');
                    var afternoon = selected.data('afternoon');

                    $('#schedule option[value="morning"]').prop('disabled', ! morning);
                    $('#schedule option[value="afternoon"]').prop('disabled', ! afternoon);
                }
                $('#service_day_id').on('change', function(){
                    updateScheduleOptions();
                    $('#seats_info').text('');
                });

                $('#schedule, #service_day_id').on('change', function(){
                    var day_id = $('#service_day_id').val();
                    var schedule = $('#schedule').val();
                    if(!day_id || !schedule) return;
                    // AJAX to get remaining seats
                    $.post(BusBooking.ajax_url, {
                        action: 'bus_remaining_seats',
                        day_id: day_id,
                        schedule: schedule,
                        _wpnonce: BusBooking.nonce
                    }, function(resp){
                        if(resp.success){
                            $('#seats_info').text('Quedan ' + resp.data.remaining + ' espacios disponibles.');
                            $('#seats').attr('max', resp.data.remaining);
                        } else {
                            $('#seats_info').text(resp.data || 'No disponible');
                            $('#seats').attr('max', 0);
                        }
                    });
                });

            });
        })(jQuery);
        </script>

        <?php
        // If form submitted (simple approach: POST to same page)
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['bus_booking_action'] ) && $_POST['bus_booking_action'] === 'add_to_cart' ) {
            $result = $this->handle_front_add_to_cart();
            if ( is_wp_error( $result ) ) {
                echo '<div class="bus-booking-error">' . esc_html( $result->get_error_message() ) . '</div>';
            } else {
                // Redirect to cart for checkout
                wp_safe_redirect( wc_get_cart_url() );
                exit;
            }
        }

        return ob_get_clean();
    }

    /* ----------------------------
       AJAX: remaining seats for a day+schedule
    ---------------------------- */
    public function ajax_remaining_seats() {
        check_ajax_referer( 'bus_booking_nonce', '_wpnonce' );

        $day_id = isset( $_POST['day_id'] ) ? intval( $_POST['day_id'] ) : 0;
        $schedule = isset( $_POST['schedule'] ) ? sanitize_text_field( $_POST['schedule'] ) : '';

        if ( ! $day_id || ! in_array( $schedule, array( 'morning', 'afternoon' ), true ) ) {
            wp_send_json_error( 'Parámetros inválidos.' );
        }

        $capacity = intval( get_post_meta( $day_id, '_capacity', true ) );
        // compute reserved seats from bookings whose orders are not cancelled/refunded
        $reserved = $this->count_reserved_for_day_schedule( $day_id, $schedule );

        $remaining = max( 0, $capacity - $reserved );
        wp_send_json_success( array( 'remaining' => $remaining ) );
    }

    /* ----------------------------
       Count reserved seats (by querying bookings and checking order status)
    ---------------------------- */
    protected function count_reserved_for_day_schedule( $day_id, $schedule ) {
        $args = array(
            'post_type' => 'bus_booking',
            'meta_query' => array(
                array( 'key' => '_service_day_id', 'value' => $day_id ),
                array( 'key' => '_schedule', 'value' => $schedule ),
            ),
            'posts_per_page' => -1,
            'fields' => 'ids',
        );
        $posts = get_posts( $args );
        $reserved = 0;
        foreach ( $posts as $pid ) {
            $order_id = get_post_meta( $pid, '_order_id', true );
            $seats = intval( get_post_meta( $pid, '_seats', true ) );
            if ( $order_id ) {
                $order = wc_get_order( $order_id );
                if ( ! $order ) continue;
                $status = $order->get_status();
                // count only active orders (exclude cancelled, refunded)
                if ( in_array( $status, array( 'cancelled', 'refunded' ), true ) ) continue;
                $reserved += $seats;
            } else {
                // booking without order? count it anyway
                $reserved += $seats;
            }
        }
        return $reserved;
    }

    /* ----------------------------
       Handle front form submission: add to cart with custom cart item data
       This is a simplified flow: create or use a virtual product on-the-fly
    ---------------------------- */
    public function handle_front_add_to_cart() {
        if ( ! isset( $_POST['service_day_id'], $_POST['schedule'], $_POST['seats'], $_POST['customer_name'], $_POST['customer_id'], $_POST['customer_email'], $_POST['customer_phone'], $_POST['price_per_seat'] ) ) {
            return new WP_Error( 'missing', 'Faltan campos.' );
        }

        $day_id = intval( $_POST['service_day_id'] );
        $schedule = sanitize_text_field( $_POST['schedule'] );
        $seats = intval( $_POST['seats'] );
        $name = sanitize_text_field( $_POST['customer_name'] );
        $id_number = sanitize_text_field( $_POST['customer_id'] );
        $email = sanitize_email( $_POST['customer_email'] );
        $phone = sanitize_text_field( $_POST['customer_phone'] );
        $price_per_seat = floatval( $_POST['price_per_seat'] );

        // Basic validation
        if ( $seats < 1 ) return new WP_Error( 'invalid_seats', 'La cantidad de asientos debe ser al menos 1.' );

        // Check remaining seats
        $capacity = intval( get_post_meta( $day_id, '_capacity', true ) );
        $reserved = $this->count_reserved_for_day_schedule( $day_id, $schedule );
        $remaining = max(0, $capacity - $reserved);
        if ( $seats > $remaining ) return new WP_Error( 'no_cupo', 'No hay suficientes espacios disponibles.' );

        // Create or get a virtual "product" to add to cart
        // Strategy: create a hidden product programmatically (one time) or use a generic placeholder product ID configured.
        // For skeleton: create a transient product (programmatic) and add it to cart with cart item data.

        // We'll create a custom cart item with 'bus_booking' flag and price meta.
        $product_id = 0; // no real product id, but wc->cart requires product id - we create a hidden product if none exists
        $hidden_product_id = $this->get_or_create_hidden_product();

        $cart_item_data = array(
            'bus_booking' => true,
            'service_day_id' => $day_id,
            'schedule' => $schedule,
            'seats' => $seats,
            'customer_name' => $name,
            'customer_id_number' => $id_number,
            'customer_email' => $email,
            'customer_phone' => $phone,
            'price_per_seat' => $price_per_seat,
        );

        $added = WC()->cart->add_to_cart( $hidden_product_id, $seats, 0, array(), $cart_item_data );
        if ( ! $added ) {
            return new WP_Error( 'cart_failed', 'No se pudo añadir al carrito.' );
        }

        return true;
    }

    protected function get_or_create_hidden_product() {
        // try to find a product with SKU 'bus-booking-hidden'
        $args = array(
            'post_type' => 'product',
            'meta_key' => '_sku',
            'meta_value' => 'bus-booking-hidden',
            'posts_per_page' => 1,
            'fields' => 'ids',
        );
        $posts = get_posts( $args );
        if ( ! empty( $posts ) ) return $posts[0];

        // create a hidden simple product
        $prod = array(
            'post_title' => 'Reserva de Bus (oculto)',
            'post_content' => 'Producto oculto para reservas de bus (usado internamente).',
            'post_status' => 'publish',
            'post_type' => 'product',
            'post_excerpt' => 'Producto oculto',
        );
        $pid = wp_insert_post( $prod );
        if ( $pid ) {
            wp_set_object_terms( $pid, 'simple', 'product_type' );
            update_post_meta( $pid, '_visibility', 'hidden' );
            update_post_meta( $pid, '_sku', 'bus-booking-hidden' );
            update_post_meta( $pid, '_price', 0 );
            update_post_meta( $pid, '_regular_price', 0 );
            update_post_meta( $pid, '_stock_status', 'instock' );
            // Hide from catalog
        }
        return $pid;
    }

    /* ----------------------------
       Add cart item data and ensure price is set
    ---------------------------- */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        if ( isset( $_POST['bus_booking_action'] ) && $_POST['bus_booking_action'] === 'add_to_cart' ) {
            // The form handler already added to cart; this filter can be used when add_to_cart happens via Woocommerce UI.
            // We'll merge incoming POST data when present.
            $data_keys = array( 'service_day_id','schedule','seats','customer_name','customer_id','customer_email','customer_phone','price_per_seat' );
            foreach ( $data_keys as $k ) {
                if ( isset( $_POST[ $k ] ) ) {
                    $cart_item_data[ $k ] = sanitize_text_field( $_POST[ $k ] );
                }
            }
        }
        // If product was added programmatically via handle_front_add_to_cart, $cart_item_data already includes fields.
        // Set 'bus_booking' flag
        if ( isset( $cart_item_data['bus_booking'] ) || isset( $_POST['bus_booking_action'] ) ) {
            $cart_item_data['bus_booking'] = true;
        }

        // Ensure price is applied on cart item price (we'll hook into woocommerce_before_calculate_totals)
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_cart_item_price' ), 20, 1 );

        return $cart_item_data;
    }

    public function set_cart_item_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( empty( $cart_item['bus_booking'] ) ) continue;
            $price_per_seat = isset( $cart_item['price_per_seat'] ) ? floatval( $cart_item['price_per_seat'] ) : 0;
            $quantity = isset( $cart_item['quantity'] ) ? intval( $cart_item['quantity'] ) : 1;
            // Price per product unit should be price_per_seat
            if ( $price_per_seat > 0 ) {
                $cart->cart_contents[ $cart_item_key ]['data']->set_price( $price_per_seat );
            }
        }
    }

    /* ----------------------------
       Show cart item meta
    ---------------------------- */
    public function show_cart_item_meta( $item_data, $cart_item ) {
        if ( empty( $cart_item['bus_booking'] ) ) return $item_data;

        $day_id = isset( $cart_item['service_day_id'] ) ? intval( $cart_item['service_day_id'] ) : 0;
        $schedule = isset( $cart_item['schedule'] ) ? sanitize_text_field( $cart_item['schedule'] ) : '';
        $seats = isset( $cart_item['seats'] ) ? intval( $cart_item['seats'] ) : 0;

        if ( $day_id ) {
            $date = get_post_meta( $day_id, '_service_date', true );
            $item_data[] = array( 'key' => 'Fecha', 'value' => date_i18n( get_option( 'date_format' ), strtotime( $date ) ) );
        }
        if ( $schedule ) $item_data[] = array( 'key' => 'Horario', 'value' => ucfirst( $schedule ) );
        if ( $seats ) $item_data[] = array( 'key' => 'Espacios', 'value' => $seats );

        return $item_data;
    }

    /* ----------------------------
       Validate add to cart - prevent overbooking
    ---------------------------- */
    public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = null, $variations = null ) {
        // If cart item is a booking, validate capacity
        // Look for POST fields or $quantity and cart item data
        $service_day_id = isset( $_POST['service_day_id'] ) ? intval( $_POST['service_day_id'] ) : ( isset( $_POST['service_day_id_hidden'] ) ? intval( $_POST['service_day_id_hidden'] ) : 0 );
        $schedule = isset( $_POST['schedule'] ) ? sanitize_text_field( $_POST['schedule'] ) : '';
        $seats = isset( $_POST['seats'] ) ? intval( $_POST['seats'] ) : $quantity;

        if ( $service_day_id && $schedule ) {
            $capacity = intval( get_post_meta( $service_day_id, '_capacity', true ) );
            $reserved = $this->count_reserved_for_day_schedule( $service_day_id, $schedule );

            // also count seats already in cart for same day+schedule
            $in_cart = 0;
            foreach ( WC()->cart->get_cart() as $ci ) {
                if ( empty( $ci['bus_booking'] ) ) continue;
                if ( intval( $ci['service_day_id'] ) === $service_day_id && $ci['schedule'] === $schedule ) {
                    $in_cart += intval( $ci['quantity'] );
                }
            }

            $available = $capacity - $reserved - $in_cart;
            if ( $seats > $available ) {
                wc_add_notice( sprintf( __( 'No hay suficientes espacios. Disponibles: %d', 'bus-booking' ), max(0, $available) ), 'error' );
                return false;
            }
        }

        return $passed;
    }

    /* ----------------------------
       On order creation: convert cart booking items into booking posts
       This runs when order is created (before payment).
    ---------------------------- */
    public function create_reservations_on_order( $order, $data ) {
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            $cart_item_meta = $item->get_meta_data(); // legacy
            // Newer approach: we need to retrieve the cart item meta that we attached earlier.
            // item->get_meta( 'key' ) can be used but our keys are not item meta until we set them on order line item.
            // Instead, create reservation by reading order item meta that we saved in checkout_create_order_line_item step.
            // But safer: attempt to read meta keys in the item meta.
            $is_booking = false;
            $service_day_id = $item->get_meta( 'service_day_id' );
            $schedule = $item->get_meta( 'schedule' );
            $seats = $item->get_quantity();
            $name = $item->get_meta( 'customer_name' );
            $id_number = $item->get_meta( 'customer_id_number' );
            $email = $item->get_meta( 'customer_email' );
            $phone = $item->get_meta( 'customer_phone' );
            $price_per_seat = $item->get_meta( 'price_per_seat' );

            if ( $service_day_id && $schedule ) $is_booking = true;

            if ( $is_booking ) {
                // Create post type bus_booking for this reservation (one post per line item)
                $title = sprintf( 'Reserva %s - %s (%s)', get_the_title( $service_day_id ), $schedule, $order->get_id() );
                $booking_id = wp_insert_post( array(
                    'post_type' => 'bus_booking',
                    'post_title' => wp_trim_words( $title, 10 ),
                    'post_status' => 'publish',
                ) );

                if ( $booking_id ) {
                    update_post_meta( $booking_id, '_service_day_id', $service_day_id );
                    update_post_meta( $booking_id, '_schedule', $schedule );
                    update_post_meta( $booking_id, '_seats', $seats );
                    update_post_meta( $booking_id, '_customer_name', $name );
                    update_post_meta( $booking_id, '_customer_id_number', $id_number );
                    update_post_meta( $booking_id, '_customer_email', $email );
                    update_post_meta( $booking_id, '_customer_phone', $phone );
                    update_post_meta( $booking_id, '_order_id', $order->get_id() );
                    update_post_meta( $booking_id, '_price_per_seat', $price_per_seat );
                }
            }
        }
    }

    /* ----------------------------
       Order status change hooks
       When completed: optionally mark reservations as finalized (if needed)
       When cancelled/refunded: revert counts or mark bookings as cancelled
    ---------------------------- */
    public function maybe_finalize_reservations( $order_id ) {
        // optional: mark booking posts as confirmed (we already created them), maybe send email
        // For skeleton, we mark a meta flag
        $this->update_booking_posts_status_by_order( $order_id, 'confirmed' );
    }

    public function maybe_revert_reservations( $order_id ) {
        // mark bookings as cancelled and allow seats to be reused
        $this->update_booking_posts_status_by_order( $order_id, 'cancelled' );
    }

    protected function update_booking_posts_status_by_order( $order_id, $status ) {
        $args = array(
            'post_type' => 'bus_booking',
            'meta_query' => array(
                array( 'key' => '_order_id', 'value' => $order_id ),
            ),
            'posts_per_page' => -1,
        );
        $posts = get_posts( $args );
        foreach ( $posts as $p ) {
            update_post_meta( $p->ID, '_booking_status', $status );
        }
    }

    /* ----------------------------
       Admin page: list bookings summary
    ---------------------------- */
    public function register_admin_pages() {
        add_submenu_page( 'edit.php?post_type=bus_service_day', 'Reservas Bus', 'Reservas', 'manage_options', 'bus_bookings_admin', array( $this, 'render_admin_bookings' ) );
    }

    public function render_admin_bookings() {
        ?>
        <div class="wrap">
            <h1>Reservas de Bus</h1>
            <p>Listado simple de reservas por fecha y horario.</p>
            <?php
            $args = array(
                'post_type' => 'bus_booking',
                'posts_per_page' => 200,
                'orderby' => 'date',
                'order' => 'DESC',
            );
            $bookings = get_posts( $args );
            if ( empty( $bookings ) ) {
                echo '<p>No hay reservas registradas aún.</p>';
                return;
            }
            echo '<table class="widefat">';
            echo '<thead><tr><th>ID</th><th>Fecha</th><th>Horario</th><th>Asientos</th><th>Titular</th><th>Contacto</th><th>Orden</th><th>Estado</th></tr></thead><tbody>';
            foreach ( $bookings as $b ) {
                $day_id = get_post_meta( $b->ID, '_service_day_id', true );
                $date = $day_id ? get_post_meta( $day_id, '_service_date', true ) : '';
                $schedule = get_post_meta( $b->ID, '_schedule', true );
                $seats = get_post_meta( $b->ID, '_seats', true );
                $name = get_post_meta( $b->ID, '_customer_name', true );
                $phone = get_post_meta( $b->ID, '_customer_phone', true );
                $order_id = get_post_meta( $b->ID, '_order_id', true );
                $status = get_post_meta( $b->ID, '_booking_status', true );
                echo '<tr>';
                echo '<td>' . esc_html( $b->ID ) . '</td>';
                echo '<td>' . esc_html( $date ) . '</td>';
                echo '<td>' . esc_html( ucfirst( $schedule ) ) . '</td>';
                echo '<td>' . esc_html( $seats ) . '</td>';
                echo '<td>' . esc_html( $name ) . '</td>';
                echo '<td>' . esc_html( $phone ) . '</td>';
                echo '<td>' . ( $order_id ? '<a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '">#' . $order_id . '</a>' : '' ) . '</td>';
                echo '<td>' . esc_html( $status ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            ?>
        </div>
        <?php
    }
}

new Bus_Booking_Plugin();

/* End of plugin file */

// Cargar script JS en frontend
add_action('wp_enqueue_scripts', function () {
    if (is_page()) { // puedes restringir a páginas específicas
        wp_enqueue_script(
            'bus-booking-js',
            plugin_dir_url(__FILE__) . 'assets/bus-booking.js',
            ['jquery'],
            '1.0',
            true
        );
        wp_localize_script('bus-booking-js', 'BusBookingAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('bus_booking_nonce'),
        ]);
    }
});

// Endpoint AJAX
add_action('wp_ajax_add_bus_booking', 'bus_booking_add_to_cart');
add_action('wp_ajax_nopriv_add_bus_booking', 'bus_booking_add_to_cart');

function bus_booking_is_valid_service_day($date) {
    $day = get_posts([
        'post_type' => 'bus_service_day',
        'meta_key'  => 'fecha_servicio',
        'meta_value'=> $date,
        'post_status' => 'publish',
        'posts_per_page' => 1,
    ]);
    return !empty($day);
}

function bus_booking_add_to_cart() {

    
    check_ajax_referer('bus_booking_nonce', 'nonce');

    $date     = sanitize_text_field($_POST['date']);
    $time     = sanitize_text_field($_POST['time']);
    $quantity = intval($_POST['quantity']);
    $name     = sanitize_text_field($_POST['name']);
    $idnum    = sanitize_text_field($_POST['idnum']);
    $email    = sanitize_email($_POST['email']);
    $phone    = sanitize_text_field($_POST['phone']);

    // Validación básica
    if (!$date || !$time || $quantity < 1) {
        wp_send_json_error(['message' => 'Datos inválidos']);
    }

    // Aquí validamos cupos antes de añadir
    $available = bus_booking_check_availability($date, $time, $quantity);
    if (!$available) {
        wp_send_json_error(['message' => 'No hay suficientes espacios disponibles']);
    }

    // Crear producto virtual temporal
    $product_id = bus_booking_get_virtual_product();

    WC()->cart->add_to_cart(
        $product_id,
        $quantity,
        0,
        [],
        [
            'date'  => $date,
            'time'  => $time,
            'name'  => $name,
            'idnum' => $idnum,
            'email' => $email,
            'phone' => $phone,
        ]
    );

    wp_send_json_success(['message' => 'Reserva añadida al carrito']);
}

function bus_booking_get_virtual_product() {
    // Crea un producto virtual oculto si no existe
    $product_id = get_option('bus_booking_virtual_product_id');
    if (!$product_id || get_post_status($product_id) != 'publish') {
        $product_id = wp_insert_post([
            'post_title'  => 'Reserva de Autobús',
            'post_type'   => 'product',
            'post_status' => 'publish'
        ]);
        wp_set_object_terms($product_id, 'simple', 'product_type');
        update_post_meta($product_id, '_virtual', 'yes');
        update_post_meta($product_id, '_price', '0');
        update_option('bus_booking_virtual_product_id', $product_id);
    }
    return $product_id;
}

function bus_booking_check_availability($date, $time, $quantity) {
    global $wpdb;

    $key = sanitize_key("bus_{$date}_{$time}_reserved");
    $reserved = intval(get_option($key, 0));

    // Capacidad fija: 40 (puede sacarse de ajustes)
    $capacity = get_option('bus_booking_capacity', 40);

    if ($reserved + $quantity > $capacity) {
        return false;
    }

    // Bloqueo simple: incrementa temporalmente
    update_option($key, $reserved + $quantity, false);
    return true;
}

add_action('admin_menu', function(){
    add_options_page(
        'Reservas Autobús',
        'Reservas Autobús',
        'manage_options',
        'bus-booking-settings',
        'bus_booking_settings_page'
    );
});

function bus_booking_settings_page(){
    if(isset($_POST['bus_booking_save'])){
        update_option('bus_booking_capacity', intval($_POST['capacity']));
        echo "<div class='updated'><p>Ajustes guardados</p></div>";
    }

    $capacity = get_option('bus_booking_capacity', 40);
    ?>
    <div class="wrap">
        <h1>Ajustes de Reservas de Autobús</h1>
        <form method="post">
            <label>Capacidad por bus: </label>
            <input type="number" name="capacity" value="<?php echo esc_attr($capacity); ?>">
            <p><input type="submit" name="bus_booking_save" class="button-primary" value="Guardar"></p>
        </form>
    </div>
    <?php
}

add_action('admin_menu', function(){
    add_menu_page(
        'Reservas Autobús',
        'Reservas Autobús',
        'manage_options',
        'bus-booking-calendar',
        'bus_booking_calendar_page',
        'dashicons-calendar',
        26
    );
});

function bus_booking_calendar_page(){
    echo "<div class='wrap'><h1>Calendario de Reservas</h1>";
    
    // Fechas de los próximos 30 días
    $capacity = get_option('bus_booking_capacity', 40);
    for($i=0; $i<30; $i++){
        $date = date('Y-m-d', strtotime("+$i days"));
        $morning = intval(get_option("bus_{$date}_morning_reserved", 0));
        $afternoon = intval(get_option("bus_{$date}_afternoon_reserved", 0));

        echo "<h3>$date</h3>";
        echo "<ul>";
        echo "<li>Mañana: $morning / $capacity</li>";
        echo "<li>Tarde: $afternoon / $capacity</li>";
        echo "</ul>";
    }

    echo "</div>";
}


// Shortcode para mostrar el formulario de reservas
add_shortcode('bus_booking_form', 'bus_booking_form_shortcode');

function bus_booking_form_shortcode() {
    ob_start();
    ?>
    <form id="bus-booking-form" method="post">
        <h3>Reserva tu espacio en el autobús</h3>

        <!-- Fecha -->
        <p>
            <label for="bus_date">Fecha del servicio:</label><br>
            <input type="date" id="bus_date" name="date" required min="<?php echo date('Y-m-d'); ?>">
        </p>

        <!-- Horario -->
        <p>
            <label for="bus_time">Horario:</label><br>
            <select id="bus_time" name="time" required>
                <option value="">-- Selecciona --</option>
                <option value="morning">Mañana</option>
                <option value="afternoon">Tarde</option>
            </select>
        </p>

        <!-- Cantidad de espacios -->
        <p>
            <label for="bus_quantity">Cantidad de espacios:</label><br>
            <input type="number" id="bus_quantity" name="quantity" min="1" max="<?php echo get_option('bus_booking_capacity', 40); ?>" required>
            <!-- <input type="number" id="bus_quantity" name="quantity" min="1" max="40" required> Para que sea una cantidad fija -->

        </p>

        <hr>

        <h4>Datos del titular de la reserva</h4>

        <!-- Nombre completo -->
        <p>
            <label for="bus_name">Nombre completo:</label><br>
            <input type="text" id="bus_name" name="name" required>
        </p>

        <!-- Identificación -->
        <p>
            <label for="bus_idnum">Identificación:</label><br>
            <input type="text" id="bus_idnum" name="idnum" required>
        </p>

        <!-- Correo electrónico -->
        <p>
            <label for="bus_email">Correo electrónico:</label><br>
            <input type="email" id="bus_email" name="email" required>
        </p>

        <!-- Teléfono con extensión -->
        <p>
            <label for="bus_phone">Teléfono (incluye extensión si aplica):</label><br>
            <input type="text" id="bus_phone" name="phone" required>
        </p>

        <p>
            <button type="submit" class="button button-primary">Reservar</button>
        </p>
    </form>
    <?php
    return ob_get_clean();
}
