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

use Fragen\GitHub_Updater\Readme_Parser as Readme_Parser;

/**
 * Trait API_Common
 *
 * @mixin \Fragen\GitHub_Updater\API
 * @method mixed bbserver_recombine_response( mixed $response )
 * @method mixed parse_tag_response( mixed $response )
 * @method array parse_tags( mixed $response, array $repo_type )
 * @method mixed parse_meta_response( mixed $response )
 * @method array parse_branch_response( mixed $response )
 * @property \stdClass|null $type
 * @property array $response
 */
trait API_Common {
    /**
     * Holds loose class method name.
     *
     * @var null
     */
    protected static $method = null;

    /**
     * Get repo type object when present on the current API instance.
     *
     * @return \stdClass|null
     */
    private function get_api_common_type_object() {
        $object_vars = get_object_vars( $this );
        $type        = isset( $object_vars['type'] ) ? $object_vars['type'] : null;

        return is_object( $type ) ? $type : null;
    }

    /**
     * Get repo type data when present on the current API instance.
     *
     * @return array
     */
    private function get_api_common_type_vars() {
        $object_vars = get_object_vars( $this );
        $type        = isset( $object_vars['type'] ) ? $object_vars['type'] : null;

        return is_object( $type ) || is_array( $type ) ? (array) $type : [];
    }

    /**
     * Get cached API response data when present on the current API instance.
     *
     * @return array
     */
    private function get_api_common_response_vars() {
        $object_vars = get_object_vars( $this );
        $response    = isset( $object_vars['response'] ) ? $object_vars['response'] : [];

        return is_array( $response ) ? $response : [];
    }

    /**
     * Decode API responses that are base64 encoded.
     *
     * @param  string $git      (github|bitbucket|gitlab|gitea).
     * @param  mixed  $response API response.
     * @return mixed
     */
    private function decode_response( $git, $response ) {
        switch ( $git ) {
            case 'github':
            case 'gitlab':
                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
                $response = isset( $response->content ) ? base64_decode( $response->content ) : $response;
                break;

            case 'bbserver':
                $response = isset( $response->lines ) ? $this->bbserver_recombine_response( $response ) : $response;
                break;
        }

        return $response;
    }

    /**
     * Parse API response that returns as stdClass.
     *
     * @param  string $git      (github|bitbucket|gitlab|gitea).
     * @param  mixed  $response API response.
     * @return mixed
     */
    private function parse_response( $git, $response ) {
        switch ( $git ) {
            case 'bitbucket':
            case 'bbserver':
                $response = isset( $response->values ) ? $response->values : $response;
                break;
        }

        return $response;
    }

    /**
     * Parse API response to release asset URI.
     *
     * @param  string $git      (github|bitbucket|gitlab|gitea).
     * @param  string $request  Query to API->api().
     * @param  mixed  $response API response.
     * @return string Release asset download link.
     */
    private function parse_release_asset( $git, $request, $response ) {
        if ( is_wp_error( $response ) ) {
            return null;
        }

        $type_vars = $this->get_api_common_type_vars();
        $slug      = isset( $type_vars['slug'] ) ? $type_vars['slug'] : '';

        switch ( $git ) {
            case 'github':
                $assets = isset( $response->assets ) ? $response->assets : [];
                foreach ( $assets as $asset ) {
                    $asset_name = isset( $asset->name ) ? $asset->name : '';

                    if ( 1 === count( $assets ) || ( $slug && 0 === strpos( $asset_name, $slug ) ) ) {
                        $response = $asset->url;
                        break;
                    }
                }
                $response = is_string( $response ) ? $response : null;
                break;

            case 'bitbucket':
                $assets = isset( $response->values ) ? $response->values : [];
                $matched_asset = null;

                foreach ( $assets as $asset ) {
                    $asset_name = isset( $asset->name ) ? $asset->name : '';

                    if ( 1 === count( $assets ) || ( $slug && 0 === strpos( $asset_name, $slug ) ) ) {
                        $matched_asset = $asset;
                        $response      = isset( $asset->links->self->href ) ? $asset->links->self->href : null;
                        break;
                    }
                }

                $response = is_string( $response ) ? $response : null;

                if ( $matched_asset ) {
                    $matched_asset->browser_download_url = $response;
                    $matched_asset->download_count       = isset( $matched_asset->downloads ) ? $matched_asset->downloads : 0;

                    $this->set_repo_cache( 'release_asset_response', $matched_asset );
                }
                break;

            case 'bbserver':
                // TODO: make work.
                break;

            case 'gitlab':
                $response = $this->get_api_url( $request );
                break;

            case 'gitea':
                break;
        }

        return $response;
    }

    /**
     * Read the remote file and parse headers.
     *
     * @param string $git     github|bitbucket|gitlab|gitea).
     * @param string $file    Filename.
     * @param string $request API request.
     *
     * @return bool
     */
    public function get_remote_api_info( $git, $file, $request ) {
        $response_cache = $this->get_api_common_response_vars();
        $response       = isset( $response_cache[ $file ] ) ? $response_cache[ $file ] : false;
        $type           = $this->get_api_common_type_vars();

        if ( ! $response ) {
            self::$method = 'file';
            $response     = $this->api( $request );
            $response     = $this->decode_response( $git, $response );
        }

        if ( $response && is_string( $response ) && ! is_wp_error( $response ) ) {
            $response = $this->get_file_headers( $response, isset( $type['type'] ) ? $type['type'] : 'plugin' );

            $this->set_repo_cache( $file, $response );
            $this->set_repo_cache( 'repo', isset( $type['slug'] ) ? $type['slug'] : 'ghu' );
        }

        if ( ! is_array( $response ) || $this->validate_response( $response ) ) {
            return false;
        }

        $response['dot_org'] = $this->get_dot_org_data();
        $this->set_file_info( $response );

        return true;
    }

    /**
     * Get remote info for tags.
     *
     * @param string $_git    Unused git provider.
     * @param string $request API request.
     *
     * @return bool
     */
    public function get_remote_api_tag( $_git, $request ) {
        $response_cache = $this->get_api_common_response_vars();
        $repo_type      = $this->return_repo_type();
        $response       = isset( $response_cache['tags'] ) ? $response_cache['tags'] : false;

        if ( ! $response ) {
            self::$method = 'tags';
            $response     = $this->api( $request );

            if ( ! $response ) {
                $response          = new \stdClass();
                $response->message = 'No tags found';
            }

            if ( $response ) {
                $response = $this->parse_tag_response( $response );
                $this->set_repo_cache( 'tags', $response );
            }
        }

        if ( $this->validate_response( $response ) ) {
            return false;
        }

        $tags = $this->parse_tags( $response, $repo_type );
        $this->sort_tags( $tags );

        return true;
    }

    /**
     * Read the remote CHANGES.md file.
     *
     * @param string $git     github|bitbucket|gitlab|gitea).
     * @param string $changes Changelog filename.
     * @param string $request API request.
     *
     * @return bool
     */
    public function get_remote_api_changes( $git, $changes, $request ) {
        $response_cache = $this->get_api_common_response_vars();
        $response       = isset( $response_cache['changes'] ) ? $response_cache['changes'] : false;
        $type           = $this->get_api_common_type_object();

        // Set $response from local file if no update available.
        if ( ! $response && $type && ! $this->can_update_repo( $type ) ) {
            $response = $this->get_local_info( $type, $changes );
        }

        if ( ! $response ) {
            self::$method = 'changes';
            $response     = $this->api( $request );
            $response     = $this->decode_response( $git, $response );
        }

        if ( ! $response && ! is_wp_error( $response ) ) {
            $response          = new \stdClass();
            $response->message = 'No changelog found';
        }

        if ( $this->validate_response( $response ) ) {
            return false;
        }

        if ( $response && ! isset( $response_cache['changes'] ) ) {
            $parser   = new \Parsedown();
            $response = $parser->text( $response );
            $this->set_repo_cache( 'changes', $response );
        }

        if ( $type ) {
            $type->sections              = isset( $type->sections ) && is_array( $type->sections ) ? $type->sections : [];
            $type->sections['changelog'] = $response;
        }

        return true;
    }

    /**
     * Read and parse remote readme.txt.
     *
     * @param string $git     github|bitbucket|gitlab|gitea).
     * @param string $request API request.
     *
     * @return bool
     */
    public function get_remote_api_readme( $git, $request ) {
        if ( ! $this->local_file_exists( 'readme.txt' ) ) {
            return false;
        }

        $response_cache = $this->get_api_common_response_vars();
        $response       = isset( $response_cache['readme'] ) ? $response_cache['readme'] : false;
        $type           = $this->get_api_common_type_object();

        // Set $response from local file if no update available.
        if ( ! $response && $type && ! $this->can_update_repo( $type ) ) {
            $response = $this->get_local_info( $type, 'readme.txt' );
        }

        if ( ! $response ) {
            self::$method = 'readme';
            $response     = $this->api( $request );
            $response     = $this->decode_response( $git, $response );
        }

        if ( ! $response && ! is_wp_error( $response ) ) {
            $response          = new \stdClass();
            $response->message = 'No readme found';
        }

        if ( $this->validate_response( $response ) ) {
            return false;
        }

        if ( $response && ! isset( $response_cache['readme'] ) ) {
            $parser   = new Readme_Parser( $response );
            $response = $parser->parse_data();
            $this->set_repo_cache( 'readme', $response );
        }

        $this->set_readme_info( $response );

        return true;
    }

    /**
     * Read the repository meta from API.
     *
     * @param string $_git    Unused git provider.
     * @param string $request API request.
     *
     * @return bool
     */
    public function get_remote_api_repo_meta( $_git, $request ) {
        $response_cache = $this->get_api_common_response_vars();
        $response       = isset( $response_cache['meta'] ) ? $response_cache['meta'] : false;
        $type           = $this->get_api_common_type_object();

        if ( ! $response ) {
            self::$method = 'meta';
            $response     = $this->api( $request );

            if ( $response ) {
                $response = $this->parse_meta_response( $response );
                $this->set_repo_cache( 'meta', $response );
            }
        }

        if ( $this->validate_response( $response ) ) {
            return false;
        }

        if ( $type ) {
            $type->repo_meta = $response;
        }
        $this->add_meta_repo_object();

        return true;
    }

    /**
     * Create array of branches and download links as array.
     *
     * @param string $git     github|bitbucket|gitlab|gitea).
     * @param string $request API request.
     *
     * @return bool
     */
    public function get_remote_api_branches( $git, $request ) {
        //debug
        //error_log('get_remote_api_branches()');

        $response_cache = $this->get_api_common_response_vars();
        $branches       = [];
        $response       = isset( $response_cache['branches'] ) ? $response_cache['branches'] : false;
        $type           = $this->get_api_common_type_object();

        //debug
        //error_log('Response: ' . json_encode($response));

        if ( $this->exit_no_update( $response, true ) ) {
            return false;
        }

        if ( ! $response ) {
            //request branches
            self::$method = 'branches';
            $response     = $this->api( $request );
            $response     = $this->parse_response( $git, $response );

            if ( $this->validate_response( $response ) ) {
                return false;
            }

            //debug
            //error_log('Branches response:' . json_encode($response));

            $branches = $this->parse_branch_response( $response );
            if ( $type ) {
                $type->branches = $branches;
            }
            $this->set_repo_cache( 'branches', $branches );

            return true;
        }

        if ( $type ) {
            $type->branches = $response;
        }

        return true;
    }

    /**
     * Get API release asset download link.
     *
     * @param  string $git     (github|bitbucket|gitlab|gitea).
     * @param  string $request Query for API->api().
     * @return string Release asset URI.
     */
    public function get_api_release_asset( $git, $request ) {
        $response_cache = $this->get_api_common_response_vars();
        $response       = isset( $response_cache['release_asset'] ) ? $response_cache['release_asset'] : false;

        if ( $response && $this->exit_no_update( $response ) ) {
            return false;
        }

        if ( ! $response ) {
            self::$method = 'release_asset';
            $response     = $this->api( $request );
            $response     = $this->parse_release_asset( $git, $request, $response );

            if ( ! $response && ! is_wp_error( $response ) ) {
                $response          = new \stdClass();
                $response->message = 'No release asset found';
            }
        }

        if ( $response && ! isset( $response_cache['release_asset'] ) ) {
            $this->set_repo_cache( 'release_asset', $response );
        }

        if ( $this->validate_response( $response ) ) {
            return false;
        }

        return $response;
    }
}
