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
                return $this->download_bitbucket_cloud_archive( $package );
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
        if ( null !== $args['filename'] ) {
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
                // Bitbucket basic auth (see https://developer.atlassian.com/server/bitbucket/how-tos/example-basic-authentication/).
                $token = $credentials['token'];

                if ( false === strpos( $token, ':' ) ) {
                    $bitbucket_host = parse_url( $url, PHP_URL_HOST );

                    if ( 'api.bitbucket.org' === $bitbucket_host ) {
                        $args['headers']['Authorization'] = 'Bearer ' . $token;
                    } else {
                        // Repository/workspace/project access tokens use x-token-auth for Git-style downloads.
                        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                        $args['headers']['Authorization'] = 'Basic ' . base64_encode( 'x-token-auth:' . $token );
                    }
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
     * Build a local zip from the Bitbucket Cloud source API.
     *
     * API tokens cannot authenticate a browser login at bitbucket.org, so private
     * archive downloads need to use the REST API and hand WordPress a local file.
     *
     * @param string $package Bitbucket Cloud archive URL.
     *
     * @return string|\WP_Error Local zip path or error.
     */
    private function download_bitbucket_cloud_archive( $package ) {
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
            return $this->download_bitbucket_cloud_archive_with_ziparchive( $zip_file, $parts, $root );
        }

        return $this->download_bitbucket_cloud_archive_with_pclzip( $zip_file, $parts, $root );
    }

    /**
     * Build the Bitbucket Cloud archive using ZipArchive.
     *
     * @param string $zip_file Local zip path.
     * @param array  $parts    Archive URL parts.
     * @param string $root     Zip root directory.
     *
     * @return string|\WP_Error Local zip path or error.
     */
    private function download_bitbucket_cloud_archive_with_ziparchive( $zip_file, $parts, $root ) {
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
            return $result;
        }

        return $zip_file;
    }

    /**
     * Build the Bitbucket Cloud archive using WordPress' bundled PclZip.
     *
     * @param string $zip_file Local zip path.
     * @param array  $parts    Archive URL parts.
     * @param string $root     Zip root directory.
     *
     * @return string|\WP_Error Local zip path or error.
     */
    private function download_bitbucket_cloud_archive_with_pclzip( $zip_file, $parts, $root ) {
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

            return $result;
        }

        $archive = new \PclZip( $zip_file );
        $result  = $archive->create( $source_root, PCLZIP_OPT_REMOVE_PATH, $source_dir );

        $this->delete_directory( $source_dir );

        if ( 0 === $result ) {
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
        $queue = [ '' ];

        while ( ! empty( $queue ) ) {
            $path = array_shift( $queue );
            $url  = $this->get_bitbucket_source_url( $parts, $path );

            do {
                $response = $this->bitbucket_api_get( $url );
                if ( is_wp_error( $response ) ) {
                    return $response;
                }

                $code = (int) wp_remote_retrieve_response_code( $response );
                if ( 200 !== $code ) {
                    return new \WP_Error(
                        'github_updater_bitbucket_api_response',
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
                        $queue[] = $entry->path;
                        continue;
                    }

                    if ( 'commit_file' !== $entry->type || empty( $entry->links->self->href ) ) {
                        continue;
                    }

                    $file = $this->bitbucket_api_get( $entry->links->self->href );
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
     * Get a Bitbucket Cloud source API URL.
     *
     * @param array  $parts Archive URL parts.
     * @param string $path  Source path.
     *
     * @return string
     */
    private function get_bitbucket_source_url( $parts, $path ) {
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

        return add_query_arg( 'pagelen', '100', 'https://api.bitbucket.org/' . implode( '/', $segments ) . '/' );
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
