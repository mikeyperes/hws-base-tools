<?php

declare( strict_types=1 );

namespace HWS\BaseTools\AcfFields;

/**
 * Provides the canonical quote repeater used by HWS-owned ACF groups.
 */
final class QuoteRepeaterDefinition {
    /** @return array<string,mixed> */
    public static function build(
        string $repeater_key,
        string $quote_key,
        string $url_key,
        string $tagline_key
    ): array {
        return [
            'key'               => $repeater_key,
            'label'             => 'Quotes',
            'name'              => 'quotes',
            'aria-label'        => '',
            'type'              => 'repeater',
            'instructions'      => 'Store reusable quotes with an optional source URL and attribution or supporting tagline.',
            'required'          => 0,
            'conditional_logic' => 0,
            'wrapper'           => [
                'width' => '',
                'class' => '',
                'id'    => '',
            ],
            'layout'            => 'row',
            'pagination'        => 0,
            'min'               => 0,
            'max'               => 0,
            'collapsed'         => $quote_key,
            'button_label'      => 'Add Quote',
            'rows_per_page'     => 20,
            'sub_fields'        => [
                [
                    'key'               => $quote_key,
                    'label'             => 'Quote',
                    'name'              => 'quote',
                    'aria-label'        => '',
                    'type'              => 'textarea',
                    'instructions'      => 'Enter the complete quote text.',
                    'required'          => 0,
                    'conditional_logic' => 0,
                    'wrapper'           => [
                        'width' => '',
                        'class' => '',
                        'id'    => '',
                    ],
                    'default_value'     => '',
                    'maxlength'         => '',
                    'rows'              => 4,
                    'placeholder'       => 'Enter the quote',
                    'new_lines'         => '',
                    'parent_repeater'   => $repeater_key,
                ],
                [
                    'key'               => $url_key,
                    'label'             => 'URL',
                    'name'              => 'url',
                    'aria-label'        => '',
                    'type'              => 'url',
                    'instructions'      => 'Optional link to the original quote, publication, interview, or source.',
                    'required'          => 0,
                    'conditional_logic' => 0,
                    'wrapper'           => [
                        'width' => '',
                        'class' => '',
                        'id'    => '',
                    ],
                    'default_value'     => '',
                    'placeholder'       => 'https://example.com/source',
                    'parent_repeater'   => $repeater_key,
                ],
                [
                    'key'               => $tagline_key,
                    'label'             => 'Attribution / Tagline',
                    'name'              => 'tagline',
                    'aria-label'        => '',
                    'type'              => 'text',
                    'instructions'      => 'Optional name, title, organization, publication, or short supporting line displayed with the quote.',
                    'required'          => 0,
                    'conditional_logic' => 0,
                    'wrapper'           => [
                        'width' => '',
                        'class' => '',
                        'id'    => '',
                    ],
                    'default_value'     => '',
                    'maxlength'         => '',
                    'placeholder'       => 'Name, title, organization, or publication',
                    'parent_repeater'   => $repeater_key,
                ],
            ],
        ];
    }
}
