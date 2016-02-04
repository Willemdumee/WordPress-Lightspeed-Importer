<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Lightspeed Importer
 * Plugin URI:        http://willemdumee.nl/lightspeed-importer
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.0
 * Author:            Willem Dumee
 * Author URI:        http://willemdumee.nl
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ctrl-lightspeed-importer
 * Domain Path:       /languages
 *
 * Lightspeed Importer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Lightspeed Importer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Easy Digital Downloads. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package           Ctrl_Lightspeed_Importer
 * @link              http://willemdumee.nl
 * @author            Willem Dumee
 * @since             1.0.0

 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' )) {
    die;
}

if ( ! defined( 'WP_LOAD_IMPORTERS' )) {
    return;
}

/** Display verbose errors */
define( 'IMPORT_DEBUG', true );

/** plugin folder path */
if ( ! defined( 'CTRL_LI_PLUGIN_DIR' ) ) {
    define( 'CTRL_LI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/** Load Importer API */
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( ! class_exists( 'WP_Importer' )) {
    $class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
    if (file_exists( $class_wp_importer )) {
        require $class_wp_importer;
    }
}

require_once CTRL_LI_PLUGIN_DIR . 'inc/class-lightspeed-importer.php';

/**
 *  The main function for starting Lightspeed Importer
 */
function ctrl_lightspeed_importer_init()
{
    $lightspeed_importer = Lightspeed_Importer::instance();

    register_importer(
        'lightspeed',
        __( 'Lightspeed Importer', 'lightspeed-importer' ),
        __( 'Import product data from a Lightspeed CSV export file.', 'lightspeed-importer' ),
        array( $lightspeed_importer, 'dispatch' )
    );
}

add_action( 'admin_init', 'ctrl_lightspeed_importer_init' );
