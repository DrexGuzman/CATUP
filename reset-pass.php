<?php
require_once('wp-load.php'); // carga WordPress
$user_login = 'c4tup-4dm1n';
$new_pass = 'c4tup-4dm1n';

$user = get_user_by('login', $user_login);
if ($user) {
    wp_set_password($new_pass, $user->ID);
    echo "Contraseña cambiada para {$user_login}";
} else {
    echo "Usuario no encontrado.";
}