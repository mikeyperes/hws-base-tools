<?php

namespace HWS\BaseTools\SitemapTools;

defined( 'ABSPATH' ) || exit;

/**
 * Requests one feed or sitemap from the server and reports whether it works:
 * the server response, whether the XML is valid, its fields and its items.
 */
final class XmlInspector {
    private const MAX_BYTES   = 6000000;
    private const SAMPLE_ITEMS = 25;
    private const VALUE_CHARS = 400;
    private const BODY_CHARS  = 4000;

    public static function feed( string $key, array $feed ): array {
        $result = self::base( $key, $feed );
        if ( ! $feed['available'] || '' === $feed['url'] ) {
            return $result;
        }
        if ( SitemapFeedRegistry::is_disabled( $key ) ) {
            $result['status'] = 'off';
            $result['summary'] = SitemapFeedRegistry::forced_off( $key )
                ? 'Off because comments are turned off: the address returns "not found".'
                : 'Turned off: the address returns "not found".';
        }

        $response = self::request( $feed['url'], $result );
        if ( null === $response ) {
            return $result;
        }

        $dom  = $response;
        $root = $dom->documentElement;
        $atom = 'feed' === $root->localName;
        $checks = &$result['checks'];
        $checks[] = self::check( 'RSS 2.0 or Atom document', 'rss' === $root->localName || $atom, '<' . $root->nodeName . '>' );

        $channel = $atom ? $root : self::child( $root, 'channel' );
        $checks[] = self::check( 'Has a channel', null !== $channel );
        if ( null === $channel ) {
            return self::finish( $result );
        }

        $item_tag = $atom ? 'entry' : 'item';
        $result['channel'] = self::fields( $channel, [ $item_tag ] );
        foreach ( $atom ? [ 'title', 'id', 'updated' ] : [ 'title', 'link', 'description' ] as $field ) {
            $checks[] = self::check( 'Channel ' . $field, '' !== trim( (string) ( $result['channel'][ $field ] ?? '' ) ), (string) ( $result['channel'][ $field ] ?? 'missing' ) );
        }
        if ( ! $atom ) {
            $checks[] = self::check( 'Self link (atom:link)', isset( $result['channel']['atom:link'] ), '', true );
        }

        $items    = self::children( $channel, $item_tag );
        $latest   = 0;
        $coverage = [];
        foreach ( $items as $index => $item ) {
            $fields = self::fields( $item );
            foreach ( array_keys( $fields ) as $name ) {
                $coverage[ $name ] = ( $coverage[ $name ] ?? 0 ) + 1;
            }
            $date   = (string) ( $fields['pubDate'] ?? $fields['published'] ?? $fields['updated'] ?? $fields['dc:date'] ?? '' );
            $time   = '' !== $date ? (int) strtotime( $date ) : 0;
            $latest = max( $latest, $time );
            if ( $index < self::SAMPLE_ITEMS ) {
                $result['items'][] = $fields;
            }
        }

        $count = count( $items );
        $result['feed_items']   = $count;
        $result['latest_item']  = $latest;
        $result['coverage']     = $coverage;
        // An empty feed is correct when nothing has been published yet.
        $nothing_published = 0 === $result['total'];
        $checks[] = $nothing_published && 0 === $count
            ? self::check( 'Empty because nothing is published yet', true )
            : self::check( 'Has items', $count > 0, $count . ' in the feed' );
        if ( $count > 0 ) {
            foreach ( $atom ? [ 'title', 'id', 'updated' ] : [ 'title', 'link', 'guid', 'pubDate' ] as $field ) {
                $has = $coverage[ $field ] ?? 0;
                $checks[] = self::check( 'Every item has ' . $field, $has === $count, $has . ' of ' . $count );
            }
        }

        return self::finish( $result );
    }

    public static function sitemap( string $key, array $sitemap ): array {
        $result = self::base( $key, $sitemap );
        if ( ! $sitemap['available'] || '' === $sitemap['url'] ) {
            return $result;
        }

        $dom = self::request( $sitemap['url'], $result );
        if ( null === $dom ) {
            return $result;
        }

        $root   = $dom->documentElement;
        $index  = 'sitemapindex' === $root->localName;
        $checks = &$result['checks'];
        $checks[] = self::check( 'Sitemap document', $index || 'urlset' === $root->localName, '<' . $root->nodeName . '>' );
        if ( ! $index && 'urlset' !== $root->localName ) {
            return self::finish( $result );
        }

        $entries  = self::children( $root, $index ? 'sitemap' : 'url' );
        $host     = wp_parse_url( home_url(), PHP_URL_HOST );
        $foreign  = 0;
        $no_loc   = 0;
        $latest   = 0;
        $news     = 0;
        $images   = 0;
        $videos   = 0;
        foreach ( $entries as $position => $entry ) {
            $fields = self::fields( $entry );
            $loc    = (string) ( $fields['loc'] ?? '' );
            if ( '' === $loc ) {
                $no_loc++;
            } elseif ( wp_parse_url( $loc, PHP_URL_HOST ) !== $host ) {
                $foreign++;
            }
            $news_fields = isset( $fields['news:news'] ) && is_array( $fields['news:news'] ) ? $fields['news:news'] : [];
            $date   = (string) ( $fields['lastmod'] ?? $news_fields['news:publication_date'] ?? '' );
            $latest = max( $latest, '' !== $date ? (int) strtotime( $date ) : 0 );
            $news  += isset( $fields['news:news'] ) ? 1 : 0;
            $images += (int) ( $fields['image:image#count'] ?? ( isset( $fields['image:image'] ) ? 1 : 0 ) );
            $videos += isset( $fields['video:video'] ) ? 1 : 0;
            if ( $position < self::SAMPLE_ITEMS ) {
                $result['items'][] = $fields;
            }
        }

        $count = count( $entries );
        $result['sitemap_type'] = $index ? 'index' : 'urlset';
        $result['feed_items']   = $count;
        $result['latest_item']  = $latest;
        $result['extra']        = array_filter( [ 'news' => $news, 'images' => $images, 'videos' => $videos ] );
        $checks[] = self::check( $index ? 'Lists sitemaps' : 'Lists addresses', $count > 0 || 'news' === $sitemap['kind'], $count . ( $index ? ' sitemaps' : ' addresses' ) );
        $checks[] = self::check( 'Every entry has an address', 0 === $no_loc, $no_loc ? $no_loc . ' without <loc>' : 'all have <loc>' );
        $checks[] = self::check( 'All addresses are on this site', 0 === $foreign, $foreign ? $foreign . ' point elsewhere' : (string) $host );
        if ( 'news' === $sitemap['kind'] ) {
            $checks[] = self::check( 'News entries from the last two days', $news > 0, $news . ' entries', true );
        }
        if ( $count >= 50000 ) {
            $checks[] = self::check( 'Under the 50,000 address limit', false, (string) $count );
        }

        return self::finish( $result );
    }

    private static function base( string $key, array $entry ): array {
        $total  = is_callable( $entry['total'] ) ? (int) call_user_func( $entry['total'] ) : null;
        $latest = is_callable( $entry['latest'] ) ? (int) call_user_func( $entry['latest'] ) : null;
        return [
            'key'          => $key,
            'url'          => (string) $entry['url'],
            'status'       => $entry['available'] && '' !== $entry['url'] ? 'unchecked' : 'unavailable',
            'summary'      => $entry['available'] ? '' : (string) $entry['note'],
            'checked_at'   => time(),
            'response'     => [],
            'checks'       => [],
            'channel'      => [],
            'items'        => [],
            'coverage'     => [],
            'feed_items'   => null,
            'latest_item'  => 0,
            'total'        => $total,
            'latest_saved' => $latest,
        ];
    }

    /** Fetches the address as a visitor would and parses it; null when it cannot be read as XML. */
    private static function request( string $url, array &$result ): ?\DOMDocument {
        $started  = microtime( true );
        $response = wp_remote_get(
            add_query_arg( 'hws_check', (string) time(), $url ),
            [
                'timeout'             => 20,
                'redirection'         => 3,
                'sslverify'           => false,
                'limit_response_size' => self::MAX_BYTES,
                'headers'             => [ 'Cache-Control' => 'no-cache', 'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.1' ],
                'user-agent'          => 'HWS Base Tools feed check (' . home_url() . ')',
            ]
        );
        $elapsed = (int) round( ( microtime( true ) - $started ) * 1000 );

        if ( is_wp_error( $response ) ) {
            $result['response'] = [ 'error' => $response->get_error_message(), 'time_ms' => $elapsed ];
            $result['checks'][] = self::check( 'Server answers', false, $response->get_error_message() );
            return self::finish_null( $result );
        }

        $code    = (int) wp_remote_retrieve_response_code( $response );
        $headers = wp_remote_retrieve_headers( $response );
        $headers = is_object( $headers ) && method_exists( $headers, 'getAll' ) ? $headers->getAll() : (array) $headers;
        $flat    = [];
        foreach ( $headers as $name => $value ) {
            $flat[ strtolower( (string) $name ) ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
        }
        $body  = (string) wp_remote_retrieve_body( $response );
        $type  = $flat['content-type'] ?? '';
        $cache = trim( ( $flat['x-litespeed-cache'] ?? '' ) . ' ' . ( $flat['x-litespeed-cache-control'] ?? '' ) );

        $result['response'] = [
            'code'         => $code,
            'message'      => (string) wp_remote_retrieve_response_message( $response ),
            'time_ms'      => $elapsed,
            'bytes'        => strlen( $body ),
            'content_type' => $type,
            'cache'        => '' !== $cache ? $cache : 'no LiteSpeed cache header',
            'headers'      => $flat,
            'body'         => mb_substr( $body, 0, self::BODY_CHARS ),
        ];
        $result['checks'][] = self::check( 'Server answers 200', 200 === $code, 'HTTP ' . $code . ' in ' . $elapsed . ' ms' );
        $result['checks'][] = self::check( 'XML content type', false !== stripos( $type, 'xml' ), '' !== $type ? $type : 'none' );
        if ( 200 !== $code ) {
            return self::finish_null( $result );
        }

        $previous = libxml_use_internal_errors( true );
        $dom      = new \DOMDocument();
        $parsed   = '' !== trim( $body ) && $dom->loadXML( ltrim( $body ), LIBXML_NONET | LIBXML_NOCDATA | LIBXML_PARSEHUGE );
        $errors   = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        $first = $errors ? trim( $errors[0]->message ) . ' (line ' . $errors[0]->line . ')' : '';
        $result['checks'][] = self::check( 'Valid XML', $parsed && null !== $dom->documentElement, $parsed ? 'parsed' : ( '' !== $first ? $first : 'empty response' ) );

        return $parsed && null !== $dom->documentElement ? $dom : self::finish_null( $result );
    }

    /** The element's child fields as name => text, with attributes where an element has no text. */
    private static function fields( \DOMElement $element, array $skip = [] ): array {
        $fields = [];
        foreach ( $element->childNodes as $node ) {
            if ( ! $node instanceof \DOMElement || in_array( $node->localName, $skip, true ) ) {
                continue;
            }
            $name  = $node->nodeName;
            $value = self::value( $node );
            if ( isset( $fields[ $name ] ) ) {
                $fields[ $name . '#count' ] = ( $fields[ $name . '#count' ] ?? 1 ) + 1;
                if ( is_string( $fields[ $name ] ) && is_string( $value ) && substr_count( $fields[ $name ], ' | ' ) < 5 ) {
                    $fields[ $name ] .= ' | ' . $value;
                }
                continue;
            }
            $fields[ $name ] = $value;
        }
        return $fields;
    }

    /** @return string|array */
    private static function value( \DOMElement $node ) {
        $has_children = false;
        foreach ( $node->childNodes as $child ) {
            if ( $child instanceof \DOMElement ) {
                $has_children = true;
                break;
            }
        }
        if ( $has_children ) {
            return self::fields( $node );
        }
        $text = trim( (string) $node->textContent );
        if ( '' === $text && $node->hasAttributes() ) {
            $attributes = [];
            foreach ( $node->attributes as $attribute ) {
                $attributes[] = $attribute->name . '="' . $attribute->value . '"';
            }
            $text = implode( ' ', $attributes );
        }
        return mb_strlen( $text ) > self::VALUE_CHARS ? mb_substr( $text, 0, self::VALUE_CHARS ) . '…' : $text;
    }

    private static function child( \DOMElement $parent, string $name ): ?\DOMElement {
        foreach ( $parent->childNodes as $node ) {
            if ( $node instanceof \DOMElement && $node->localName === $name ) {
                return $node;
            }
        }
        return null;
    }

    /** @return \DOMElement[] */
    private static function children( \DOMElement $parent, string $name ): array {
        $found = [];
        foreach ( $parent->childNodes as $node ) {
            if ( $node instanceof \DOMElement && $node->localName === $name ) {
                $found[] = $node;
            }
        }
        return $found;
    }

    private static function check( string $label, bool $ok, string $detail = '', bool $warning_only = false ): array {
        return [ 'label' => $label, 'ok' => $ok, 'detail' => $detail, 'warning' => $warning_only ];
    }

    private static function finish( array $result ): array {
        $failed  = array_filter( $result['checks'], static fn( array $check ): bool => ! $check['ok'] && ! $check['warning'] );
        $warned  = array_filter( $result['checks'], static fn( array $check ): bool => ! $check['ok'] && $check['warning'] );
        if ( 'off' === $result['status'] ) {
            return $result;
        }
        $result['status']  = $failed ? 'failed' : 'healthy';
        $result['summary'] = $failed
            ? 'Not working: ' . strtolower( implode( '; ', array_column( $failed, 'label' ) ) ) . '.'
            : ( $warned ? 'Working, with a note: ' . strtolower( implode( '; ', array_column( $warned, 'label' ) ) ) . '.' : 'Working.' );
        return $result;
    }

    private static function finish_null( array &$result ): ?\DOMDocument {
        $result = self::finish( $result );
        return null;
    }
}
