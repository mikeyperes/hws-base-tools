<?php

namespace HWS\BaseTools\DatabaseOptimization;

use WP_Error;

/**
 * The cleanup items. Each one can count what it would remove and remove one
 * batch. Rows are removed through WordPress's own delete functions so other
 * plugins hear about it and clean up their own data; only this site's tables
 * are read.
 */
final class CleanupTasks {
    private const TIME_BUDGET = 12.0;

    /**
     * @return array<string,array{label:string,group:string,description:string,default:bool}>
     */
    public static function definitions(): array {
        $s = OptimizationSettings::get();

        return [
            'revisions'            => [ 'label' => 'Post revisions', 'group' => 'Posts', 'default' => true, 'description' => sprintf( 'Old saved versions of posts and pages. The newest %d of each post are kept%s.', $s['keep_revisions'], $s['revision_days'] ? ', and only ones older than ' . $s['revision_days'] . ' days are removed' : '' ) ],
            'autodrafts'           => [ 'label' => 'Auto-drafts', 'group' => 'Posts', 'default' => true, 'description' => self::age( 'Empty drafts WordPress created automatically', $s['autodraft_days'] ) ],
            'trash_posts'          => [ 'label' => 'Trashed posts and pages', 'group' => 'Posts', 'default' => true, 'description' => self::age( 'Items in the trash', $s['trash_days'] ) ],
            'spam_comments'        => [ 'label' => 'Spam comments', 'group' => 'Comments', 'default' => true, 'description' => self::age( 'Comments marked as spam', $s['comment_days'] ) ],
            'trash_comments'       => [ 'label' => 'Trashed comments', 'group' => 'Comments', 'default' => true, 'description' => self::age( 'Comments in the trash', $s['comment_days'] ) ],
            'pingbacks'            => [ 'label' => 'Pingbacks', 'group' => 'Comments', 'default' => true, 'description' => 'Automatic link notifications from other blogs.' ],
            'trackbacks'           => [ 'label' => 'Trackbacks', 'group' => 'Comments', 'default' => true, 'description' => 'Older style automatic link notifications.' ],
            'expired_transients'   => [ 'label' => 'Expired temporary data', 'group' => 'Temporary data', 'default' => true, 'description' => 'Cached values (transients) whose expiry time has passed.' ],
            'oembed_cache'         => [ 'label' => 'Embed caches', 'group' => 'Temporary data', 'default' => true, 'description' => 'Saved copies of embedded videos and posts (oEmbed). WordPress rebuilds them when a page is viewed.' ],
            'orphan_postmeta'      => [ 'label' => 'Leftover post data', 'group' => 'Leftover data', 'default' => true, 'description' => 'Custom field rows whose post no longer exists.' ],
            'orphan_commentmeta'   => [ 'label' => 'Leftover comment data', 'group' => 'Leftover data', 'default' => true, 'description' => 'Comment data rows whose comment no longer exists.' ],
            'orphan_usermeta'      => [ 'label' => 'Leftover user data', 'group' => 'Leftover data', 'default' => true, 'description' => 'User data rows whose user no longer exists.' ],
            'orphan_termmeta'      => [ 'label' => 'Leftover category data', 'group' => 'Leftover data', 'default' => true, 'description' => 'Category and tag data rows whose term no longer exists.' ],
            'orphan_relationships' => [ 'label' => 'Leftover category links', 'group' => 'Leftover data', 'default' => true, 'description' => 'Links between deleted posts and their categories or tags.' ],
            'unapproved_comments'  => [ 'label' => 'Unapproved comments', 'group' => 'Review first', 'default' => false, 'description' => self::age( 'Real comments still waiting for review', $s['unapproved_days'] ) ],
            'all_transients'       => [ 'label' => 'All temporary data', 'group' => 'Review first', 'default' => false, 'description' => 'Every transient, including ones that are still valid. Plugins rebuild them, so pages can be slower for a moment.' ],
            'duplicate_meta'       => [ 'label' => 'Duplicate custom fields', 'group' => 'Review first', 'default' => false, 'description' => 'Identical custom field rows on the same item. The oldest copy is kept.' ],
            'unused_terms'         => [ 'label' => 'Unused categories and tags', 'group' => 'Review first', 'default' => false, 'description' => 'Visible categories and tags with no posts. Default categories and parent terms are kept.' ],
        ];
    }

    public static function exists( string $task ): bool {
        return array_key_exists( $task, self::definitions() );
    }

    public static function count( string $task ): int|WP_Error {
        if ( ! self::exists( $task ) ) {
            return new WP_Error( 'hws_dbo_unknown_task', 'Unknown cleanup item.' );
        }
        if ( 'orphan_usermeta' === $task && ! self::owns_user_tables() ) {
            return 0;
        }

        global $wpdb;
        $s = OptimizationSettings::get();

        switch ( $task ) {
            case 'revisions':
                return (int) $wpdb->get_var( self::revision_sql( 'COUNT(*)', $s ) );
            case 'expired_transients':
                return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE ( option_name LIKE %s OR option_name LIKE %s ) AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', $wpdb->esc_like( '_site_transient_timeout_' ) . '%', time() ) );
            case 'all_transients':
                return (int) $wpdb->get_var( self::all_transients_sql( 'COUNT(*)' ) );
            case 'oembed_cache':
                return (int) $wpdb->get_var( self::oembed_meta_sql( 'COUNT(*)' ) ) + (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'oembed_cache'" );
            case 'duplicate_meta':
                $total = 0;
                foreach ( self::meta_types() as $type ) {
                    $total += (int) $wpdb->get_var( 'SELECT COALESCE(SUM(c - 1), 0) FROM ( ' . self::duplicate_groups_sql( $type, 'COUNT(*) AS c' ) . ' ) d' );
                }
                return $total;
            case 'orphan_relationships':
                return (int) $wpdb->get_var( self::relationship_sql( 'COUNT(*)' ) );
            case 'unused_terms':
                return (int) $wpdb->get_var( self::unused_terms_sql( 'COUNT(*)' ) );
        }

        $sql = self::id_sql( $task, 'COUNT(*)', $s );

        return null === $sql ? 0 : (int) $wpdb->get_var( $sql );
    }

    /**
     * Remove one batch.
     *
     * @return array{removed:int,failed:int,done:bool}|WP_Error
     */
    public static function clean( string $task ): array|WP_Error {
        if ( ! self::exists( $task ) ) {
            return new WP_Error( 'hws_dbo_unknown_task', 'Unknown cleanup item.' );
        }
        if ( 'orphan_usermeta' === $task && ! self::owns_user_tables() ) {
            return [ 'removed' => 0, 'failed' => 0, 'done' => true ];
        }
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }

        $s     = OptimizationSettings::get();
        $limit = $s['batch_size'];

        switch ( $task ) {
            case 'expired_transients':
                $before = self::count( $task );
                delete_expired_transients( true );
                $after = self::count( $task );
                return [ 'removed' => max( 0, (int) $before - (int) $after ), 'failed' => 0, 'done' => true ];
            case 'all_transients':
                return self::clean_all_transients( $limit );
            case 'oembed_cache':
                return self::clean_oembed( $limit );
            case 'duplicate_meta':
                return self::clean_duplicate_meta( $limit );
            case 'orphan_relationships':
                return self::clean_relationships( $limit );
            case 'unused_terms':
                return self::clean_unused_terms( $limit );
        }

        global $wpdb;
        $sql = 'revisions' === $task ? self::revision_sql( 'r.ID', $s ) . ' ORDER BY r.ID' : self::id_sql( $task, 'id', $s );
        if ( null === $sql ) {
            return [ 'removed' => 0, 'failed' => 0, 'done' => true ];
        }
        $ids = array_map( 'intval', (array) $wpdb->get_col( $sql . ' LIMIT ' . (int) $limit ) );

        return self::delete_each( $ids, $limit, static fn( int $id ): bool => self::delete_one( $task, $id ) );
    }

    private static function delete_one( string $task, int $id ): bool {
        switch ( $task ) {
            case 'revisions':
                return (bool) wp_delete_post_revision( $id );
            case 'autodrafts':
            case 'trash_posts':
                return (bool) wp_delete_post( $id, true );
            case 'spam_comments':
            case 'trash_comments':
            case 'unapproved_comments':
            case 'pingbacks':
            case 'trackbacks':
                return (bool) wp_delete_comment( $id, true );
            case 'orphan_postmeta':
                return (bool) delete_metadata_by_mid( 'post', $id );
            case 'orphan_commentmeta':
                return (bool) delete_metadata_by_mid( 'comment', $id );
            case 'orphan_usermeta':
                return (bool) delete_metadata_by_mid( 'user', $id );
            case 'orphan_termmeta':
                return (bool) delete_metadata_by_mid( 'term', $id );
        }

        return false;
    }

    /**
     * SQL selecting the ids (or a count) for the simple one-id items.
     *
     * @param array<string,int> $s
     */
    private static function id_sql( string $task, string $select, array $s ): ?string {
        global $wpdb;
        $col = 'COUNT(*)' === $select;

        switch ( $task ) {
            case 'autodrafts':
                return $wpdb->prepare( 'SELECT ' . ( $col ? 'COUNT(*)' : 'ID' ) . " FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_date_gmt < %s" . ( $col ? '' : ' ORDER BY ID' ), self::cutoff( $s['autodraft_days'] ) );
            case 'trash_posts':
                return $wpdb->prepare( 'SELECT ' . ( $col ? 'COUNT(*)' : 'p.ID' ) . " FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_wp_trash_meta_time' WHERE p.post_status = 'trash' AND COALESCE( CAST( t.meta_value AS UNSIGNED ), UNIX_TIMESTAMP( p.post_modified_gmt ) ) < %d" . ( $col ? '' : ' ORDER BY p.ID' ), time() - DAY_IN_SECONDS * $s['trash_days'] );
            case 'spam_comments':
                return self::comment_sql( $col, "comment_approved = 'spam'", $s['comment_days'] );
            case 'trash_comments':
                return self::comment_sql( $col, "comment_approved = 'trash'", $s['comment_days'] );
            case 'unapproved_comments':
                return self::comment_sql( $col, "comment_approved = '0'", $s['unapproved_days'] );
            case 'pingbacks':
                return self::comment_sql( $col, "comment_type = 'pingback'", 0 );
            case 'trackbacks':
                return self::comment_sql( $col, "comment_type = 'trackback'", 0 );
            case 'orphan_postmeta':
                return 'SELECT ' . ( $col ? 'COUNT(*)' : 'm.meta_id' ) . " FROM {$wpdb->postmeta} m LEFT JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.ID IS NULL";
            case 'orphan_commentmeta':
                return 'SELECT ' . ( $col ? 'COUNT(*)' : 'm.meta_id' ) . " FROM {$wpdb->commentmeta} m LEFT JOIN {$wpdb->comments} c ON c.comment_ID = m.comment_id WHERE c.comment_ID IS NULL";
            case 'orphan_usermeta':
                return 'SELECT ' . ( $col ? 'COUNT(*)' : 'm.umeta_id' ) . " FROM {$wpdb->usermeta} m LEFT JOIN {$wpdb->users} u ON u.ID = m.user_id WHERE u.ID IS NULL";
            case 'orphan_termmeta':
                return 'SELECT ' . ( $col ? 'COUNT(*)' : 'm.meta_id' ) . " FROM {$wpdb->termmeta} m LEFT JOIN {$wpdb->terms} t ON t.term_id = m.term_id WHERE t.term_id IS NULL";
        }

        return null;
    }

    private static function comment_sql( bool $count, string $where, int $days ): string {
        global $wpdb;

        return $wpdb->prepare( 'SELECT ' . ( $count ? 'COUNT(*)' : 'comment_ID' ) . " FROM {$wpdb->comments} WHERE {$where} AND comment_date_gmt < %s" . ( $count ? '' : ' ORDER BY comment_ID' ), self::cutoff( $days ) );
    }

    /**
     * Revisions beyond the newest N of each post. A revision's rank is the
     * number of newer revisions of the same post, so removing older ones
     * never changes which newer ones are kept.
     *
     * @param array<string,int> $s
     */
    private static function revision_sql( string $select, array $s ): string {
        global $wpdb;
        $age = $s['revision_days'] > 0 ? $wpdb->prepare( ' AND r.post_date_gmt < %s', self::cutoff( $s['revision_days'] ) ) : '';
        if ( $s['keep_revisions'] <= 0 ) {
            return "SELECT {$select} FROM {$wpdb->posts} r WHERE r.post_type = 'revision'{$age}";
        }

        return $wpdb->prepare(
            "SELECT {$select} FROM {$wpdb->posts} r WHERE r.post_type = 'revision'{$age} AND ( SELECT COUNT(*) FROM {$wpdb->posts} n WHERE n.post_type = 'revision' AND n.post_parent = r.post_parent AND ( n.post_date_gmt > r.post_date_gmt OR ( n.post_date_gmt = r.post_date_gmt AND n.ID > r.ID ) ) ) >= %d",
            $s['keep_revisions']
        );
    }

    private static function all_transients_sql( string $select ): string {
        global $wpdb;

        return $wpdb->prepare(
            "SELECT {$select} FROM {$wpdb->options} WHERE ( option_name LIKE %s AND option_name NOT LIKE %s ) OR ( option_name LIKE %s AND option_name NOT LIKE %s )",
            $wpdb->esc_like( '_transient_' ) . '%',
            $wpdb->esc_like( '_transient_timeout_' ) . '%',
            $wpdb->esc_like( '_site_transient_' ) . '%',
            $wpdb->esc_like( '_site_transient_timeout_' ) . '%'
        );
    }

    /** @return array{removed:int,failed:int,done:bool} */
    private static function clean_all_transients( int $limit ): array {
        global $wpdb;
        $names = (array) $wpdb->get_col( self::all_transients_sql( 'option_name' ) . ' LIMIT ' . $limit );

        return self::delete_each(
            $names,
            $limit,
            static function ( string $name ): bool {
                if ( str_starts_with( $name, '_site_transient_' ) ) {
                    return delete_site_transient( substr( $name, 16 ) ) || delete_option( $name );
                }

                return delete_transient( substr( $name, 11 ) ) || delete_option( $name );
            }
        );
    }

    private static function oembed_meta_sql( string $select ): string {
        global $wpdb;

        return $wpdb->prepare( "SELECT {$select} FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_oembed_' ) . '%' );
    }

    /** @return array{removed:int,failed:int,done:bool} */
    private static function clean_oembed( int $limit ): array {
        global $wpdb;
        $mids   = array_map( 'intval', (array) $wpdb->get_col( self::oembed_meta_sql( 'meta_id' ) . ' LIMIT ' . $limit ) );
        $result = self::delete_each( $mids, $limit, static fn( int $mid ): bool => (bool) delete_metadata_by_mid( 'post', $mid ) );
        if ( ! $result['done'] ) {
            return $result;
        }

        $posts = array_map( 'intval', (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'oembed_cache' ORDER BY ID LIMIT " . $limit ) );
        $more  = self::delete_each( $posts, $limit, static fn( int $id ): bool => (bool) wp_delete_post( $id, true ) );

        return [ 'removed' => $result['removed'] + $more['removed'], 'failed' => $result['failed'] + $more['failed'], 'done' => $more['done'] ];
    }

    /** @return array<string,array{table:string,object:string,id:string}> */
    private static function meta_types(): array {
        global $wpdb;
        $types = [
            'post'    => [ 'table' => $wpdb->postmeta, 'object' => 'post_id', 'id' => 'meta_id' ],
            'comment' => [ 'table' => $wpdb->commentmeta, 'object' => 'comment_id', 'id' => 'meta_id' ],
            'term'    => [ 'table' => $wpdb->termmeta, 'object' => 'term_id', 'id' => 'meta_id' ],
        ];
        if ( self::owns_user_tables() ) {
            $types['user'] = [ 'table' => $wpdb->usermeta, 'object' => 'user_id', 'id' => 'umeta_id' ];
        }

        return $types;
    }

    /** Groups of identical rows. MD5 compares the full value, not just its first 1 KB. */
    private static function duplicate_groups_sql( array $type, string $select ): string {
        return "SELECT {$select} FROM {$type['table']} GROUP BY {$type['object']}, meta_key, MD5( meta_value ) HAVING COUNT(*) > 1";
    }

    /** @return array{removed:int,failed:int,done:bool} */
    private static function clean_duplicate_meta( int $limit ): array {
        global $wpdb;
        $removed = 0;
        $failed  = 0;
        $start   = microtime( true );

        foreach ( self::meta_types() as $meta_type => $type ) {
            $groups = (array) $wpdb->get_results( self::duplicate_groups_sql( $type, "{$type['object']} AS object_id, meta_key, MD5( meta_value ) AS hash, MIN( {$type['id']} ) AS keep_id" ) . ' LIMIT ' . $limit, ARRAY_A );
            foreach ( $groups as $group ) {
                $mids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT {$type['id']} FROM {$type['table']} WHERE {$type['object']} = %d AND meta_key = %s AND MD5( meta_value ) = %s AND {$type['id']} <> %d", $group['object_id'], $group['meta_key'], $group['hash'], $group['keep_id'] ) );
                foreach ( $mids as $mid ) {
                    delete_metadata_by_mid( $meta_type, (int) $mid ) ? $removed++ : $failed++;
                }
                if ( $removed + $failed >= $limit || microtime( true ) - $start > self::TIME_BUDGET ) {
                    return [ 'removed' => $removed, 'failed' => $failed, 'done' => false ];
                }
            }
        }

        return [ 'removed' => $removed, 'failed' => $failed, 'done' => true ];
    }

    /** Taxonomies attached to post types; links for users, links or unknown taxonomies are left alone. */
    private static function relationship_sql( string $select ): string {
        global $wpdb;
        $taxonomies = [];
        $post_types = get_post_types();
        foreach ( get_taxonomies( [], 'objects' ) as $name => $taxonomy ) {
            if ( 'link_category' !== $name && array_intersect( (array) $taxonomy->object_type, $post_types ) ) {
                $taxonomies[] = $name;
            }
        }
        $in = $taxonomies ? "'" . implode( "','", array_map( 'esc_sql', $taxonomies ) ) . "'" : "''";

        return "SELECT {$select} FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.term_taxonomy_id IS NULL OR ( p.ID IS NULL AND tt.taxonomy IN ( {$in} ) )";
    }

    /** @return array{removed:int,failed:int,done:bool} */
    private static function clean_relationships( int $limit ): array {
        global $wpdb;
        $rows = (array) $wpdb->get_results( self::relationship_sql( 'tr.object_id, tr.term_taxonomy_id, tt.term_id, tt.taxonomy' ) . ' LIMIT ' . $limit, ARRAY_A );

        return self::delete_each(
            $rows,
            $limit,
            static function ( array $row ) use ( $wpdb ): bool {
                if ( ! empty( $row['taxonomy'] ) && taxonomy_exists( $row['taxonomy'] ) ) {
                    $done = wp_remove_object_terms( (int) $row['object_id'], (int) $row['term_id'], $row['taxonomy'] );
                    if ( true === $done ) {
                        return true;
                    }
                }
                $deleted = (bool) $wpdb->delete( $wpdb->term_relationships, [ 'object_id' => (int) $row['object_id'], 'term_taxonomy_id' => (int) $row['term_taxonomy_id'] ], [ '%d', '%d' ] );
                if ( $deleted && ! empty( $row['taxonomy'] ) ) {
                    clean_object_term_cache( (int) $row['object_id'], $row['taxonomy'] );
                    wp_update_term_count_now( [ (int) $row['term_taxonomy_id'] ], $row['taxonomy'] );
                }

                return $deleted;
            }
        );
    }

    /** Visible taxonomies only; default terms, parents and terms with any link are kept. */
    private static function unused_terms_sql( string $select ): string {
        global $wpdb;
        $taxonomies = [];
        foreach ( get_taxonomies( [ 'public' => true, 'show_ui' => true ] ) as $name ) {
            if ( ! in_array( $name, [ 'nav_menu', 'link_category', 'post_format', 'wp_theme', 'wp_template_part_area', 'wp_pattern_category' ], true ) ) {
                $taxonomies[] = $name;
            }
        }
        if ( ! $taxonomies ) {
            return "SELECT {$select} FROM {$wpdb->term_taxonomy} tt WHERE 1 = 0";
        }
        $keep = array_filter( array_map( 'intval', [ get_option( 'default_category' ), get_option( 'default_link_category' ), get_option( 'default_product_cat' ), get_option( 'default_post_format' ) ] ) );
        $keep = $keep ? ' AND tt.term_id NOT IN ( ' . implode( ',', $keep ) . ' )' : '';
        $in   = "'" . implode( "','", array_map( 'esc_sql', $taxonomies ) ) . "'";

        return "SELECT {$select} FROM {$wpdb->term_taxonomy} tt WHERE tt.count = 0 AND tt.taxonomy IN ( {$in} ){$keep}"
            . " AND tt.term_id NOT IN ( SELECT parent FROM ( SELECT DISTINCT parent FROM {$wpdb->term_taxonomy} WHERE parent > 0 ) parents )"
            . " AND NOT EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} tr WHERE tr.term_taxonomy_id = tt.term_taxonomy_id )";
    }

    /** @return array{removed:int,failed:int,done:bool} */
    private static function clean_unused_terms( int $limit ): array {
        global $wpdb;
        $rows = (array) $wpdb->get_results( self::unused_terms_sql( 'tt.term_id, tt.taxonomy' ) . ' LIMIT ' . $limit, ARRAY_A );

        return self::delete_each( $rows, $limit, static fn( array $row ): bool => true === wp_delete_term( (int) $row['term_id'], $row['taxonomy'] ) );
    }

    /**
     * @param array<int,mixed> $items
     * @return array{removed:int,failed:int,done:bool}
     */
    private static function delete_each( array $items, int $limit, callable $delete ): array {
        $removed = 0;
        $failed  = 0;
        $start   = microtime( true );
        foreach ( $items as $index => $item ) {
            $delete( $item ) ? $removed++ : $failed++;
            if ( microtime( true ) - $start > self::TIME_BUDGET && $index < count( $items ) - 1 ) {
                return [ 'removed' => $removed, 'failed' => $failed, 'done' => false ];
            }
        }

        // A full batch means there may be more. A batch where nothing could be removed stops the loop.
        return [ 'removed' => $removed, 'failed' => $failed, 'done' => count( $items ) < $limit || 0 === $removed ];
    }

    /** On a multisite network only the main site may clean the shared user tables. */
    private static function owns_user_tables(): bool {
        return ! is_multisite() || is_main_site();
    }

    private static function cutoff( int $days ): string {
        return gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * max( 0, $days ) );
    }

    private static function age( string $text, int $days ): string {
        return $text . ( $days > 0 ? ', older than ' . $days . ' days.' : ', at any age.' );
    }
}
