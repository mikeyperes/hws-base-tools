<?php

namespace HWS\BaseTools\DatabaseOptimization;

/**
 * Retention rules for database cleanup. Every age is in days; 0 means "any age".
 */
final class OptimizationSettings {
    public const OPTION = 'hws_database_optimization_settings';

    /** @var array<string,int> */
    private const DEFAULTS = [
        'keep_revisions'  => 5,
        'revision_days'   => 0,
        'autodraft_days'  => 7,
        'trash_days'      => 7,
        'comment_days'    => 7,
        'unapproved_days' => 30,
        'batch_size'      => 250,
    ];

    /** @var array<string,array{label:string,help:string,min:int,max:int}> */
    private const FIELDS = [
        'keep_revisions'  => [ 'label' => 'Revisions to keep per post', 'help' => 'The newest revisions of every post are always kept.', 'min' => 0, 'max' => 100 ],
        'revision_days'   => [ 'label' => 'Only remove revisions older than (days)', 'help' => '0 removes every revision beyond the kept ones.', 'min' => 0, 'max' => 3650 ],
        'autodraft_days'  => [ 'label' => 'Auto-drafts older than (days)', 'help' => 'Empty drafts WordPress creates when an editor is opened.', 'min' => 0, 'max' => 3650 ],
        'trash_days'      => [ 'label' => 'Trashed posts older than (days)', 'help' => 'Counted from the day the item was trashed.', 'min' => 0, 'max' => 3650 ],
        'comment_days'    => [ 'label' => 'Spam and trashed comments older than (days)', 'help' => 'Pingbacks and trackbacks are removed at any age.', 'min' => 0, 'max' => 3650 ],
        'unapproved_days' => [ 'label' => 'Unapproved comments older than (days)', 'help' => 'Only used when the unapproved comments item is selected.', 'min' => 0, 'max' => 3650 ],
        'batch_size'      => [ 'label' => 'Rows per batch', 'help' => 'Each live step removes at most this many rows.', 'min' => 25, 'max' => 1000 ],
    ];

    /** @return array<string,int> */
    public static function get(): array {
        $stored = function_exists( 'get_option' ) ? get_option( self::OPTION, [] ) : [];

        return self::sanitize( is_array( $stored ) ? $stored : [] );
    }

    public static function value( string $key ): int {
        return self::get()[ $key ] ?? ( self::DEFAULTS[ $key ] ?? 0 );
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,int>
     */
    public static function save( array $input ): array {
        $clean = self::sanitize( array_merge( self::get(), $input ) );
        update_option( self::OPTION, $clean, false );

        return $clean;
    }

    /** @return array<string,array{label:string,help:string,min:int,max:int}> */
    public static function fields(): array {
        return self::FIELDS;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,int>
     */
    public static function sanitize( array $input ): array {
        $clean = [];
        foreach ( self::DEFAULTS as $key => $default ) {
            $value         = array_key_exists( $key, $input ) && is_numeric( $input[ $key ] ) ? (int) $input[ $key ] : $default;
            $clean[ $key ] = max( self::FIELDS[ $key ]['min'], min( self::FIELDS[ $key ]['max'], $value ) );
        }

        return $clean;
    }
}
