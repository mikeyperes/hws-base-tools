<?php
namespace HWS\BaseTools\Media;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

/** Explicit CLI migration only. No visitor hooks or automatic downloads. */
final class ExternalFeaturedImageModule implements ModuleInterface {
    public function register(): void {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('hws media import-external-featured', [$this, 'run']);
        }
    }

    /**
     * Import external featured images as ordinary local attachments.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Inspect URLs and proposed actions without writing.
     * [--post-type=<type>]
     * : Restrict to one post type. Default: all non-attachment types.
     * [--meta-key=<key>]
     * : Source URL post meta. Default: fifu_image_url.
     */
    public function run(array $args, array $assoc): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $key = (string)($assoc['meta-key'] ?? 'fifu_image_url');
        $fallback = $key === 'fifu_image_url'
            ? " OR (a.post_author='7777777777' AND a.post_content_filtered REGEXP '^https?://')"
            : '';
        $sql = $wpdb->prepare("SELECT DISTINCT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key=%s LEFT JOIN {$wpdb->postmeta} t ON t.post_id=p.ID AND t.meta_key='_thumbnail_id' LEFT JOIN {$wpdb->posts} a ON a.ID=CAST(t.meta_value AS UNSIGNED) AND a.post_type='attachment' WHERE (m.meta_value<>'' {$fallback}) AND p.post_type NOT IN ('attachment','revision')", $key);
        if (isset($assoc['post-type'])) {
            $sql .= $wpdb->prepare(' AND p.post_type=%s', $assoc['post-type']);
        }
        $ids = $wpdb->get_col($sql . ' ORDER BY p.ID');
        $failed = 0;
        foreach ($ids as $id) {
            $url = trim((string)get_post_meta($id, $key, true));
            if ($url === '' && $key === 'fifu_image_url') {
                $old = get_post((int)get_post_meta($id, '_thumbnail_id', true));
                if ($old && $old->post_type === 'attachment' && $old->post_author === '7777777777') {
                    $url = trim($old->post_content_filtered);
                }
            }
            $result = ['post_id'=>(int)$id, 'post_url'=>get_permalink($id), 'source_url'=>$url];
            $temp = null;
            try {
                if (!wp_http_validate_url($url)) {
                    throw new \RuntimeException('Unsafe or invalid HTTP image URL.');
                }
                $response = wp_safe_remote_get($url, ['timeout'=>40, 'redirection'=>5, 'stream'=>true, 'filename'=>($temp=wp_tempnam('hws-external-image'))]);
                if (is_wp_error($response)) {
                    throw new \RuntimeException($response->get_error_message());
                }
                $status = wp_remote_retrieve_response_code($response);
                $result['http_status'] = $status;
                if ($status !== 200) {
                    throw new \RuntimeException('Source returned HTTP ' . $status . '; current featured image preserved.');
                }
                $mime = wp_get_image_mime($temp);
                $svg = $mime ? null : $this->svg_dimensions($temp);
                if ($svg) { $mime = 'image/svg+xml'; }
                $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','image/avif'=>'avif','image/svg+xml'=>'svg'];
                if (!isset($extensions[$mime])) {
                    throw new \RuntimeException('Response is not a supported image; current featured image preserved.');
                }
                $hash = hash('sha256', $url);
                $attachment = (int)$wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_hws_external_featured_url_hash' AND meta_value=%s LIMIT 1", $hash));
                if (!$this->local($attachment)) {
                    $attachment = (int)$wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND guid=%s LIMIT 1", $url));
                }
                if (!$this->local($attachment)) {
                    $attachment = 0;
                }
                if (isset($assoc['dry-run'])) {
                    $result += ['result'=>$attachment ? 'would_reuse' : 'would_import', 'attachment_id'=>$attachment];
                } else {
                    if (!$attachment) {
                        $title = wp_strip_all_tags(get_the_title($id));
                        $name = sanitize_title($title) ?: 'featured-image';
                        $svg_mimes = static function(array $mimes): array { $mimes['svg'] = 'image/svg+xml'; return $mimes; };
                        if ($svg) { add_filter('upload_mimes', $svg_mimes); }
                        try {
                            $attachment = media_handle_sideload(['name'=>$name . '-' . substr($hash,0,12) . '.' . $extensions[$mime], 'tmp_name'=>$temp], (int)$id, $title);
                        } finally {
                            if ($svg) { remove_filter('upload_mimes', $svg_mimes); }
                        }
                        if (is_wp_error($attachment)) {
                            throw new \RuntimeException($attachment->get_error_message());
                        }
                        $temp = null;
                        if ($svg) {
                            $svg['file'] = get_post_meta($attachment, '_wp_attached_file', true);
                            $svg['sizes'] = [];
                            wp_update_attachment_metadata($attachment, $svg);
                        }
                        update_post_meta($attachment, '_wp_attachment_image_alt', $title);
                    }
                    update_post_meta($attachment, '_hws_external_featured_url_hash', $hash);
                    update_post_meta($attachment, '_hws_external_featured_source_url', $url);
                    set_post_thumbnail((int)$id, $attachment);
                    if ((int)get_post_meta($id,'_thumbnail_id',true) !== $attachment || !$this->local($attachment)) {
                        throw new \RuntimeException('Featured image assignment did not persist.');
                    }
                    $result += ['result'=>'imported_or_reused','attachment_id'=>$attachment];
                }
            } catch (\Throwable $e) {
                ++$failed;
                $result += ['result'=>'error','error'=>$e->getMessage()];
            } finally {
                if ($temp && is_file($temp)) {
                    wp_delete_file($temp);
                }
            }
            \WP_CLI::line(wp_json_encode($result, JSON_UNESCAPED_SLASHES));
        }
        \WP_CLI::line(wp_json_encode(['total'=>count($ids),'failed'=>$failed,'dry_run'=>isset($assoc['dry-run'])]));
        if ($failed) {
            \WP_CLI::halt(1);
        }
    }

    /** Accept passive vector artwork only; no global SVG upload setting changes. */
    private function svg_dimensions(string $file): ?array {
        if (!class_exists('DOMDocument')) { return null; }
        $xml = file_get_contents($file);
        if (!is_string($xml) || stripos($xml, '<svg') === false ||
            preg_match('/<!DOCTYPE|<!ENTITY|<\?(?!xml)/i', $xml)) { return null; }
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$doc->loadXML($xml, LIBXML_NONET) || !$doc->documentElement ||
                $doc->documentElement->localName !== 'svg') { return null; }
            $tags = ['svg','g','path','defs','rect','circle','ellipse','line','polyline','polygon',
                'text','tspan','title','desc','linearGradient','radialGradient','stop','clipPath',
                'mask','use','symbol','pattern','marker'];
            foreach ($doc->getElementsByTagName('*') as $element) {
                if (!in_array($element->localName, $tags, true)) { return null; }
                foreach ($element->attributes as $attr) {
                    if (stripos($attr->localName, 'on') === 0 ||
                        in_array($attr->localName, ['base','style'], true) ||
                        ($attr->localName === 'href' && !str_starts_with($attr->value, '#')) ||
                        preg_match('/url\(\s*[^#]|javascript:|data:/i', $attr->value)) { return null; }
                }
            }
            $root = $doc->documentElement;
            $box = preg_split('/[\s,]+/', trim($root->getAttribute('viewBox')));
            $width = count($box) === 4 ? (float)$box[2] : (float)$root->getAttribute('width');
            $height = count($box) === 4 ? (float)$box[3] : (float)$root->getAttribute('height');
            return $width > 0 && $height > 0
                ? ['width'=>(int)ceil($width), 'height'=>(int)ceil($height)] : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function local(int $id): bool {
        $file = $id ? get_attached_file($id, true) : '';
        return $id && wp_attachment_is_image($id) && $file && is_file($file);
    }
}
