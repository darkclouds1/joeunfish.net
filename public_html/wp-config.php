<?php

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
define('AUTH_KEY',         'OQ~Qf894tW8*-VrKo#>@k+ YI6rAZ7rB.q]+*-+e3b`knTSWvjmR `cC9h6<UHQ-');
define('SECURE_AUTH_KEY',  '}<fTE*9j1M<*CkWGfhVWYT,AdF&=v)E,3zolXvhMhMgISb{JWQ!bor_:$+2J7us:');
define('LOGGED_IN_KEY',    'lBg>+F?/8F-_sUQrhDdovk%9#W[u|.-R(Bb[,qf~e92_eTW@6_U@!6 PT`RY|=oC');
define('NONCE_KEY',        '+>-.gl_8Z#~v|<TMVxMc-/=U-.!0|Rx`-PSu-(oliJtTi=W5P[b3|HGY^,!$4EI8');
define('AUTH_SALT',        'OEjqG]T3XMY:N3hKqr46S^wU|]y+clig|sPktg%)AA]q]-)N#s/weITO6@h~Z<MA');
define('SECURE_AUTH_SALT', '^i(,FsEZ]eAD=!.n|H,xiu( 8;z;dyq+;a%WVmi@SRe-(c9(_{I6k;gpF(A%Y#o@');
define('LOGGED_IN_SALT',   'W6i&Smh/ix`v !&,pzs+{W/?a#MnZ;]Vt}5yK)#MF$X1t_9>Ka*>!BBQv!2/@YTo');
define('NONCE_SALT',       'G|2|Y:1&<!tt1Q62i+[+qY*;tU16N,2bX_ikdE.]+fkEm?1zd-xDL@]$#Ntd{Bp-');

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

/** Absolute path to the WordPress directory. */
if (!defined('ABSPATH')) { 
	define('ABSPATH', __DIR__ . '/');
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

// 고정 도메인 사용
define('WP_HOME', 'https://joeunfish.net');
define('WP_SITEURL', 'https://joeunfish.net');

// 현재 도메인 사용
// define('WP_HOME', 'https://' . $_SERVER['HTTP_HOST']);
// define('WP_SITEURL', 'https://' . $_SERVER['HTTP_HOST']);
