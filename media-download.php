<?php

/**
 * Plugin Name: Media Library File Download
 * Description: A lightweight plugin that adds one-click download functionality to your Media Library.
 * Author: Calculabs
 * Author URI: https://calculabs.com
 * Version: 1.4
 * Text Domain: media-download
 *
 * Requires at least: 4.7
 * Tested up to: 6.2
 *
 * Requires PHP: 7.4
 * PHP tested up to: 8.1
 *
 * Domain Path: /languages/
 */
defined( 'ABSPATH' ) || exit;

if ( !function_exists( 'aagk_mlfd_fs' ) ) {
    // Create a helper function for easy SDK access.
    function aagk_mlfd_fs()
    {
        global  $aagk_mlfd_fs ;
        
        if ( !isset( $aagk_mlfd_fs ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/lib/freemius/start.php';
            $aagk_mlfd_fs = fs_dynamic_init( array(
                'id'             => '6101',
                'slug'           => 'media-download',
                'premium_slug'   => 'media-download-pro',
                'type'           => 'plugin',
                'public_key'     => 'pk_511f89dd4ac1e3fc4c17b1d19e6bf',
                'is_premium'     => false,
                'premium_suffix' => 'PRO',
                'has_addons'     => false,
                'has_paid_plans' => true,
                'trial'          => array(
                'days'               => 3,
                'is_require_payment' => false,
            ),
                'menu'           => array(
                'first-path' => 'plugins.php',
                'contact'    => false,
                'support'    => false,
            ),
                'is_live'        => true,
            ) );
        }
        
        return $aagk_mlfd_fs;
    }
    
    // Init Freemius.
    aagk_mlfd_fs();
    // Signal that SDK was initiated.
    do_action( 'aagk_mlfd_fs_loaded' );
    require_once __DIR__ . '/class-media-download.php';
    AAGK_Media_Download::bootstrap();
    // Go
} else {
    aagk_mlfd_fs()->set_basename( false, __FILE__ );
}

if ( !class_exists( 'ComposerAutoloaderInit5735f4b3d1fcc674adb6221eee8f399a' ) ) {
    require_once dirname( __FILE__ ) . '/lib/media/vendor/autoload.php';
}
