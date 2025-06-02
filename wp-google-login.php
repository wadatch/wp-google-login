<?php
/*
Plugin Name: wp-google-login
Plugin URI: https://github.com/wadatch/wp-google-login
Description: GoogleアカウントでWordPressにログインできるシンプルなプラグインです。
Version: 1.0.0
Author: wadatch
Author URI: https://github.com/wadatch
License: MIT
Text Domain: wp-google-login
*/

// Composer autoload
if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[GSL] vendor/autoload.php not found. Run "composer install".' );
    }
}

use Google\Client as Google_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// === Constants & Options ==================================================

const GSL_OPTION_KEY = 'gsl_options';

function gsl_get_option( $key, $default = '' ) {
    $opts = get_option( GSL_OPTION_KEY, [] );
    return isset( $opts[ $key ] ) ? $opts[ $key ] : $default;
}

function gsl_client_id() { return gsl_get_option( 'client_id' ); }
function gsl_client_secret() { return gsl_get_option( 'client_secret' ); }
function gsl_redirect_uri() { return wp_login_url() . '?google_oauth_callback=1'; }

// === Logging helper =======================================================

function gsl_log( $message, $context = [] ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[GSL] ' . $message . ' ' . ( $context ? wp_json_encode( $context ) : '' ) );
    }
}

// === Google Client factory ===============================================

if ( ! function_exists( 'gsl_google_client' ) ) {
    function gsl_google_client() : Google_Client {
        $client = new Google_Client();
        $client->setClientId( gsl_client_id() );
        $client->setClientSecret( gsl_client_secret() );
        $client->setRedirectUri( gsl_redirect_uri() );
        $client->setScopes( [ 'email', 'profile', 'openid' ] );
        $client->setAccessType( 'online' );
        return $client;
    }
}

// === Admin settings page ========================================================

add_action( 'admin_menu', function () {
    add_options_page(
        'Google Simple Login',
        'Google Simple Login',
        'manage_options',
        'google-simple-login',
        'gsl_settings_page'
    );
} );

add_action( 'admin_init', function () {
    register_setting( 'gsl_options_group', GSL_OPTION_KEY, 'gsl_options_validate' );

    add_settings_section(
        'gsl_general',
        'General Settings',
        '__return_false',
        'google-simple-login'
    );

    add_settings_field(
        'gsl_client_id',
        'Google Client ID',
        'gsl_field_client_id',
        'google-simple-login',
        'gsl_general'
    );

    add_settings_field(
        'gsl_client_secret',
        'Google Client Secret',
        'gsl_field_client_secret',
        'google-simple-login',
        'gsl_general'
    );

    add_settings_field(
        'gsl_domain_roles',
        'Domain → Role Mapping',
        'gsl_field_domain_roles',
        'google-simple-login',
        'gsl_general'
    );
} );

function gsl_field_client_id() {
    printf(
        '<input type="text" name="%s[client_id]" value="%s" class="regular-text" />',
        esc_attr( GSL_OPTION_KEY ),
        esc_attr( gsl_client_id() )
    );
}

function gsl_field_client_secret() {
    printf(
        '<input type="text" name="%s[client_secret]" value="%s" class="regular-text" />',
        esc_attr( GSL_OPTION_KEY ),
        esc_attr( gsl_client_secret() )
    );
}

function gsl_field_domain_roles() {
    $val = gsl_get_option( 'domain_roles', '' );
    echo '<textarea name="' . esc_attr( GSL_OPTION_KEY ) . '[domain_roles]" rows="5" cols="60" class="large-text code" placeholder="example.com=editor&#10;sub.example.com=author">' . esc_textarea( $val ) . '</textarea>';
    echo '<p class="description">One mapping per line: <code>domain.tld=role</code>. First match wins.</p>';
}

function gsl_options_validate( $input ) {
    $output = [];
    $output['client_id']     = sanitize_text_field( $input['client_id'] ?? '' );
    $output['client_secret'] = sanitize_text_field( $input['client_secret'] ?? '' );
    $output['domain_roles']  = sanitize_textarea_field( $input['domain_roles'] ?? '' );
    return $output;
}

function gsl_settings_page() {
    ?>
    <div class="wrap">
        <h1>Google Simple Login</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'gsl_options_group' );
            do_settings_sections( 'google-simple-login' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// === Login form button ====================================================

add_action( 'login_form', function () {
    if ( ! gsl_client_id() || ! gsl_client_secret() ) {
        return;
    }
    $client = gsl_google_client();
    $auth   = esc_url( $client->createAuthUrl() );
    echo '<p style="text-align:center; margin-top:1em;">';
    echo '<a class="button button-primary button-large" href="' . $auth . '">Google でログイン</a>';
    echo '</p>';
} );

// === Google One Tap =======================================================

add_action( 'login_enqueue_scripts', function () {
    if ( ! is_login() ) { return; }
    if ( ! gsl_client_id() ) { return; }

    wp_enqueue_script(
        'gsl-google-accounts',
        'https://accounts.google.com/gsi/client',
        [],
        null,
        true
    );
    $inline = "
    window.gslOneTapInit = function() {
        if (typeof google === 'undefined' || !google.accounts || !google.accounts.id) { return; }
        google.accounts.id.initialize({
            client_id: '" . esc_js( gsl_client_id() ) . "',
            callback: (response) => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.pathname + '?google_onetap=1';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'id_token';
                input.value = response.credential;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        });
        google.accounts.id.prompt();
    };
    window.addEventListener('load', window.gslOneTapInit);
    ";
    wp_add_inline_script( 'gsl-google-accounts', $inline );
} );

// === OAuth & One Tap callbacks ===========================================

add_action( 'init', function () {
    if ( isset( $_GET['google_oauth_callback'], $_GET['code'] ) ) {
        gsl_handle_oauth_callback( sanitize_text_field( $_GET['code'] ) );
    }
    if ( isset( $_GET['google_onetap'], $_POST['id_token'] ) ) {
        gsl_handle_id_token( sanitize_text_field( $_POST['id_token'] ) );
    }
} );

function gsl_handle_oauth_callback( $code ) {
    $client = gsl_google_client();
    $token  = $client->fetchAccessTokenWithAuthCode( $code );

    if ( isset( $token['error'] ) ) {
        gsl_log( 'OAuth token error', $token );
        wp_die( 'Google 認証失敗: ' . esc_html( $token['error_description'] ?? $token['error'] ) );
    }

    $idinfo = $client->verifyIdToken( $token['id_token'] );
    gsl_handle_idinfo( $idinfo );
}

function gsl_handle_id_token( $id_token ) {
    $client = gsl_google_client();
    $idinfo = $client->verifyIdToken( $id_token );
    gsl_handle_idinfo( $idinfo );
}

function gsl_handle_idinfo( $idinfo ) {
    if ( ! $idinfo || ! isset( $idinfo['email'], $idinfo['sub'] ) ) {
        gsl_log( 'ID token verification failed', [ 'idinfo' => $idinfo ] );
        wp_die( 'ID トークン検証に失敗しました。' );
    }

    $email = sanitize_email( $idinfo['email'] );
    $sub   = sanitize_text_field( $idinfo['sub'] );

    $user = get_user_by( 'email', $email ); // by email

    if ( ! $user ) { // by sub
        $users = get_users( [
            'meta_key'   => 'google_sub',
            'meta_value' => $sub,
            'number'     => 1,
            'fields'     => 'all',
        ] );
        $user = $users[0] ?? null;
    }

    if ( ! $user ) { // create
        $random_pwd = wp_generate_password( 32, true, true );
        $user_id    = wp_create_user( $email, $random_pwd, $email );
        if ( is_wp_error( $user_id ) ) {
            gsl_log( 'User creation failed', [ 'error' => $user_id->get_error_message() ] );
            wp_die( 'ユーザー作成に失敗しました: ' . esc_html( $user_id->get_error_message() ) );
        }
        $user = get_user_by( 'id', $user_id );
    }

    if ( ! get_user_meta( $user->ID, 'google_sub', true ) ) {
        update_user_meta( $user->ID, 'google_sub', $sub );
    }

    gsl_maybe_assign_role( $user );
    wp_set_auth_cookie( $user->ID, true );

    do_action( 'gsl_after_login', $user->ID, $idinfo );

    wp_redirect( admin_url() );
    exit;
}

// === Domain→Role assignment ==============================================

function gsl_maybe_assign_role( WP_User $user ) {
    $map_raw = gsl_get_option( 'domain_roles', '' );
    if ( ! $map_raw ) { return; }

    $lines = array_filter( array_map( 'trim', explode( "\n", $map_raw ) ) );
    $mapping = [];
    foreach ( $lines as $line ) {
        if ( strpos( $line, '=' ) !== false ) {
            list( $domain, $role ) = array_map( 'trim', explode( '=', $line, 2 ) );
            if ( $domain && $role ) {
                $mapping[ strtolower( $domain ) ] = $role;
            }
        }
    }

    if ( empty( $mapping ) ) { return; }

    $email_domain = strtolower( substr( strrchr( $user->user_email, '@' ), 1 ) );

    foreach ( $mapping as $domain => $role ) {
        if ( $email_domain === $domain || substr( $email_domain, -strlen( $domain ) ) === $domain ) {
            if ( ! in_array( $role, $user->roles, true ) ) {
                $user->set_role( $role );
            }
            break;
        }
    }
} 