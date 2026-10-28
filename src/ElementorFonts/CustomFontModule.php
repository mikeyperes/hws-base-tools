<?php

namespace HWS\BaseTools\ElementorFonts;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use ElementorPro\Modules\AssetsManager\AssetTypes\Fonts_Manager;
use ElementorPro\Modules\AssetsManager\AssetTypes\Fonts\Custom_Fonts;

/** Narrow CLI import: no persistent upload permissions or front-end assets. */
final class CustomFontModule implements ModuleInterface {
    public function register(): void {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'hws elementor font import', [ $this, 'import' ] );
        }
    }

    /**
     * Import a WOFF/WOFF2 face into Elementor Pro Custom Fonts.
     *
     * ## OPTIONS
     *
     * <family>
     * : Font family, exactly as selected in Elementor.
     *
     * <file>
     * : Readable local WOFF/WOFF2 file or HTTPS URL ending in that extension.
     *
     * [--weight=<weight>]
     * : CSS weight (1-1000), default 400. Add each required weight separately.
     *
     * [--style=<style>]
     * : normal, italic or oblique. Default normal.
     *
     * [--replace]
     * : Replace this weight/style/format only; preserve every other face.
     *
     * ## EXAMPLES
     *
     *     wp hws elementor font import Newsreader /path/newsreader.woff2 --weight=400 --user=1
     *
     * Variable fonts are copied without conversion, preserving all axes (including opsz).
     * Requires --user selecting an administrator with upload_files permission.
     */
    public function import( array $args, array $options ): void {
        try {
            $result = $this->run_import( $args, $options );
        } catch ( \Throwable $error ) {
            \WP_CLI::error( $error->getMessage() );
            return;
        }
        \WP_CLI::line( wp_json_encode( $result, JSON_UNESCAPED_SLASHES ) );
        \WP_CLI::success( 'Elementor custom font ready.' );
    }

    private function run_import( array $args, array $options ): array {
        if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'upload_files' ) ) {
            throw new \RuntimeException( 'Select an administrator using --user; manage_options and upload_files are required.' );
        }
        if ( ! class_exists( Fonts_Manager::class ) || ! post_type_exists( Fonts_Manager::CPT ) ) {
            throw new \RuntimeException( 'Elementor Pro Custom Fonts must be active.' );
        }
        $family = trim( (string) ( $args[0] ?? '' ) );
        if ( ! preg_match( '/^[\p{L}\p{N} _-]{1,100}$/u', $family ) ) {
            throw new \RuntimeException( 'Use a family name containing letters, numbers, spaces, underscores or hyphens.' );
        }
        $weight = (string) ( $options['weight'] ?? '400' );
        $style = (string) ( $options['style'] ?? 'normal' );
        if ( ! ctype_digit( $weight ) || (int) $weight < 1 || (int) $weight > 1000 || ! in_array( $style, [ 'normal', 'italic', 'oblique' ], true ) ) {
            throw new \RuntimeException( 'Weight must be 1-1000 and style normal, italic or oblique.' );
        }
        $manager = \ElementorPro\Modules\AssetsManager\Module::instance()->get_assets_manager( 'font' );
        if ( ! $manager || ! $manager->get_font_type_object( 'custom' ) ) {
            throw new \RuntimeException( 'Elementor custom font manager is unavailable.' );
        }
        $source = (string) ( $args[1] ?? '' );
        $extension = strtolower( pathinfo( (string) parse_url( $source, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
        if ( ! in_array( $extension, [ 'woff', 'woff2' ], true ) ) {
            throw new \RuntimeException( 'Only .woff and .woff2 font files are accepted.' );
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $temporary = '';
        $attachment = 0;
        $created_font = 0;
        try {
            if ( preg_match( '~^https://~i', $source ) ) {
                $temporary = download_url( $source, 30 );
                if ( is_wp_error( $temporary ) ) {
                    throw new \RuntimeException( $temporary->get_error_message() );
                }
                $path = $temporary;
            } else {
                $path = realpath( $source );
            }
            if ( ! $path || ! is_file( $path ) || ! is_readable( $path ) || filesize( $path ) > 20971520 ) {
                throw new \RuntimeException( 'Font must be a readable local file or HTTPS download, at most 20 MB.' );
            }
            $header = file_get_contents( $path, false, null, 0, 48 );
            $minimum = 'woff2' === $extension ? 48 : 44;
            $signature = 'woff2' === $extension ? 'wOF2' : 'wOFF';
            if ( strlen( $header ) < $minimum || substr( $header, 0, 4 ) !== $signature ) {
                throw new \RuntimeException( 'File signature does not match the font extension.' );
            }
            $length = unpack( 'Nlength', substr( $header, 8, 4 ) )['length'];
            $tables = unpack( 'ntables', substr( $header, 12, 2 ) )['tables'];
            if ( $length !== filesize( $path ) || $tables < 1 || substr( $header, 14, 2 ) !== "\0\0" || ! in_array( substr( $header, 4, 4 ), [ "\0\1\0\0", 'OTTO' ], true ) ) {
                throw new \RuntimeException( 'Invalid WOFF container header.' );
            }
            $hash = hash_file( 'sha256', $path );
            $fonts = get_posts( [ 'post_type' => Fonts_Manager::CPT, 'post_status' => 'any', 'title' => $family, 'numberposts' => 2 ] );
            if ( count( $fonts ) > 1 ) {
                throw new \RuntimeException( 'Multiple font families have this title; resolve the duplicate before importing.' );
            }
            $font_id = $fonts ? (int) $fonts[0]->ID : 0;
            if ( $font_id && ! has_term( 'custom', Fonts_Manager::TAXONOMY, $font_id ) ) {
                throw new \RuntimeException( 'This family belongs to another font provider; choose a separate family.' );
            }
            $faces = $font_id ? get_post_meta( $font_id, Custom_Fonts::FONT_META_KEY, true ) : [];
            $faces = is_array( $faces ) ? $faces : [];
            foreach ( $faces as $existing ) {
                if ( ( $existing['font_type'] ?? '' ) === 'variable' ) {
                    throw new \RuntimeException( 'This family uses Elementor variable controls; use a separate family to preserve its configuration.' );
                }
            }
            $index = count( $faces );
            foreach ( $faces as $key => $face ) {
                if ( (string) ( $face['font_weight'] ?? '' ) === $weight && ( $face['font_style'] ?? '' ) === $style ) {
                    $index = $key;
                    if ( isset( $face[ $extension ]['id'] ) && hash_equals( $hash, (string) get_post_meta( (int) $face[ $extension ]['id'], '_hws_font_sha256', true ) ) ) {
                        return [ 'font_id' => $font_id, 'attachment_id' => (int) $face[ $extension ]['id'], 'url' => $face[ $extension ]['url'], 'sha256' => $hash, 'unchanged' => true ];
                    }
                    if ( ! empty( $face[ $extension ] ) && ! isset( $options['replace'] ) ) {
                        throw new \RuntimeException( 'This weight/style/format exists. Use --replace to change it.' );
                    }
                    break;
                }
            }
            if ( '' === $temporary ) {
                $temporary = wp_tempnam( 'hws-font.' . $extension );
                if ( ! $temporary || ! copy( $path, $temporary ) ) {
                    throw new \RuntimeException( 'Could not prepare the font upload.' );
                }
            }
            $name = sanitize_file_name( $family . '-' . $style . '-' . $weight . '.' . $extension );
            $mime = 'font/' . $extension;
            // Only this already-validated temporary file gets the MIME exception.
            $checked_file = $temporary;
            $check = static function ( $data, $file, $filename ) use ( $checked_file, $name, $extension, $mime ) {
                return $file === $checked_file && $filename === $name
                    ? [ 'ext' => $extension, 'type' => $mime, 'proper_filename' => false ] : $data;
            };
            add_filter( 'wp_check_filetype_and_ext', $check, PHP_INT_MAX, 3 );
            try {
                $file = [ 'name' => $name, 'tmp_name' => $temporary, 'error' => 0, 'size' => filesize( $temporary ) ];
                $upload = wp_handle_sideload( $file, [ 'test_form' => false, 'mimes' => [ $extension => $mime ] ] );
            } finally {
                remove_filter( 'wp_check_filetype_and_ext', $check, PHP_INT_MAX );
            }
            if ( isset( $upload['error'] ) ) {
                throw new \RuntimeException( $upload['error'] );
            }
            $insert = wp_insert_attachment( [ 'post_title' => $family . ' ' . $style . ' ' . $weight, 'post_mime_type' => $mime, 'post_status' => 'inherit' ], $upload['file'], $font_id, true );
            if ( is_wp_error( $insert ) ) {
                wp_delete_file( $upload['file'] );
                throw new \RuntimeException( $insert->get_error_message() );
            }
            $attachment = (int) $insert;
            update_post_meta( $attachment, '_hws_font_sha256', $hash );
            if ( ! $font_id ) {
                $insert = wp_insert_post( [ 'post_type' => Fonts_Manager::CPT, 'post_title' => $family, 'post_status' => 'publish' ], true );
                if ( is_wp_error( $insert ) ) {
                    throw new \RuntimeException( $insert->get_error_message() );
                }
                $font_id = $created_font = (int) $insert;
            }
            $term = wp_set_object_terms( $font_id, 'custom', Fonts_Manager::TAXONOMY );
            if ( is_wp_error( $term ) ) {
                throw new \RuntimeException( $term->get_error_message() );
            }
            $face = $faces[ $index ] ?? [];
            // Native face descriptors leave the binary's optical sizing and other axes intact.
            $face['font_weight'] = $weight;
            $face['font_style'] = $style;
            $face[ $extension ] = [ 'id' => $attachment, 'url' => $upload['url'] ];
            $faces[ $index ] = $face;
            $manager->get_font_type_object( 'custom' )->save_meta( $font_id, [ 'font_face' => array_values( $faces ) ] );
            $manager->clear_fonts_list();
            return [ 'font_id' => $font_id, 'attachment_id' => $attachment, 'url' => $upload['url'], 'weight' => $weight, 'style' => $style, 'sha256' => $hash ];
        } catch ( \Throwable $error ) {
            if ( $created_font ) {
                wp_delete_post( $created_font, true );
            }
            if ( $attachment ) {
                wp_delete_attachment( $attachment, true );
            }
            throw $error;
        } finally {
            if ( is_string( $temporary ) && '' !== $temporary && is_file( $temporary ) ) {
                wp_delete_file( $temporary );
            }
        }
    }
}
