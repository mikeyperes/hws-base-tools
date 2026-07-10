<?php

namespace HWS\BaseTools\AcfFields;

/**
 * Legacy SMP user fields retained only for explicit HWS compatibility mode.
 * New person/publication plugins must own their user field groups directly.
 */
final class LegacySmpUserFields {
    public static function register(): void {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        acf_add_local_field_group(
            [
            'key'    => 'group_65a8b18d98147',
            'title'  => 'User - Admin',
            'fields' => [
                [
                    'key'           => 'field_65a8b18d1293a',
                    'label'         => 'Team Member',
                    'name'          => 'team_member',
                    'type'          => 'true_false',
                    'required'      => 0,
                    'message'       => '',
                    'default_value' => 0,
                    'ui'            => 1,
                ],
                [
                    'key'           => 'field_65a8b1bbcf0b9',
                    'label'         => 'Team Member Title',
                    'name'          => 'team_member_title',
                    'type'          => 'text',
                    'required'      => 0,
                    'default_value' => '',
                    'maxlength'     => '',
                    'placeholder'   => '',
                    'prepend'       => '',
                    'append'        => '',
                ],
                [
                    'key'           => 'field_647563b9aba66',
                    'label'         => 'What best describes you?',
                    'name'          => 'what_best_describe_you',
                    'type'          => 'radio',
                    'required'      => 0,
                    'choices'       => [
                        'Journalist'  => "I'm a Journalist",
                        'Publication' => "I'm a Publication",
                    ],
                    'allow_null'     => 0,
                    'other_choice'   => 0,
                    'default_value'  => '',
                    'layout'         => 'vertical',
                    'return_format'  => 'value',
                ],
                [
                    'key'           => 'field_6482a0f010e43',
                    'label'         => 'Profile Photo',
                    'name'          => 'profile_photo',
                    'type'          => 'image',
                    'required'      => 0,
                    'return_format' => 'id',
                    'preview_size'  => 'medium',
                    'library'       => 'all',
                    'mime_types'    => 'jpg,jpeg,png,webp',
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'current_user_role',
                        'operator' => '==',
                        'value'    => 'administrator',
                    ],
                    [
                        'param'    => 'user_form',
                        'operator' => '==',
                        'value'    => 'all',
                    ],
                ],
            ],
            'menu_order'            => 0,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
            'active'                => true,
            'show_in_rest'          => 0,
            ]
        );
    }
}
