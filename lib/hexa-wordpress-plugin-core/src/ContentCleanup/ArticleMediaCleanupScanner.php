<?php

namespace Hexa\PluginCore\ContentCleanup;

final class ArticleMediaCleanupScanner {
    private ArticleMediaCleanupConfig $config;

    public function __construct( ArticleMediaCleanupConfig $config ) {
        $this->config = $config;
    }

    public function scan( array $criteria ): array {
        $criteria = $this->normalize_criteria( $criteria );
        $log      = [
            $this->log( 'info', 'Normalized article cleanup criteria.', $criteria ),
            $this->log( 'info', 'Building WordPress article cleanup query.' ),
        ];

        $args = [
            'post_type'              => $criteria['post_type'],
            'post_status'            => $this->query_statuses( $criteria['status'] ),
            'posts_per_page'         => $criteria['limit'],
            'offset'                 => $criteria['keep_recent'],
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ];

        if ( '' !== $criteria['search'] ) {
            $args['s'] = $criteria['search'];
        }

        $posts = function_exists( 'get_posts' ) ? get_posts( $args ) : [];
        $rows  = [];
        foreach ( $posts as $post ) {
            if ( $post instanceof \WP_Post ) {
                $rows[] = $this->row_from_post( $post );
            }
        }

        $log[] = $this->log( 'success', 'Reported ' . count( $rows ) . ' article cleanup candidate(s).' );

        return [
            'criteria' => $criteria,
            'rows'     => $rows,
            'count'    => count( $rows ),
            'log'      => $log,
        ];
    }

    public function delete_post( int $post_id, bool $delete_media ): array|\WP_Error {
        $post = $this->editable_post_or_error( $post_id );
        if ( $post instanceof \WP_Error ) {
            return $post;
        }

        $title = get_the_title( $post_id );
        $media = $this->associated_media( $post );
        $log   = [
            $this->log( 'warning', 'Requested article deletion.', [ 'post_id' => $post_id, 'delete_media' => $delete_media ? 'yes' : 'no' ] ),
            $this->log( 'info', 'Detected associated media before deleting article.', [ 'media_count' => count( $media ), 'media_ids' => array_column( $media, 'id' ) ] ),
        ];

        $deleted_post = wp_delete_post( $post_id, true );
        if ( ! $deleted_post ) {
            return new \WP_Error( 'article_delete_failed', 'WordPress could not permanently delete this article.' );
        }

        $deleted_media = [];
        $media_errors  = [];

        if ( $delete_media ) {
            foreach ( $media as $item ) {
                $attachment_id = (int) $item['id'];
                if ( $attachment_id <= 0 ) {
                    continue;
                }

                if ( ! function_exists( 'wp_delete_attachment' ) ) {
                    $media_errors[] = 'wp_delete_attachment is unavailable.';
                    break;
                }

                $deleted = wp_delete_attachment( $attachment_id, true );
                if ( $deleted ) {
                    $deleted_media[] = $item;
                    $log[] = $this->log( 'success', 'Deleted associated media.', [ 'attachment_id' => $attachment_id, 'title' => $item['title'], 'source' => $item['source'] ] );
                } else {
                    $media_errors[] = 'Failed to delete media ID ' . $attachment_id;
                    $log[] = $this->log( 'error', 'Associated media delete failed.', [ 'attachment_id' => $attachment_id, 'title' => $item['title'] ] );
                }
            }
        } else {
            $log[] = $this->log( 'info', 'Associated media deletion was not enabled. Media was left untouched.', [ 'media_count' => count( $media ) ] );
        }

        $log[] = $this->log( 'success', 'Permanently deleted article.', [ 'post_id' => $post_id, 'title' => $title ] );

        return [
            'id'            => $post_id,
            'message'       => 'Deleted article: ' . $title,
            'media'         => $media,
            'deleted_media' => $deleted_media,
            'media_errors'  => $media_errors,
            'log'           => $log,
        ];
    }

    public function normalize_criteria( array $criteria ): array {
        $defaults = $this->config->default_criteria();
        $criteria = array_merge( $defaults, $criteria );

        $post_type = $this->clean_key( (string) ( $criteria['post_type'] ?? $defaults['post_type'] ) );
        if ( ! isset( $this->config->post_types()[ $post_type ] ) ) {
            $post_type = (string) $defaults['post_type'];
        }

        $status = $this->clean_key( (string) ( $criteria['status'] ?? $defaults['status'] ) );
        if ( ! isset( $this->config->statuses()[ $status ] ) ) {
            $status = (string) $defaults['status'];
        }

        return [
            'post_type'   => $post_type,
            'status'      => $status,
            'keep_recent' => max( 0, (int) ( $criteria['keep_recent'] ?? $defaults['keep_recent'] ) ),
            'search'      => $this->sanitize_text( (string) ( $criteria['search'] ?? '' ) ),
            'limit'       => min( $this->config->max_limit(), max( 1, (int) ( $criteria['limit'] ?? $defaults['limit'] ) ) ),
        ];
    }

    private function row_from_post( \WP_Post $post ): array {
        $media = $this->associated_media( $post );
        $slug  = '' !== (string) $post->post_name ? (string) $post->post_name : '(no slug)';
        $title = get_the_title( $post );

        return [
            'id'              => (int) $post->ID,
            'title'           => '' !== $title ? $title : '(untitled)',
            'slug'            => $slug,
            'status'          => (string) $post->post_status,
            'post_type'       => (string) $post->post_type,
            'author'          => function_exists( 'get_the_author_meta' ) ? (string) get_the_author_meta( 'display_name', (int) $post->post_author ) : (string) $post->post_author,
            'published'       => (string) $post->post_date,
            'published_label' => $this->date_label( (string) $post->post_date ),
            'edit_url'        => function_exists( 'get_edit_post_link' ) ? (string) get_edit_post_link( $post->ID, 'raw' ) : '',
            'view_url'        => function_exists( 'get_permalink' ) ? (string) get_permalink( $post ) : '',
            'media'           => $media,
            'media_count'     => count( $media ),
        ];
    }

    private function associated_media( \WP_Post $post ): array {
        $items = [];
        $seen  = [];

        $add = function ( int $attachment_id, string $source ) use ( &$items, &$seen ): void {
            if ( $attachment_id <= 0 || isset( $seen[ $attachment_id ] ) ) {
                return;
            }

            $attachment = get_post( $attachment_id );
            if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type ) {
                return;
            }

            $seen[ $attachment_id ] = true;
            $items[] = [
                'id'     => $attachment_id,
                'title'  => get_the_title( $attachment_id ) ?: '(untitled media)',
                'source' => $source,
                'url'    => function_exists( 'wp_get_attachment_url' ) ? (string) wp_get_attachment_url( $attachment_id ) : '',
            ];
        };

        if ( function_exists( 'get_post_thumbnail_id' ) ) {
            $add( (int) get_post_thumbnail_id( $post->ID ), 'featured image' );
        }

        $content = (string) $post->post_content;
        if ( preg_match_all( '/wp-image-([0-9]+)/i', $content, $matches ) ) {
            foreach ( $matches[1] as $id ) {
                $add( (int) $id, 'inline image' );
            }
        }

        if ( preg_match_all( '/<img[^>]+src=[\"\']([^\"\']+)[\"\']/i', $content, $matches ) && function_exists( 'attachment_url_to_postid' ) ) {
            foreach ( $matches[1] as $url ) {
                $add( (int) attachment_url_to_postid( html_entity_decode( (string) $url ) ), 'inline image' );
            }
        }

        if ( preg_match_all( '/\[gallery[^\]]*ids=[\"\']?([^\"\'\]]+)/i', $content, $matches ) ) {
            foreach ( $matches[1] as $ids ) {
                foreach ( preg_split( '/\s*,\s*/', (string) $ids ) ?: [] as $id ) {
                    $add( (int) $id, 'gallery image' );
                }
            }
        }

        return $items;
    }

    private function editable_post_or_error( int $post_id ): \WP_Post|\WP_Error {
        if ( $post_id <= 0 ) {
            return new \WP_Error( 'missing_post_id', 'Missing article ID.' );
        }

        $post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
        if ( ! $post instanceof \WP_Post ) {
            return new \WP_Error( 'article_not_found', 'Article was not found.' );
        }

        if ( ! isset( $this->config->post_types()[ $post->post_type ] ) ) {
            return new \WP_Error( 'post_type_not_allowed', 'This post type is not managed by this cleanup tool.' );
        }

        if ( function_exists( 'current_user_can' ) && ! current_user_can( 'delete_post', $post_id ) ) {
            return new \WP_Error( 'delete_permission_denied', 'You do not have permission to delete this article.' );
        }

        return $post;
    }

    private function query_statuses( string $status ): string|array {
        if ( 'any' !== $status ) {
            return $status;
        }

        return array_values( array_filter( array_keys( $this->config->statuses() ), static fn( string $item ): bool => 'any' !== $item ) );
    }

    private function log( string $level, string $message, array $context = [] ): array {
        return [
            'time'    => gmdate( 'H:i:s' ),
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    private function date_label( string $mysql_date ): string {
        if ( '' === $mysql_date || '0000-00-00 00:00:00' === $mysql_date ) {
            return 'Not set';
        }

        return function_exists( 'mysql2date' ) ? mysql2date( 'M j, Y g:i a', $mysql_date ) : $mysql_date;
    }

    private function sanitize_text( string $value ): string {
        return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( $value ) );
    }

    private function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }
}
