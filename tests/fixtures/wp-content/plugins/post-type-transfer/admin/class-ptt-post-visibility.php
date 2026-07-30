<?php

class PTT_Post_Visibility {
    /** @var list<string> */
    public array $calls = [];

    public function fix_queried_object( $query ) {
        $this->calls[] = 'fix_queried_object';
    }

    public function filter_queries( $query ) {
        $this->calls[] = 'filter_queries';
    }

    public function filter_recent_posts_widget( $args ) {
        $this->calls[] = 'filter_recent_posts_widget';

        return $args;
    }

    public function filter_query_loop_block( $query ) {
        $this->calls[] = 'filter_query_loop_block';

        return $query;
    }
}
