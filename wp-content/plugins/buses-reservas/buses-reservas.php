<?php
/**
 * Plugin Name: Buses Reservas
 * Description: Plugin personalizado para gestionar buses y reservas en WooCommerce.
 * Version: 1.0.0
 * Author: Drexler Guzmán Cruz
 */

// Seguridad: evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) exit;

register_activation_hook( __FILE__, 'crear_producto_reserva_bus' );

function crear_producto_reserva_bus() {
    $producto_id = wc_get_product_id_by_sku( 'reserva_bus_oculto' );
    if ( $producto_id ) return;

    $producto = new WC_Product_Simple();
    $producto->set_name( 'Reserva de Bus' );
    $producto->set_status( 'publish' );
    $producto->set_catalog_visibility( 'hidden' );
    $producto->set_price( 0 );
    $producto->set_sku( 'reserva_bus_oculto' );
    $producto->save();
}

/**
 * Registrar el Custom Post Type "Buses"
 */
function br_register_buses_cpt() {
    $labels = array(
        'name'               => 'Buses',
        'singular_name'      => 'Bus',
        'menu_name'          => 'Buses',
        'name_admin_bar'     => 'Bus',
        'add_new'            => 'Añadir nuevo',
        'add_new_item'       => 'Añadir nuevo bus',
        'new_item'           => 'Nuevo bus',
        'edit_item'          => 'Editar bus',
        'view_item'          => 'Ver bus',
        'all_items'          => 'Todos los buses',
        'search_items'       => 'Buscar buses',
        'not_found'          => 'No se encontraron buses',
        'not_found_in_trash' => 'No se encontraron buses en la papelera'
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'menu_icon'          => 'dashicons-bus',
        'supports'           => array( 'title' ),
        'has_archive'        => false,
        'show_in_menu'       => true,
        'show_in_rest'       => true, // Compatible con Gutenberg
    );

    register_post_type( 'bus', $args );
}
add_action( 'init', 'br_register_buses_cpt' );

/**
 * Añadir metaboxes para capacidad, fecha y precio
 */
function br_add_bus_metaboxes() {
    add_meta_box(
        'br_bus_details',
        'Detalles del Bus',
        'br_render_bus_metabox',
        'bus',
        'normal',
        'default'
    );
}
add_action( 'add_meta_boxes', 'br_add_bus_metaboxes' );

function br_render_bus_metabox( $post ) {
    $capacidad = get_post_meta( $post->ID, '_br_capacidad', true );
    $fecha     = get_post_meta( $post->ID, '_br_fecha', true );
    $precio    = get_post_meta( $post->ID, '_br_precio', true );
    ?>
    <p>
        <label for="br_capacidad">Capacidad total de pasajeros:</label><br>
        <input type="number" id="br_capacidad" name="br_capacidad" value="<?php echo esc_attr($capacidad); ?>" min="1" required>
    </p>
    <p>
        <label for="br_fecha">Fecha del viaje:</label><br>
        <input type="date" id="br_fecha" name="br_fecha" value="<?php echo esc_attr($fecha); ?>" required>
    </p>
    <p>
        <label for="br_precio">Precio por persona:</label><br>
        <input type="number" step="5" id="br_precio" name="br_precio" value="<?php echo esc_attr($precio); ?>" min="0" required>
    </p>
    <?php
}

/**
 * Guardar metadatos
 */
function br_save_bus_metabox( $post_id ) {
    if ( isset( $_POST['br_capacidad'] ) ) {
        update_post_meta( $post_id, '_br_capacidad', intval($_POST['br_capacidad']) );
    }
    if ( isset( $_POST['br_fecha'] ) ) {
        update_post_meta( $post_id, '_br_fecha', sanitize_text_field($_POST['br_fecha']) );
    }
    if ( isset( $_POST['br_precio'] ) ) {
        update_post_meta( $post_id, '_br_precio', floatval($_POST['br_precio']) );
    }
}
add_action( 'save_post', 'br_save_bus_metabox' );


/**
 * Shortcode para mostrar formulario de reservas de buses
 * Uso: [buses_reservas]
 */
function br_buses_reservas_shortcode() {
    ob_start();

    // Mes y año seleccionados por el cliente
    $mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
    $anio = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');

    ?>
    <form method="get" class="br-filtro-fechas">
        <label for="mes">Mes:</label>
        <select name="mes" id="mes">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?php echo $m; ?>" <?php selected($mes, $m); ?>>
                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                </option>
            <?php endfor; ?>
        </select>

        <label for="anio">Año:</label>
        <select name="anio" id="anio">
            <?php for ($y = date('Y'); $y <= date('Y') + 2; $y++): ?>
                <option value="<?php echo $y; ?>" <?php selected($anio, $y); ?>>
                    <?php echo $y; ?>
                </option>
            <?php endfor; ?>
        </select>

        <button type="submit">Ver buses</button>
    </form>
    <hr>
    <?php

    // Rango de fechas del mes seleccionado
    $fecha_inicio = "$anio-$mes-01";
    $fecha_fin    = date("Y-m-t", strtotime($fecha_inicio));

    // Buscar buses en el rango
    $args = array(
        'post_type' => 'bus',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_br_fecha',
                'value' => array($fecha_inicio, $fecha_fin),
                'compare' => 'BETWEEN',
                'type' => 'DATE'
            )
        ),
        'orderby' => 'meta_value',
        'meta_key' => '_br_fecha',
        'order' => 'ASC'
    );
    $buses = new WP_Query($args);

    if ($buses->have_posts()) {
        echo '<div class="br-lista-buses">';
        while ($buses->have_posts()) {
            $buses->the_post();
            $bus_id   = get_the_ID();
            $titulo   = get_the_title();
            $capacidad = get_post_meta($bus_id, '_br_capacidad', true);
            $fecha     = get_post_meta($bus_id, '_br_fecha', true);
            $precio    = get_post_meta($bus_id, '_br_precio', true);
            $disponibles = $capacidad; // luego se recalculará con reservas

            ?>
            <div class="br-bus-item">
                <h3><?php echo esc_html($titulo); ?></h3>
                <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($fecha)); ?></p>
                <p><strong>Precio por persona:</strong> $<?php echo number_format($precio, 2); ?></p>
                <p><strong>Espacios disponibles:</strong> <?php echo $disponibles; ?></p>

                <?php if ($disponibles > 0): ?>
                    <button 
                        class="br-open-modal" 
                        data-bus='<?php echo json_encode([
                            "id" => $bus_id,
                            "titulo" => $titulo,
                            "fecha" => $fecha,
                            "precio" => $precio,
                            "max" => $disponibles
                        ]); ?>'>
                        Reservar
                    </button>
                <?php else: ?>
                    <p style="color:red;">No hay espacios disponibles</p>
                <?php endif; ?>
            </div>
            <hr>
            <?php
        }
        echo '</div>';
        wp_reset_postdata();
    } else {
        echo "<p>No hay buses disponibles en este mes.</p>";
    }

    // Modal oculto
    ?>
    <div id="br-modal" class="br-modal">
        <div class="br-modal-content">
            <span class="br-close">&times;</span>
            <div id="br-modal-body"></div>
        </div>
    </div>

    <style>
        .br-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
        }
        .br-modal-content {
            background: #fff;
            margin: 5% auto;
            padding: 20px;
            max-width: 500px;
            border-radius: 8px;
            position: relative;
        }
        .br-close {
            position: absolute;
            top: 10px; right: 15px;
            font-size: 22px;
            cursor: pointer;
        }
    </style>

    <script>
document.addEventListener("DOMContentLoaded", function() {
    const modal = document.getElementById("br-modal");
    const modalBody = document.getElementById("br-modal-body");
    const closeBtn = document.querySelector(".br-close");

    document.querySelectorAll(".br-open-modal").forEach(btn => {
        btn.addEventListener("click", function() {
            const data = JSON.parse(this.getAttribute("data-bus"));

            modalBody.innerHTML = `
                <h2>Reserva para ${data.titulo}</h2>
                <p><strong>Fecha:</strong> ${new Date(data.fecha).toLocaleDateString()}</p>
                <p><strong>Precio por persona:</strong> $${Number(data.precio).toFixed(2)}</p>
                <form method="post" id="br-form-reserva">
                    <input type="hidden" name="bus_id" value="${data.id}">
                    <input type="hidden" name="precio" value="${data.precio}">
                    <p>
                        <label>Cantidad de espacios:</label><br>
                        <input type="number" id="br-cantidad" name="cantidad" min="1" max="${data.max}" required>
                    </p>
                    <div id="br-total" style="margin-bottom:10px; font-weight:bold;"></div>
                    <p>
                        <label>Nombre completo:</label><br>
                        <input type="text" name="nombre" required>
                    </p>
                    <p>
                        <label>Identificación:</label><br>
                        <input type="text" name="identificacion" required>
                    </p>
                    <p>
                        <label>Teléfono:</label><br>
                        <input type="text" name="telefono" required>
                    </p>
                    <p>
                        <label>Correo electrónico:</label><br>
                        <input type="email" name="correo" required>
                    </p>
                    <input type="hidden" name="action" value="agregar_reserva_bus">
                    <button type="submit" name="br_confirmar_reserva">Confirmar y pagar</button>
                </form>
            `;

            modal.style.display = "block";

            // === Calcular total dinámico ===
            const cantidadInput = document.getElementById("br-cantidad");
            const totalDiv = document.getElementById("br-total");
            const precio = parseFloat(data.precio);

            cantidadInput.addEventListener("input", function() {
                const cantidad = parseInt(this.value) || 0;
                if (cantidad > 0) {
                    const total = precio * cantidad;
                    totalDiv.innerHTML = `Total a pagar: $${total.toLocaleString("es-CR", {minimumFractionDigits: 2})}`;
                } else {
                    totalDiv.innerHTML = "";
                }
            });
        });
    });

    closeBtn.addEventListener("click", () => modal.style.display = "none");
    window.addEventListener("click", e => {
        if (e.target === modal) modal.style.display = "none";
    });
});
</script>

    <?php

    return ob_get_clean();
}
add_shortcode('buses_reservas', 'br_buses_reservas_shortcode');


add_action( 'wp_ajax_agregar_reserva_bus', 'agregar_reserva_bus' );
add_action( 'wp_ajax_nopriv_agregar_reserva_bus', 'agregar_reserva_bus' );

function agregar_reserva_bus() {
    $producto_id = wc_get_product_id_by_sku( 'reserva_bus_oculto' );
    if ( ! $producto_id ) {
        wp_send_json_error(['msg' => 'Producto oculto no encontrado']);
    }

    $bus_id      = intval($_POST['bus_id']);
    $fecha       = sanitize_text_field($_POST['fecha']);
    $cantidad    = intval($_POST['cantidad']);
    $precio_unit = floatval($_POST['precio']);
    $nombre      = sanitize_text_field($_POST['nombre']);
    $identificacion = sanitize_text_field($_POST['identificacion']);
    $telefono    = sanitize_text_field($_POST['telefono']);
    $correo      = sanitize_email($_POST['correo']);

    $cart_item_data = array(
        'bus_id'   => $bus_id,
        'fecha'    => $fecha,
        'cantidad' => $cantidad,
        'precio_unit' => $precio_unit,
        'nombre'   => $nombre,
        'identificacion' => $identificacion,
        'telefono' => $telefono,
        'correo'   => $correo,
    );

    WC()->cart->add_to_cart( $producto_id, 1, 0, array(), $cart_item_data );

    wp_send_json_success([ 'redirect' => wc_get_cart_url() ]);
}

add_filter( 'woocommerce_get_item_data', function( $item_data, $cart_item ) {
    if ( isset($cart_item['bus_id']) ) {
        $item_data[] = array('name' => 'Bus', 'value' => 'ID ' . $cart_item['bus_id']);
        $item_data[] = array('name' => 'Fecha', 'value' => $cart_item['fecha']);
        $item_data[] = array('name' => 'Pasajeros', 'value' => $cart_item['cantidad']);
        $item_data[] = array('name' => 'Nombre', 'value' => $cart_item['nombre']);
        $item_data[] = array('name' => 'Teléfono', 'value' => $cart_item['telefono']);
    }
    return $item_data;
}, 10, 2 );

add_action( 'woocommerce_checkout_create_order_line_item', function( $item, $cart_item_key, $values, $order ) {
    if ( isset($values['bus_id']) ) {
        $item->add_meta_data( 'Bus ID', $values['bus_id'] );
        $item->add_meta_data( 'Fecha', $values['fecha'] );
        $item->add_meta_data( 'Pasajeros', $values['cantidad'] );
        $item->add_meta_data( 'Nombre', $values['nombre'] );
        $item->add_meta_data( 'Identificación', $values['identificacion'] );
        $item->add_meta_data( 'Teléfono', $values['telefono'] );
        $item->add_meta_data( 'Correo', $values['correo'] );
    }
}, 10, 4 );

// Ajustar el precio de cada ítem de reserva
add_action( 'woocommerce_before_calculate_totals', function( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['precio_unit'] ) ) {
            $cart_item['data']->set_price( floatval( $cart_item['precio_unit'] ) * intval($cart_item['cantidad']) );
        }
    }
});