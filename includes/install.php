<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function applylunch_install(){
  global $wpdb;
  $table = $wpdb->prefix . 'applylaunch_jobs';
  $charset = $wpdb->get_charset_collate();
  $sql = "CREATE TABLE $table (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    employee_id BIGINT(20) UNSIGNED NOT NULL,
    job_title VARCHAR(255) NOT NULL,
    company VARCHAR(255),
    location VARCHAR(255),
    website VARCHAR(255),
    applied_date DATE,
    status VARCHAR(50),
    notes TEXT,
    meta LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY customer_id (customer_id),
    KEY employee_id (employee_id),
    KEY applied_date (applied_date),
    KEY status (status)
  ) $charset;";
  require_once ABSPATH.'wp-admin/includes/upgrade.php';
  dbDelta($sql);

  add_role('applylunch_customer', 'ApplyLaunch Customer', ['read'=>true]);
  add_role('applylunch_employee', 'ApplyLaunch Agent', ['read'=>true]);
  add_role('applylunch_superadmin', 'ApplyLaunch Super Admin', ['read'=>true, 'list_users'=>true, 'edit_users'=>true]);
}
