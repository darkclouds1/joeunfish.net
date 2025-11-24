<?php
define( 'WP_CACHE', true );

define('COOKIE_DOMAIN', false);
define('SITECOOKIEPATH', '/');

define('DISABLE_WP_CRON', false);
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys 
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

//  서버의 파일 시스템에 직접 접근을 시도 -by dc21
//  define('FS_METHOD', 'direct');

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'joeu_fish');
define('DB_USER', 'u753849441_cASrk'); 
define('DB_PASSWORD', 'w3m69P21!@'); //vA6v9E%j0K%rqcCe
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4'); 
define('DB_COLLATE', ''); 

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'meMD]P&&VYkn8;m3Gxlg(:;-PH`0$OW3fZ)O?9KtH,P6ushgp;}3nOu0y9Bpunbv');
define('SECURE_AUTH_KEY',  'z|44OOs-9Dm$kix]j[+c:O*LwtQ=/1O,dB$L:0%mk<`n rv5F[`aFs(MG1Up_:u0');
define('LOGGED_IN_KEY',    '`Ep]qADVM*gT06+MBry+KSru3#@?{-!k+-Y8:{//BY-;#N<0>%7<unT9yPNZAtX4');
define('NONCE_KEY',        'Wol0cK+/2-z-9AZi;s6)&Da<nZMLE ntV-@Z&~* KZ/CMPG@U5++~r*BX<s@Db%r');
define('AUTH_SALT',        '+!SiM|[#;Ic+]4yLkh6*aY@-g*8|p8<{+ j+9AWtlGWP:Y.#@7-~$syE3fIS|1$9');
define('SECURE_AUTH_SALT', 'JJ?Kx9aZx1ig9;)Ml||)NS/Gru4auAp!m=gF^3)g0ax)uXQ_F=-R[C-iys|=5wd6');
define('LOGGED_IN_SALT',   '>izxWdva8KmyrC0S:`Owh5E?]d1fQ_g^qQFf,Yp{U3H*~pf5job|RAN|A|20fFkV');
define('NONCE_SALT',       'GU@c63851?fI}ctRpncPF@{6Odz`c9k|jFUfsX?Y_n;q9gz,`X,EI]#RMX-EBR+T');

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
# WP_DEBUG 활성화하여 자세한 에러 확인 (임시)
define( 'WP_DEBUG', false ); 
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );

/* Add any custom values between this line and the "stop editing" line. */


/* That's all, stop editing! Happy publishing. */
// 고정 도메인 사용
define('WP_HOME', 'https://joeunfish.net');
define('WP_SITEURL', 'https://joeunfish.net');


/** Absolute path to the WordPress directory. */
if (!defined('ABSPATH')) { 
	define('ABSPATH', __DIR__ . '/');
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';