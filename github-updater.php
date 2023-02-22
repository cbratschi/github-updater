<?php
/**
 * GitHub Updater
 *
 * @author  Andy Fragen
 * @license GPL-2.0+
 * @link    https://github.com/cbratschi/github-updater
 * @package github-updater
 */

/**
 * Plugin Name:       GitHub Updater
 * Plugin URI:        https://github.com/cbratschi/github-updater
 * Description:       A plugin to automatically update GitHub, Bitbucket, GitLab, or Gitea hosted plugins, themes, and language packs. It also allows for remote installation of plugins or themes into WordPress.
 * Version:           9.9.15
 * Author:            Andy Fragen, Christoph Bratschi
 * License:           GNU General Public License v2
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path:       /languages
 * Text Domain:       github-updater
 * Network:           true
 * GitHub Plugin URI: https://github.com/cbratschi/github-updater
 * GitHub Languages:  https://github.com/afragen/github-updater-translations
 * Requires at least: 6.0
 * Tested up to:      6.1.1
 * Requires PHP:      7.4
 */

namespace Fragen\GitHub_Updater;

/*
 * Exit if called directly.
 * PHP version check and exit.
 */
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Check environment.
$ghu_needed = is_admin() || ( strpos($_SERVER[ 'REQUEST_URI' ], '/wp-json/github-updater/') !== false ) || (defined( 'WP_CLI' ) && \WP_CLI);

if ( ! $ghu_needed ) {
    //debug
    /*
    if (function_exists( 'ap_debug' )) {
        ap_debug( 'GHU not needed.' );
    } else {
        error_log( 'GHU not needed. ' . $_SERVER[ 'REQUEST_URI' ] );
    }
    */

    //skip
    return;
}

// Load the Composer autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require __DIR__ . '/vendor/autoload.php';
}

// Check for composer autoloader.
if ( ! class_exists( 'Fragen\GitHub_Updater\Bootstrap' ) ) {
    require_once __DIR__ . '/src/GitHub_Updater/Bootstrap.php';

    ( new Bootstrap( __FILE__ ) )->deactivate_die();
}

// Load plugin.
( new Bootstrap( __FILE__ ) )->run();