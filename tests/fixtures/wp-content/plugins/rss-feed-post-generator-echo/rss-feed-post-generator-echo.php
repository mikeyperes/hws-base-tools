<?php

function echo_get_version() {
    return '5.5.1.2';
}

function hws_test_echo_source_callback(): Closure {
    return function( $qry ) {
        if ( is_admin() ) return;
        if ( is_tax( 'coderevolution_post_source' ) ) {
            $qry->set_404();
        }
    };
}

function hws_test_echo_drifted_callback(): Closure {
    return function( $qry ) {
        if ( is_tax( 'changed_post_source' ) ) {
            $qry->set_404();
        }
    };
}
