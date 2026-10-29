<?php
/**
 * PHPStan bootstrap: defines the plugin constants.
 *
 * @package ShapeLMS
 */

define( 'SHAPELMS_VERSION', '1.0.0' );
define( 'SHAPELMS_DB_VERSION', '1' );
define( 'SHAPELMS_FILE', __DIR__ . '/../shapelms.php' );
define( 'SHAPELMS_DIR', __DIR__ . '/../' );
define( 'SHAPELMS_URL', 'https://example.com/wp-content/plugins/shapelms/' );
define( 'SHAPELMS_BASENAME', 'shapelms/shapelms.php' );

// Core cookie constants (defined by wp_cookie_constants() at runtime).
define( 'COOKIEHASH', 'phpstan' );
define( 'COOKIEPATH', '/' );
define( 'COOKIE_DOMAIN', '' );
