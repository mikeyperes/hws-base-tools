# People filter chips and team paging

## People

`[hws_search profile="people" directory="people" filter_layout="chips" style="none"]`

Publication people-list shortcodes also accept `filter_layout="chips" search_style="none"` with `per_page="10"`. Defaults remain dropdowns. Chips choose one group; All removes the group constraint and preserves the name search. Existing dropdowns retain multiple selection.

Markup: `.hls-filters > fieldset.hls-chips > legend` names the group; each `label.hls-chip` contains a native `input[type=radio][name="hf[group][]"]` and a `span` label. Values are empty (All), `byline`, `contributor`, `staff`. Checked state and `.is-active` identify the selection. Native keyboard and focus behavior are retained. `style="none"` emits the same HTML without chip decoration. Site CSS can use `.hls-chip:has(input:checked)`; never hide the input without preserving focus indication.

## Team

`[hws_team_members per_page="10" pagination="load_more" search="1" search_style="none" design="none"]`

`pagination="infinite"` uses the same next-page link with automatic scrolling. Omit `per_page` for the unchanged static list. Optional `search="0"` hides the form while retaining paging. Existing category, featured, ordering, roles-first and card options apply. Paging uses the full matching directory rather than the static `limit`. Names are searched in published `team-member` post titles, never WordPress users. The shared callback sorter retains its Core candidate bound.

`[hws_search profile="people" directory="team" style="none"]` is a shortcut to team-post search with ten per page. Use the team shortcode for explicit card and paging options.

Wrapper `.hws-team-search` contains `.hls` search and `.hls-results`; each `.hls-list > .hls-item` holds existing `.hws-team-directory` and `.hws-team-card` markup. `.hls-more` is the next batch link; status is announced by `.hls-status`. State uses `hs` (profile), `hq` (name), `hp` (batch), and `hf[group][]` (people group). GET URLs work without JavaScript; the existing scoped search script upgrades them to in-place updates, aborts superseded requests and exposes retryable errors.

Assets are emitted only when these shortcodes render. Existing page and article defaults do not enable chips or team paging. No media is created or modified.
