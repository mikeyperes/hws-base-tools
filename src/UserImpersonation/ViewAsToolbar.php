<?php

declare( strict_types=1 );

namespace HWS\BaseTools\UserImpersonation;

use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

/** Generic entry points over the existing cookie-preserving virtual sessions. */
final class ViewAsToolbar {
    private const SEARCH_ACTION = 'hws_view_as_users';

    public function __construct( private readonly VirtualRequestContext $context ) {}

    public function register(): void {
        add_action( 'admin_bar_menu', [ $this, 'toolbar' ], 90 );
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'assets' ] );
        add_action( 'wp_ajax_' . self::SEARCH_ACTION, [ $this, 'search_users' ] );
    }

    public function toolbar( \WP_Admin_Bar $bar ): void {
        $page = $this->current_page();
        if ( ! $page || ! $this->available() ) {
            return;
        }
        $bar->add_node( [ 'id' => 'hws-view-as', 'title' => 'View as user', 'href' => false ] );
        foreach ( $this->default_users( $page ) as $id => $entry ) {
            $bar->add_node( [
                'id' => 'hws-view-as-' . $id,
                'parent' => 'hws-view-as',
                'title' => esc_html( $entry['source'] . ': ' . $this->user_label( $entry['user'] ) ),
                'href' => ViewAsController::start_url( (int) $id, $page['destination'] ),
                'meta' => [ 'target' => '_blank', 'rel' => 'noopener noreferrer' ],
            ] );
        }
        $bar->add_node( [
            'id' => 'hws-view-as-search', 'parent' => 'hws-view-as',
            'title' => 'Choose another user', 'href' => false,
            'meta' => [ 'html' => '<div class="hws-view-as-picker"><label for="hws-view-as-query">Search name, username or email</label><input id="hws-view-as-query" type="search" autocomplete="off" placeholder="Type at least 2 characters" aria-controls="hws-view-as-results"><p id="hws-view-as-status" role="status" aria-live="polite"></p><ul id="hws-view-as-results"></ul><p>Opens in a new tab. Your admin tab stays signed in.</p></div>' ],
        ] );
    }

    public function assets(): void {
        $page = $this->current_page();
        if ( ! $page || ! $this->available() || ! is_admin_bar_showing() ) {
            return;
        }
        $url = plugin_dir_url( PluginMetadata::plugin_file() );
        wp_enqueue_style( 'hws-view-as-picker', $url . 'assets/user-impersonation/view-as-picker.css', [], PluginMetadata::VERSION );
        wp_enqueue_script( 'hws-view-as-picker', $url . 'assets/user-impersonation/view-as-picker.js', [], PluginMetadata::VERSION, true );
        wp_localize_script( 'hws-view-as-picker', 'hwsViewAsPicker', [
            'url' => admin_url( 'admin-ajax.php' ), 'action' => self::SEARCH_ACTION,
            'nonce' => wp_create_nonce( self::SEARCH_ACTION ),
            'postId' => $page['post_id'], 'view' => $page['location'],
        ] );
    }

    public function search_users(): void {
        if ( ! $this->available() ) {
            wp_send_json_error( [ 'message' => 'Only administrators can select a View As user.' ], 403 );
            return;
        }
        check_ajax_referer( self::SEARCH_ACTION, 'nonce' );
        $location = isset( $_POST['view'] ) && is_string( $_POST['view'] ) ? sanitize_key( wp_unslash( $_POST['view'] ) ) : '';
        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! UserImpersonationFeature::location_enabled( $location ) ) {
            wp_send_json_error( [ 'message' => 'View As is disabled for this location.' ], 403 );
            return;
        }
        $destination = admin_url();
        if ( in_array( $location, [ 'post_editor', 'single_content' ], true ) ) {
            $post = get_post( $post_id );
            if ( ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $post_id ) ) {
                wp_send_json_error( [ 'message' => 'Content not found or inaccessible.' ], 403 );
                return;
            }
            $destination = $this->post_destination( $post, 'post_editor' === $location );
        }
        $query = isset( $_POST['query'] ) && is_string( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
        if ( strlen( $query ) < 2 ) {
            wp_send_json_success( [ 'users' => [] ] );
            return;
        }
        $matches = new \WP_User_Query( [
            'number' => 20, 'count_total' => false, 'orderby' => 'display_name', 'order' => 'ASC',
            'search' => '*' . trim( $query, '*' ) . '*',
            'search_columns' => [ 'display_name', 'user_login', 'user_email' ],
        ] );
        $users = [];
        foreach ( $matches->get_results() as $user ) {
            $users[] = [ 'label' => $this->user_label( $user ), 'url' => ViewAsController::start_url( (int) $user->ID, $destination ) ];
        }
        wp_send_json_success( [ 'users' => $users ] );
    }

    private function available(): bool {
        return current_user_can( 'manage_options' ) && UserImpersonationFeature::enabled()
            && ! $this->context->is_active() && '' === VirtualRequestContext::request_token_from_globals();
    }

    /** @return array{location:string,post_id:int,user_id:int,destination:string}|null */
    private function current_page(): ?array {
        if ( is_admin() ) {
            $screen = get_current_screen();
            if ( ! $screen ) {
                return null;
            }
            if ( in_array( $screen->base, [ 'users', 'user', 'user-edit', 'profile' ], true ) && UserImpersonationFeature::location_enabled( 'users' ) ) {
                $user_id = 'profile' === $screen->base ? get_current_user_id() : absint( $_GET['user_id'] ?? 0 );
                return [ 'location' => 'users', 'post_id' => 0, 'user_id' => $user_id, 'destination' => admin_url() ];
            }
            if ( 'post' !== $screen->base || ! UserImpersonationFeature::location_enabled( 'post_editor' ) ) {
                return null;
            }
            $location = 'post_editor';
            $post = get_post( absint( $_GET['post'] ?? 0 ) );
        } else {
            if ( ! is_singular() || ! UserImpersonationFeature::location_enabled( 'single_content' ) ) {
                return null;
            }
            $location = 'single_content';
            $post = get_queried_object();
        }
        if ( ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
            return null;
        }
        return [ 'location' => $location, 'post_id' => (int) $post->ID, 'user_id' => 0, 'destination' => $this->post_destination( $post, 'post_editor' === $location ) ];
    }

    /** @param array{post_id:int,user_id:int} $page @return array<int,array{user:\WP_User,source:string}> */
    private function default_users( array $page ): array {
        $users = [];
        $add = static function ( int $id, string $label ) use ( &$users ): void {
            $user = get_userdata( $id );
            if ( ! $user instanceof \WP_User ) {
                return;
            }
            if ( isset( $users[$id] ) ) {
                $users[$id]['source'] .= ' / ' . $label;
            } else {
                $users[$id] = [ 'user' => $user, 'source' => $label ];
            }
        };
        if ( $page['post_id'] ) {
            $post = get_post( $page['post_id'] );
            $add( (int) $post->post_author, 'Author' );
            foreach ( UserImpersonationFeature::settings()['owner_fields'] as $field ) {
                $value = get_post_meta( $post->ID, $field, true );
                // ACF user fields store either one ID or a list of IDs in post metadata.
                foreach ( (array) $value as $id ) {
                    if ( is_scalar( $id ) && ctype_digit( (string) $id ) ) {
                        $add( (int) $id, ucwords( str_replace( [ '_', '-' ], ' ', $field ) ) );
                    }
                }
            }
        } elseif ( $page['user_id'] ) {
            $add( $page['user_id'], 'User' );
        }
        return $users;
    }

    private function post_destination( \WP_Post $post, bool $editor ): string {
        return $editor ? admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) : (string) get_permalink( $post );
    }

    private function user_label( \WP_User $user ): string {
        return $user->display_name . ' (' . $user->user_login . ')';
    }
}
