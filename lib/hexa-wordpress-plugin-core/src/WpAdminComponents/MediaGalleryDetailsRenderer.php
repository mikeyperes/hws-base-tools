<?php

declare( strict_types=1 );

namespace Hexa\PluginCore\WpAdminComponents;

/**
 * Renders selectable WordPress image attachments with copyable size URLs.
 */
final class MediaGalleryDetailsRenderer {
    /**
     * @param array<int,mixed> $attachments Attachment IDs, ACF image arrays, or attachment objects.
     * @param array<string,mixed> $args
     */
    public static function render( array $attachments, array $args = [] ): string {
        $attachment_ids = self::attachment_ids( $attachments );
        $title          = (string) ( $args['title'] ?? 'Details' );
        $persist_key    = (string) ( $args['persist_key'] ?? 'media-gallery-details' );
        $open           = ! empty( $args['open'] );

        ob_start();
        DynamicButton::render_assets();
        self::render_assets();
        $assets = (string) ob_get_clean();

        $body = '<div class="hpc-media-gallery-details" data-hpc-media-gallery-details>';
        if ( [] === $attachment_ids ) {
            $body .= '<p class="hpc-media-gallery-empty">No gallery images are currently selected.</p>';
        } else {
            $body .= '<div class="hpc-media-gallery-toolbar">'
                . '<label><input type="checkbox" data-hpc-gallery-select-all> <span>Select all images</span></label>'
                . '<span role="status" aria-live="polite" data-hpc-gallery-selection-status>0 selected</span>'
                . '</div>';
            $body .= '<div class="hpc-media-gallery-items">';
            foreach ( $attachment_ids as $attachment_id ) {
                $item = self::render_attachment( $attachment_id );
                if ( '' !== $item ) {
                    $body .= $item;
                }
            }
            $body .= '</div>';
        }
        $body .= '</div>';

        $count = count( $attachment_ids );
        $meta  = CoreUi::pill( $count . ' ' . ( 1 === $count ? 'image' : 'images' ) );
        $card  = CoreUi::detail_card(
            [
                'title'       => $title,
                'body_html'   => $body,
                'meta_html'   => $meta,
                'open'        => $open,
                'persist_key' => $persist_key,
                'class'       => 'hpc-media-gallery-details-card',
            ]
        );

        return $assets . '<div class="hpc-ui hpc-media-gallery-details-shell">' . $card . '</div>';
    }

    /** @param array<int,mixed> $attachments @return array<int,int> */
    public static function attachment_ids( array $attachments ): array {
        $ids = [];
        foreach ( $attachments as $attachment ) {
            $id = 0;
            if ( is_numeric( $attachment ) ) {
                $id = (int) $attachment;
            } elseif ( is_object( $attachment ) && isset( $attachment->ID ) ) {
                $id = (int) $attachment->ID;
            } elseif ( is_array( $attachment ) ) {
                $id = (int) ( $attachment['ID'] ?? $attachment['id'] ?? 0 );
            }
            if ( $id > 0 && ! in_array( $id, $ids, true ) && wp_attachment_is_image( $id ) ) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private static function render_attachment( int $attachment_id ): string {
        $sizes = self::image_sizes( $attachment_id );
        if ( [] === $sizes ) {
            return '';
        }

        $title       = trim( (string) get_the_title( $attachment_id ) );
        $full_url    = (string) ( $sizes['full']['url'] ?? '' );
        $preview     = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
        $preview_url = is_array( $preview ) && ! empty( $preview[0] ) ? (string) $preview[0] : $full_url;
        $filename    = '' !== $full_url ? wp_basename( (string) wp_parse_url( $full_url, PHP_URL_PATH ) ) : '';
        if ( '' === $title ) {
            $title = '' !== $filename ? $filename : 'Image ' . $attachment_id;
        }

        $html  = '<article class="hpc-media-gallery-item" data-hpc-gallery-item data-attachment-id="' . esc_attr( (string) $attachment_id ) . '">';
        $html .= '<label class="hpc-media-gallery-item-select">'
            . '<input type="checkbox" value="' . esc_attr( (string) $attachment_id ) . '" data-hpc-gallery-select>'
            . '<span class="hpc-media-gallery-thumb"><img src="' . esc_url( $preview_url ) . '" alt=""></span>'
            . '<span class="hpc-media-gallery-item-title"><strong>' . esc_html( $title ) . '</strong>'
            . '<small>Attachment #' . esc_html( (string) $attachment_id ) . ( '' !== $filename ? ' &middot; ' . esc_html( $filename ) : '' ) . '</small></span>'
            . '</label>';
        $html .= '<div class="hpc-media-gallery-size-list">';

        foreach ( $sizes as $size_name => $size ) {
            $dimensions = '';
            if ( $size['width'] > 0 && $size['height'] > 0 ) {
                $dimensions = $size['width'] . ' x ' . $size['height'] . ' px';
            }
            $size_label = 'full' === $size_name ? 'Full' : ucwords( str_replace( [ '-', '_' ], ' ', $size_name ) );
            $html      .= '<div class="hpc-media-gallery-size">'
                . '<div class="hpc-media-gallery-size-label"><strong>' . esc_html( $size_label ) . '</strong>'
                . ( '' !== $dimensions ? '<span>' . esc_html( $dimensions ) . '</span>' : '' ) . '</div>'
                . '<a class="hpc-media-gallery-url hpc-external" href="' . esc_url( $size['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $size['url'] ) . '</a>'
                . DynamicButton::render(
                    [
                        'label'         => 'Copy',
                        'working_label' => 'Copy to clipboard',
                        'success_label' => 'Copied',
                        'error_label'   => 'Copy failed',
                        'class'         => 'hpc-button secondary hpc-media-gallery-copy',
                        'render_assets' => false,
                        'attrs'         => [
                            'data-hpc-gallery-copy' => $size['url'],
                            'aria-label'            => 'Copy ' . $size_label . ' image URL to clipboard',
                        ],
                    ]
                )
                . '</div>';
        }

        return $html . '</div></article>';
    }

    /** @return array<string,array{url:string,width:int,height:int}> */
    private static function image_sizes( int $attachment_id ): array {
        $metadata = wp_get_attachment_metadata( $attachment_id );
        $full_url = wp_get_attachment_url( $attachment_id );
        if ( ! is_string( $full_url ) || '' === $full_url ) {
            return [];
        }

        $sizes = [
            'full' => [
                'url'    => $full_url,
                'width'  => is_array( $metadata ) ? (int) ( $metadata['width'] ?? 0 ) : 0,
                'height' => is_array( $metadata ) ? (int) ( $metadata['height'] ?? 0 ) : 0,
            ],
        ];
        $generated = is_array( $metadata ) && isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] )
            ? $metadata['sizes']
            : [];
        $names = array_keys( $generated );
        usort( $names, [ self::class, 'compare_size_names' ] );

        foreach ( $names as $name ) {
            $source = wp_get_attachment_image_src( $attachment_id, (string) $name );
            if ( ! is_array( $source ) || empty( $source[0] ) ) {
                continue;
            }
            $data = is_array( $generated[ $name ] ?? null ) ? $generated[ $name ] : [];
            $sizes[ (string) $name ] = [
                'url'    => (string) $source[0],
                'width'  => (int) ( $data['width'] ?? $source[1] ?? 0 ),
                'height' => (int) ( $data['height'] ?? $source[2] ?? 0 ),
            ];
        }

        return $sizes;
    }

    private static function compare_size_names( string $left, string $right ): int {
        $order = [ 'thumbnail', 'medium', 'medium_large', 'large', '1536x1536', '2048x2048' ];
        $a     = array_search( $left, $order, true );
        $b     = array_search( $right, $order, true );
        $a     = false === $a ? PHP_INT_MAX : $a;
        $b     = false === $b ? PHP_INT_MAX : $b;

        return $a === $b ? strnatcasecmp( $left, $right ) : $a <=> $b;
    }

    private static function render_assets(): void {
        static $rendered = false;
        if ( $rendered ) {
            return;
        }
        $rendered = true;
        ?>
        <style>
            .hpc-media-gallery-details-shell{margin-top:14px;max-width:100%}
            .hpc-media-gallery-details-card{margin-bottom:0}
            .hpc-media-gallery-toolbar{align-items:center;border-bottom:1px solid var(--hpc-line);display:flex;gap:16px;justify-content:space-between;margin-bottom:12px;padding-bottom:12px}
            .hpc-media-gallery-toolbar label{align-items:center;display:inline-flex;font-weight:700;gap:7px}
            .hpc-media-gallery-toolbar [data-hpc-gallery-selection-status]{color:var(--hpc-muted);font-size:12px}
            .hpc-media-gallery-items{display:grid;gap:12px}
            .hpc-media-gallery-item{background:#fff;border:1px solid var(--hpc-line);border-radius:7px;min-width:0;overflow:hidden}
            .hpc-media-gallery-item.is-selected{border-color:var(--hpc-blue);box-shadow:0 0 0 1px var(--hpc-blue)}
            .hpc-media-gallery-item-select{align-items:center;background:#f8fafc;border-bottom:1px solid var(--hpc-line);cursor:pointer;display:grid;gap:10px;grid-template-columns:auto 52px minmax(0,1fr);padding:10px 12px}
            .hpc-media-gallery-item-select input{height:16px;margin:0;width:16px}
            .hpc-media-gallery-thumb{background:#eef2f7;border:1px solid var(--hpc-line);border-radius:5px;display:block;height:52px;overflow:hidden;width:52px}
            .hpc-media-gallery-thumb img{display:block;height:100%;object-fit:cover;width:100%}
            .hpc-media-gallery-item-title{min-width:0}
            .hpc-media-gallery-item-title strong,.hpc-media-gallery-item-title small{display:block;overflow-wrap:anywhere}
            .hpc-media-gallery-item-title strong{font-size:13px;margin-bottom:3px}
            .hpc-media-gallery-item-title small{color:var(--hpc-muted);font-size:11px}
            .hpc-media-gallery-size-list{display:grid}
            .hpc-media-gallery-size{align-items:center;border-bottom:1px solid #edf1f6;display:grid;gap:10px;grid-template-columns:130px minmax(0,1fr) auto;padding:9px 12px}
            .hpc-media-gallery-size:last-child{border-bottom:0}
            .hpc-media-gallery-size-label strong,.hpc-media-gallery-size-label span{display:block}
            .hpc-media-gallery-size-label strong{font-size:12px}
            .hpc-media-gallery-size-label span{color:var(--hpc-muted);font-size:10px;margin-top:2px}
            .hpc-media-gallery-url{color:var(--hpc-blue);font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;font-size:11px;min-width:0;overflow-wrap:anywhere;user-select:text}
            .hpc-media-gallery-copy{font-size:11px;min-height:32px;padding:7px 9px;white-space:nowrap}
            .hpc-media-gallery-copy .hpc-dynamic-button-spinner{height:16px;min-height:16px;min-width:16px;width:16px}
            .hpc-media-gallery-empty{color:var(--hpc-muted);margin:0}
            @media(max-width:782px){.hpc-media-gallery-size{align-items:start;grid-template-columns:minmax(0,1fr)}.hpc-media-gallery-copy{justify-self:start}.hpc-media-gallery-toolbar{align-items:flex-start;flex-direction:column;gap:7px}}
        </style>
        <script>
        (function(){
            if(window.hexaCoreMediaGalleryDetailsReady)return;
            window.hexaCoreMediaGalleryDetailsReady=true;
            function legacyCopy(value){
                return new Promise(function(resolve,reject){
                    var input=document.createElement('textarea');
                    input.value=value;input.setAttribute('readonly','');input.style.position='fixed';input.style.opacity='0';
                    document.body.appendChild(input);input.select();
                    try{document.execCommand('copy')?resolve():reject(new Error('Copy command failed.'))}catch(error){reject(error)}
                    document.body.removeChild(input);
                });
            }
            function copyText(value){
                if(navigator.clipboard&&window.isSecureContext){
                    return navigator.clipboard.writeText(value).catch(function(){return legacyCopy(value)});
                }
                return legacyCopy(value);
            }
            function updateSelection(root){
                if(!root)return;
                var boxes=Array.prototype.slice.call(root.querySelectorAll('[data-hpc-gallery-select]'));
                var selected=boxes.filter(function(box){return box.checked});
                boxes.forEach(function(box){var item=box.closest('[data-hpc-gallery-item]');if(item)item.classList.toggle('is-selected',box.checked)});
                var all=root.querySelector('[data-hpc-gallery-select-all]');
                if(all){all.checked=boxes.length>0&&selected.length===boxes.length;all.indeterminate=selected.length>0&&selected.length<boxes.length}
                var status=root.querySelector('[data-hpc-gallery-selection-status]');
                if(status)status.textContent=selected.length+' selected';
            }
            document.addEventListener('change',function(event){
                var select=event.target.closest('[data-hpc-gallery-select]');
                if(select){updateSelection(select.closest('[data-hpc-media-gallery-details]'));return}
                var all=event.target.closest('[data-hpc-gallery-select-all]');
                if(all){var root=all.closest('[data-hpc-media-gallery-details]');root.querySelectorAll('[data-hpc-gallery-select]').forEach(function(box){box.checked=all.checked});updateSelection(root)}
            });
            document.addEventListener('click',function(event){
                var button=event.target.closest('[data-hpc-gallery-copy]');
                if(!button)return;
                event.preventDefault();
                var api=window.HexaWpCoreDynamicButton;
                if(api)api.start(button,'Copy to clipboard');
                copyText(button.getAttribute('data-hpc-gallery-copy')||'').then(function(){
                    window.setTimeout(function(){if(api)api.success(button,'Copied')},220);
                }).catch(function(){if(api)api.error(button,'Copy failed')});
            });
            document.querySelectorAll('[data-hpc-media-gallery-details]').forEach(updateSelection);
        })();
        </script>
        <?php
    }
}
