<?php
/**
 * Plugin Name: ApplyLaunch Dashboard
 * Description: Dashboards for customers, agents, and super admins with CSV export, inline edit, pagination, and user management.
 * Version: 3.3.5
 * Author: ApplyLaunch
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define('AL_APPLY_PATH', plugin_dir_path(__FILE__));
define('AL_APPLY_URL', plugin_dir_url(__FILE__));

require_once AL_APPLY_PATH . 'includes/install.php';
require_once AL_APPLY_PATH . 'includes/helpers.php';
require_once AL_APPLY_PATH . 'includes/shortcodes.php';
require_once AL_APPLY_PATH . 'includes/user-manager.php';
require_once AL_APPLY_PATH . 'includes/compat-universal.php';

register_activation_hook(__FILE__, 'applylunch_install');

add_action('wp_enqueue_scripts', function(){
    wp_enqueue_style('applylaunch-style', AL_APPLY_URL.'assets/style.css', [], '3.3.5');
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);
    wp_enqueue_script('applylaunch-js', AL_APPLY_URL.'assets/app.js', ['jquery'], '3.3.5', true);
    wp_localize_script('applylaunch-js', 'ALCFG', [
        'ajax' => admin_url('admin-ajax.php'),
        'nonce_inline' => wp_create_nonce('al_inline'),
        'nonce_dedupe' => wp_create_nonce('al_dedupe'),
    ]);
});
