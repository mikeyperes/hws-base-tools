# People search visibility

For `[hws_search profile="people" directory="people"]`, `search="always"` forces the search form on even for small directories. `search_threshold="0"` likewise shows the form for any nonempty directory. The default threshold is 10: ten or fewer people hide the form, while pagination and cards remain available. A positive threshold changes that count; negative values clamp to zero and nonnumeric values use 10. `search="0"` always hides the form, including when the threshold is zero. Empty directories still render nothing.

These options do not change the eligible people, order, filtering, pagination, styling or default behavior. Existing pages must opt in by passing an attribute; installing an update does not edit pages.
