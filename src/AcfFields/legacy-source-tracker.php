<?php namespace hws_base_tools;

/**
 * ACF Source Tracker / Context Cards
 * ------------------------------------------------------------------------
 * Generic, toggleable feature. On admin edit screens it renders a small
 * CONTEXT CARD at the top of each ACF field group, explaining where the group
 * comes from and what it is:
 *
 *   - the group TITLE + the registering plugin/theme (or database)
 *   - the field list (labels)
 *   - file:line and the unique group_key
 *   - optional curated note / override warning
 *
 * Each group is also EXPANDABLE / COLLAPSIBLE. The collapse behaviour is one of
 * four modes (per group, filterable):
 *   - expanded            (default; open on load)
 *   - collapsed           (closed on load)
 *   - expanded_persist    (open by default, remembers last state)
 *   - collapsed_persist   (closed by default, remembers last state)
 *
 * Source colour coding (subtle): hws-base-tools = blue, smp-verified-profiles
 * = light purple, database groups = grey, anything else = a stable hashed hue.
 *
 * Provenance is derived by scanning plugin/theme PHP files for each group's
 * unique "group_..." key literal (ACF stores no provenance of its own); the
 * result, including file + line, is cached in a transient. This makes the
 * feature accurate on every site regardless of which build of a plugin is
 * deployed.
 *
 * Default OFF. Activated through the HWS feature toggle
 * 'enable_acf_source_tracker'. The active-screen list defaults to the user
 * edit/profile screens and is filterable via 'hws_acf_source_tracker_screens'.
 * Per-group metadata (colour / collapse / note / warning) is filterable via
 * 'hws_acf_group_meta' so any plugin can enrich its own groups.
 *
 * Self-contained: removing this file plus its two registration lines fully
 * removes the feature.
 *
 * @package hws-base-tools
 */

defined( 'ABSPATH' ) || exit;

const HWS_ACF_SOURCE_TRACKER_OPTION    = 'enable_acf_source_tracker';
const HWS_ACF_SOURCE_TRACKER_TRANSIENT = 'hws_acf_source_file_map';

/**
 * Snippet entry point. Called by activate_snippets() only when the toggle is on.
 */
function enable_acf_source_tracker(): void {
	add_action( 'admin_footer', __NAMESPACE__ . '\\hws_acf_source_tracker_render', 99 );
}

/**
 * Screen IDs the tracker renders on. user-edit.php => 'user-edit',
 * profile.php => 'profile'. Filterable so other screens can opt in later.
 *
 * @return string[]
 */
function hws_acf_source_tracker_screen_ids(): array {
	$ids = apply_filters( 'hws_acf_source_tracker_screens', array( 'user-edit', 'profile' ) );

	return array_values( array_filter( array_map( 'strval', (array) $ids ) ) );
}

/**
 * Built-in per-group overrides (colour / collapse / note / warning), merged with
 * the 'hws_acf_group_meta' filter. Keyed by group_key.
 *
 * @return array<string,array>
 */
function hws_acf_group_overrides(): array {
	$overrides = array(
		// User - Additional (hws-base-tools): powers the company/founder shortcodes.
		'group_6842_additional_user_fields_2025' => array(
			'note' => 'Powers the [company id="additional_*"] / [founder id="additional_*"] shortcodes.',
		),
		// The two verified-profile admin groups: closed by default, remember last state.
		'group_65a8b25062d91' => array( 'collapse' => 'collapsed_persist' ),
		'group_658602c9eaa49' => array( 'collapse' => 'collapsed_persist' ),
	);

	return (array) apply_filters( 'hws_acf_group_meta', $overrides );
}

/**
 * Render the inline CSS + JS that builds a context card on each ACF group.
 */
function hws_acf_source_tracker_render(): void {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, hws_acf_source_tracker_screen_ids(), true ) ) {
		return;
	}

	if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
		return;
	}

	$data = hws_acf_source_tracker_build_payload();
	if ( empty( $data['fields'] ) ) {
		return;
	}

	$json = wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
	if ( false === $json ) {
		return;
	}
	?>
	<style id="hws-acf-source-tracker-css">
		.hws-acf-card {
			margin: 14px 0 6px; padding: 10px 12px 10px 14px;
			background: var(--t, #f6f7f9);
			border: 1px solid rgba(0,0,0,.06); border-left: 4px solid var(--c, #cfd3da);
			border-radius: 6px; font-size: 12px; color: #4b4f58;
		}
		.hws-acf-head { display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; }
		.hws-acf-caret { width: 0; height: 0; border-left: 6px solid #9aa0aa; border-top: 4px solid transparent; border-bottom: 4px solid transparent; transition: transform .12s ease; transform: rotate(90deg); flex: 0 0 auto; }
		.hws-acf-card.is-collapsed .hws-acf-caret { transform: rotate(0deg); }
		.hws-acf-dot { width: 9px; height: 9px; border-radius: 50%; background: var(--c, #cfd3da); flex: 0 0 auto; }
		.hws-acf-title { font-size: 13px; font-weight: 600; color: #23272f; }
		.hws-acf-src { margin-left: auto; font-size: 10px; letter-spacing: .04em; text-transform: uppercase; color: var(--c, #707782); font-weight: 600; }
		.hws-acf-meta { margin: 8px 0 0 17px; display: grid; gap: 3px; }
		.hws-acf-card.is-collapsed .hws-acf-meta { display: none; }
		.hws-acf-row { line-height: 1.45; }
		.hws-acf-fields { color: #3b3f47; }
		.hws-acf-src2 { font-family: Menlo, Consolas, monospace; font-size: 11px; color: #8a909a; }
		.hws-acf-note { color: #5a6270; }
		.hws-acf-warn { color: #8a5a00; background: #fff6e5; border-radius: 4px; padding: 4px 7px; }
	</style>
	<script id="hws-acf-source-tracker-js">
	(function () {
		var DATA = <?php echo $json; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_json_encode with HEX flags. ?>;

		function pkey(g) { return 'hws_acf_collapse_' + g; }

		function startCollapsed(src) {
			var mode = src.collapse || 'expanded';
			var base = mode.indexOf('collapsed') === 0;
			if (mode.indexOf('persist') !== -1) {
				try {
					var v = window.localStorage.getItem(pkey(src.group));
					if (v === '1') { return true; }
					if (v === '0') { return false; }
				} catch (e) {}
			}
			return base;
		}

		function row(cls, text) {
			var d = document.createElement('div');
			d.className = 'hws-acf-row ' + cls;
			d.textContent = text;
			return d;
		}

		function apply(block) {
			if (block.getAttribute('data-hws-src')) { return; }
			var fld = block.querySelector('.acf-field[data-key]');
			if (!fld) { return; }
			var gk = DATA.fields[fld.getAttribute('data-key')];
			if (!gk) { return; }
			var src = DATA.groups[gk];
			if (!src) { return; }
			block.setAttribute('data-hws-src', '1');

			var h2 = block.previousElementSibling;
			if (!(h2 && h2.tagName === 'H2')) { h2 = null; }

			var card = document.createElement('div');
			card.className = 'hws-acf-card';
			card.style.setProperty('--c', src.accent);
			card.style.setProperty('--t', src.color);

			var head = document.createElement('div');
			head.className = 'hws-acf-head';
			var caret = document.createElement('span'); caret.className = 'hws-acf-caret';
			var dot = document.createElement('span'); dot.className = 'hws-acf-dot';
			var title = document.createElement('span'); title.className = 'hws-acf-title'; title.textContent = src.title || gk;
			var srcLabel = document.createElement('span'); srcLabel.className = 'hws-acf-src'; srcLabel.textContent = src.plugin;
			head.appendChild(caret); head.appendChild(dot); head.appendChild(title); head.appendChild(srcLabel);
			card.appendChild(head);

			var meta = document.createElement('div');
			meta.className = 'hws-acf-meta';
			if (src.fields && src.fields.length) { meta.appendChild(row('hws-acf-fields', src.fields.join('   ·   '))); }
			var ref = (src.file ? src.file + ':' + src.line + '   ' : '') + '(' + src.group + ')';
			meta.appendChild(row('hws-acf-src2', ref));
			if (src.note) { meta.appendChild(row('hws-acf-note', src.note)); }
			if (src.warning) { meta.appendChild(row('hws-acf-warn', src.warning)); }
			card.appendChild(meta);

			var anchor = h2 || block;
			anchor.parentNode.insertBefore(card, anchor);

			var collapsed = startCollapsed(src);
			function paint() {
				card.classList.toggle('is-collapsed', collapsed);
				if (h2) { h2.style.display = collapsed ? 'none' : ''; }
				block.style.display = collapsed ? 'none' : '';
			}
			paint();

			head.addEventListener('click', function () {
				collapsed = !collapsed;
				if ((src.collapse || '').indexOf('persist') !== -1) {
					try { window.localStorage.setItem(pkey(src.group), collapsed ? '1' : '0'); } catch (e) {}
				}
				paint();
			});
		}

		function run() {
			var blocks = document.querySelectorAll('[class*="acf-user-"][class*="-fields"]');
			Array.prototype.forEach.call(blocks, apply);
		}

		if (document.readyState !== 'loading') { run(); } else { document.addEventListener('DOMContentLoaded', run); }
	})();
	</script>
	<?php
}

/**
 * Build the field_key => group_key and group_key => context maps for the JS.
 *
 * @return array{fields: array<string,string>, groups: array<string,array>}
 */
function hws_acf_source_tracker_build_payload(): array {
	$fields_map = array();
	$groups_map = array();

	$groups = acf_get_field_groups();
	if ( ! is_array( $groups ) ) {
		return array( 'fields' => $fields_map, 'groups' => $groups_map );
	}

	$file_map     = hws_acf_source_file_map();
	$overrides    = hws_acf_group_overrides();
	$rebuilt_once = false;

	foreach ( $groups as $group ) {
		$key = is_array( $group ) ? ( $group['key'] ?? '' ) : '';
		if ( '' === $key ) {
			continue;
		}

		$fields = acf_get_fields( $group );
		if ( ! is_array( $fields ) || empty( $fields ) ) {
			// Only decorate groups whose fields we can match in the DOM.
			continue;
		}

		// A local group whose key is not in the cache means the cache is stale.
		if ( ! isset( $file_map[ $key ] ) && ! $rebuilt_once && ! empty( $group['local'] ) ) {
			$file_map     = hws_acf_source_file_map( true );
			$rebuilt_once = true;
		}

		$labels = array();
		foreach ( $fields as $field ) {
			if ( ! empty( $field['key'] ) ) {
				$fields_map[ $field['key'] ] = $key;
			}
			$labels[] = ! empty( $field['label'] ) ? $field['label'] : ( $field['name'] ?? '' );
		}

		$groups_map[ $key ] = hws_acf_source_for_group( $group, $file_map, $labels, $overrides[ $key ] ?? array() );
	}

	return array( 'fields' => $fields_map, 'groups' => $groups_map );
}

/**
 * Resolve the full context payload for a single field group.
 *
 * @param array $group     ACF field group array.
 * @param array $file_map  group_key => array{label,slug,file,line}.
 * @param array $labels    Field labels in render order.
 * @param array $override   Per-group override (collapse/note/warning/color).
 * @return array
 */
function hws_acf_source_for_group( array $group, array $file_map, array $labels, array $override ): array {
	$key  = $group['key'] ?? '';
	$file = '';
	$line = 0;

	if ( isset( $file_map[ $key ] ) ) {
		$label = $file_map[ $key ]['label'];
		$slug  = $file_map[ $key ]['slug'];
		$file  = $file_map[ $key ]['file'];
		$line  = $file_map[ $key ]['line'];
	} elseif ( empty( $group['local'] ) ) {
		$label = 'Database (ACF UI)';
		$slug  = 'acf-database';
	} else {
		$label = 'Unknown source';
		$slug  = 'unknown';
	}

	$colors = hws_acf_source_colors( $slug );

	return array(
		'title'    => $group['title'] ?? '',
		'plugin'   => $label,
		'slug'     => $slug,
		'group'    => $key,
		'file'     => $file,
		'line'     => $line,
		'fields'   => array_values( array_filter( $labels ) ),
		'color'    => $override['color_tint']   ?? $colors['tint'],
		'accent'   => $override['color_accent'] ?? $colors['accent'],
		'note'     => $override['note']     ?? '',
		'warning'  => $override['warning']  ?? '',
		'collapse' => $override['collapse'] ?? 'expanded',
	);
}

/**
 * Subtle tint + accent colour pair for a source slug. Known Hexa sources get a
 * fixed hue (hws-base = blue, verified-profiles = light purple); others get a
 * stable hashed hue.
 *
 * @return array{tint:string,accent:string}
 */
function hws_acf_source_colors( string $slug ): array {
	$known = array(
		'hws-base-tools'        => array( 'h' => 212, 's' => 72, 'al' => 52 ), // blue
		'smp-verified-profiles' => array( 'h' => 270, 's' => 50, 'al' => 70 ), // light purple
		'acf-database'          => array( 'h' => 220, 's' => 8,  'al' => 60 ), // grey
	);

	if ( isset( $known[ $slug ] ) ) {
		$h  = $known[ $slug ]['h'];
		$s  = $known[ $slug ]['s'];
		$al = $known[ $slug ]['al'];
	} else {
		$h  = abs( crc32( $slug ) ) % 360;
		$s  = 55;
		$al = 60;
	}

	return array(
		'tint'   => 'hsl(' . $h . ', ' . $s . '%, 97%)',
		'accent' => 'hsl(' . $h . ', ' . $s . '%, ' . $al . '%)',
	);
}

/**
 * Cached group_key => array{label,slug,file,line} provenance map.
 *
 * @param bool $force Rebuild the map and refresh the cache.
 * @return array<string,array{label:string,slug:string,file:string,line:int}>
 */
function hws_acf_source_file_map( bool $force = false ): array {
	if ( ! $force ) {
		$cached = get_transient( HWS_ACF_SOURCE_TRACKER_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}
	}

	$map = hws_acf_build_source_file_map();
	set_transient( HWS_ACF_SOURCE_TRACKER_TRANSIENT, $map, 6 * HOUR_IN_SECONDS );

	return $map;
}

/**
 * Scan plugin / mu-plugin / theme PHP files for ACF field-group key literals and
 * map each key to its owning plugin/theme, with file path and line number.
 *
 * @return array<string,array{label:string,slug:string,file:string,line:int}>
 */
function hws_acf_build_source_file_map(): array {
	$roots = array();

	if ( defined( 'WP_PLUGIN_DIR' ) && is_dir( WP_PLUGIN_DIR ) ) {
		$roots['plugin'] = WP_PLUGIN_DIR;
	}
	if ( defined( 'WPMU_PLUGIN_DIR' ) && is_dir( WPMU_PLUGIN_DIR ) ) {
		$roots['muplugin'] = WPMU_PLUGIN_DIR;
	}
	if ( function_exists( 'get_theme_root' ) && is_dir( get_theme_root() ) ) {
		$roots['theme'] = get_theme_root();
	}

	$labels = hws_acf_source_plugin_labels();
	$map    = array();
	$skip   = array( 'node_modules', 'vendor', 'tests', 'test', '.git', 'languages', 'advanced-custom-fields-pro', 'advanced-custom-fields' );

	foreach ( $roots as $type => $root ) {
		try {
			$it = new \RecursiveIteratorIterator(
				new \RecursiveCallbackFilterIterator(
					new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ),
					static function ( $current ) use ( $skip ) {
						$name = $current->getFilename();
						if ( $current->isDir() ) {
							return ! in_array( $name, $skip, true );
						}
						return '.php' === strtolower( substr( $name, -4 ) ) && $current->getSize() < 1048576;
					}
				),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);
		} catch ( \Throwable $e ) {
			continue;
		}

		foreach ( $it as $file ) {
			$path = $file->getPathname();

			$contents = @file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false === $contents || false === strpos( $contents, 'group_' ) ) {
				continue;
			}

			if ( ! preg_match_all( '/[\'"]key[\'"]\s*=>\s*[\'"](group_[A-Za-z0-9_]+)[\'"]/', $contents, $matches, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}

			$relative = ltrim( str_replace( $root, '', $path ), '/\\' );
			$slug     = strtok( $relative, '/\\' );
			if ( ! $slug ) {
				continue;
			}

			if ( 'theme' === $type ) {
				$label = hws_acf_source_theme_label( $slug );
			} else {
				$label = $labels[ $slug ] ?? hws_acf_humanize_slug( $slug );
			}

			foreach ( $matches[1] as $match ) {
				$group_key = $match[0];
				$offset    = $match[1];

				// First definition wins; do not let later/example files overwrite.
				if ( isset( $map[ $group_key ] ) ) {
					continue;
				}

				$line = substr_count( substr( $contents, 0, $offset ), "\n" ) + 1;

				$map[ $group_key ] = array(
					'label' => $label,
					'slug'  => $slug,
					'file'  => str_replace( '\\', '/', $relative ),
					'line'  => $line,
				);
			}
		}
	}

	return $map;
}

/**
 * folder slug => plugin display name, from the installed-plugins list.
 *
 * @return array<string,string>
 */
function hws_acf_source_plugin_labels(): array {
	static $labels = null;

	if ( null !== $labels ) {
		return $labels;
	}

	$labels = array();

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	foreach ( get_plugins() as $plugin_file => $plugin_data ) {
		$folder = strtok( $plugin_file, '/' );
		if ( $folder && ! isset( $labels[ $folder ] ) ) {
			$labels[ $folder ] = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : $folder;
		}
	}

	return $labels;
}

/**
 * Resolve a theme folder slug to its display name.
 */
function hws_acf_source_theme_label( string $slug ): string {
	if ( function_exists( 'wp_get_theme' ) ) {
		$theme = wp_get_theme( $slug );
		if ( $theme && $theme->exists() ) {
			return $theme->get( 'Name' ) . ' (theme)';
		}
	}

	return hws_acf_humanize_slug( $slug ) . ' (theme)';
}

/**
 * Turn a folder slug into a readable label.
 */
function hws_acf_humanize_slug( string $slug ): string {
	return ucwords( trim( str_replace( array( '-', '_' ), ' ', $slug ) ) );
}
