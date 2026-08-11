<?php

namespace HWS\BaseTools\ArticleImageIndexing;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the permanent, WordPress-native article image crops used by every HWS site.
 */
final class ImageFamily {
    public const LANDSCAPE = 'hws-article-16x9';
    public const STANDARD  = 'hws-article-4x3';
    public const SQUARE    = 'hws-article-1x1';

    /**
     * @return array<string,array{width:int,height:int,crop:bool}>
     */
    public static function definitions(): array {
        return [
            self::LANDSCAPE => [ 'width' => 1200, 'height' => 675, 'crop' => true ],
            self::STANDARD  => [ 'width' => 1200, 'height' => 900, 'crop' => true ],
            self::SQUARE    => [ 'width' => 1200, 'height' => 1200, 'crop' => true ],
        ];
    }

    public static function register_sizes(): void {
        foreach ( self::definitions() as $name => $definition ) {
            add_image_size( $name, $definition['width'], $definition['height'], $definition['crop'] );
        }
    }

    /**
     * Generate only the missing HWS crops for an existing attachment.
     *
     * @return array{complete:bool,generated:array<int,string>,missing:array<int,string>,error:string}
     */
    public static function ensure( int $attachment_id ): array {
        $result = [
            'complete'  => false,
            'generated' => [],
            'missing'   => [],
            'error'     => '',
        ];

        if ( $attachment_id < 1 || ! wp_attachment_is_image( $attachment_id ) ) {
            $result['error'] = 'The featured media is not a WordPress image attachment.';
            return $result;
        }

        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( ! is_array( $metadata ) || empty( $metadata['width'] ) || empty( $metadata['height'] ) ) {
            $result['error'] = 'The attachment does not have readable image metadata.';
            return $result;
        }

        $missing = self::missing_definitions( $attachment_id, $metadata );
        if ( empty( $missing ) ) {
            $result['complete'] = true;
            return $result;
        }

        if ( ! function_exists( 'wp_get_image_editor' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $source = function_exists( 'wp_get_original_image_path' )
            ? (string) wp_get_original_image_path( $attachment_id )
            : '';
        if ( '' === $source || ! is_readable( $source ) ) {
            $source = (string) get_attached_file( $attachment_id );
        }

        if ( '' === $source || ! is_readable( $source ) ) {
            $result['missing'] = array_keys( $missing );
            $result['error']   = 'The original attachment file is not readable.';
            return $result;
        }

        $editor = wp_get_image_editor( $source );
        if ( is_wp_error( $editor ) ) {
            $result['missing'] = array_keys( $missing );
            $result['error']   = $editor->get_error_message();
            return $result;
        }

        add_filter( 'image_resize_dimensions', [ self::class, 'exact_crop_dimensions' ], PHP_INT_MAX, 6 );
        try {
            $generated = $editor->multi_resize( $missing );
        } finally {
            remove_filter( 'image_resize_dimensions', [ self::class, 'exact_crop_dimensions' ], PHP_INT_MAX );
        }

        if ( ! is_array( $generated ) ) {
            $generated = [];
        }

        if ( ! isset( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
            $metadata['sizes'] = [];
        }

        foreach ( $generated as $name => $size_metadata ) {
            if ( ! isset( $missing[ $name ] ) || ! is_array( $size_metadata ) || empty( $size_metadata['file'] ) ) {
                continue;
            }

            $definition = $missing[ $name ];
            if (
                (int) ( $size_metadata['width'] ?? 0 ) !== $definition['width']
                || (int) ( $size_metadata['height'] ?? 0 ) !== $definition['height']
            ) {
                continue;
            }

            $metadata['sizes'][ $name ] = $size_metadata;
            $result['generated'][]      = (string) $name;
        }

        if ( ! empty( $result['generated'] ) ) {
            wp_update_attachment_metadata( $attachment_id, $metadata );
        }

        $remaining          = self::missing_definitions( $attachment_id, $metadata );
        $result['missing']  = array_keys( $remaining );
        $result['complete'] = empty( $remaining );

        if ( ! $result['complete'] && '' === $result['error'] ) {
            $result['error'] = 'The source image is too small or the active image editor could not create every 1200px crop.';
        }

        return $result;
    }

    /**
     * Return exact center-crop dimensions for the HWS image family.
     *
     * WordPress normally caps hard crops at the source dimensions. Article
     * sources are often high-resolution landscapes whose shorter edge is
     * below 1200px, so that behavior silently creates a 1058px square while
     * labelling the requested sub-size as generated. This callback is attached
     * only while HWS creates its three fixed crops and permits the final resize
     * to 1200px without changing the source aspect ratio.
     *
     * @param mixed      $output Existing preempted dimensions, if any.
     * @param int        $orig_w Original width.
     * @param int        $orig_h Original height.
     * @param int        $dest_w Requested width.
     * @param int        $dest_h Requested height.
     * @param bool|array $crop   Crop mode and optional alignment.
     * @return mixed
     */
    public static function exact_crop_dimensions( $output, int $orig_w, int $orig_h, int $dest_w, int $dest_h, $crop ) {
        $requested = false;
        foreach ( self::definitions() as $definition ) {
            if ( $dest_w === $definition['width'] && $dest_h === $definition['height'] ) {
                $requested = true;
                break;
            }
        }

        if ( ! $requested || ! $crop || $orig_w < 1 || $orig_h < 1 || $dest_w < 1 || $dest_h < 1 ) {
            return $output;
        }

        $source_ratio = $orig_w / $orig_h;
        $target_ratio = $dest_w / $dest_h;

        if ( $source_ratio > $target_ratio ) {
            $crop_h = $orig_h;
            $crop_w = min( $orig_w, (int) round( $orig_h * $target_ratio ) );
        } else {
            $crop_w = $orig_w;
            $crop_h = min( $orig_h, (int) round( $orig_w / $target_ratio ) );
        }

        $position = is_array( $crop ) && 2 === count( $crop ) ? array_values( $crop ) : [ 'center', 'center' ];
        $source_x = self::crop_offset( $orig_w, $crop_w, (string) $position[0] );
        $source_y = self::crop_offset( $orig_h, $crop_h, (string) $position[1] );

        return [ 0, 0, $source_x, $source_y, $dest_w, $dest_h, $crop_w, $crop_h ];
    }

    /**
     * @return array<string,array{size:string,url:string,width:int,height:int,mime_type:string,attachment_id:int,alt:string}>
     */
    public static function variants( int $attachment_id ): array {
        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( ! is_array( $metadata ) || empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
            return [];
        }

        $variants = [];
        $alt      = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );

        foreach ( self::definitions() as $name => $definition ) {
            $stored = $metadata['sizes'][ $name ] ?? null;
            if ( ! is_array( $stored ) || empty( $stored['file'] ) ) {
                continue;
            }

            $image = wp_get_attachment_image_src( $attachment_id, $name );
            if ( ! is_array( $image ) || empty( $image[0] ) ) {
                continue;
            }

            $width  = (int) ( $stored['width'] ?? $image[1] ?? 0 );
            $height = (int) ( $stored['height'] ?? $image[2] ?? 0 );
            if ( $width !== $definition['width'] || $height !== $definition['height'] ) {
                continue;
            }

            $variants[ $name ] = [
                'size'          => $name,
                'url'           => (string) $image[0],
                'width'         => $width,
                'height'        => $height,
                'mime_type'     => (string) ( $stored['mime-type'] ?? get_post_mime_type( $attachment_id ) ),
                'attachment_id' => $attachment_id,
                'alt'           => $alt,
            ];
        }

        return $variants;
    }

    public static function complete( int $attachment_id ): bool {
        return count( self::variants( $attachment_id ) ) === count( self::definitions() );
    }

    /**
     * @return array{size:string,url:string,width:int,height:int,mime_type:string,attachment_id:int,alt:string}|null
     */
    public static function landscape( int $attachment_id ): ?array {
        $variants = self::variants( $attachment_id );

        return $variants[ self::LANDSCAPE ] ?? null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function schema_objects( int $attachment_id ): array {
        $variants = self::variants( $attachment_id );
        if ( count( $variants ) !== count( self::definitions() ) ) {
            return [];
        }

        $caption = trim( (string) wp_get_attachment_caption( $attachment_id ) );
        if ( '' === $caption ) {
            $caption = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
        }

        $objects = [];
        foreach ( self::definitions() as $name => $unused ) {
            unset( $unused );
            $variant = $variants[ $name ];
            $object  = [
                '@type'      => 'ImageObject',
                'url'        => $variant['url'],
                'contentUrl' => $variant['url'],
                'width'      => $variant['width'],
                'height'     => $variant['height'],
            ];

            if ( '' !== $caption ) {
                $object['caption'] = $caption;
            }

            $objects[] = $object;
        }

        return $objects;
    }

    /**
     * Return filename fragments for every derivative of this attachment.
     * LiteSpeed treats each value as a request-local substring exclusion.
     *
     * @return array<int,string>
     */
    public static function filename_fragments( int $attachment_id ): array {
        $fragments = [];
        $file      = (string) get_attached_file( $attachment_id );
        if ( '' !== $file ) {
            $fragments[] = (string) pathinfo( basename( $file ), PATHINFO_FILENAME );
        }

        $metadata = wp_get_attachment_metadata( $attachment_id );
        foreach ( (array) ( is_array( $metadata ) ? ( $metadata['sizes'] ?? [] ) : [] ) as $stored ) {
            if ( ! empty( $stored['file'] ) ) {
                $fragments[] = (string) pathinfo( basename( (string) $stored['file'] ), PATHINFO_FILENAME );
            }
        }

        return array_values( array_unique( array_filter( $fragments ) ) );
    }

    /**
     * @param array<string,mixed> $metadata
     * @return array<string,array{width:int,height:int,crop:bool}>
     */
    private static function missing_definitions( int $attachment_id, array $metadata ): array {
        $missing  = [];
        $base_dir = dirname( (string) get_attached_file( $attachment_id ) );

        foreach ( self::definitions() as $name => $definition ) {
            $stored = $metadata['sizes'][ $name ] ?? null;
            $exists = is_array( $stored )
                && ! empty( $stored['file'] )
                && (int) ( $stored['width'] ?? 0 ) === $definition['width']
                && (int) ( $stored['height'] ?? 0 ) === $definition['height']
                && is_readable( $base_dir . '/' . basename( (string) $stored['file'] ) );

            if ( ! $exists ) {
                $missing[ $name ] = $definition;
            }
        }

        return $missing;
    }

    private static function crop_offset( int $original, int $cropped, string $position ): int {
        if ( 'left' === $position || 'top' === $position ) {
            return 0;
        }

        if ( 'right' === $position || 'bottom' === $position ) {
            return max( 0, $original - $cropped );
        }

        return max( 0, (int) floor( ( $original - $cropped ) / 2 ) );
    }
}
