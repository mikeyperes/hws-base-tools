<?php

namespace HWS\BaseTools\TeamMembers;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

defined( 'ABSPATH' ) || exit;

final class TeamMemberFeature {
    /**
     * @return array<string,mixed>
     */
    public static function definition(): array {
        return [
            'id'               => TeamMemberDirectory::FEATURE_OPTION,
            'name'             => 'Team Member Directory Templates',
            'description'      => 'Displays the HWS Team Member custom post type through one reusable shortcode with three clean, responsive layouts.',
            'info'             => 'Requires the HWS <code>team-member</code> custom post type. The Position and Featured fields come from the matching HWS ACF group.',
            'function'         => 'enable_team_member_directory_templates',
            'scope_admin_only' => false,
            'code_example'     => "[hws_team_members]\n[hws_team_members style=\"portrait_grid\" columns=\"3\"]\n[hws_team_members style=\"editorial_list\"]\n[hws_team_members style=\"compact_directory\"]",
        ];
    }

    public static function render_settings(): void {
        CoreUi::render_assets();

        $report = TeamMemberDirectory::integrity_report();
        $selected = (string) $report['style'];
        $templates = TeamMemberDirectory::template_options();
        $setup_url = admin_url( 'options-general.php?page=hws-core-tools&tab=website-types' );
        $team_url = admin_url( 'edit.php?post_type=' . TeamMemberDirectory::POST_TYPE );
        ?>
        <div class="hws-team-feature-settings" data-feature-settings="<?php echo esc_attr( TeamMemberDirectory::FEATURE_OPTION ); ?>">
            <style>
                <?php echo TeamMemberDirectory::styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                .hws-team-readiness{background:#f8fafc;border:1px solid #d8dee8;border-left:4px solid #b42336;border-radius:8px;margin:0 0 16px;padding:14px}
                .hws-team-readiness.is-ready{background:#f2faf5;border-left-color:#16803c}
                .hws-team-readiness h5{font-size:14px;margin:0 0 8px}
                .hws-team-status-list{display:grid;gap:7px;margin:0 0 12px}
                .hws-team-status-row{align-items:center;display:flex;font-size:13px;font-weight:650;gap:7px}
                .hws-team-status-row.is-pass .dashicons{color:#16803c}
                .hws-team-status-row.is-fail .dashicons{color:#b42336}
                .hws-team-status-row.is-warning .dashicons{color:#9a6700}
                .hws-team-readiness .hpc-actions{margin-top:10px}
                .hws-team-template-list{display:grid;gap:12px}
                .hws-team-template-option{background:#fff;border:1px solid #d8dee8;border-radius:8px;cursor:pointer;display:block;padding:12px;transition:border-color .16s,box-shadow .16s}
                .hws-team-template-option.is-selected{border-color:#3157d5;box-shadow:inset 0 0 0 1px #3157d5}
                .hws-team-template-heading{align-items:flex-start;display:flex;gap:9px;margin-bottom:10px}
                .hws-team-template-heading input{margin-top:2px}
                .hws-team-template-copy{display:grid;gap:8px;margin-top:16px}
                .hws-team-shortcode-row{align-items:center;background:#f8fafc;border:1px solid #d8dee8;border-radius:8px;display:grid;gap:10px;grid-template-columns:170px minmax(0,1fr) auto;padding:10px 12px}
                .hws-team-shortcode-row strong{font-size:12px}
                .hws-team-shortcode-row code{overflow-wrap:anywhere;white-space:normal}
                .hws-team-template-status{color:#3157d5;font-size:12px;font-weight:700;min-height:18px;margin-top:8px}
                .hws-team-feature-settings .hws-team-directory--preview{background:#fff;border:1px solid #edf0f4;border-radius:6px;max-width:820px;padding:10px}
                @media(max-width:782px){.hws-team-shortcode-row{grid-template-columns:minmax(0,1fr)}.hws-team-shortcode-row .hpc-button{justify-self:start}}
            </style>

            <div class="hws-team-readiness <?php echo $report['post_type_active'] ? 'is-ready' : ''; ?>">
                <h5>Prerequisite check</h5>
                <div class="hws-team-status-list">
                    <?php self::status_row( (bool) $report['post_type_active'], 'Team Member CPT (team-member): ' . ( $report['post_type_active'] ? 'Active' : 'Inactive' ) ); ?>
                    <?php self::status_row( (bool) $report['acf_option_enabled'], 'Team Member ACF fields (position, featured): ' . ( $report['acf_option_enabled'] ? 'Active' : 'Inactive' ) ); ?>
                    <?php self::status_row( (int) $report['published'] > 0, 'Published Team Members: ' . (int) $report['published'], true ); ?>
                </div>
                <p><?php echo $report['post_type_active'] ? 'Ready. Choose a template, enable the feature, and place the shortcode on the team page.' : 'Enable the Team Member custom post type before using this directory.'; ?></p>
                <div class="hpc-actions">
                    <a class="hpc-button secondary" href="<?php echo esc_url( $setup_url ); ?>">Open Team Member setup</a>
                    <?php if ( $report['post_type_active'] ) : ?>
                        <?php echo CoreUi::external_link( $team_url, 'Manage Team Members' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                </div>
            </div>

            <h5>Default team directory template</h5>
            <div class="hws-team-template-list">
                <?php foreach ( $templates as $style => $template ) : ?>
                    <label class="hws-team-template-option <?php echo $selected === $style ? 'is-selected' : ''; ?>">
                        <span class="hws-team-template-heading">
                            <input type="radio" name="hws_team_member_directory_style" value="<?php echo esc_attr( $style ); ?>" data-hws-team-template <?php checked( $selected, $style ); ?>>
                            <span><strong><?php echo esc_html( $template['label'] ); ?></strong><br><small><?php echo esc_html( $template['description'] ); ?></small></span>
                        </span>
                        <?php echo TeamMemberDirectory::preview_html( $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="hws-team-template-status" data-hws-team-template-status aria-live="polite"></div>

            <h5>Copy-ready shortcodes</h5>
            <div class="hws-team-template-copy">
                <?php foreach ( self::shortcode_examples() as $label => $shortcode ) : ?>
                    <div class="hws-team-shortcode-row">
                        <strong><?php echo esc_html( $label ); ?></strong>
                        <code<?php echo 'Selected default' === $label ? ' data-hws-team-selected-shortcode' : ''; ?>><?php echo esc_html( $shortcode ); ?></code>
                        <?php echo CoreUi::copy_button( $shortcode, 'Copy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * @return array<string,string>
     */
    public static function save_style( string $style ): array {
        $style = TeamMemberDirectory::normalize_style( $style );
        update_option( TeamMemberDirectory::STYLE_OPTION, $style, false );

        $templates = TeamMemberDirectory::template_options();

        return [
            'style'     => $style,
            'label'     => (string) $templates[ $style ]['label'],
            'shortcode' => '[hws_team_members style="' . $style . '"]',
        ];
    }

    /**
     * @return array{passed:bool,message:string,proof:string,ran_at:string}
     */
    public static function test_report(): array {
        $report = TeamMemberDirectory::integrity_report();
        $templates = TeamMemberDirectory::template_options();
        $passed = (bool) $report['post_type_active']
            && (bool) $report['acf_option_enabled']
            && (bool) $report['shortcode_registered']
            && 3 === count( $templates );

        return [
            'passed'  => $passed,
            'message' => $passed
                ? 'Team Member CPT, ACF fields, shortcode, and all three templates are active.'
                : 'The Team Member directory is missing a required CPT, ACF field group, shortcode, or template.',
            'proof'   => 'CPT ' . ( $report['post_type_active'] ? 'active' : 'inactive' )
                . '; ACF ' . ( $report['acf_option_enabled'] ? 'active' : 'inactive' )
                . '; shortcode ' . ( $report['shortcode_registered'] ? 'registered' : 'missing' )
                . '; templates ' . count( $templates )
                . '; published ' . (int) $report['published']
                . '; selected ' . (string) $report['style'] . '.',
            'ran_at'  => current_time( 'mysql' ),
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function shortcode_examples(): array {
        return [
            'Selected default'      => '[hws_team_members]',
            'Minimal portrait grid' => '[hws_team_members style="portrait_grid" columns="3"]',
            'Editorial list'        => '[hws_team_members style="editorial_list"]',
            'Compact directory'     => '[hws_team_members style="compact_directory"]',
            'Featured people only'  => '[hws_team_members featured_only="1"]',
            'Filtered example'      => '[hws_team_members category="leadership" limit="6" show_excerpt="0"]',
        ];
    }

    private static function status_row( bool $passed, string $label, bool $warning_when_false = false ): void {
        $class = $passed ? 'is-pass' : ( $warning_when_false ? 'is-warning' : 'is-fail' );
        $icon = $passed ? 'dashicons-yes-alt' : ( $warning_when_false ? 'dashicons-warning' : 'dashicons-dismiss' );
        echo '<div class="hws-team-status-row ' . esc_attr( $class ) . '"><span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span><span>' . esc_html( $label ) . '</span></div>';
    }
}
