<?php

namespace GravityExtra_KlaviyoAddon;

if (!class_exists('License')) {
    class License {
        protected $_gravity_extra_id = [
            '10'        =>  '228b25587479f2fc7570428e8bcbabdc',
            '5'         =>  '6a8018b3a00b69c008601b8becae392b',
            '1-5'       =>  '73efcfe5fedd98e5b1008f456d2a8197',
            '1'         =>  '1bf50aaf147b3b0ddd26a820d2ed394d',
            'unlimited' =>  '525b8410cc8612283c9ecaf9a319f8ed',
            '1-10'      =>  '8ede06ecd4c633266fdced4089d42ddd',
        ];
        protected $_slug;
        protected $_path;
        protected $_title;
        protected $_short_title;
        protected $_is_gravityforms_supported;
        protected $_license_key     = 'license_key';
        protected $_license_status  = 'license_status';
        protected $_plugin_domain   = 'gravityextra';
        protected $_api_endpoint    = 'https://gravityextra.com/wp-json/ge-api/v1/license/';
        protected $_service_domain  = 'https://gravityextra.com/';
        private static $_instance   = null;
        function __construct($args, $_is_gravityforms_supported) {
            if (empty($args['_slug'])) return;
            $this->_slug = $args['_slug'];
            $this->_path = $args['_path'];
            $this->_title = $args['_title'];
            $this->_short_title = $args['_short_title'];
            $this->_is_gravityforms_supported = $_is_gravityforms_supported;
            add_action('wp_ajax_gravity_extra_license__' . $this->_slug, array($this, 'license_init'));
            add_action('admin_notices', array($this, 'notices_user_active_license'));
        }
        public static function get_instance($args, $_is_gravityforms_supported) {
            if (self::$_instance == null) {
                self::$_instance = new License($args, $_is_gravityforms_supported);
            }
            return self::$_instance;
        }
        public function is_valid_license() {
            $license_status = $this->get_license_status();
            if ((!empty($license_status) && $license_status['success'])) {
                return true;
            }
            return false;
        }
        function notices_user_active_license() {
            $plugin_domain = $this->get_plugin_domain();
            $setting_url = admin_url('admin.php?page=gf_settings&subview=' . $this->get_slug());
            if (!$this->is_valid_license()) { ?>
                <div class="error">
                    <p><?php
                        printf(
                            // translators: Placeholders represent opening and closing link tag.
                            esc_html__('%1$sPlease activate the license key to use the full functionality of %2$s%3$s%4$s.%5$s', $plugin_domain),
                            '<strong>',
                            '<a href="' . $setting_url . '">',
                            $this->get_title(),
                            '</a>',
                            '</strong>'
                        );
                        ?></p>
                </div>
            <?php
            }
        }
        public function get_slug() {
            return $this->_slug;
        }
        public function get_title() {
            return $this->_title;
        }
        public function get_service_site() {
            return $this->_service_domain;
        }
        public function get_plugin_domain() {
            return $this->_plugin_domain;
        }
        public function get_meta_license_key() {
            return $this->_slug . '_' . $this->_license_key;
        }
        public function get_meta_license_status() {
            return $this->_slug . '_' . $this->_license_status;
        }
        public function get_license_key() {
            return get_option($this->get_meta_license_key());
        }
        public function get_license_status() {
            return get_option($this->get_meta_license_status());
        }
        public function update_license_key($value) {
            update_option($this->get_meta_license_key(), $value);
        }
        public function update_license_status($value) {
            update_option($this->get_meta_license_status(), $value);
        }
        public function delete_license_key() {
            delete_option($this->get_meta_license_key());
        }
        public function delete_license_status() {
            delete_option($this->get_meta_license_status());
        }
        public function license_section() {
            $license_section = array(
                'title' => esc_html__('Gravity Extra License', $this->get_plugin_domain()),
                'fields' => array(),
            );
            $license_form = $this->license_form();
            if ($this->_is_gravityforms_supported) {
                $license_section['fields'][] = array(
                    'type' => 'html',
                    'name' => 'license_setup_instructions',
                    'html' => $license_form,
                );
            } else {
                $license_section['description'] = $license_form;
            }
            return $license_section;
        }
        public function license_form() {
            $slug = $this->get_slug();
            $plugin_domain = $this->get_plugin_domain();
            $service_site = $this->get_service_site();
            $license_key = $this->get_license_key();
            $license_status = $this->get_license_status();
            ob_start();
            require plugin_dir_path(__FILE__) . 'setup-license.php';
            $instructions = ob_get_clean();
            return $instructions;
        }
        public function get_endpoint() {
            return $this->_api_endpoint;
        }
        public function activate_license($license, $status) {
            $this->update_license_key($license);
            $this->update_license_status($status);
        }
        public function deactivate_license() {
            $this->delete_license_key();
            $this->delete_license_status();
        }
        public function license_init() {
            $license = (isset($_POST['license'])) ? esc_attr($_POST['license']) : '';
            $edd_action = (isset($_POST['ge_license_action'])) ? esc_attr($_POST['ge_license_action']) : '';
            foreach ($this->_gravity_extra_id as $site => $gravity_extra_id) {
                $api_params = array(
                    'edd_action' => $edd_action,
                    'license'    => $license,
                    '_gravity_extra_id'    => $gravity_extra_id,
                    'url'        => home_url()
                );

                $endpoint = $this->get_endpoint();

                // Call the API.
                $response = wp_remote_post( $endpoint , array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

                $license_data = json_decode( wp_remote_retrieve_body( $response ), true );
                if ($license_data["success"]) {
                    $license_data["site"] = $site;
                    break;
                }
            }
            if ($license_data["success"]) {
                switch ($edd_action) {
                    case 'deactivate_license':
                        $this->deactivate_license();
                        break;
                    case 'activate_license':
                        $this->activate_license($license, $license_data);
                        break;
                    default: // check_license
                        // $this->activate_license( $license, $status );
                        break;
                }
            }
            wp_send_json($license_data);
            die();
        }
    }
}
