<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater\Traits;

use Fragen\Singleton;

/**
 * Trait GHU_Trait
 *
 * @property \stdClass|null $type
 * @property array $response
 */
trait GHU_Trait {
    /**
     * Variable to hold all repository remote info.
     *
     * @access protected
     * @var array
     */
    protected $response = [];

    /**
     * Checks to see if a heartbeat is resulting in activity.
     *
     * @return bool
     */
    public static function is_heartbeat() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return isset( $_POST['action'] ) && 'heartbeat' === $_POST['action'];
    }

    /**
     * Checks to see if WP_CLI.
     *
     * @return bool
     */
    public static function is_wp_cli() {
        return defined( 'WP_CLI' ) && \WP_CLI;
    }

    /**
     * Checks to see if DOING_AJAX.
     *
     * @return bool
     */
    public static function is_doing_ajax() {
        return defined( 'DOING_AJAX' ) && \DOING_AJAX;
    }

    /**
     * Load site options.
     */
    public function load_options() {
        $base           = Singleton::get_instance( 'Base', $this );
        $base::$options = get_site_option( 'github_updater', [] );
        $base::$options = $this->modify_options( $base::$options );
    }

    /**
     * Check current page.
     *
     * @param  array $pages Array of pages.
     * @return bool
     */
    public function is_current_page( array $pages ) {
        global $pagenow;

        return in_array( $pagenow, $pages, true );
    }

    /**
     * Returns repo cached data.
     *
     * @access protected
     *
     * @param string|bool $repo Repo name or false.
     *
     * @return array|bool The repo cache. False if expired.
     */
    public function get_repo_cache( $repo = false ) {
        if ( ! $repo ) {
            $repo = $this->get_repo_cache_slug();
        }
        $cache_key = 'ghu-' . md5( $repo );
        $cache     = get_site_option( $cache_key );

        if ( empty( $cache['timeout'] ) || time() > $cache['timeout'] ) {
            return false;
        }

        return $cache;
    }

    /**
     * Sets repo data for cache in site option.
     *
     * @access protected
     *
     * @param string      $id       Data Identifier.
     * @param mixed       $response Data to be stored.
     * @param string|bool $repo     Repo name or false.
     * @param string|bool $timeout  Timeout for cache.
     *                              Default is $hours (12 hours).
     *
     * @return bool
     */
    public function set_repo_cache( $id, $response, $repo = false, $timeout = false ) {
        if ( is_wp_error( $response ) ) {
            return false;
        }

        $hours = $this->get_class_vars( 'API', 'hours' );

        if ( ! $repo ) {
            $repo = $this->get_repo_cache_slug();
        }

        $cache_key = 'ghu-' . md5( $repo );
        $timeout   = $timeout ? $timeout : '+' . $hours . ' hours';

        /**
         * Allow filtering of cache timeout for repo information.
         *
         * @since 8.7.1
         *
         * @param string      $timeout  Timeout value used with strtotime().
         * @param string      $id       Data Identifier.
         * @param mixed       $response Data to be stored.
         * @param string|bool $repo     Repo name or false.
         */
        $timeout = apply_filters( 'github_updater_repo_cache_timeout', $timeout, $id, $response, $repo );

        if ( ! is_array( $this->response ) ) {
            $this->response = [];
        }

        $this->response['timeout'] = strtotime( $timeout );
        $this->response[ $id ]     = $response;

        update_site_option( $cache_key, $this->response );

        return true;
    }

    /**
     * Get the current repo slug for cache keys.
     *
     * @return string
     */
    private function get_repo_cache_slug() {
        $type_vars = $this->get_repo_type_vars();

        return ! empty( $type_vars['slug'] ) ? $type_vars['slug'] : 'ghu';
    }

    /**
     * Get repo type data when present on the current object.
     *
     * @return array
     */
    private function get_repo_type_vars() {
        $object_vars = get_object_vars( $this );
        $type        = isset( $object_vars['type'] ) ? $object_vars['type'] : null;

        return is_object( $type ) || is_array( $type ) ? (array) $type : [];
    }

    /**
     * Getter for class variables.
     *
     * @param string $class_name Name of class.
     * @param string $var        Name of variable.
     *
     * @return mixed
     */
    public function get_class_vars( $class_name, $var ) {
        $class          = Singleton::get_instance( $class_name, $this );
        $reflection_obj = new \ReflectionObject( $class );
        if ( ! $reflection_obj->hasProperty( $var ) ) {
            return false;
        }
        $property = $reflection_obj->getProperty( $var );
        if ( \PHP_VERSION_ID < 80100 ) {
            $set_accessible = 'setAccessible';
            $property->$set_accessible( true );
        }

        return $property->getValue( $class );
    }

    /**
     * Returns API error codes.
     *
     * @return array
     */
    public function get_error_codes() {
        return $this->get_class_vars( 'API', 'error_code' );
    }

    /**
     * Function to check if plugin or theme object is able to be updated.
     *
     * @param \stdClass $type Repo object.
     *
     * @return bool
     */
    public function can_update_repo( $type ) {
        $wp_version = get_bloginfo( 'version' );

        $wp_version_ok   = ! empty( $type->requires )
            ? version_compare( $wp_version, $type->requires, '>=' )
            : true;
        $php_version_ok  = ! empty( $type->requires_php )
            ? version_compare( phpversion(), $type->requires_php, '>=' )
            : true;
        $remote_is_newer = isset( $type->remote_version )
            ? version_compare( $type->remote_version, $type->local_version, '>' )
            : false;

        /**
         * Filter $remote_is_newer if you use another method to test for updates.
         *
         * @since 8.7.0
         * @param bool      $remote_is_newer
         * @param \stdClass $type            Plugin/Theme data.
         */
        $remote_is_newer = apply_filters( 'github_updater_remote_is_newer', $remote_is_newer, $type );

        return $remote_is_newer && $wp_version_ok && $php_version_ok;
    }

    /**
     * Delete all `ghu-` prefixed data from options table.
     *
     * @return bool
     */
    public function delete_all_cached_data() {
        global $wpdb;

        $table         = is_multisite() ? $wpdb->base_prefix . 'sitemeta' : $wpdb->base_prefix . 'options';
        $column        = is_multisite() ? 'meta_key' : 'option_name';
        $delete_string = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s LIMIT 1000';

        $wpdb->query( $wpdb->prepare( $delete_string, [ '%ghu-%' ] ) ); // phpcs:ignore

        wp_cron();

        return true;
    }

    /**
     * Is this a private repo with a token/checked or needing token/checked?
     * Test for whether remote_version is set ( default = 0.0.0 ) or
     * a repo option is set/not empty.
     *
     * @param \stdClass $repo Repository.
     *
     * @return bool
     */
    public function is_private( $repo ) {
        if ( ! isset( $repo->remote_version ) && ! self::is_doing_ajax() ) {
            return true;
        }
        if ( isset( $repo->remote_version ) && ! self::is_doing_ajax() ) {
            $options   = property_exists( static::class, 'options' ) && is_array( static::${'options'} ) ? static::${'options'} : [];
            $repo_slug = is_object( $repo ) && property_exists( $repo, 'slug' ) ? $repo->slug : null;

            return ( '0.0.0' === $repo->remote_version ) || ( null !== $repo_slug && ! empty( $options[ $repo_slug ] ) );
        }

        return false;
    }

    /**
     * Do we override dot org updates?
     *
     * @param string    $type (plugin|theme).
     * @param array|\stdClass $repo Repository data.
     *
     * @return bool
     */
    public function override_dot_org( $type, $repo ) {
        // Correctly account for dashicon in Settings page.
        $icon           = is_array( $repo );
        $repo           = is_array( $repo ) ? (object) $repo : $repo;
        $dot_org_master = ! $icon ? is_object( $repo ) && property_exists( $repo, 'dot_org' ) && $repo->dot_org && $repo->primary_branch === $repo->branch : true;

        $transient_key = 'plugin' === $type ? $repo->file : null;
        $transient_key = 'theme' === $type ? $repo->slug : $transient_key;

        /**
         * Filter update to override dot org.
         *
         * @since 8.5.0
         *
         * @return bool
         */
        $override = in_array( $transient_key, apply_filters( 'github_updater_override_dot_org', [] ), true );
        $override = $override || $this->is_override_constant_set();

        // Set $override if set in Skip Updates plugin.
        if ( ! $override && \class_exists( '\\Fragen\\Skip_Updates\\Bootstrap' ) ) {
            $skip_updates = get_site_option( 'skip_updates' );
            foreach ( $skip_updates as $skip ) {
                if ( $repo->file === $skip['slug'] ) {
                    $override = true;
                    break;
                }
            }
        }

        return ! $dot_org_master || $override;
    }

    /**
     * Deprecated dot org override constant.
     *
     * @deprecated 8.5.0
     *
     * @return bool
     */
    public function deprecate_override_constant() {
        return $this->is_override_constant_set();
    }

    /**
     * Is the deprecated dot org override constant set?
     *
     * @return bool
     */
    private function is_override_constant_set() {
        if ( defined( 'GITHUB_UPDATER_OVERRIDE_DOT_ORG' ) && GITHUB_UPDATER_OVERRIDE_DOT_ORG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'GITHUB_UPDATER_OVERRIDE_DOT_ORG constant deprecated. Use `github_updater_override_dot_org` filter hook.' );

            return true;
        }

        return false;
    }

    /**
     * Sanitize each setting field as needed.
     *
     * @param array $input Contains all settings fields as array keys.
     *
     * @return array
     */
    public function sanitize( $input ) {
        $new_input = [];
        foreach ( array_keys( (array) $input ) as $id ) {
            if ( is_string( $id ) ) {
                $new_input[ sanitize_file_name( $id ) ] = sanitize_text_field( $input[ $id ] );
            } else {
                $new_input[ sanitize_text_field( $id ) ] = sanitize_text_field( $input[ $id ] );
            }
        }

        return $new_input;
    }

    /**
     * Return an array of the running git servers.
     *
     * @access public
     * @return array
     */
    public function get_running_git_servers() {
        $plugins = Singleton::get_instance( 'Plugin', $this )->get_plugin_configs();
        $themes  = Singleton::get_instance( 'Theme', $this )->get_theme_configs();

        $repos = array_merge( $plugins, $themes );
        $gits  = array_map(
            function ( $e ) {
                if ( ! empty( $e->enterprise ) ) {
                    if ( 'bitbucket' === $e->git ) {
                        return 'bbserver';
                    }
                    if ( 'gitlab' === $e->git ) {
                        return 'gitlabce';
                    }
                }

                return $e->git;
            },
            $repos
        );

        return array_unique( array_values( $gits ) );
    }

    /**
     * Check to see if wp-cron/background updating has finished.
     *
     * @param null|\stdClass $repo Repo object.
     *
     * @return bool true when waiting for background job to finish.
     */
    protected function waiting_for_background_update( $repo = null ) {
        $caches = [];
        if ( null !== $repo ) {
            $cache = isset( $repo->slug ) ? $this->get_repo_cache( $repo->slug ) : null;

            return empty( $cache );
        }
        $repos = array_merge(
            Singleton::get_instance( 'Plugin', $this )->get_plugin_configs(),
            Singleton::get_instance( 'Theme', $this )->get_theme_configs()
        );
        foreach ( $repos as $git_repo ) {
            $caches[ $git_repo->slug ] = $this->get_repo_cache( $git_repo->slug );
        }
        $waiting = array_filter(
            $caches,
            function ( $e ) {
                return empty( $e );
            }
        );

        return ! empty( $waiting );
    }

    /**
     * Parse URI param returning array of parts.
     *
     * @param string $repo_header Repo URL.
     *
     * @return array
     */
    protected function parse_header_uri( $repo_header ) {
        $header_parts         = parse_url( $repo_header );
        $header_path          = pathinfo( $header_parts['path'] );
        $header['original']   = $repo_header;
        $header['scheme']     = isset( $header_parts['scheme'] ) ? $header_parts['scheme'] : null;
        $header['host']       = isset( $header_parts['host'] ) ? $header_parts['host'] : null;
        $header['owner']      = trim( $header_path['dirname'], '/' );
        $header['repo']       = isset( $header_path['extension'] ) && 'git' === $header_path['extension'] ? $header_path['filename'] : $header_path['basename'];
        $header['owner_repo'] = implode( '/', [ $header['owner'], $header['repo'] ] );
        $header['base_uri']   = str_replace( $header_parts['path'], '', $repo_header );
        $header['uri']        = isset( $header['scheme'] ) ? trim( $repo_header, '/' ) : null;

        $header = $this->sanitize( $header );

        return $header;
    }

    /**
     * Create repo parts.
     *
     * @param string $repo Repo type.
     * @param string $type plugin|theme.
     *
     * @return mixed
     */
    protected function get_repo_parts( $repo, $type ) {
        $extra_repo_headers = $this->get_class_vars( 'Base', 'extra_repo_headers' );

        $arr['bool']    = false;
        $pattern        = '/' . strtolower( $repo ) . '_/';
        $type           = preg_replace( $pattern, '', $type );
        $repo_types     = [
            'GitHub'    => 'github_' . $type,
            'Bitbucket' => 'bitbucket_' . $type,
            'GitLab'    => 'gitlab_' . $type,
            'Gitea'     => 'gitea_' . $type,
            'Gist'      => 'gist_' . $type,
        ];
        $repo_base_uris = [
            'GitHub'    => 'https://github.com/',
            'Bitbucket' => 'https://bitbucket.org/',
            'GitLab'    => 'https://gitlab.com/',
            'Gitea'     => '',
            'Gist'      => 'https://gist.github.com/',
        ];

        if ( array_key_exists( $repo, $repo_types ) ) {
            $arr['type']       = $repo_types[ $repo ];
            $arr['git_server'] = strtolower( $repo );
            $arr['base_uri']   = $repo_base_uris[ $repo ];
            $arr['bool']       = true;
            foreach ( $extra_repo_headers as $key => $value ) {
                $arr[ $key ] = $repo . ' ' . $value;
            }
        }

        return $arr;
    }

    /**
     * Set array with normal repo names.
     * Fix name even if installed without renaming originally, eg <repo>-master
     *
     * @param string            $slug            Repo slug.
     * @param \Fragen\GitHub_Updater\Base|\Fragen\GitHub_Updater\Plugin|\Fragen\GitHub_Updater\Theme|null $upgrader_object Upgrader object.
     *
     * @return array
     */
    protected function get_repo_slugs( $slug, $upgrader_object = null ) {
        $arr    = [];
        $rename = explode( '-', $slug );
        array_pop( $rename );
        $rename = implode( '-', $rename );

        if ( null === $upgrader_object ) {
            $upgrader_object = $this;
        }

        $rename = isset( $upgrader_object->config[ $slug ] ) ? $slug : $rename;
        $config = $this->get_class_vars( ( new \ReflectionClass( $upgrader_object ) )->getShortName(), 'config' );

        foreach ( (array) $config as $repo ) {
            // Check repo slug or directory name for match.
            $slug_check = [
                $repo->slug,
                dirname( $repo->file ),
            ];

            // Exact match.
            if ( \in_array( $slug, $slug_check, true ) ) {
                $arr['slug'] = $repo->slug;
                break;
            }

            // Soft match, there may still be an exact $slug match.
            if ( \in_array( $rename, $slug_check, true ) ) {
                $arr['slug'] = $repo->slug;
            }
        }

        return $arr;
    }

    /**
     * Get default headers plus extra headers.
     *
     * @param string $type plugin|theme.
     *
     * @return array
     */
    public function get_headers( $type ) {
        $default_plugin_headers = [
            'Name'        => 'Plugin Name',
            'PluginURI'   => 'Plugin URI',
            'Version'     => 'Version',
            'Description' => 'Description',
            'Author'      => 'Author',
            'AuthorURI'   => 'Author URI',
            'TextDomain'  => 'Text Domain',
            'DomainPath'  => 'Domain Path',
            'Network'     => 'Network',
            'Requires'    => 'Requires at least',
            'RequiresPHP' => 'Requires PHP',
        ];

        $default_theme_headers = [
            'Name'        => 'Theme Name',
            'ThemeURI'    => 'Theme URI',
            'Description' => 'Description',
            'Author'      => 'Author',
            'AuthorURI'   => 'Author URI',
            'Version'     => 'Version',
            'Template'    => 'Template',
            'Status'      => 'Status',
            'Tags'        => 'Tags',
            'TextDomain'  => 'Text Domain',
            'DomainPath'  => 'Domain Path',
            'Requires'    => 'Requires at least',
            'RequiresPHP' => 'Requires PHP',
        ];

        $default_headers = 'theme' === $type ? $default_theme_headers : $default_plugin_headers;
        $extra_headers   = $this->get_class_vars( 'Base', 'extra_headers' );
        $extra_headers   = is_array( $extra_headers ) ? $extra_headers : [];
        return array_merge( $default_headers, $extra_headers );
    }

    /**
     * Take remote file contents as string or array and parse and reduce headers.
     *
     * @param string|array $contents File contents or array of file headers.
     * @param string       $type     plugin|theme.
     *
     * @return array Reduced array of all headers.
     */
    public function get_file_headers( $contents, $type ) {
        $headers = $this->get_headers( $type );
        $headers = array_unique( $headers );

        /*
         * Make sure we catch CR-only line endings.
         */
        if ( is_string( $contents ) ) {
            $file_data = str_replace( "\r", "\n", $contents );

            foreach ( $headers as $field => $regex ) {
                if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_data, $match ) && $match[1] ) {
                    $headers[ $field ] = _cleanup_header_comment( $match[1] );
                } else {
                    $headers[ $field ] = '';
                }
            }
        }

        $headers = is_array( $contents ) ? $contents : $headers;

        // Reduce array to only headers with data.
        $headers = array_filter( $headers );

        return $headers;
    }

    /**
     * Parse Enterprise, Languages, Release Asset, and CI Job headers for plugins and themes.
     *
     * @param array           $header       Array of repo headers.
     * @param array|\WP_Theme $headers      Array of theme headers.
     * @param array           $header_parts Array of header parts.
     * @param array           $_repo_parts  Unused array of repo parts.
     *
     * @return array
     */
    public function parse_extra_headers( $header, $headers, $header_parts, $_repo_parts ) {
        $extra_repo_headers = $this->get_class_vars( 'Base', 'extra_repo_headers' );
        $hosted_domains     = [ 'github.com', 'bitbucket.org', 'gitlab.com' ];

        $header['enterprise_uri'] = null;
        $header['enterprise_api'] = null;
        $header['languages']      = null;
        $header['ci_job']         = false;
        $header['release_asset']  = false;
        $header['primary_branch'] = false;

        if ( ! empty( $header['host'] ) && ! in_array( $header['host'], $hosted_domains, true ) ) {
            $header['enterprise_uri'] = $header['base_uri'];
            $header['enterprise_api'] = trim( $header['enterprise_uri'], '/' );
            switch ( $header_parts[0] ) {
                case 'GitHub':
                    $header['enterprise_api'] .= '/api/v3';
                    break;
                case 'GitLab':
                    $header['enterprise_api'] .= '/api/v4';
                    break;
                case 'Bitbucket':
                    $header['enterprise_api'] .= '/rest/api';
                    break;
            }
        }

        $self_hosted_parts = array_keys( $extra_repo_headers );
        foreach ( $self_hosted_parts as $part ) {
            if ( ! empty( $headers[ $header_parts[0] . $part ] ) ) {
                switch ( $part ) {
                    case 'Languages':
                        $header['languages'] = $headers[ $header_parts[0] . $part ];
                        break;
                    case 'CIJob':
                        $header['ci_job'] = $headers[ $header_parts[0] . $part ];
                        break;
                }
            }
        }
        $header['release_asset']  = ! $header['release_asset'] && ! empty( $headers['ReleaseAsset'] ) ? 'true' === $headers['ReleaseAsset'] : $header['release_asset'];
        $header['primary_branch'] = ! $header['primary_branch'] && ! empty( $headers['PrimaryBranch'] ) ? $headers['PrimaryBranch'] : 'master';

        return $header;
    }

    /**
     * Checks if dupicate wp-cron event exists.
     *
     * @param string $event Name of wp-cron event.
     *
     * @return bool
     */
    public function is_duplicate_wp_cron_event( $event ) {
        $cron = _get_cron_array() ?: [];
        foreach ( $cron as $timestamp => $cronhooks ) {
            if ( key( $cronhooks ) === $event ) {
                $this->is_cron_overdue( $cron, $timestamp );

                return true;
            }
        }

        return false;
    }

    /**
     * Check to see if wp-cron event is overdue by 24 hours and report error message.
     *
     * @param array $_cron     Unused array of WP-Cron events.
     * @param int   $timestamp WP-Cron event timestamp.
     */
    public function is_cron_overdue( $_cron, $timestamp ) {
        $overdue = ( ( time() - $timestamp ) / HOUR_IN_SECONDS ) > 24;
        if ( $overdue ) {
            $error_msg = esc_html__( 'There may be a problem with WP-Cron. A GitHub Updater WP-Cron event is overdue.', 'github-updater' );
            $error     = new \WP_Error( 'github_updater_cron_error', $error_msg );
            Singleton::get_instance( 'Messages', $this )->create_error_message( $error );
        }
    }

    /**
     * Returns current plugin version.
     *
     * @return string GitHub Updater plugin version
     */
    public static function get_plugin_version() {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $dir         = constant( str_replace( '\Traits', '', __NAMESPACE__ . '\DIR' ) );
        $plugin_data = \get_plugin_data( $dir . '/github-updater.php' );

        return $plugin_data['Version'];
    }

    /**
     * Rename or recursive file copy and delete.
     *
     * This is more versatile than `$wp_filesystem->move()` for FS_METHOD 'direct'.
     * It moves/renames directories as well as files.
     * Fix for https://github.com/afragen/github-updater/issues/826,
     * strange failure of `rename()`.
     *
     * @param string $source      File path of source.
     * @param string $destination File path of destination.
     *
     * @return bool True for success, false for failure.
     */
    public function move( $source, $destination ) {
        if ( $this->filesystem_move( $source, $destination ) ) {
            return true;
        }
        if ( is_dir( $destination ) && rename( $source, $destination ) ) {
            return true;
        }
        // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
        if ( $dir = opendir( $source ) ) {
            if ( ! file_exists( $destination ) ) {
                mkdir( $destination );
            }
            $source = untrailingslashit( $source );
            // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
            while ( false !== ( $file = readdir( $dir ) ) ) {
                if ( ( '.' !== $file ) && ( '..' !== $file ) && "{$source}/{$file}" !== $destination ) {
                    if ( is_dir( "{$source}/{$file}" ) ) {
                        $this->move( "{$source}/{$file}", "{$destination}/{$file}" );
                    } else {
                        copy( "{$source}/{$file}", "{$destination}/{$file}" );
                        unlink( "{$source}/{$file}" );
                    }
                }
            }
            $iterator = new \FilesystemIterator( $source );
            if ( ! $iterator->valid() ) { // True if directory is empty.
                rmdir( $source );
            }
            closedir( $dir );

            return true;
        }

        return false;
    }

    /**
     * Non-direct filesystem move.
     *
     * @uses $wp_filesystem->move() when FS_METHOD is not 'direct'
     *
     * @param string $source      File path of source.
     * @param string $destination File path of destination.
     *
     * @return bool|void True on success, false on failure.
     */
    public function filesystem_move( $source, $destination ) {
        global $wp_filesystem;
        if ( 'direct' !== $wp_filesystem->method ) {
            return $wp_filesystem->move( $source, $destination );
        }

        return false;
    }

    /**
     * Test whether to use release asset.
     *
     * @param bool|string $branch_switch Branch to switch to or false.
     *
     * @return bool
     */
    public function use_release_asset( $branch_switch = false ) {
        $type                    = $this->get_repo_type_vars();
        $branches                = ! empty( $type['branches'] ) && is_array( $type['branches'] ) ? $type['branches'] : [];
        $primary_branch          = isset( $type['primary_branch'] ) ? $type['primary_branch'] : null;
        $branch                  = isset( $type['branch'] ) ? $type['branch'] : null;
        $newest_tag              = isset( $type['newest_tag'] ) ? $type['newest_tag'] : '0.0.0';
        $is_tag                  = $branch_switch && ! empty( $branches ) && ! array_key_exists( $branch_switch, $branches );
        $switch_master_tag       = $primary_branch === $branch_switch || $is_tag;
        $current_master_noswitch = $primary_branch === $branch && false === $branch_switch;

        $need_release_asset = $switch_master_tag || $current_master_noswitch;
        $use_release_asset  = ! empty( $type['release_asset'] ) && '0.0.0' !== $newest_tag
            && $need_release_asset;

        return $use_release_asset;
    }

    /**
     * Modify options without saving.
     *
     * Check if a filter effecting a checkbox is set elsewhere.
     * Adds value '-1' without saving so that checkbox is checked and disabled.
     *
     * @param  array $options Site options.
     * @return array
     */
    private function modify_options( $options ) {
        // Remove any inadvertently saved options with value -1.
        $options = array_filter(
            $options,
            function ( $e ) {
                return '-1' !== $e;
            }
        );

        // Check if filter set elsewhere.
        if ( ! isset( $options['bypass_background_processing'] ) && apply_filters( 'github_updater_disable_wpcron', false ) ) {
            $options['bypass_background_processing'] = '-1';
        }

        return $options;
    }
}
