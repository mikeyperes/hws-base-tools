<?php namespace hws_base_tools;
function enable_cpt_testimonial(){


	register_post_type( 'testimonial', array(
	'labels' => array(
		'name' => 'Testimonials',
		'singular_name' => 'Testimonial',
		'menu_name' => 'Testimonials',
		'all_items' => 'All Testimonials',
		'edit_item' => 'Edit Testimonial',
		'view_item' => 'View Testimonial',
		'view_items' => 'View Testimonials',
		'add_new_item' => 'Add New Testimonial',
		'add_new' => 'Add New Testimonial',
		'new_item' => 'New Testimonial',
		'parent_item_colon' => 'Parent Testimonial:',
		'search_items' => 'Search Testimonials',
		'not_found' => 'No testimonials found',
		'not_found_in_trash' => 'No testimonials found in Trash',
		'archives' => 'Testimonial Archives',
		'attributes' => 'Testimonial Attributes',
		'insert_into_item' => 'Insert into testimonial',
		'uploaded_to_this_item' => 'Uploaded to this testimonial',
		'filter_items_list' => 'Filter testimonials list',
		'filter_by_date' => 'Filter testimonials by date',
		'items_list_navigation' => 'Testimonials list navigation',
		'items_list' => 'Testimonials list',
		'item_published' => 'Testimonial published.',
		'item_published_privately' => 'Testimonial published privately.',
		'item_reverted_to_draft' => 'Testimonial reverted to draft.',
		'item_scheduled' => 'Testimonial scheduled.',
		'item_updated' => 'Testimonial updated.',
		'item_link' => 'Testimonial Link',
		'item_link_description' => 'A link to a testimonial.',
	),
	'public' => true,
	'show_in_rest' => true,
	'menu_icon' => 'dashicons-admin-post',
	'supports' => array(
		0 => 'title',
		1 => 'editor',
		2 => 'thumbnail',
		3 => 'custom-fields',
	),
	'taxonomies' => array(
		0 => 'category',
	),
	'delete_with_user' => false,
) );


}?>
