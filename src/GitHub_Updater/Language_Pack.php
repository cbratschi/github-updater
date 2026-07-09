<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater;

use Fragen\Singleton;
use Fragen\GitHub_Updater\Traits\GHU_Trait;
use Fragen\GitHub_Updater\API\Language_Pack_API;

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class Language_Pack
 */
class Language_Pack {
    use GHU_Trait;

    /**
     * Language pack URI from the plugin/theme config.
     *
     * @var string|null
     */
    private $languages = null;

    /**
     * Variable containing the Language_Pack_API.
     *
     * @var Language_Pack_API|null
     */
    private $repo_api = null;

    /**
     * Language_Pack constructor.
     *
     * @param \stdClass         $repo Plugin/theme config data.
     * @param Language_Pack_API $api  Language_Pack_API object.
     */
    public function __construct( $repo, Language_Pack_API $api ) {
        $repo_vars = (array) $repo;

        if ( empty( $repo_vars['languages'] ) ) {
            return;
        }

        $this->languages = $repo_vars['languages'];
        $this->repo_api  = $api;
    }

    /**
     * Do the Language Pack integration.
     */
    public function run() {
        if ( null === $this->languages || null === $this->repo_api ) {
            return false;
        }

        $headers = $this->parse_header_uri( $this->languages );
        $this->repo_api->get_language_pack( $headers );

        add_filter( 'site_transient_update_plugins', [ $this, 'update_site_transient' ] );
        add_filter( 'site_transient_update_themes', [ $this, 'update_site_transient' ] );
    }

    /**
     * Add language translations to update_plugins or update_themes transients.
     *
     * @param mixed $transient Update transient.
     *
     * @return mixed
     */
    public function update_site_transient( $transient ) {
        $locales = get_available_languages();
        $locales = ! empty( $locales ) ? $locales : [ get_locale() ];
        $repos   = [];

        if ( ! is_object( $transient ) || ! isset( $transient->translations ) || ! is_array( $transient->translations ) ) {
            return $transient;
        }

        if ( 'site_transient_update_plugins' === current_filter() ) {
            $repos        = Singleton::get_instance( 'Plugin', $this )->get_plugin_configs();
            $translations = wp_get_installed_translations( 'plugins' );
        }

        if ( 'site_transient_update_themes' === current_filter() ) {
            $repos        = Singleton::get_instance( 'Theme', $this )->get_theme_configs();
            $translations = wp_get_installed_translations( 'themes' );
        }

        $repos = array_filter(
            $repos,
            function ( $e ) {
                return isset( $e->language_packs );
            }
        );

        foreach ( $repos as $repo ) {
            foreach ( $locales as $locale ) {
                $lang_pack_mod   = isset( $repo->language_packs->$locale )
                    ? strtotime( $repo->language_packs->$locale->updated )
                    : 0;
                $translation_mod = isset( $translations[ $repo->slug ][ $locale ] )
                    ? strtotime( $translations[ $repo->slug ][ $locale ]['PO-Revision-Date'] )
                    : 0;
                if ( $lang_pack_mod > $translation_mod ) {
                    $transient->translations[] = (array) $repo->language_packs->$locale;
                }
            }
        }

        $transient->translations = array_unique( $transient->translations, SORT_REGULAR );

        return $transient;
    }
}
