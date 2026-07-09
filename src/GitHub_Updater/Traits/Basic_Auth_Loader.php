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
use Fragen\GitHub_Updater\Install;
use Fragen\GitHub_Updater\API\Bitbucket_API;
use Fragen\GitHub_Updater\API\Bitbucket_Server_API;
use Fragen\GitHub_Updater\API\GitHub_API;
use Fragen\GitHub_Updater\API\GitLab_API;
use Fragen\GitHub_Updater\API\Gist_API;
use Fragen\GitHub_Updater\API\Gitea_API;
use Fragen\GitHub_Updater\API\Language_Pack_API;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Trait Basic_Auth_Loader
 */
trait Basic_Auth_Loader {
    /**
     * Stores array of git servers requiring Basic Authentication.
     *
     * @var array
     */
    private static $basic_auth_required = [ 'Bitbucket', 'GitHub', 'GitLab', 'Gitea' ];

    /**
     * Pre-download packages that need special handling.
     *
     * @param bool|\WP_Error|string $reply      Default false, or a pre-existing result.
     * @param string                $package    Package URL.
     * @param mixed                 $_upgrader  Unused upgrader object.
     * @param array                 $_hook_extra Unused hook data.
     *
     * @return bool|\WP_Error|string
     */
    public function pre_download_package( $reply, $package, $_upgrader = null, $_hook_extra = null ) {
        if ( false !== $reply || ! is_string( $package ) ) {
            return $reply;
        }

        if ( $this->is_bitbucket_cloud_archive_url( $package ) ) {
            $credentials = $this->get_credentials( $package );

            if ( 'bitbucket' === $credentials['type'] && ! $credentials['enterprise'] && ! empty( $credentials['token'] ) ) {
                return $this->download_bitbucket_cloud_archive( $package, $credentials );
            }
        }

        add_filter( 'http_request_args', [ $this, 'download_package' ], 15, 2 );

        return $reply;
    }

    /**
     * Add authentication headers for download packages.
     * Remove authentication headers from release assets.
     * Hooks into 'http_request_args' filter.
     *
     * @param array  $args HTTP GET REQUEST args.
     * @param string $url  URL.
     *
     * @return array
     */
    public function download_package( $args, $url ) {
        if ( isset( $args['filename'] ) && null !== $args['filename'] ) {
            $args = array_merge( $args, $this->add_auth_header( $args, $url ) );
            $args = array_merge( $args, $this->unset_release_asset_auth( $args, $url ) );
        }

        remove_filter( 'http_request_args', [ $this, 'download_package' ] );

        return $args;
    }

    /**
     * Add authentication header to wp_remote_get().
     *
     * @access public
     *
     * @param array  $args Args passed to the URL.
     * @param string $url  The URL.
     *
     * @return array
     */
    public function add_auth_header( $args, $url ) {
        $credentials = $this->get_credentials( $url );

        if ( ! $credentials['isset'] || $credentials['api.wordpress'] ) {
            return $args;
        }

        if ( null !== $credentials['token'] ) {
            if ( 'github' === $credentials['type'] || 'gitea' === $credentials['type'] ) {
                $args['headers']['Authorization'] = 'token ' . $credentials['token'];
            }

            if ( 'bitbucket' === $credentials['type'] ) {
                $token = $credentials['token'];

                if ( false === strpos( $token, ':' ) ) {
                    $args['headers']['Authorization'] = 'Bearer ' . $token;
                } else {
                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                    $args['headers']['Authorization'] = 'Basic ' . base64_encode( $token );
                }
            }

            if ( 'gitlab' === $credentials['type'] ) {
                // https://gitlab.com/gitlab-org/gitlab-foss/issues/63438.
                if ( ! $credentials['enterprise'] ) {
                    // Used in GitLab v12.2 or greater.
                    $args['headers']['Authorization'] = 'Bearer ' . $credentials['token'];
                } else {
                    // Used in versions prior to GitLab v12.2.
                    $args['headers']['PRIVATE-TOKEN'] = $credentials['token'];
                }
            }
        }

        $args['headers'] = isset( $args['headers'] ) ? $args['headers'] : [];

        return $args;
    }

    /**
     * Get credentials for authentication headers.
     *
     * @access private
     *
     * @param string $url The URL.
     *
     * @return array
     */
    private function get_credentials( $url ) {
        $options      = (array) get_site_option( 'github_updater' );
        $headers      = parse_url( $url );
        $headers      = is_array( $headers ) ? $headers : [];
        $host         = isset( $headers['host'] ) ? $headers['host'] : '';
        $credentials  = [
            'api.wordpress' => 'api.wordpress.org' === $host,
            'isset'         => false,
            'token'         => null,
            'type'          => null,
            'enterprise'    => null,
        ];
        $hosts        = [ 'bitbucket.org', 'api.bitbucket.org', 'github.com', 'api.github.com', 'gitlab.com', 'gist.githubusercontent.com' ];

        if ( $credentials['api.wordpress'] ) {
            return $credentials;
        }

        $repos = array_merge(
            Singleton::get_instance( 'Plugin', $this )->get_plugin_configs(),
            Singleton::get_instance( 'Theme', $this )->get_theme_configs()
        );
        $slug  = $this->get_slug_for_credentials( $headers, $repos, $url, $options );
        $type  = $this->get_type_for_credentials( $slug, $repos, $url );

        if ( false === $slug && ! in_array( $host, $hosts, true ) && ! $this instanceof Install ) {
            return $credentials;
        }

        // Set $type for Language Packs.
        if ( $type instanceof Language_Pack_API ) {
            $type_vars = get_object_vars( $type );
            $repo_type = isset( $type_vars['type'] ) && is_object( $type_vars['type'] ) ? $type_vars['type'] : null;
            $type      = $repo_type && isset( $repo_type->git ) ? $repo_type->git : null;
        }

        switch ( $type ) {
            case 'bitbucket':
            case $type instanceof Bitbucket_API:
            case $type instanceof Bitbucket_Server_API:
                $bitbucket_org   = in_array( $host, $hosts, true );
                $bitbucket_token = ! empty( $options['bitbucket_access_token'] ) ? $options['bitbucket_access_token'] : null;
                $bbserver_token  = ! empty( $options['bbserver_access_token'] ) ? $options['bbserver_access_token'] : null;
                $token           = ! empty( $options[ $slug ] ) ? $options[ $slug ] : null;
                $token           = null === $token && $bitbucket_org ? $bitbucket_token : $token;
                $token           = null === $token && ! $bitbucket_org ? $bbserver_token : $token;
                $type            = 'bitbucket';
                break;

            case 'github':
            case 'gist':
            case $type instanceof GitHub_API:
            case $type instanceof Gist_API:
                $token = ! empty( $options['github_access_token'] ) ? $options['github_access_token'] : null;
                $token = ! empty( $options[ $slug ] ) ? $options[ $slug ] : $token;
                $type  = 'github';
                break;

            case 'gitlab':
            case $type instanceof GitLab_API:
                $token = ! empty( $options['gitlab_access_token'] ) ? $options['gitlab_access_token'] : null;
                $token = ! empty( $options[ $slug ] ) ? $options[ $slug ] : $token;
                $type  = 'gitlab';
                break;

            case 'gitea':
            case $type instanceof Gitea_API:
                $token = ! empty( $options['gitea_access_token'] ) ? $options['gitea_access_token'] : null;
                $token = ! empty( $options[ $slug ] ) ? $options[ $slug ] : $token;
                $type  = 'gitea';
        }

        if ( 'bitbucket' !== $type && in_array( $host, [ 'bitbucket.org', 'api.bitbucket.org' ], true ) ) {
            $token = ! empty( $options['bitbucket_access_token'] ) ? $options['bitbucket_access_token'] : null;
            $type  = 'bitbucket';
        }

        $credentials['isset']      = true;
        $credentials['type']       = $type;
        $credentials['token']      = isset( $token ) ? $token : null;
        $credentials['enterprise'] = ! in_array( $host, $hosts, true );

        return $credentials;
    }

    /**
     * Get $slug for authentication header credentials.
     *
     * @param array  $headers Array of headers from parse_url().
     * @param array  $repos   Array of repositories.
     * @param string $url     URL being called by API.
     * @param array  $options Array of site options.
     *
     * @return bool|string
     */
    private function get_slug_for_credentials( $headers, $repos, $url, $options ) {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $slug = isset( $_REQUEST['slug'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['slug'] ) ) : false;
        $slug = ! $slug && isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : $slug;
        $slug = ! $slug && isset( $_REQUEST['theme'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['theme'] ) ) : $slug;

        // Some installers, like TGMPA, pass an array.
        $slug = is_array( $slug ) ? array_pop( $slug ) : $slug;

        $slug = is_string( $slug ) && false !== strpos( $slug, '/' ) ? dirname( $slug ) : $slug;

        // Set for bulk upgrade.
        if ( ! $slug ) {
            $plugins     = isset( $_REQUEST['plugins'] )
                ? array_map( 'dirname', explode( ',', sanitize_text_field( wp_unslash( $_REQUEST['plugins'] ) ) ) )
                : [];
            $themes      = isset( $_REQUEST['themes'] )
                ? explode( ',', sanitize_text_field( wp_unslash( $_REQUEST['themes'] ) ) )
                : [];
            $bulk_update = array_merge( $plugins, $themes );
            if ( ! empty( $bulk_update ) ) {
                $slug = array_filter(
                    $bulk_update,
                    function ( $e ) use ( $url ) {
                        return false !== strpos( $url, $e );
                    }
                );
                $slug = array_pop( $slug );
            }
        }
        // phpcs:enable

        // In case $type set from Base::$caller doesn't match.
        if ( ! $slug && isset( $headers['path'] ) ) {
            $path_arr = explode( '/', $headers['path'] );

            foreach ( $path_arr as $key ) {
                $key = basename( rawurldecode( $key ) ); // For GitLab.

                if ( ! empty( $options[ $key ] ) || array_key_exists( $key, $repos ) ) {
                    $slug = $key;
                    break;
                }
            }
        }

        return $slug;
    }

    /**
     * Get repo type for authentication header credentials.
     *
     * @param string $slug  Repository slug.
     * @param array  $repos Array of repositories.
     * @param string $url   URL being called by API.
     *
     * @return string
     */
    private function get_type_for_credentials( $slug, $repos, $url ) {
        $type = $this->get_class_vars( 'Base', 'caller' );
        $repo = $slug && isset( $repos[ $slug ] ) ? $repos[ $slug ] : null;

        $type = is_object( $repo ) && property_exists( $repo, 'git' )
            ? $repo->git
            : $type;

        // Set for WP-CLI.
        if ( ! $slug ) {
            foreach ( $repos as $repo ) {
                if ( is_object( $repo ) && property_exists( $repo, 'download_link' ) && $url === $repo->download_link ) {
                    $type = $repo->git;
                    break;
                }
            }
        }

        // Set for Remote Install.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $type = isset( $_POST['github_updater_api'], $_POST['github_updater_repo'] )
                && false !== strpos( $url, basename( sanitize_text_field( wp_unslash( $_POST['github_updater_repo'] ) ) ) )
            ? sanitize_text_field( wp_unslash( $_POST['github_updater_api'] ) )
            : $type;
        // phpcs:enable

        return $type;
    }

    /**
     * Removes authentication header for Release Assets.
     * Storage in AmazonS3 buckets, uses Query String Request Authentication Alternative.
     *
     * @access public
     * @link   http://docs.aws.amazon.com/AmazonS3/latest/dev/RESTAuthentication.html#RESTAuthenticationQueryStringAuth
     *
     * @param array  $args The URL arguments passed.
     * @param string $url  The URL.
     *
     * @return array
     */
    public function unset_release_asset_auth( $args, $url ) {
        $aws_host        = false !== strpos( $url, 's3.amazonaws.com' );
        $github_releases = false !== strpos( $url, 'releases/download' )
            || false !== strpos( $url, 'github-releases' );

        if ( $aws_host || $github_releases ) {
            unset( $args['headers']['Authorization'] );
        }

        return $args;
    }

    /**
     * Check whether a URL is a Bitbucket Cloud archive URL.
     *
     * @param string $url URL.
     *
     * @return bool
     */
    private function is_bitbucket_cloud_archive_url( $url ) {
        $headers = parse_url( $url );

        if ( ! is_array( $headers ) || 'bitbucket.org' !== ( isset( $headers['host'] ) ? $headers['host'] : '' ) ) {
            return false;
        }

        $path = isset( $headers['path'] ) ? trim( $headers['path'], '/' ) : '';

        return 1 === preg_match( '#^[^/]+/[^/]+/get/.+\.zip$#', $path );
    }

    /**
     * Download an authenticated Bitbucket Cloud archive.
     *
     * @param string $package     Bitbucket Cloud archive URL.
     * @param array  $credentials Repository credentials.
     *
     * @return string|\WP_Error Local zip path or error.
     */
    private function download_bitbucket_cloud_archive( $package, $credentials ) {
        $this->extend_bitbucket_cloud_download_time_limit();

        $direct = $this->download_bitbucket_cloud_archive_direct( $package, $credentials );
        if ( ! is_wp_error( $direct ) ) {
            return $direct;
        }

        $this->log_bitbucket_cloud_archive_fallback( $package, $direct );

        return $this->download_bitbucket_cloud_source_archive( $package );
    }

    /**
     * Download a Bitbucket Cloud archive directly from the web archive endpoint.
     *
     * @param string $package     Bitbucket Cloud archive URL.
     * @param array  $credentials Repository credentials.
     *
     * @return string|\WP_Error Local zip path or error.
     */
    private function download_bitbucket_cloud_archive_direct( $package, $credentials ) {
        $zip_file = wp_tempnam( $package );
        if ( ! $zip_file ) {
            return new \WP_Error(
                'github_updater_bitbucket_temp_file',
                __( 'Could not create a temporary file for the Bitbucket archive.', 'github-updater' )
            );
        }

        $auth_method = $this->get_bitbucket_cloud_archive_auth_method( $credentials['token'] );
        $redirection = 20;
        $response = wp_remote_get(
            $package,
            [
                'timeout'     => 300,
                'redirection' => $redirection,
                'stream'      => true,
                'filename'    => $zip_file,
                'headers'     => $this->get_bitbucket_cloud_archive_headers( $credentials['token'] ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            $response->add_data(
                [
                    'auth_method'       => $auth_method,
                    'redirection_limit' => $redirection,
                ],
                $response->get_error_code()
            );
            $this->delete_file( $zip_file );

            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            $error_data = [
                'auth_method'       => $auth_method,
                'status'            => $code,
                'content_type'      => $this->get_bitbucket_cloud_archive_response_header( $response, 'content-type' ),
                'location'          => $this->get_bitbucket_cloud_archive_response_header( $response, 'location' ),
                'body'              => $this->get_bitbucket_cloud_archive_error_body( $response, $zip_file ),
                'redirection_limit' => $redirection,
            ];
            $this->delete_file( $zip_file );

            return new \WP_Error(
                'github_updater_bitbucket_archive_response',
                sprintf(
                    /* translators: %d: HTTP status code */
                    __( 'Bitbucket archive download failed with HTTP status %d.', 'github-updater' ),
                    $code
                ),
                $error_data
            );
        }

        return $zip_file;
    }

    /**
     * Get Bitbucket Cloud web archive authentication method.
     *
     * @param string $token Bitbucket token.
     *
     * @return string
     */
    private function get_bitbucket_cloud_archive_auth_method( $token ) {
        return false === strpos( $token, ':' ) ? 'bearer' : 'basic';
    }

    /**
     * Get Bitbucket Cloud web archive authentication headers.
     *
     * @param string $token Bitbucket token.
     *
     * @return array
     */
    private function get_bitbucket_cloud_archive_headers( $token ) {
        if ( false === strpos( $token, ':' ) ) {
            return [
                'Authorization' => 'Bearer ' . $token,
            ];
        }

        return [
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
            'Authorization' => 'Basic ' . base64_encode( $token ),
        ];
    }

    /**
     * Get a response header for Bitbucket Cloud archive diagnostics.
     *
     * @param array  $response HTTP response.
     * @param string $header   Header name.
     *
     * @return string
     */
    private function get_bitbucket_cloud_archive_response_header( $response, $header ) {
        $value = wp_remote_retrieve_header( $response, $header );

        if ( is_array( $value ) ) {
            return implode( ', ', array_map( 'strval', $value ) );
        }

        return is_scalar( $value ) ? (string) $value : '';
    }

    /**
     * Get a safe response body snippet for Bitbucket Cloud archive diagnostics.
     *
     * @param array  $response HTTP response.
     * @param string $file     Streamed response file.
     *
     * @return string
     */
    private function get_bitbucket_cloud_archive_error_body( $response, $file ) {
        $content_type = strtolower( $this->get_bitbucket_cloud_archive_response_header( $response, 'content-type' ) );

        if ( '' !== $content_type && 1 !== preg_match( '#(json|text|html|xml)#', $content_type ) ) {
            return '';
        }

        $body = wp_remote_retrieve_body( $response );
        if ( '' === $body && is_string( $file ) && file_exists( $file ) ) {
            $contents = file_get_contents( $file, false, null, 0, 1000 );
            $body     = false !== $contents ? $contents : '';
        }

        $body = preg_replace( '/\s+/', ' ', trim( strip_tags( (string) $body ) ) );

        return is_string( $body ) ? substr( $body, 0, 500 ) : '';
    }

    /**
     * Log Bitbucket Cloud archive fallback diagnostics.
     *
     * @param string    $package Bitbucket Cloud archive URL.
     * @param \WP_Error $error   Direct archive download error.
     *
     * @return void
     */
    private function log_bitbucket_cloud_archive_fallback( $package, $error ) {
        $data = is_wp_error( $error ) && is_array( $error->get_error_data() ) ? $error->get_error_data() : [];
        $log  = array_merge(
            [
                'event'         => 'bitbucket_archive_direct_failed',
                'package'       => $package,
                'error_code'    => is_wp_error( $error ) ? $error->get_error_code() : '',
                'error_message' => is_wp_error( $error ) ? $error->get_error_message() : '',
                'fallback'      => 'source_api_zip_builder',
            ],
            $data
        );

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( 'GitHub Updater: ' . json_encode( $log ) );
    }

    /**
     * Build a local zip from the Bitbucket Cloud source API.
     *
     * @param string $package Bitbucket Cloud archive URL.
     *
     * @return string|\WP_Error Local zip path or error.
     */
    private function download_bitbucket_cloud_source_archive( $package ) {
        $parts = $this->parse_bitbucket_cloud_archive_url( $package );
        if ( ! $parts ) {
            return new \WP_Error(
                'github_updater_bitbucket_archive_url',
                __( 'Invalid Bitbucket archive URL.', 'github-updater' )
            );
        }

        $zip_file = wp_tempnam( $package );
        if ( ! $zip_file ) {
            return new \WP_Error(
                'github_updater_bitbucket_temp_file',
                __( 'Could not create a temporary file for the Bitbucket archive.', 'github-updater' )
            );
        }

        $root = $parts['owner'] . '-' . $parts['repo'] . '-' . sanitize_file_name( $parts['ref'] ) . '/';

        if ( class_exists( 'ZipArchive' ) ) {
            return $this->download_bitbucket_cloud_source_archive_with_ziparchive( $zip_file, $parts, $root );
        }

        return $this->download_bitbucket_cloud_source_archive_with_pclzip( $zip_file, $parts, $root );
    }

    /**
     * Build the Bitbucket Cloud source archive using ZipArchive.
     *
     * @param string $zip_file Local zip path.
     * @param array  $parts    Archive URL parts.
     * @param string $root     Zip root directory.
     *
     * @return string|\WP_Error Local zip path or error.
     */
    private function download_bitbucket_cloud_source_archive_with_ziparchive( $zip_file, $parts, $root ) {
        $zip = new \ZipArchive();
        if ( true !== $zip->open( $zip_file, \ZipArchive::OVERWRITE ) ) {
            return new \WP_Error(
                'github_updater_bitbucket_zip_open',
                __( 'Could not open a temporary zip file for the Bitbucket archive.', 'github-updater' )
            );
        }

        $result = $this->walk_bitbucket_source_files(
            $parts,
            function ( $path, $contents ) use ( $zip, $root ) {
                if ( true !== $zip->addFromString( $root . $path, $contents ) ) {
                    return new \WP_Error(
                        'github_updater_bitbucket_zip_add',
                        __( 'Could not add a Bitbucket source file to the archive.', 'github-updater' )
                    );
                }

                return true;
            }
        );
        $zip->close();

        if ( is_wp_error( $result ) ) {
            $this->delete_file( $zip_file );

            return $result;
        }

        return $zip_file;
    }

    /**
     * Build the Bitbucket Cloud source archive using WordPress' bundled PclZip.
     *
     * @param string $zip_file Local zip path.
     * @param array  $parts    Archive URL parts.
     * @param string $root     Zip root directory.
     *
     * @return string|\WP_Error Local zip path or error.
     */
    private function download_bitbucket_cloud_source_archive_with_pclzip( $zip_file, $parts, $root ) {
        if ( ! class_exists( 'PclZip' ) && defined( 'ABSPATH' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
        }

        if ( ! class_exists( 'PclZip' ) ) {
            return new \WP_Error(
                'github_updater_bitbucket_zip_support_missing',
                __( 'Bitbucket archive download requires ZipArchive or WordPress PclZip support.', 'github-updater' )
            );
        }

        $source_dir  = trailingslashit( get_temp_dir() ) . 'github-updater-bitbucket-' . uniqid( '', true );
        $source_root = trailingslashit( $source_dir ) . rtrim( $root, '/' );

        if ( ! wp_mkdir_p( $source_root ) ) {
            return new \WP_Error(
                'github_updater_bitbucket_temp_dir',
                __( 'Could not create a temporary directory for the Bitbucket archive.', 'github-updater' )
            );
        }

        $result = $this->walk_bitbucket_source_files(
            $parts,
            function ( $path, $contents ) use ( $source_root ) {
                $target = trailingslashit( $source_root ) . $path;

                if ( ! wp_mkdir_p( dirname( $target ) ) ) {
                    return new \WP_Error(
                        'github_updater_bitbucket_temp_dir',
                        __( 'Could not create a temporary directory for a Bitbucket source file.', 'github-updater' )
                    );
                }

                if ( false === file_put_contents( $target, $contents ) ) {
                    return new \WP_Error(
                        'github_updater_bitbucket_temp_file',
                        __( 'Could not write a Bitbucket source file to the temporary archive directory.', 'github-updater' )
                    );
                }

                return true;
            }
        );

        if ( is_wp_error( $result ) ) {
            $this->delete_directory( $source_dir );
            $this->delete_file( $zip_file );

            return $result;
        }

        $archive = new \PclZip( $zip_file );
        $result  = $archive->create( $source_root, PCLZIP_OPT_REMOVE_PATH, $source_dir );

        $this->delete_directory( $source_dir );

        if ( 0 === $result ) {
            $this->delete_file( $zip_file );

            return new \WP_Error(
                'github_updater_bitbucket_zip_create',
                sprintf(
                    /* translators: %s: PclZip error message */
                    __( 'Could not create Bitbucket archive: %s', 'github-updater' ),
                    $archive->errorInfo( true )
                )
            );
        }

        return $zip_file;
    }

    /**
     * Parse a Bitbucket Cloud archive URL.
     *
     * @param string $package Bitbucket Cloud archive URL.
     *
     * @return array|false
     */
    private function parse_bitbucket_cloud_archive_url( $package ) {
        $headers = parse_url( $package );
        $path    = isset( $headers['path'] ) ? trim( $headers['path'], '/' ) : '';
        $parts   = explode( '/', $path );

        if ( count( $parts ) < 4 || 'get' !== $parts[2] ) {
            return false;
        }

        $ref = implode( '/', array_slice( $parts, 3 ) );
        if ( '.zip' === substr( $ref, -4 ) ) {
            $ref = substr( $ref, 0, -4 );
        }

        return [
            'owner' => rawurldecode( $parts[0] ),
            'repo'  => rawurldecode( $parts[1] ),
            'ref'   => rawurldecode( $ref ),
        ];
    }

    /**
     * Walk Bitbucket source files.
     *
     * @param array    $parts    Archive URL parts.
     * @param callable $callback Callback to receive each file path and contents.
     *
     * @return bool|\WP_Error
     */
    private function walk_bitbucket_source_files( $parts, $callback ) {
        $recursive_depth = 20;
        $result          = $this->walk_bitbucket_source_file_list( $parts, $callback, $recursive_depth );

        if ( is_wp_error( $result ) && 'github_updater_bitbucket_api_timeout' === $result->get_error_code() ) {
            return $this->walk_bitbucket_source_file_list( $parts, $callback );
        }

        return $result;
    }

    /**
     * Walk Bitbucket source files using an optional recursive listing depth.
     *
     * @param array    $parts     Archive URL parts.
     * @param callable $callback  Callback to receive each file path and contents.
     * @param int      $max_depth Recursive listing depth for the root path.
     *
     * @return bool|\WP_Error
     */
    private function walk_bitbucket_source_file_list( $parts, $callback, $max_depth = 0 ) {
        $queue = [ '' ];

        while ( ! empty( $queue ) ) {
            $path          = array_shift( $queue );
            $current_depth = '' === $path ? $max_depth : 0;
            $url           = $this->get_bitbucket_source_url( $parts, $path, $current_depth );

            do {
                $response = $this->bitbucket_api_get( $url );
                if ( is_wp_error( $response ) ) {
                    return $response;
                }

                $code = (int) wp_remote_retrieve_response_code( $response );
                if ( 200 !== $code ) {
                    return new \WP_Error(
                        555 === $code ? 'github_updater_bitbucket_api_timeout' : 'github_updater_bitbucket_api_response',
                        sprintf(
                            /* translators: %d: HTTP status code */
                            __( 'Bitbucket API request failed with HTTP status %d.', 'github-updater' ),
                            $code
                        )
                    );
                }

                $body = json_decode( wp_remote_retrieve_body( $response ) );
                if ( ! is_object( $body ) || ! isset( $body->values ) || ! is_array( $body->values ) ) {
                    return new \WP_Error(
                        'github_updater_bitbucket_api_response',
                        __( 'Bitbucket API returned an invalid source listing.', 'github-updater' )
                    );
                }

                foreach ( $body->values as $entry ) {
                    if ( ! is_object( $entry ) || empty( $entry->path ) || empty( $entry->type ) ) {
                        continue;
                    }

                    if ( 'commit_directory' === $entry->type ) {
                        if ( 0 === $current_depth || $this->is_bitbucket_source_boundary_directory( $entry->path, $current_depth ) ) {
                            $queue[] = $entry->path;
                        }
                        continue;
                    }

                    $links = isset( $entry->links ) && is_object( $entry->links ) ? $entry->links : null;
                    $self  = $links && isset( $links->self ) && is_object( $links->self ) ? $links->self : null;

                    if ( 'commit_file' !== $entry->type || empty( $self->href ) ) {
                        continue;
                    }

                    $file = $this->bitbucket_api_get( $self->href );
                    if ( is_wp_error( $file ) ) {
                        return $file;
                    }

                    $file_code = (int) wp_remote_retrieve_response_code( $file );
                    if ( 200 !== $file_code ) {
                        return new \WP_Error(
                            'github_updater_bitbucket_api_response',
                            sprintf(
                                /* translators: %d: HTTP status code */
                                __( 'Bitbucket API file request failed with HTTP status %d.', 'github-updater' ),
                                $file_code
                            )
                        );
                    }

                    $result = call_user_func( $callback, $entry->path, wp_remote_retrieve_body( $file ) );
                    if ( is_wp_error( $result ) ) {
                        return $result;
                    }
                }

                $url = ! empty( $body->next ) ? $body->next : null;
            } while ( $url );
        }

        return true;
    }

    /**
     * Check whether a directory sits on the recursive listing boundary.
     *
     * @param string $path      Directory path.
     * @param int    $max_depth Recursive listing depth.
     *
     * @return bool
     */
    private function is_bitbucket_source_boundary_directory( $path, $max_depth ) {
        $path_depth = substr_count( trim( $path, '/' ), '/' ) + 1;

        return $path_depth >= $max_depth;
    }

    /**
     * Get a Bitbucket Cloud source API URL.
     *
     * @param array  $parts     Archive URL parts.
     * @param string $path      Source path.
     * @param int    $max_depth Recursive listing depth.
     *
     * @return string
     */
    private function get_bitbucket_source_url( $parts, $path, $max_depth = 0 ) {
        $segments = [
            '2.0',
            'repositories',
            rawurlencode( $parts['owner'] ),
            rawurlencode( $parts['repo'] ),
            'src',
            $this->rawurlencode_path( $parts['ref'] ),
        ];

        if ( '' !== $path ) {
            $segments[] = $this->rawurlencode_path( $path );
        }

        $query = [
            'pagelen' => '100',
            'fields'  => 'values.path,values.type,values.links.self.href,next',
        ];

        if ( $max_depth > 0 ) {
            $query['max_depth'] = (string) $max_depth;
        }

        return add_query_arg(
            $query,
            'https://api.bitbucket.org/' . implode( '/', $segments ) . '/'
        );
    }

    /**
     * Add authentication headers to a Bitbucket API request.
     *
     * @param string $url URL.
     *
     * @return array|\WP_Error
     */
    private function bitbucket_api_get( $url ) {
        $auth_header = $this->add_auth_header( [ 'headers' => [] ], $url );
        $args        = array_merge(
            [
                'timeout' => 30,
            ],
            $auth_header
        );

        return wp_remote_get( $url, $args );
    }

    /**
     * URL-encode each path segment without encoding slashes.
     *
     * @param string $path Path.
     *
     * @return string
     */
    private function rawurlencode_path( $path ) {
        return implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) );
    }

    /**
     * Extend the time available to build authenticated Bitbucket packages.
     *
     * @return void
     */
    private function extend_bitbucket_cloud_download_time_limit() {
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }
    }

    /**
     * Delete a file if it exists.
     *
     * @param string $file File path.
     *
     * @return void
     */
    private function delete_file( $file ) {
        if ( is_string( $file ) && file_exists( $file ) ) {
            unlink( $file );
        }
    }

    /**
     * Delete a directory tree.
     *
     * @param string $dir Directory.
     *
     * @return void
     */
    private function delete_directory( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $iterator as $file ) {
            if ( $file->isDir() ) {
                rmdir( $file->getPathname() );
            } else {
                unlink( $file->getPathname() );
            }
        }

        rmdir( $dir );
    }
}
