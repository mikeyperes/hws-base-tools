<?php

namespace hws_base_tools;
/**
 * Generate a nicely styled HTML output for one or more ACF field groups,
 * listing each field (with nested sub-fields if it’s a “group”) and then
 * the display conditions, all wrapped in semantic tags and inline CSS.
 *
 * @param string|array $group_keys A single ACF group key (e.g., 'group_65a8b18d98147')
 *                                 or an array of keys.
 * @return string HTML string with improved styling:
 *                • <div> wrapper per group
 *                • <h2> for group title/key
 *                • <ul> for fields, <li> for each field
 *                • Nested <ul> for sub-fields
 *                • <h3> for “Display Conditions”
 *                • <ul> for condition sets, nested <ul> for individual rules
 */
/**
 * Generate a detailed, recursive HTML hierarchy for one or more ACF field groups.
 * Shows field name, label, key, and type. Handles nested groups, repeaters, and
 * flexible content layouts recursively to any depth.
 *
 * @param string|array $group_keys  Single group key or array of group keys
 * @param bool         $deprecated  If true, shows with red background (pending delete)
 * @return string  HTML output
 */
/**
 * Returns a CLOSURE that renders ACF field group structure(s) as HTML.
 *
 * Returning a closure (instead of a string) ensures the ACF field groups are
 * looked up lazily — at render time on the admin page — rather than eagerly
 * at array-construction time inside get_snippets(), when the groups may not
 * yet be registered.
 *
 * Both snippet renderers (settings-dashboard-snippets.php and
 * settings-dashboard-website-types.php) already handle callable 'info' values
 * via is_callable() + call_user_func().
 *
 * @param string|array $group_keys  One ACF group key or an array of keys.
 * @param bool         $deprecated  Whether to style the box as deprecated.
 * @return callable                 Closure that returns HTML string when invoked.
 */
function display_acf_structure( $group_keys, $deprecated = false ) {
    // — Return a closure so it's evaluated lazily at render time
    return function() use ( $group_keys, $deprecated ) {
        // — Bail if ACF isn't active
        if ( ! function_exists( 'acf_get_field_group' ) ) {
            return '<em style="color:#999;">ACF not active — cannot display field structure.</em>';
        }

        $keys   = is_array( $group_keys ) ? $group_keys : [ $group_keys ];
        $output = '';

        foreach ( $keys as $group_key ) {
            // — Fetch the group object using the correct singular API
            //   acf_get_field_group( $key ) returns the group array or false
            //   (acf_get_field_groups() with a key filter does NOT work reliably)
            $group = acf_get_field_group( $group_key );
            if ( empty( $group ) ) {
                // — Group not registered — show a helpful fallback instead of nothing
                $output .= '<div style="border:1px solid #dba617;border-radius:6px;padding:10px;margin-bottom:12px;background:#fff8e5;font-size:12px;color:#6a5400;">'
                         . '⚠️ ACF group <code>' . esc_html( $group_key ) . '</code> not found. '
                         . 'Enable the snippet that registers it, or verify the group key.'
                         . '</div>';
                continue;
            }

            // — Background color based on deprecation
            $bg     = $deprecated ? 'rgba(255,0,0,0.08)' : '#f8f9fa';
            $border = $deprecated ? '#d63638' : '#ddd';

            // — Open group container
            $output .= '<div style="border:1px solid ' . $border . ';border-radius:6px;padding:14px;margin-bottom:16px;font-family:-apple-system,sans-serif;background:' . $bg . ';font-size:13px;">';

            // — Group title + key
            $output .= '<div style="font-weight:700;font-size:14px;margin-bottom:10px;color:#1d2327;">'
                     . esc_html( $group['title'] )
                     . ' <code style="background:#e0e0e0;padding:2px 6px;border-radius:3px;font-size:11px;color:#555;font-weight:400;">'
                     . esc_html( $group_key )
                     . '</code></div>';

            // — Render fields recursively
            $fields = acf_get_fields( $group_key );
            if ( ! empty( $fields ) ) {
                $output .= hws_render_acf_fields_recursive( $fields, 0 );
            } else {
                $output .= '<div style="color:#999;font-size:12px;font-style:italic;">No fields found in this group.</div>';
            }

            // — Display conditions (location rules)
            if ( isset( $group['location'] ) && is_array( $group['location'] ) && ! empty( $group['location'] ) ) {
                $output .= '<div style="margin-top:10px;padding-top:8px;border-top:1px solid #ddd;">';
                $output .= '<div style="font-weight:600;color:#646970;font-size:12px;margin-bottom:4px;">📍 Display Conditions</div>';
                foreach ( $group['location'] as $si => $rules ) {
                    $parts = [];
                    foreach ( $rules as $rule ) {
                        $parts[] = esc_html( ( $rule['param'] ?? '' ) . ' ' . ( $rule['operator'] ?? '' ) . ' ' . ( $rule['value'] ?? '' ) );
                    }
                    $output .= '<div style="font-size:12px;color:#888;margin-left:12px;">Set ' . ( $si + 1 ) . ': ' . implode( ' AND ', $parts ) . '</div>';
                }
                $output .= '</div>';
            }

            $output .= '</div>';
        }

        return $output;
    };
}


/**
 * Recursively render ACF fields as a nested HTML list.
 * Handles: group, repeater, flexible_content (with layouts), and all leaf types.
 *
 * @param array $fields  Array of ACF field definitions
 * @param int   $depth   Current nesting depth (for indentation)
 * @return string HTML output
 */
function hws_render_acf_fields_recursive( array $fields, int $depth = 0 ): string {
    if ( empty( $fields ) ) return '';

    $indent = $depth * 16;
    $output = '<div style="margin-left:' . $indent . 'px;">';

    foreach ( $fields as $field ) {
        $name  = $field['name'] ?? '';
        $label = $field['label'] ?? '';
        $key   = $field['key'] ?? '';
        $type  = $field['type'] ?? '';

        // — Type badge color
        $type_color = '#646970';
        if ( in_array( $type, [ 'group', 'repeater', 'flexible_content' ], true ) ) {
            $type_color = '#2271b1';
        }

        // — Field row
        $output .= '<div style="padding:3px 0;border-bottom:1px solid #f0f0f1;display:flex;align-items:baseline;gap:6px;flex-wrap:wrap;">';

        // — Expand indicator for parent fields
        if ( in_array( $type, [ 'group', 'repeater', 'flexible_content' ], true ) ) {
            $output .= '<span style="color:#2271b1;font-weight:700;">▼</span>';
        } else {
            $output .= '<span style="color:#ddd;margin-left:2px;">·</span>';
        }

        // — Field name (bold)
        $output .= '<span style="font-weight:600;color:#1d2327;">' . esc_html( $name ) . '</span>';

        // — Label
        if ( $label && $label !== $name ) {
            $output .= '<span style="color:#646970;"> — ' . esc_html( $label ) . '</span>';
        }

        // — Type badge
        $output .= '<code style="background:#f0f0f1;padding:1px 5px;border-radius:3px;font-size:11px;color:' . $type_color . ';">' . esc_html( $type ) . '</code>';

        // — Field key
        $output .= '<code style="font-size:10px;color:#999;">' . esc_html( $key ) . '</code>';

        $output .= '</div>';

        // — Recurse into sub_fields (group, repeater)
        if ( in_array( $type, [ 'group', 'repeater' ], true ) && ! empty( $field['sub_fields'] ) ) {
            $output .= hws_render_acf_fields_recursive( $field['sub_fields'], $depth + 1 );
        }

        // — Recurse into flexible content layouts
        if ( $type === 'flexible_content' && ! empty( $field['layouts'] ) ) {
            foreach ( $field['layouts'] as $layout ) {
                $layout_label = $layout['label'] ?? $layout['name'] ?? 'Layout';
                $layout_key   = $layout['key'] ?? '';
                $output .= '<div style="margin-left:' . ( ( $depth + 1 ) * 16 ) . 'px;padding:3px 0;color:#8c5e00;font-size:12px;">';
                $output .= '📐 <strong>' . esc_html( $layout_label ) . '</strong>';
                if ( $layout_key ) {
                    $output .= ' <code style="font-size:10px;color:#999;">' . esc_html( $layout_key ) . '</code>';
                }
                $output .= '</div>';
                if ( ! empty( $layout['sub_fields'] ) ) {
                    $output .= hws_render_acf_fields_recursive( $layout['sub_fields'], $depth + 2 );
                }
            }
        }
    }

    $output .= '</div>';
    return $output;
}

/**
 * Returns a CLOSURE that renders CPT (Custom Post Type) structure as HTML.
 *
 * Same lazy-evaluation pattern as display_acf_structure():
 * the post type is looked up at render time when the admin page displays,
 * not at array-construction time inside get_snippets().
 *
 * @param string $cpt_slug  The registered post type slug (e.g. 'team-member').
 * @return callable          Closure that returns HTML string when invoked.
 */
function display_cpt_structure( string $cpt_slug ) {
    // — Return a closure so CPT lookup happens lazily at render time
    return function() use ( $cpt_slug ) {
        // — Get the post type object; if not registered, show helpful fallback
        $pt_obj = get_post_type_object( $cpt_slug );
        if ( ! $pt_obj ) {
            return '<div style="border:1px solid #dba617;border-radius:6px;padding:10px;margin-bottom:12px;background:#fff8e5;font-size:12px;color:#6a5400;">'
                 . '⚠️ CPT <code>' . esc_html( $cpt_slug ) . '</code> not registered. '
                 . 'Enable the snippet that registers it.'
                 . '</div>';
        }

        $output = '';

        // — Open CPT container
        $output .= '<div style="'
                 . 'border:1px solid #666;'
                 . 'border-radius:4px;'
                 . 'padding:16px;'
                 . 'margin-bottom:24px;'
                 . 'font-family:Arial, sans-serif;'
                 . 'background-color:#f5f5f5;'
                 . '">';

        // — CPT title and slug
        $output .= '<h2 style="'
                 . 'margin:0 0 12px;'
                 . 'font-size:1.25em;'
                 . 'color:#222;'
                 . '">'
                 . esc_html( $pt_obj->labels->name )
                 . ' <code style="'
                 . 'background:#e0e0e0;'
                 . 'padding:2px 4px;'
                 . 'border-radius:3px;'
                 . 'font-size:0.9em;'
                 . 'color:#444;'
                 . '">'
                 . esc_html( $cpt_slug )
                 . '</code>'
                 . '</h2>';

        // — 1) Labels Section
        $output .= '<h3 style="'
                 . 'margin:12px 0 6px;'
                 . 'font-size:1.1em;'
                 . 'color:#333;'
                 . '">'
                 . 'Labels'
                 . '</h3>';
        $output .= '<ul style="'
                 . 'list-style-type:disc;'
                 . 'margin:0 0 16px 20px;'
                 . 'padding:0;'
                 . '">';
        $label_props = [
            'singular_name', 'add_new_item', 'edit_item', 'view_item',
            'all_items', 'menu_name', 'archives', 'attributes',
            'insert_into_item', 'uploaded_to_this_item', 'filter_items_list',
            'search_items', 'not_found', 'not_found_in_trash',
            'items_list', 'items_list_navigation'
        ];
        foreach ( $label_props as $prop ) {
            if ( isset( $pt_obj->labels->$prop ) && $pt_obj->labels->$prop !== '' ) {
                $output .= '<li style="margin-bottom:6px;">'
                         . '<span style="font-weight:bold; color:#222;">'
                         . esc_html( $prop )
                         . '</span>: '
                         . '<span style="color:#555;">'
                         . esc_html( $pt_obj->labels->$prop )
                         . '</span>'
                         . '</li>';
            }
        }
        $output .= '</ul>';

        // — 2) Supports Section
        if ( ! empty( $pt_obj->supports ) ) {
            $output .= '<h3 style="'
                     . 'margin:12px 0 6px;'
                     . 'font-size:1.1em;'
                     . 'color:#333;'
                     . '">'
                     . 'Supported Features'
                     . '</h3>';
            $output .= '<ul style="'
                     . 'list-style-type:disc;'
                     . 'margin:0 0 16px 20px;'
                     . 'padding:0;'
                     . '">';
            foreach ( $pt_obj->supports as $support ) {
                $output .= '<li style="margin-bottom:6px; color:#555;">'
                         . esc_html( $support )
                         . '</li>';
            }
            $output .= '</ul>';
        }

        // — 3) Taxonomies Section
        if ( ! empty( $pt_obj->taxonomies ) ) {
            $output .= '<h3 style="'
                     . 'margin:12px 0 6px;'
                     . 'font-size:1.1em;'
                     . 'color:#333;'
                     . '">'
                     . 'Attached Taxonomies'
                     . '</h3>';
            $output .= '<ul style="'
                     . 'list-style-type:disc;'
                     . 'margin:0 0 16px 20px;'
                     . 'padding:0;'
                     . '">';
            foreach ( $pt_obj->taxonomies as $tax ) {
                $output .= '<li style="margin-bottom:6px; color:#555;">'
                         . esc_html( $tax )
                         . '</li>';
            }
            $output .= '</ul>';
        }

        // — 4) Flags & Arguments Section
        $output .= '<h3 style="'
                 . 'margin:12px 0 6px;'
                 . 'font-size:1.1em;'
                 . 'color:#333;'
                 . '">'
                 . 'Settings & Flags'
                 . '</h3>';
        $output .= '<ul style="'
                 . 'list-style-type:disc;'
                 . 'margin:0 0 0 20px;'
                 . 'padding:0;'
                 . '">';
        // — Public
        $output .= '<li style="margin-bottom:6px; color:#555;">'
                 . '<strong>public</strong>: '
                 . ( $pt_obj->public ? 'true' : 'false' )
                 . '</li>';
        // — Show in REST
        $show_in_rest = isset( $pt_obj->show_in_rest ) ? $pt_obj->show_in_rest : false;
        $output     .= '<li style="margin-bottom:6px; color:#555;">'
                     . '<strong>show_in_rest</strong>: '
                     . ( $show_in_rest ? 'true' : 'false' )
                     . '</li>';
        // — Menu Icon
        $menu_icon = isset( $pt_obj->menu_icon ) && $pt_obj->menu_icon !== ''
                     ? esc_html( $pt_obj->menu_icon )
                     : '—';
        $output  .= '<li style="margin-bottom:6px; color:#555;">'
                  . '<strong>menu_icon</strong>: '
                  . $menu_icon
                  . '</li>';
        // — Delete with user
        $del_with_user = isset( $pt_obj->delete_with_user ) && $pt_obj->delete_with_user
                         ? 'true'
                         : 'false';
        $output     .= '<li style="margin-bottom:6px; color:#555;">'
                     . '<strong>delete_with_user</strong>: '
                     . $del_with_user
                     . '</li>';
        $output .= '</ul>';

        // — Close CPT container
        $output .= '</div>';

        return $output;
    };
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * REUSABLE INSTRUCTION BOX RENDERER
 * ═══════════════════════════════════════════════════════════════════════════
 * @since 10.9.0
 */
if ( ! function_exists( __NAMESPACE__ . '\\hws_render_instructions' ) ) {
function hws_render_instructions( $title, $steps, $icon = '📋', $collapsed = true ) {
    $uid     = 'hws-instr-' . substr( md5( $title . count( $steps ) ), 0, 8 );
    $display = $collapsed ? 'none' : 'block';
    $arrow   = $collapsed ? '▶' : '▼';
    $html  = '<div class="hws-instruction-box" style="margin-top:12px;border:1px solid #c3d9f0;border-radius:6px;background:#f0f7ff;">';
    $html .= '<div onclick="(function(el){var b=document.getElementById(\'' . $uid . '\');var a=el.querySelector(\'.hws-instr-arrow\');if(b.style.display===\'none\'){b.style.display=\'block\';a.textContent=\'▼\';}else{b.style.display=\'none\';a.textContent=\'▶\';}})(this)" '
           . 'style="padding:10px 14px;cursor:pointer;display:flex;align-items:center;gap:8px;font-weight:600;font-size:13px;user-select:none;">'
           . '<span class="hws-instr-arrow">' . $arrow . '</span> ' . $icon . ' ' . esc_html( $title )
           . '</div>';
    $html .= '<div id="' . $uid . '" style="display:' . $display . ';padding:0 14px 12px;">';
    $html .= '<ol style="margin:0;padding-left:20px;font-size:12.5px;line-height:1.8;color:#1d2327;">';
    foreach ( $steps as $step ) {
        $html .= '<li style="margin-bottom:4px;">' . $step . '</li>';
    }
    $html .= '</ol></div></div>';
    return $html;
}
}
