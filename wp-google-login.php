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

if ( version_compare( PHP_VERSION, '8.1.0', '<' ) ) {
    if ( function_exists( 'deactivate_plugins' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
    }
    wp_die( 'wp-google-loginプラグインはPHP 8.1.0以上が必要です。現在のバージョン: ' . PHP_VERSION );
}

// Composer autoload
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
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
        <div style="margin:1em 0;padding:1em;background:#f8f8f8;border-left:4px solid #4285f4;">
            <strong>コールバックURL:</strong><br>
            <code><?php echo esc_html( gsl_redirect_uri() ); ?></code><br>
            <small>このURLをGoogle Cloud Consoleの「承認済みのリダイレクトURI」に登録してください。</small>
            <br><br>
            <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer" style="color:#4285f4;text-decoration:underline;font-weight:bold;">Google Cloud Console（OAuthクライアント設定はこちら）</a>
        </div>
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

add_action( 'login_footer', function () {
    if ( ! gsl_client_id() || ! gsl_client_secret() ) {
        return;
    }
    $client = gsl_google_client();
    $auth   = esc_url( $client->createAuthUrl() );
    echo '<div id="gsl-google-login-btn" style="margin-top:1.5em; text-align:center;">';
    echo '<style>.gsi-material-button{display:inline-flex;align-items:center;justify-content:center;background:#fff;color:#3c4043;border:none;border-radius:4px;box-shadow:0 1px 2px 0 rgba(60,64,67,.3),0 1.5px 5px 0 rgba(60,64,67,.15);padding:0;min-width:240px;min-height:40px;cursor:pointer;transition:box-shadow .2s;outline:none;position:relative;text-decoration:none;}.gsi-material-button:hover{box-shadow:0 2px 4px 0 rgba(60,64,67,.3),0 3px 10px 0 rgba(60,64,67,.15);}.gsi-material-button-content-wrapper{display:flex;align-items:center;width:100%;height:100%;padding:10px 24px;}.gsi-material-button-icon{margin-right:12px;display:flex;align-items:center;}.gsi-material-button-contents{font-family:Roboto,Arial,sans-serif;font-weight:500;font-size:16px;letter-spacing:.25px;}.gsi-material-button-state{position:absolute;top:0;right:0;bottom:0;left:0;border-radius:inherit;}</style>';
    echo '<a href="' . $auth . '" class="gsi-material-button" style="text-decoration:none;">
      <div class="gsi-material-button-state"></div>
      <div class="gsi-material-button-content-wrapper">
        <div class="gsi-material-button-icon">
          <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" xmlns:xlink="http://www.w3.org/1999/xlink" style="display: block;" width="24" height="24">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path>
            <path fill="none" d="M0 0h48v48H0z"></path>
          </svg>
        </div>
        <span class="gsi-material-button-contents">Sign in with Google</span>
        <span style="display: none;">Sign in with Google</span>
      </div>
    </a>';
    echo '</div>';
    // Lost your password? の下に移動
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        var btn = document.getElementById("gsl-google-login-btn");
        var nav = document.getElementById("nav");
        if(btn && nav && nav.parentNode) {
            nav.parentNode.insertBefore(btn, nav.nextSibling);
        }
    });
    </script>';
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

    if ( ! $user ) {
        $domain_roles = gsl_get_option( 'domain_roles', '' );
        if ( empty( trim( $domain_roles ) ) ) {
            gsl_log( 'User creation blocked: no Domain → Role Mapping', [ 'email' => $email ] );
            wp_die( '未登録ユーザーです' );
        }
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

    // Googleプロフィール情報をWPユーザーに反映（未設定時のみ）
    if ( $user ) {
        // First Name
        if ( empty( get_user_meta( $user->ID, 'first_name', true ) ) && !empty($idinfo['given_name']) ) {
            update_user_meta( $user->ID, 'first_name', sanitize_text_field($idinfo['given_name']) );
        }
        // Last Name
        if ( empty( get_user_meta( $user->ID, 'last_name', true ) ) && !empty($idinfo['family_name']) ) {
            update_user_meta( $user->ID, 'last_name', sanitize_text_field($idinfo['family_name']) );
        }
        // Nickname
        if ( empty( $user->nickname ) && !empty($idinfo['name']) ) {
            wp_update_user( [ 'ID' => $user->ID, 'nickname' => sanitize_text_field($idinfo['name']) ] );
        }
        // Profile Picture
        if ( empty( get_user_meta( $user->ID, 'profile_picture', true ) ) && !empty($idinfo['picture']) ) {
            update_user_meta( $user->ID, 'profile_picture', esc_url_raw($idinfo['picture']) );
        }
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