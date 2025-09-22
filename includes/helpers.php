<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function al_table(){ global $wpdb; return $wpdb->prefix.'applylaunch_jobs'; }
function al_is_role($u,$r){ return in_array($r,(array)$u->roles,true); }

function al_normalize_url($raw){
  $raw = trim((string)$raw);
  if ($raw === '') return '';
  if (!preg_match('~^https?://~i', $raw)) $raw = 'https://' . $raw;
  $raw = preg_replace('~\s+~', '', $raw);
  return esc_url_raw($raw);
}

function al_prepare($sql, $params=array()){
  global $wpdb;
  if(empty($params)) return $sql;
  return $wpdb->prepare($sql, $params);
}

function al_paginated_jobs($where_sql, $where_params, $order_sql=' ORDER BY applied_date DESC, id DESC ', $page=1, $per=50){
  global $wpdb; $t = al_table();
  $page = max(1, intval($page)); 
  $per  = min(100, max(10, intval($per)));
  $offset = ($page-1)*$per;

  $sql_count = "SELECT COUNT(*) FROM $t $where_sql";
  $total = (int)$wpdb->get_var( al_prepare($sql_count, $where_params) );

  $sql_rows = "SELECT * FROM $t $where_sql $order_sql LIMIT %d OFFSET %d";
  $args = array_merge($where_params, array($per, $offset));
  $rows = $wpdb->get_results( $wpdb->prepare($sql_rows, $args) );

  $pages = max(1, ceil($total/$per));
  return array($rows, $total, $pages, $page, $per);
}

function al_counts_by_range($jobs,$range){
  $out = []; $now = current_time('timestamp');
  if($range==='7'){
    for($i=6;$i>=0;$i--){ $d=date('Y-m-d', strtotime("-{$i} days", $now)); $out[$d]=0; }
    foreach($jobs as $j){ if(!empty($j->applied_date) && isset($out[$j->applied_date])) $out[$j->applied_date]++; }
  } elseif($range==='30'){
    for($i=29;$i>=0;$i--){ $d=date('Y-m-d', strtotime("-{$i} days", $now)); $out[$d]=0; }
    foreach($jobs as $j){ if(!empty($j->applied_date) && isset($out[$j->applied_date])) $out[$j->applied_date]++; }
  } elseif($range==='12'){
    for($i=11;$i>=0;$i--){ $m=date('Y-m', strtotime("-{$i} months", $now)); $out[$m]=0; }
    foreach($jobs as $j){ if(!empty($j->applied_date)){ $m=substr($j->applied_date,0,7); if(isset($out[$m])) $out[$m]++; } }
  } else { // 6m default
    for($i=5;$i>=0;$i--){ $m=date('Y-m', strtotime("-{$i} months", $now)); $out[$m]=0; }
    foreach($jobs as $j){ if(!empty($j->applied_date)){ $m=substr($j->applied_date,0,7); if(isset($out[$m])) $out[$m]++; } }
  }
  return $out;
}
