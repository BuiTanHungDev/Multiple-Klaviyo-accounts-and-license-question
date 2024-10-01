<?php
/*
Plugin Name: Gravity Extra License Key Tool - Dev Tool
Plugin URI: https://staging.gravityextra.com/
Description: The plugin is for developer use only, not for commercial use, not installed on client site.
Version: 1.0
Author: Gravity Extra
Author URI: https://staging.gravityextra.com/
*/

define('GFGE_LICENSE_KEY_TOOL_VERSION', '1.0');

class GFGE_License_Key_Tool {

    public function __construct() {

        add_filter('gform_addon_navigation', array($this, 'create_plugin_page_menu'));

        add_action('admin_init', array($this, 'gravity_extra_license_key_settings_init'));
        add_filter('pre_option', array($this, 'gravity_extra_license_key'), 9999, 3);
    }

    public function create_plugin_page_menu($menus) {
        $menus[] = array('name' => 'gravityextralicensekeytool', 'label' => __('Gravity Extra License Key Tool'), 'callback' => array($this, 'gravity_extra_license_keys_callback'), 'permission' => 'manage_options');
        return $menus;
    }

    public function gravity_extra_license_key_settings_init() {
        register_setting(
            'gravity_extra_license_key_tool_settings_group',
            'gravity_extra_license_key_tool_settings'
        );
    }

    public function gravity_extra_license_keys_callback() {
        $plugin_slugs = $this->get_setting('plugin_slug');
        echo '<style>

			#custom-repeater-wrapper{
				display: flex;
				flex-direction: column;
				gap: 8px;
			}

			.custom-repeater-item{
				display: flex;
				flex-direction: row;
				gap: 8px;
			}

		</style>';
        echo '<div class="wrap">';
        echo '<h1>Gravity Extra License Key Tool</h1>';

        echo '<p style="color: red;"><strong>The plugin is for developer use only, not for commercial use, not installed on client site.</strong></p>';
        echo '<form method="post" action="options.php">';
        settings_fields('gravity_extra_license_key_tool_settings_group');
        echo '<h3 class="sub-title">Add plugin slug</h3>';
        echo '<p>Get full access to the plugin features without entering the Gravity Extra License Key.</p>';

        echo '<div id="custom-repeater-wrapper">';
        if (!empty($plugin_slugs)) {
            foreach ($plugin_slugs as $slug) {
                echo '<div class="custom-repeater-item">';
                echo '<input type="text" name="gravity_extra_license_key_tool_settings[plugin_slug][]" value="' . esc_attr($slug) . '" style="width: 80%;"/>';
                echo '<button class="button remove-repeater-field" type="button">Delete</button>';
                echo '</div>';
            }
        } else {
            echo '<div class="custom-repeater-item">';
            echo '<input type="text" name="gravity_extra_license_key_tool_settings[plugin_slug][]" value="" style="width: 80%;"/>';
            echo '<button class="button remove-repeater-field" type="button">Delete</button>';
            echo '</div>';
        }

        echo '</div>';
        echo '<button class="button add-repeater-field" style="margin-top: 16px;" type="button">Add</button>';
        submit_button();
        echo '</form>';
        echo '</div>';
?>
        <script>
            jQuery(document).ready(function($) {
                jQuery('.add-repeater-field').on('click', function() {
                    var newField = '<div class="custom-repeater-item"><input type="text" name="gravity_extra_license_key_tool_settings[plugin_slug][]" value="" style="width: 80%;"/><button class="button remove-repeater-field" type="button">Delete</button></div>';
                    jQuery('#custom-repeater-wrapper').append(newField);
                });

                jQuery(document).on('click', '.remove-repeater-field', function() {
                    if (jQuery('#custom-repeater-wrapper .custom-repeater-item').length > 1) {
                        jQuery(this).parent().remove();
                    } else {
                        jQuery(this).parent().find('input').val('');
                    }
                });
            });
        </script>
<?php
    }

    public function gravity_extra_license_key($pre, $option, $default_value) {
        if (!str_ends_with($option, '_license_status')) return $pre;
        $slug = str_replace('_license_status', '', $option);
        $plugin_slugs = $this->get_setting('plugin_slug');
        if (!empty($plugin_slugs) && in_array($slug, $plugin_slugs)) {
            return array(
                'success' => 1,
            );
        }
        return $pre;
    }

    public function get_settings() {
        return $settings = get_option('gravity_extra_license_key_tool_settings', array());
    }

    public function get_setting($name, $default_value = null) {
        $settings = $this->get_settings();
        return !empty($settings[$name]) ? $settings[$name] : $default_value;
    }
}

new GFGE_License_Key_Tool();
