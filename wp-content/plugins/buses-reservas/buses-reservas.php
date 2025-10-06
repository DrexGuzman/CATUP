<?php
/**
 * Plugin Name: Buses Reservas
 * Description: Plugin personalizado para gestionar buses y reservas en WooCommerce.
 * Version: 1.3.0
 * Author: Drexler Guzmán Cruz
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * CPT Bus
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
        'labels'        => $labels,
        'public'        => true,
        'menu_icon'     => 'dashicons-bus',
        'supports'      => array( 'title' ),
        'has_archive'   => false,
        'show_in_menu'  => true,
        'show_in_rest'  => true,
    );
    register_post_type( 'bus', $args );
}
add_action( 'init', 'br_register_buses_cpt' );

/**
 * CPT Reservas (se crea sólo para administración)
 */
function br_register_reservas_cpt() {
    $labels = array(
        'name'               => 'Reservas',
        'singular_name'      => 'Reserva',
        'menu_name'          => 'Reservas',
        'add_new'            => 'Añadir',
        'add_new_item'       => 'Añadir reserva',
        'edit_item'          => 'Editar reserva',
        'new_item'           => 'Nueva reserva',
        'view_item'          => 'Ver reserva',
        'search_items'       => 'Buscar reservas',
        'not_found'          => 'No hay reservas',
        'not_found_in_trash' => 'No hay reservas en la papelera'
    );
    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => 'edit.php?post_type=bus',
        'supports'           => array('title'),
        'capability_type'    => 'post',
        'map_meta_cap'       => true
    );
    register_post_type( 'bus_reserva', $args );
}
add_action( 'init', 'br_register_reservas_cpt' );

/**
 * Metabox Bus
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
    add_meta_box(
        'br_bus_reservas_list',
        'Reservas Confirmadas',
        'br_render_bus_reservas_list',
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
    $reservados = intval( get_post_meta( $post->ID, '_br_reservados', true ) );
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
        <input type="number" step="0.01" id="br_precio" name="br_precio" value="<?php echo esc_attr($precio); ?>" min="0" required>
    </p>
    <p><em>Reservados confirmados: <?php echo $reservados; ?></em></p>
    <?php
}

function br_render_bus_reservas_list( $post ) {
    $bus_id = $post->ID;
    $nonce = wp_create_nonce('br_print_reservas');
    $reservas = get_posts(array(
        'post_type'  => 'bus_reserva',
        'numberposts'=> -1,
        'meta_key'   => '_br_bus_id',
        'meta_value' => $bus_id,
        'orderby'    => 'date',
        'order'      => 'ASC'
    ));
    if ( ! $reservas ) {
        echo '<p>No hay reservas confirmadas.</p>';
        return;
    }
    echo '<table class="widefat striped"><thead><tr>
            <th>Pasajero</th>
            <th>Identificación</th>
            <th>Asientos</th>
            <th>Total Pagado</th>
            <th>Estado</th>
            <th>Pedido</th>
        </tr></thead><tbody>';
    foreach ( $reservas as $r ) {
        $nombre  = get_post_meta($r->ID, '_br_nombre', true);
        $ident   = get_post_meta($r->ID, '_br_identificacion', true);
        $asientos= get_post_meta($r->ID, '_br_asientos', true);
        $total   = get_post_meta($r->ID, '_br_total', true);
        $estado  = get_post_meta($r->ID, '_br_estado', true);
        $order_id= get_post_meta($r->ID, '_br_order_id', true);
        $order_link = $order_id ? '<a href="'.esc_url(get_edit_post_link($order_id)).'">#'.$order_id.'</a>' : '-';
        echo '<tr>
            <td>'.esc_html($nombre).'</td>
            <td>'.esc_html($ident).'</td>
            <td>'.intval($asientos).'</td>
            <td>$'.number_format((float)$total,2).'</td>
            <td>'.esc_html(ucfirst($estado)).'</td>
            <td>'.$order_link.'</td>
        </tr>';
    }
    echo '</tbody></table>';
    echo '<p><a class="button" target="_blank" href="'.esc_url(
        admin_url('admin-ajax.php?action=br_print_reservas&bus_id='.$bus_id.'&nonce='.$nonce)
    ).'">Imprimir Lista</a></p>';
}

function br_save_bus_metabox( $post_id ) {
    if ( isset( $_POST['br_capacidad'] ) )
        update_post_meta( $post_id, '_br_capacidad', intval($_POST['br_capacidad']) );
    if ( isset( $_POST['br_fecha'] ) )
        update_post_meta( $post_id, '_br_fecha', sanitize_text_field($_POST['br_fecha']) );
    if ( isset( $_POST['br_precio'] ) )
        update_post_meta( $post_id, '_br_precio', floatval($_POST['br_precio']) );
}
add_action( 'save_post', 'br_save_bus_metabox' );

/**
 * Helpers capacidad
 */
function br_get_reservados( $bus_id ) {
    return intval( get_post_meta( $bus_id, '_br_reservados', true ) );
}
function br_add_reservados( $bus_id, $cantidad ) {
    $actual = br_get_reservados( $bus_id );
    update_post_meta( $bus_id, '_br_reservados', $actual + intval($cantidad) );
}

/**
 * Crear entrada de reserva (CPT) si no existe para item
 */
function br_create_reservation_post( $order_id, $item, $bus_id, $cantidad, $total_item ) {
    $existing = get_posts(array(
        'post_type'  => 'bus_reserva',
        'numberposts'=> 1,
        'meta_query' => array(
            array(
                'key'   => '_br_order_item_id',
                'value' => $item->get_id()
            )
        )
    ));
    if ( $existing ) return;

    $order = wc_get_order($order_id);
    $nombre = $order ? $order->get_formatted_billing_full_name() : '';
    $identificacion = $order ? $order->get_meta('_br_identificacion') : '';
    $fecha_bus = get_post_meta($bus_id,'_br_fecha', true);
    $titulo_bus= get_the_title($bus_id);

    $post_id = wp_insert_post(array(
        'post_type'   => 'bus_reserva',
        'post_title'  => 'Reserva #'.$order_id.' - '.$titulo_bus,
        'post_status' => 'publish'
    ));
    if ( $post_id ) {
        update_post_meta($post_id, '_br_order_id', $order_id);
        update_post_meta($post_id, '_br_order_item_id', $item->get_id());
        update_post_meta($post_id, '_br_bus_id', $bus_id);
        update_post_meta($post_id, '_br_nombre', $nombre);
        update_post_meta($post_id, '_br_identificacion', $identificacion);
        update_post_meta($post_id, '_br_asientos', $cantidad);
        update_post_meta($post_id, '_br_total', $total_item);
        update_post_meta($post_id, '_br_fecha_bus', $fecha_bus);
        update_post_meta($post_id, '_br_estado', 'confirmado');
    }
}

/**
 * Confirmar cupos y crear reservas sólo cuando la orden está pagada
 */
function br_confirm_bus_reservation( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || $order->get_meta('_br_seats_reserved') ) return;
    $added = false;
    foreach ( $order->get_items() as $item ) {
        $bus_id = $item->get_meta('_br_bus_id');
        $cantidad = intval($item->get_meta('_br_cantidad'));
        if ( $bus_id && $cantidad > 0 ) {
            br_add_reservados( $bus_id, $cantidad );
            $added = true;
            $total_item = (float) $item->get_total();
            br_create_reservation_post( $order_id, $item, $bus_id, $cantidad, $total_item );
        }
    }
    if ( $added ) {
        $order->update_meta_data('_br_seats_reserved', 'yes');
        $order->save();
    }
}
add_action( 'woocommerce_order_status_processing', 'br_confirm_bus_reservation' );
add_action( 'woocommerce_order_status_completed', 'br_confirm_bus_reservation' );

/**
 * Liberar cupos (refunded/cancelled) y marcar reservas
 */
function br_release_reservados( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;
    if ( ! $order->get_meta('_br_seats_reserved') ) return;
    foreach ( $order->get_items() as $item ) {
        $bus_id = $item->get_meta('_br_bus_id');
        $cantidad = intval( $item->get_meta('_br_cantidad') );
        if ( $bus_id && $cantidad ) {
            $actual = br_get_reservados( $bus_id );
            $nuevo = max( 0, $actual - $cantidad );
            update_post_meta( $bus_id, '_br_reservados', $nuevo );
        }
        // actualizar reservas asociadas
        $res = get_posts(array(
            'post_type'  => 'bus_reserva',
            'numberposts'=> -1,
            'meta_query' => array(
                array('key'=>'_br_order_id','value'=>$order_id),
                array('key'=>'_br_order_item_id','value'=>$item->get_id())
            )
        ));
        foreach ( $res as $r ) {
            update_post_meta($r->ID, '_br_estado', 'cancelado');
        }
    }
    $order->update_meta_data('_br_seats_reserved', 'released');
    $order->save();
}
add_action( 'woocommerce_order_status_cancelled', 'br_release_reservados' );
add_action( 'woocommerce_order_status_refunded', 'br_release_reservados' );

/**
 * Shortcode listado público con reservas (formulario)
 */
function br_buses_reservas_shortcode() {
    ob_start();
    $mes  = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
    $anio = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y'); ?>
    <form method="get">
        <div  class="br-filtro-fechas">
            <label for="mes">Mes:</label>
        <select name="mes" id="mes">
            <?php for ($m=1;$m<=12;$m++): ?>
                <option value="<?php echo $m; ?>" <?php selected($mes,$m); ?>>
                    <?php echo date_i18n('F', mktime(0,0,0,$m,1)); ?>
                </option>
            <?php endfor; ?>
        </select>
        <label for="anio">Año:</label>
        <select name="anio" id="anio">
            <?php for ($y=date('Y'); $y<=date('Y')+2; $y++): ?>
                <option value="<?php echo $y; ?>" <?php selected($anio,$y); ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
        </div >
        <div class="div-buscar-bus">
            <button class="btn-filtro-fechas" type="submit">Ver buses</button>
        </div>
    </form>
    <?php
    $fecha_inicio = "$anio-$mes-01";
    $fecha_fin    = date("Y-m-t", strtotime($fecha_inicio));
    $args = array(
        'post_type'      => 'bus',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_br_fecha',
                'value'   => array($fecha_inicio, $fecha_fin),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            )
        ),
        'orderby'   => 'meta_value',
        'meta_key'  => '_br_fecha',
        'order'     => 'ASC'
    );
    $buses = new WP_Query($args);
    if ( $buses->have_posts() ) {
        echo '
        <h2>Buses disponibles:</h2>
        <div class="br-lista-buses">';
        while ( $buses->have_posts() ) {
            $buses->the_post();
            $bus_id     = get_the_ID();
            $titulo     = get_the_title();
            $capacidad  = intval(get_post_meta($bus_id,'_br_capacidad', true));
            $fecha      = get_post_meta($bus_id,'_br_fecha', true);
            $precio     = floatval(get_post_meta($bus_id,'_br_precio', true));
            $reservados = br_get_reservados($bus_id);
            $disponibles = max(0, $capacidad - $reservados);
            ?>
            <div class="scpt-card">
                <?php 
                // ID de la imagen cargada en la biblioteca de medios de WordPress
                $img_id = 290; // <-- Cambia este ID por el de tu imagen deseada
                $logo = wp_get_attachment_image_url($img_id, 'medium');
                if ( $logo ) : ?>
                    <div class="scpt-card-logo">
                        <img src="<?php echo esc_url( $logo ); ?>" alt="Imagen fija">
                    </div>
                <?php endif; ?>
                <h3><?php echo esc_html($titulo); ?></h3>
                <div class="scpt-card-info">
                    <p style="margin-bottom: 0;">Fecha: <?php echo date_i18n('d/m/Y', strtotime($fecha)); ?></p>
                    <p style="margin-bottom: 0;">Disponibles: <?php echo $disponibles; ?></p>
                    <p>Precio por persona: $<?php echo number_format($precio,2); ?></p>
                </div>
                <?php if ( $disponibles > 0 ): ?>
                    <button class="scpt-btn br-open-modal"
                        data-bus='<?php echo wp_json_encode(array(
                            "id"    => $bus_id,
                            "titulo"=> $titulo,
                            "fecha" => $fecha,
                            "precio"=> $precio,
                            "max"   => $disponibles
                        )); ?>'>
                        Reservar
                    </button>
                <?php else: ?>
                    <em>Completo</em>
                <?php endif; ?>
            </div>
            <?php
        }
        echo '</div>';
        wp_reset_postdata();
    } else {
        echo "<p>No hay buses disponibles en este mes.</p>";
    } ?>
    <div id="br-modal" class="br-modal">
        <div class="br-modal-content">
            <span class="br-close">&times;</span>
            <div id="br-modal-body"></div>
        </div>
    </div>
    <style>
        .br-modal { display:none; position:fixed; z-index:9999; inset:0; background:rgba(0,0,0,.55); }
        .br-modal-content { background:#fff; margin:5% auto; padding:20px; max-width:520px; border-radius:8px; position:relative; }
        .br-close { position:absolute; top:10px; right:15px; font-size:22px; cursor:pointer; }
        .br-error { background:#ffefef; color:#b10000; padding:8px 10px; border-radius:4px; margin-bottom:10px; font-size:14px; }
        .br-success { background:#e8fff1; color:#0b6b2b; padding:8px 10px; border-radius:4px; margin-bottom:10px; font-size:14px; }
        .br-loading { opacity:.6; pointer-events:none; }
    </style>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const modal = document.getElementById("br-modal");
        const modalBody = document.getElementById("br-modal-body");
        const closeBtn = document.querySelector(".br-close");
        function openModal(html){ modalBody.innerHTML = html; modal.style.display='block'; }
        function closeModal(){ modal.style.display='none'; }

        document.querySelectorAll(".br-open-modal").forEach(btn=>{
            btn.addEventListener("click", ()=>{
                const data = JSON.parse(btn.getAttribute("data-bus"));
                openModal(`
                    <h2>Reserva: ${data.titulo}</h2>
                    <p><strong>Fecha:</strong> ${new Date(data.fecha).toLocaleDateString()}</p>
                    <p><strong>Precio por persona:</strong> $${Number(data.precio).toFixed(2)}</p>
                    <form id="br-form-reserva">
                        <input type="hidden" name="action" value="br_procesar_reserva">
                        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('br_reserva'); ?>">
                        <input type="hidden" name="bus_id" value="${data.id}">
                        <p><label>Cantidad:</label><br>
                           <input type="number" name="cantidad" id="br-cantidad" min="1" max="${data.max}" required></p>
                        <div id="br-total" style="margin-bottom:10px;font-weight:bold;"></div>
                        <p><label>Nombre completo:</label><br><input type="text" name="nombre" required></p>
                        <p><label>Identificación:</label><br><input type="text" name="identificacion" required></p>
                        <p><label>Teléfono:</label><br><input type="text" name="telefono" required></p>
                        <p><label>Correo electrónico:</label><br><input type="email" name="correo" required></p>
                        <div id="br-msg"></div>
                        <button type="submit" id="br-submit">Procesar y pagar</button>
                    </form>
                `);
                const cantidadInput = modalBody.querySelector("#br-cantidad");
                const totalDiv = modalBody.querySelector("#br-total");
                cantidadInput.addEventListener("input", ()=>{
                    const c = parseInt(cantidadInput.value)||0;
                    totalDiv.textContent = c>0 ? `Total: $${(c*Number(data.precio)).toFixed(2)}` : '';
                });
                modalBody.querySelector("#br-form-reserva").addEventListener("submit", function(e){
                    e.preventDefault();
                    const form = e.target;
                    const submitBtn = form.querySelector("#br-submit");
                    const msg = form.querySelector("#br-msg");
                    msg.innerHTML = "";
                    submitBtn.disabled = true;
                    form.classList.add("br-loading");
                    const fd = new FormData(form);
                    fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                        method: "POST",
                        body: fd
                    }).then(r=>r.json()).then(res=>{
                        if(res.success){
                            msg.innerHTML = `<div class="br-success">Redirigiendo a pago...</div>`;
                            window.location.href = res.redirect;
                        } else {
                            msg.innerHTML = `<div class="br-error">${res.message||'Error'}</div>`;
                            submitBtn.disabled = false;
                            form.classList.remove("br-loading");
                        }
                    }).catch(()=>{
                        msg.innerHTML = `<div class="br-error">Error de conexión.</div>`;
                        submitBtn.disabled = false;
                        form.classList.remove("br-loading");
                    });
                });
            });
        });
        closeBtn.addEventListener("click", closeModal);
        window.addEventListener("click", e => { if(e.target===modal) closeModal(); });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('buses_reservas', 'br_buses_reservas_shortcode');

/**
 * AJAX crear orden
 */
function br_ajax_procesar_reserva() {
    if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( $_POST['nonce'], 'br_reserva' ) ) {
        wp_send_json( array('success'=>false,'message'=>'Nonce inválido') );
    }
    if ( ! class_exists('WooCommerce') ) {
        wp_send_json( array('success'=>false,'message'=>'WooCommerce no disponible') );
    }
    $bus_id = intval($_POST['bus_id'] ?? 0);
    $cantidad = intval($_POST['cantidad'] ?? 0);
    $nombre = sanitize_text_field($_POST['nombre'] ?? '');
    $identificacion = sanitize_text_field($_POST['identificacion'] ?? '');
    $telefono = sanitize_text_field($_POST['telefono'] ?? '');
    $correo = sanitize_email($_POST['correo'] ?? '');
    if ( $bus_id <=0 || $cantidad<=0 ) {
        wp_send_json( array('success'=>false,'message'=>'Datos incompletos') );
    }
    $capacidad = intval(get_post_meta($bus_id,'_br_capacidad', true));
    $fecha     = get_post_meta($bus_id,'_br_fecha', true);
    $precio    = floatval(get_post_meta($bus_id,'_br_precio', true));
    $titulo    = get_the_title($bus_id);
    $reservados = br_get_reservados($bus_id);
    $disponibles = max(0, $capacidad - $reservados);
    if ( $cantidad > $disponibles ) {
        wp_send_json( array('success'=>false,'message'=>'No hay suficientes espacios disponibles.') );
    }
    try {
        $order = wc_create_order();
        $item = new WC_Order_Item_Product();
        $item->set_name( sprintf('Reserva Bus: %s (%s)', $titulo, date_i18n('d/m/Y', strtotime($fecha))) );
        $item->set_quantity( $cantidad );
        $item->set_subtotal( $precio * $cantidad );
        $item->set_total( $precio * $cantidad );
        $item->add_meta_data('_br_bus_id', $bus_id, true);
        $item->add_meta_data('_br_fecha', $fecha, true);
        $item->add_meta_data('_br_precio_unitario', $precio, true);
        $item->add_meta_data('_br_cantidad', $cantidad, true);
        $order->add_item( $item );

        $order->set_customer_note( 'Reserva de bus generada automáticamente (pendiente de pago).' );
        $order->set_billing_first_name( $nombre );
        $order->set_billing_last_name( '' );
        $order->set_billing_email( $correo );
        $order->set_billing_phone( $telefono );
        $order->calculate_totals();

        $order->update_meta_data('_br_identificacion', $identificacion);
        $order->update_meta_data('_br_bus_id', $bus_id);
        $order->update_meta_data('_br_bus_fecha', $fecha);
        $order->update_meta_data('_br_bus_cantidad', $cantidad);
        $order->save();

        $redirect = $order->get_checkout_payment_url();
        wp_send_json( array('success'=>true,'redirect'=>$redirect) );
    } catch ( Exception $e ) {
        wp_send_json( array('success'=>false,'message'=>'Error creando la orden: '.$e->getMessage()) );
    }
}
add_action('wp_ajax_br_procesar_reserva', 'br_ajax_procesar_reserva');
add_action('wp_ajax_nopriv_br_procesar_reserva', 'br_ajax_procesar_reserva');

/**
 * Columnas personalizadas en Reservas
 */
function br_reservas_columns( $cols ) {
    $new = array();
    $new['cb'] = $cols['cb'];
    $new['title'] = 'Título';
    $new['br_bus'] = 'Bus';
    $new['br_pasajero'] = 'Pasajero';
    $new['br_asientos'] = 'Asientos';
    $new['br_total'] = 'Total';
    $new['br_estado'] = 'Estado';
    $new['br_pedido'] = 'Pedido';
    $new['date'] = $cols['date'];
    return $new;
}
add_filter('manage_edit-bus_reserva_columns','br_reservas_columns');

function br_reservas_custom_column( $col, $post_id ) {
    switch ( $col ) {
        case 'br_bus':
            $bus_id = get_post_meta($post_id,'_br_bus_id', true);
            if ( $bus_id ) {
                echo '<a href="'.esc_url(get_edit_post_link($bus_id)).'">'.esc_html(get_the_title($bus_id)).'</a>';
            } else echo '-';
            break;
        case 'br_pasajero':
            echo esc_html( get_post_meta($post_id,'_br_nombre', true) );
            break;
        case 'br_asientos':
            echo intval( get_post_meta($post_id,'_br_asientos', true) );
            break;
        case 'br_total':
            $t = (float) get_post_meta($post_id,'_br_total', true);
            echo '$'.number_format($t,2);
            break;
        case 'br_estado':
            echo esc_html( ucfirst(get_post_meta($post_id,'_br_estado', true)) );
            break;
        case 'br_pedido':
            $order_id = get_post_meta($post_id,'_br_order_id', true);
            if ( $order_id ) {
                echo '<a href="'.esc_url(get_edit_post_link($order_id)).'">#'.$order_id.'</a>';
            } else echo '-';
            break;
    }
}
add_action('manage_bus_reserva_posts_custom_column','br_reservas_custom_column',10,2);

/**
 * Filtro por Bus en lista reservas
 */
function br_reservas_bus_filter() {
    global $typenow;
    if ( $typenow === 'bus_reserva' ) {
        $selected = isset($_GET['br_bus_filter']) ? intval($_GET['br_bus_filter']) : 0;
        $buses = get_posts(array('post_type'=>'bus','numberposts'=>-1,'orderby'=>'title','order'=>'ASC'));
        echo '<select name="br_bus_filter"><option value="">Filtrar por Bus</option>';
        foreach ( $buses as $b ) {
            echo '<option value="'.$b->ID.'" '.selected($selected,$b->ID,false).'>'.esc_html($b->post_title).'</option>';
        }
        echo '</select>';
    }
}
add_action('restrict_manage_posts','br_reservas_bus_filter');

function br_reservas_bus_filter_query( $query ) {
    global $pagenow;
    if ( is_admin() && $pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type']=='bus_reserva' && !empty($_GET['br_bus_filter']) ) {
        $query->query_vars['meta_key'] = '_br_bus_id';
        $query->query_vars['meta_value'] = intval($_GET['br_bus_filter']);
    }
}
add_filter('parse_query','br_reservas_bus_filter_query');

/**
 * Vista impresión (admin-ajax)
 */
function br_ajax_print_reservas() {
    if ( ! current_user_can('edit_posts') ) wp_die('Permiso denegado');
    $nonce = $_GET['nonce'] ?? '';
    if ( ! wp_verify_nonce($nonce,'br_print_reservas') ) wp_die('Nonce inválido');
    $bus_id = isset($_GET['bus_id']) ? intval($_GET['bus_id']) : 0;
    if ( ! $bus_id ) wp_die('Bus inválido');
    $bus = get_post($bus_id);
    if ( ! $bus || $bus->post_type !== 'bus') wp_die('Bus no encontrado');

    $reservas = get_posts(array(
        'post_type'=>'bus_reserva',
        'numberposts'=>-1,
        'meta_key'=>'_br_bus_id',
        'meta_value'=>$bus_id,
        'orderby'=>'date',
        'order'=>'ASC'
    ));
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <html>
    <head>
        <title>Reservas - <?php echo esc_html($bus->post_title); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin:20px; }
            h1 { margin-top:0; }
            table { border-collapse: collapse; width:100%; }
            th,td { border:1px solid #444; padding:6px 8px; font-size:13px; }
            th { background:#eee; }
            .small { font-size:11px; color:#555; }
            @media print {
                .no-print { display:none; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="margin-bottom:10px;">
            <button onclick="window.print()">Imprimir</button>
            <button onclick="window.close()">Cerrar</button>
        </div>
        <h1>Reservas Confirmadas</h1>
        <p><strong>Bus:</strong> <?php echo esc_html($bus->post_title); ?><br>
           <strong>Fecha:</strong> <?php echo esc_html( date_i18n('d/m/Y', strtotime(get_post_meta($bus_id,'_br_fecha', true))) ); ?><br>
           <strong>Generado:</strong> <?php echo date_i18n('d/m/Y H:i'); ?></p>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Pasajero</th>
                    <th>Identificación</th>
                    <th>Asientos</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Pedido</th>
                    <th>Firma</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $i=1; $suma_asientos=0; $suma_total=0;
            foreach ( $reservas as $r ):
                $estado = get_post_meta($r->ID,'_br_estado', true);
                if ( $estado !== 'confirmado') continue;
                $nombre  = get_post_meta($r->ID,'_br_nombre', true);
                $ident   = get_post_meta($r->ID,'_br_identificacion', true);
                $asientos= (int)get_post_meta($r->ID,'_br_asientos', true);
                $total   = (float)get_post_meta($r->ID,'_br_total', true);
                $order_id= get_post_meta($r->ID,'_br_order_id', true);
                $suma_asientos += $asientos;
                $suma_total += $total;
                ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td><?php echo esc_html($nombre); ?></td>
                    <td><?php echo esc_html($ident); ?></td>
                    <td><?php echo $asientos; ?></td>
                    <td>$<?php echo number_format($total,2); ?></td>
                    <td><?php echo ucfirst($estado); ?></td>
                    <td>#<?php echo $order_id; ?></td>
                    <td></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" style="text-align:right;">Totales:</th>
                    <th><?php echo $suma_asientos; ?></th>
                    <th>$<?php echo number_format($suma_total,2); ?></th>
                    <th colspan="3"></th>
                </tr>
            </tfoot>
        </table>
        <p class="small">Lista generada automáticamente.</p>
    </body>
    </html>
    <?php
    exit;
}
add_action('wp_ajax_br_print_reservas','br_ajax_print_reservas');