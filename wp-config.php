<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
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

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'Multiple-Klaviyo-accounts' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

if ( !defined('WP_CLI') ) {
    define( 'WP_SITEURL', $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] );
    define( 'WP_HOME',    $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] );
}



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
define( 'AUTH_KEY',         'EiyONHFylQ1XsawBHiIHg41SJ1acSJthA8N4RbvL9g3k1EzUwLVtNL2ATZ8YOKPe' );
define( 'SECURE_AUTH_KEY',  'RAlqF7C0PanMKjRipxDmWVQeYtOYK6szsuykkDLBaGkPrErmfmXtEpULd2WEN7vJ' );
define( 'LOGGED_IN_KEY',    'UbuqMznXmccyxmyUrJ6lctSW4UzjPWK32UMualTY7TlctlTnixZTLElOs4Q6oJTZ' );
define( 'NONCE_KEY',        'SQxtsNzSqgqBFK2CyIA4Hm1sYiYs5FowAoUDtiBQ8ss8zdKSxkP6kpJ0ODHqSjtt' );
define( 'AUTH_SALT',        'RkWBwFCZxZ2gzmIX78D9l8HP4O0aKBHJxnfhabwQ9n7C6RutIZjLNwOPpDbz5BsA' );
define( 'SECURE_AUTH_SALT', 'm6NQYT0uwdVrMt0gS2x8lRPUOwP0HMGx89Pbb7wLb25ghJf1Tea3RsUbVzAmetBK' );
define( 'LOGGED_IN_SALT',   'isr52buVfDkYNFRjXCbioPbYU9qeZN2vtFhkKadohFlUijhdbm8XGrEvTyEc1taH' );
define( 'NONCE_SALT',       'BMa0QeWSCVqD3smRq7tTciCiZoogIfmbM3aE4vesoBRoj6ORKGQAsNE5NIax29nc' );

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
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
