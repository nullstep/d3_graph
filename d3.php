<?php

/*
 * Plugin Name: d3
 * Plugin URI: https://nullstep.com/d3
 * Description: A d3.js plugin
 * Author: nullstep
 * Author URI: https://nullstep.com
 * Version: 1.0.0
 */

defined('ABSPATH') or die('nope');

// defines      

define('_PLUGIN_D3', 'd3');

define('_URL_D3', plugin_dir_url(__FILE__));
define('_PATH_D3', plugin_dir_path(__FILE__));

define('_ARGS_D3', [
	'plonk' => [
		'type' => 'string',
		'default' => ''
	],
	'plink' => [
		'type' => 'integer',
		'default' => 1
	],
	'file_name' => [
		'type' => 'string',
		'default' => ''
	],
	'some_text' => [
		'type' => 'string',
		'default' => ''
	],
	'text_colour' => [
		'type' => 'string',
		'default' => ''
	]
]);

define('_FORM_D3', [
	'settings' => [
		'label' => 'Settings',
		'fields' => [
			'plonk' => [
				'label' => 'Plonk',
				'type' => 'input'
			]
		]
	],
	'choices' => [
		'label' => 'You Choose',
		'fields' => [
			'plink' => [
				'label' => 'Plink',
				'type' => 'select',
				'values' => [
					'0' => 'No',
					'1' => 'Yes',
					'2' => 'Maybe'
				]
			]
		]
	],
	'the_file' => [
		'label' => 'File Attachment',
		'fields' => [
			'file_name' => [
				'label' => 'File',
				'type' => 'file'
			]
		]
	],
	'test_text' => [
		'label' => 'Some Text',
		'fields' => [
			'some_text' => [
				'label' => 'Text',
				'type' => 'code'
			]
		]
	],
	'colours' => [
		'label' => 'Colours',
		'fields' => [
			'text_colour' => [
				'label' => 'Text Colour',
				'type' => 'colour'
			]
		]
	]
]);

// classes

class d3_API {
	public function add_routes() {
		register_rest_route(_PLUGIN_D3 . '-plugin-api/v1', '/settings', [
				'methods' => 'POST',
				'callback' => [$this, 'update_settings'],
				'args' => d3_Settings::args(),
				'permission_callback' => [$this, 'permissions']
			]
		);
		register_rest_route(_PLUGIN_D3 . '-plugin-api/v1', '/settings', [
				'methods' => 'GET',
				'callback' => [$this, 'get_settings'],
				'args' => [],
				'permission_callback' => [$this, 'permissions']
			]
		);
	}

	public function permissions() {
		return current_user_can('manage_options');
	}

	public function update_settings(WP_REST_Request $request) {
		$settings = [];
		foreach (d3_Settings::args() as $key => $val) {
			$settings[$key] = $request->get_param($key);
		}
		d3_Settings::save_settings($settings);
		return rest_ensure_response(d3_Settings::get_settings());
	}

	public function get_settings(WP_REST_Request $request) {
		return rest_ensure_response(d3_Settings::get_settings());
	}
}

class d3_Settings {
	protected static $option_key = _PLUGIN_D3 . '-settings';

	public static function args() {
		$args = _ARGS_D3;
		foreach (_ARGS_D3 as $key => $val) {
			$val['required'] = true;
			switch ($val['type']) {
				case 'integer': {
					$cb = 'absint';
					break;
				}
				default: {
					$cb = 'sanitize_text_field';
				}
				$val['sanitize_callback'] = $cb;
			}
		}
		return $args;
	}

	public static function get_settings() {
		$defaults = [];
		foreach (_ARGS_D3 as $key => $val) {
			$defaults[$key] = $val['default'];
		}
		$saved = get_option(self::$option_key, []);
		if (!is_array($saved) || empty($saved)) {
			return $defaults;
		}
		return wp_parse_args($saved, $defaults);
	}

	public static function save_settings(array $settings) {
		$defaults = [];
		foreach (_ARGS_D3 as $key => $val) {
			$defaults[$key] = $val['default'];
		}
		foreach ($settings as $i => $setting) {
			if (!array_key_exists($i, $defaults)) {
				unset($settings[$i]);
			}
		}
		update_option(self::$option_key, $settings);
	}
}

class d3_Menu {
	protected $slug = _PLUGIN_D3 . '-menu';
	protected $assets_url;

	public function __construct($assets_url) {
		$this->assets_url = $assets_url;
		add_action('admin_menu', [$this, 'add_page']);
		add_action('admin_enqueue_scripts', [$this, 'register_assets']);
	}

	public function add_page() {
		add_menu_page(
			_PLUGIN_D3,
			_PLUGIN_D3,
			'manage_options',
			$this->slug,
			[$this, 'render_admin'],
			'dashicons-chart-area',
			3
		);

		// add taxonomies menus

		$types = [
			'display' => 'document'
		];

		foreach ($types as $type => $child) {
			add_submenu_page(
				$this->slug,
				$type . 's',
				$type . 's',
				'manage_options',
				'/edit-tags.php?taxonomy=' . $type . '&post_type=' . $child
			);
		}

		// add posts menus

		$types = [
			'document'
		];

		foreach ($types as $type) {
			add_submenu_page(
				$this->slug,
				$type . 's',
				$type . 's',
				'manage_options',
				'/edit.php?post_type=' . $type
			);
		}
	}

	public function register_assets() {
		wp_register_script($this->slug, $this->assets_url . '/' . _PLUGIN_D3 . '.js', ['jquery']);
		wp_register_style($this->slug, $this->assets_url . '/' . _PLUGIN_D3 . '.css');

		wp_localize_script($this->slug, _PLUGIN_D3, [
			'strings' => [
				'saved' => 'Settings Saved',
				'error' => 'Error'
			],
			'api' => [
				'url' => esc_url_raw(rest_url(_PLUGIN_D3 . '-plugin-api/v1/settings')),
				'nonce' => wp_create_nonce('wp_rest')
			]
		]);
	}

	public function enqueue_assets() {
		if (!wp_script_is($this->slug, 'registered')) {
			$this->register_assets();
		}

		wp_enqueue_script($this->slug);
		wp_enqueue_style($this->slug);
	}

	public function render_admin() {
		wp_enqueue_media();
		$this->enqueue_assets();

		$name = _PLUGIN_D3;
		$form = _FORM_D3;

		// build form

		echo '<div id="' . $name . '-wrap" class="wrap">';
			echo '<h1>' . $name . '</h1>';
			echo '<p>Configure your ' . $name . ' settings...</p>';
			echo '<form id="' . $name . '-form" method="post">';
				echo '<nav id="' . $name . '-nav" class="nav-tab-wrapper">';
				foreach ($form as $tid => $tab) {
					echo '<a href="#' . $name . '-' . $tid . '" class="nav-tab">' . $tab['label'] . '</a>';
				}
				echo '</nav>';
				echo '<div class="tab-content">';
				foreach ($form as $tid => $tab) {
					echo '<div id="' . $name . '-' . $tid . '" class="' . $name . '-tab">';
					foreach ($tab['fields'] as $fid => $field) {
						echo '<div class="form-block">';
						switch ($field['type']) {
							case 'input': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<input id="' . $fid . '" type="text" name="' . $fid . '">';
								break;
							}
							case 'select': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<select id="' . $fid . '" name="' . $fid . '">';
									foreach ($field['values'] as $value => $label) {
										echo '<option value="' . $value . '">' . $label . '</option>';
									}
								echo '</select>';
								break;
							}
							case 'text': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<textarea id="' . $fid . '" class="tabs" name="' . $fid . '"></textarea>';
								break;
							}
							case 'file': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<input id="' . $fid . '" type="text" name="' . $fid . '">';
								echo '<input data-id="' . $fid . '" type="button" class="button-primary choose-file-button" value="Select...">';
								break;
							}
							case 'colour': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<input id="' . $fid . '" type="text" name="' . $fid . '">';
								echo '<input data-id="' . $fid . '" type="color" class="choose-colour-button" value="#000000">';
								break;
							}
							case 'code': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<textarea id="' . $fid . '" class="code" name="' . $fid . '"></textarea>';
								break;
							}
						}
						echo '</div>';
					}
					echo '</div>';
				}
				echo '<div>';
					submit_button();
				echo '</div>';
				echo '<div id="' . $name . '-feedback"></div>';
			echo '</form>';
		echo '</div>';
	}
}

// functions

function d3_init($dir) {
	if (is_admin()) {
		new d3_Menu(_URL_D3);
	}

	// set up post types

	$types = [
		'document'
	];

	foreach ($types as $type) {
		$uc_type = ucwords($type);

		$labels = [
			'name' => $uc_type . 's',
			'singular_name' => $uc_type,
			'menu_name' => $uc_type . 's',
			'name_admin_bar' => $uc_type . 's',
			'add_new' => 'Add New',
			'add_new_item' => 'Add New ' . $uc_type,
			'new_item' => 'New ' . $uc_type,
			'edit_item' => 'Edit ' . $uc_type,
			'view_item' => 'View ' . $uc_type,
			'all_items' => $uc_type . 's',
			'search_items' => 'Search ' . $uc_type . 's',
			'not_found' => 'No ' . $uc_type . 's Found'
		];

		register_post_type($type, [
			'supports' => [
				'title',
				'thumbnail',
				'revisions',
				'post-formats'
			],
			'hierarchical' => true,
			'labels' => $labels,
			'show_ui' => true,
			'show_in_menu' => false,
			'query_var' => true,
			'has_archive' => false,
			'rewrite' => ['slug' => $type]
		]);
	}

	// set up taxonomies

	$types = [
		'display' => 'document'
	];

	foreach ($types as $type => $child) {
		$uc_type = ucwords($type);

		$labels = [
			'name' => $uc_type . 's',
			'singular_name' => $uc_type,
			'search_items' => 'Search ' . $uc_type . 's',
			'all_items' => 'All ' . $uc_type . 's',
			'parent_item' => 'Parent ' . $uc_type,
			'parent_item_colon' => 'Parent ' . $uc_type . ':',
			'edit_item' => 'Edit ' . $uc_type, 
			'update_item' => 'Update ' . $uc_type,
			'add_new_item' => 'Add New ' . $uc_type,
			'new_item_name' => 'New ' . $uc_type . ' Name',
			'menu_name' => $uc_type . 's',
		];

		register_taxonomy($type, [$child], [
			'hierarchical' => true,
			'labels' => $labels,
			'show_ui' => true,
			'show_in_menu' => false,
			'show_in_rest' => true,
			'show_admin_column' => true,
			'query_var' => true,
			'rewrite' => ['slug' => $type],
		]);
	}
}

function d3_api_init() {
	d3_Settings::args();
	$api = new d3_API();
	$api->add_routes();
}

function d3_add_metaboxes() {
    $screens = ['document'];
    foreach ($screens as $screen) {
        add_meta_box(
            'd3_meta_box',
            'Document Data',
            'd3_product_metabox',
            $screen
        );
    }
}

function d3_product_metabox($post) {
	$prefix = '_d3-item_';
	$keys = [
		'uid',
		'title',
		'script',
		'data'
	];
	foreach ($keys as $key) {
		$$key = get_post_meta($post->ID, $prefix . $key, true);
	}
    wp_nonce_field(plugins_url(__FILE__), 'wr_plugin_noncename');
    ?>
    <style>
		#d3_meta_box label {
			display: inline-block;
			font-size: 12px;
		}
		#d3_meta_box input,
		#d3_meta_box textarea {
			width: 300px;
			margin: 0 0 5px;
			padding: 3px;
		}
    </style>
    <div>
    	<br>
		<label>Unique ID:</label>
		<br>
		<input name="_d3-item_uid" value="<?php echo $uid; ?>">
		<br>
		<label>Title:</label>
		<br>
		<input name="_d3-item_title" value="<?php echo $title; ?>">
		<br>
		<label>Script:</label>
		<br>
		<textarea name="_d3-item_script"><?php echo $script; ?></textarea>
		<br>
		<label>Data:</label>
		<br>
		<textarea name="_d3-item_data"><?php echo $data; ?></textarea>
	</div>
    <?php
}

function d3_save_postdata($post_id) {
	$prefix = '_d3-item_';
	$keys = [
		'uid',
		'title',
		'script',
		'data'
	];
	foreach ($keys as $key) {
		if (array_key_exists($prefix . $key, $_POST)) {
			update_post_meta(
				$post_id,
				$prefix . $key,
				$_POST[$prefix . $key]
			);
		}
	}
}

function d3_set_current_menu($parent_file) {
	global $submenu_file, $current_screen, $pagenow;
	$taxonomy = 'display';

	if ($current_screen->id == 'edit-' . $taxonomy) {
		if ($pagenow == 'post.php') {
			$submenu_file = 'edit.php?post_type=' . $current_screen->post_type;
		}
		if ($pagenow == 'edit-tags.php') {
			$submenu_file = 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=' . $current_screen->post_type;
		}
		$parent_file = _PLUGIN_D3 . '-menu';
	}
	return $parent_file;
}

function d3_admin_scripts() {
	$screen = get_current_screen();

	if (null === $screen) {
		return;
	}
	if ($screen->base !== 'toplevel_page_d3-menu') {
		return;
	}

	wp_enqueue_code_editor(['type' => 'application/x-httpd-php']);
}

function d3_scripts() {
	wp_enqueue_script('d3', 'https://d3js.org/d3.v6.min.js', [], false, false);
}

//     ▄██████▄    ▄██████▄   
//    ███    ███  ███    ███  
//    ███    █▀   ███    ███  
//   ▄███         ███    ███  
//  ▀▀███ ████▄   ███    ███  
//    ███    ███  ███    ███  
//    ███    ███  ███    ███  
//    ████████▀    ▀██████▀   

add_action('init', 'd3_init');
add_action('wp_enqueue_scripts', 'd3_scripts');
add_action('admin_enqueue_scripts', 'd3_admin_scripts');
add_action('rest_api_init', 'd3_api_init');
add_action('add_meta_boxes', 'd3_add_metaboxes');
add_action('save_post', 'd3_save_postdata');

add_filter('parent_file', 'd3_set_current_menu');

// EOF