<?php
/**
 * WordPress symbols for Intelephense.
 *
 * This file is not loaded by GitHub Updater. It lets editors resolve
 * WordPress classes that are loaded conditionally from wp-admin.
 *
 * @package github-updater
 */

const ABSPATH = '';
const DISABLE_WP_CRON = false;
const DOING_AJAX = false;
const GITHUB_UPDATER_OVERRIDE_DOT_ORG = false;
const HOUR_IN_SECONDS = 3600;
const REST_REQUEST = false;
const WP_CLI = false;
const WP_CONTENT_DIR = '';
const WP_DEBUG = false;
const WP_PLUGIN_DIR = '';
const WP_PLUGIN_URL = '';

if ( false ) {
    /**
     * Return an unknown WordPress value for editor stubs.
     *
     * @return mixed
     */
    function github_updater_intelephense_mixed( ...$args ) {
        return $args[0] ?? null;
    }

    class WP_Error {
        public $errors = [];

        public function __construct( ...$args ) {
            github_updater_intelephense_mixed( ...$args );
        }

        public function get_error_message( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }

        public function get_error_data( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }
    }

    class WP_REST_Request {
        public function get_params( ...$args ) {
            github_updater_intelephense_mixed( ...$args );

            return [];
        }

        public function get_param( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }

        public function get_header( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }
    }

    class WP_REST_Server {
        public const READABLE  = 'GET';
        public const CREATABLE = 'POST';
    }

    class WP_Upgrader_Skin {
        /**
         * @var mixed
         */
        public $upgrader;

        public function __construct( ...$args ) {
            github_updater_intelephense_mixed( ...$args );
        }

        /**
         * @param mixed $errors Error messages.
         *
         * @return mixed
         */
        public function error( $errors ) {
            return github_updater_intelephense_mixed( $errors );
        }
    }

    class Plugin_Installer_Skin extends WP_Upgrader_Skin {}
    class Theme_Installer_Skin extends WP_Upgrader_Skin {}

    class Plugin_Upgrader {
        public function __construct( ...$args ) {
            github_updater_intelephense_mixed( ...$args );
        }

        public function install( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }

        public function upgrade( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }
    }

    class Theme_Upgrader {
        public function __construct( ...$args ) {
            github_updater_intelephense_mixed( ...$args );
        }

        public function install( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }

        public function upgrade( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }
    }

    class WP_Theme {
        /**
         * @var string
         */
        public $theme_root;

        /**
         * @var string
         */
        public $stylesheet;

        public function is_allowed( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }
    }

    class WP_CLI_Command {}

    class WP_CLI {
        public static function add_command( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }

        public static function success( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }

        public static function error( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }

        public static function warning( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }

        public static function runcommand( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }
    }

    class WP_UnitTestCase {
        public function assertTrue( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }

        public function assertSame( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }

        public function assertEqualSets( ...$args ) {
            return github_updater_intelephense_mixed( ...$args );
        }
    }

    function __( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function _cleanup_header_comment( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function _get_cron_array( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function _get_list_table( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function absint( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function activate_plugin( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function add_action( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function add_filter( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function add_query_arg( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function add_settings_field( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function add_settings_section( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function add_site_option( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function add_submenu_page( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function admin_url( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function apply_filters( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function apply_filters_deprecated( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function checked( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function current_filter( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function current_user_can( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function deactivate_plugins( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function delete_option( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function delete_site_option( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function disabled( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function do_action( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function do_settings_sections( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function esc_attr( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function esc_attr__( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function esc_attr_e( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function esc_attr_x( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function esc_html( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function esc_html__( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function esc_html_e( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function esc_html_x( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function esc_url( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function esc_url_raw( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function get_available_languages( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function get_bloginfo( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function get_file_data( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function get_locale( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function get_plugin_data( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function get_plugins( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function get_site_option( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function get_site_transient( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function get_stylesheet_directory( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function get_theme_root( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function home_url( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function is_admin( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function is_main_site( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function is_multisite( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function is_network_admin( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function is_plugin_active( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function is_rtl( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function is_wp_error( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function load_plugin_textdomain( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function network_admin_url( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function plugin_basename( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function plugins_url( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function register_activation_hook( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function register_deactivation_hook( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function register_rest_route( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function register_setting( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function remove_action( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function remove_filter( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function remove_query_arg( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function rest_get_url_prefix( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function sanitize_file_name( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function sanitize_text_field( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function selected( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function self_admin_url( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function settings_fields( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function submit_button( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function tests_add_filter( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function trailingslashit( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function untrailingslashit( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function update_site_option( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_cache_delete( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_cache_flush( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_cron( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_die( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_enqueue_script( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_enqueue_style( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_get_installed_translations( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_get_theme( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_get_themes( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_kses( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_kses_post( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_next_scheduled( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_nonce_url( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_rand( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_register_script( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_register_style( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_remote_get( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_remote_head( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_remote_retrieve_body( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_remote_retrieve_response_code( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_safe_redirect( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_schedule_single_event( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_send_json_error( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_send_json_success( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_unschedule_event( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
    function wp_unslash( ...$args ) { return github_updater_intelephense_mixed( ...$args ); }
}
