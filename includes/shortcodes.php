<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* Chart helper */
function al_render_chart($id,$labels,$data,$title){
  $pretty=[];
  foreach($labels as $lab){ $pretty[] = (strlen($lab)===7)? date_i18n('M y', strtotime($lab.'-01')) : date_i18n('M j', strtotime($lab)); }
  $labels_json = wp_json_encode(array_values($pretty));
  $data_json = wp_json_encode(array_values($data));
  ob_start(); ?>
  <div class="al-chart"><canvas id="<?php echo esc_attr($id); ?>"></canvas></div>
  <script>document.addEventListener('DOMContentLoaded',function(){var c=document.getElementById('<?php echo esc_js($id); ?>'); if(!c) return; new Chart(c,{type:'bar', data:{labels:<?php echo $labels_json; ?>, datasets:[{label:"<?php echo esc_js($title); ?>",data:<?php echo $data_json; ?>,borderWidth:1}]}, options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true}}}});});</script>
  <?php return ob_get_clean();
}

/* ===== AJAX: inline update & de-dupe ===== */
add_action('wp_ajax_al_inline_update', function(){
  check_ajax_referer('al_inline','nonce');
  if(!is_user_logged_in()) wp_send_json_error('noauth');
  $u=wp_get_current_user();
  $id=intval($_POST['id']??0);
  $field=sanitize_key($_POST['field']??'');
  $value= sanitize_text_field($_POST['value']??'');
  $allowed=['job_title','company','applied_date','status','website','location'];
  if(!in_array($field,$allowed,true)) wp_send_json_error('badfield');
  if($field==='website') $value=al_normalize_url($value);
  global $wpdb; $t=al_table();
  if(al_is_role($u,'applylunch_employee')||al_is_role($u,'applylaunch_employee')){
    $owner=$wpdb->get_var($wpdb->prepare("SELECT employee_id FROM $t WHERE id=%d",$id));
    if(intval($owner)!==intval($u->ID)) wp_send_json_error('forbidden');
  }
  $wpdb->update($t,[$field=>$value],['id'=>$id]);
  wp_send_json_success(['value'=>$value]);
});

add_action('wp_ajax_al_dedupe_check', function(){
  check_ajax_referer('al_dedupe','nonce');
  if(!is_user_logged_in()) wp_send_json_error('noauth');
  global $wpdb; $t=al_table();
  $customer=intval($_POST['customer_id']??0);
  $title = sanitize_text_field($_POST['job_title']??'');
  $company = sanitize_text_field($_POST['company']??'');
  $website = sanitize_text_field($_POST['website']??'');
  $since = date('Y-m-d', strtotime('-60 days', current_time('timestamp')));
  $like_title = '%'.$wpdb->esc_like($title).'%';
  $like_company = '%'.$wpdb->esc_like($company).'%';
  $cnt = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(*) FROM $t
     WHERE customer_id=%d AND applied_date>=%s
       AND (job_title LIKE %s OR company LIKE %s OR website=%s)
  ", $customer, $since, $like_title, $like_company, $website));
  wp_send_json_success(['count'=>intval($cnt)]);
});

/* ===== CUSTOMER DASHBOARD ===== */
add_shortcode('applylunch_customer_dashboard', function(){
  if(!is_user_logged_in()) return '<p>Please log in.</p>';
  $u=wp_get_current_user(); global $wpdb; $t=al_table();
  $q = sanitize_text_field($_GET['q']??''); $page=max(1,intval($_GET['p']??1)); $per=min(100,max(10,intval($_GET['per']??50)));
  $where="WHERE customer_id=%d"; $params=[$u->ID];
  if($q!==''){ $where.=" AND (job_title LIKE %s OR company LIKE %s)"; $like='%'.$wpdb->esc_like($q).'%'; array_push($params,$like,$like); }
  list($rows,$total,$pages,$page,$per) = al_paginated_jobs($where,$params,' ORDER BY applied_date DESC, id DESC ',$page,$per);

  if(isset($_GET['al_export']) && $_GET['al_export']=='1'){
    $filename='my-applications-'.date('Ymd-His').'.csv'; header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out=fopen('php://output','w'); fputcsv($out,['Title','Company','Location','Website','Applied Date','Status']);
    foreach($rows as $j){ fputcsv($out,[$j->job_title,$j->company,$j->location,$j->website,$j->applied_date,$j->status]); }
    fclose($out); exit;
  }

  $range = sanitize_text_field($_GET['range']??'30');
  $jobs_all = $wpdb->get_results($wpdb->prepare("SELECT applied_date FROM $t WHERE customer_id=%d", $u->ID));
  $counts = al_counts_by_range($jobs_all,$range);
  $labels=array_keys($counts); $data=array_values($counts);

  ob_start(); ?>
  <div class="al-card">
    <div class="al-flex">
      <h3>Your Applications</h3>
      <form method="get" class="al-inline">
        <input id="al-search" type="search" name="q" value="<?php echo esc_attr($q); ?>" placeholder="Search title or company">
        <select name="range" onchange="this.form.submit()">
          <option value="7"  <?php selected($range,'7'); ?>>7d</option>
          <option value="30" <?php selected($range,'30'); ?>>30d</option>
          <option value="6"  <?php selected($range,'6'); ?>>6m</option>
          <option value="12" <?php selected($range,'12'); ?>>12m</option>
        </select>
        <a class="al-btn" href="<?php echo esc_url(add_query_arg(['al_export'=>'1'])); ?>">Export CSV</a>
      </form>
    </div>
    <?php echo al_render_chart('al_cust_chart',$labels,$data,'Applications'); ?>
  </div>

  <div class="al-card">
    <h3>Applications</h3>
    <div class="al-table-wrap">
      <table class="al-table">
        <thead><tr><th>Title</th><th>Company</th><th>Location</th><th>Website</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach($rows as $j): ?>
          <tr>
            <td><?php echo esc_html($j->job_title); ?></td>
            <td><?php echo esc_html($j->company); ?></td>
            <td><?php echo esc_html($j->location); ?></td>
            <td><?php if($j->website) echo '<a target="_blank" href="'.esc_url($j->website).'">Link</a>'; ?></td>
            <td><?php echo esc_html($j->applied_date); ?></td>
            <td><?php echo esc_html($j->status); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="al-pager">
      <?php if($pages>1): for($i=1;$i<=$pages;$i++): $url=add_query_arg(['p'=>$i]); ?>
        <a class="al-page <?php echo $i==$page?'active':''; ?>" href="<?php echo esc_url($url); ?>"><?php echo $i; ?></a>
      <?php endfor; endif; ?>
    </div>
  </div>
  <?php return ob_get_clean();
});

/* ===== EMPLOYEE DASHBOARD (Add job + chart + table) ===== */
add_shortcode('applylunch_employee_dashboard', function(){
  if(!is_user_logged_in()) return '<p>Please log in.</p>';
  $u=wp_get_current_user(); if(!(al_is_role($u,'applylunch_employee')||al_is_role($u,'applylaunch_employee')||al_is_role($u,'applylunch_superadmin')||al_is_role($u,'applylaunch_superadmin'))) return '<p>No permission.</p>';
  global $wpdb; $t=al_table();

  $notice='';
  if(isset($_POST['al_add_job']) && wp_verify_nonce($_POST['al_nonce']??'','al_add_job')){
    $cid=intval($_POST['customer_id']??0);
    $title=sanitize_text_field($_POST['job_title']??'');
    $company=sanitize_text_field($_POST['company']??'');
    $location=sanitize_text_field($_POST['location']??'');
    $website=al_normalize_url($_POST['website']??'');
    $applied=sanitize_text_field($_POST['applied_date']??'');
    $status=sanitize_text_field($_POST['status']??'Applied');
    if($cid && $title){
      $wpdb->insert($t,['customer_id'=>$cid,'employee_id'=>$u->ID,'job_title'=>$title,'company'=>$company,'location'=>$location,'website'=>$website,'applied_date'=>$applied,'status'=>$status,'meta'=>json_encode([])]);
      $notice='<div class="al-alert al-success">Saved.</div>';
    } else { $notice='<div class="al-alert">Customer & Title required.</div>'; }
  }

  $customers=get_users(['role__in'=>['applylunch_customer','applylaunch_customer'],'number'=>999]);
  $q=sanitize_text_field($_GET['q']??''); $page=max(1,intval($_GET['p']??1)); $per=min(100,max(10,intval($_GET['per']??50)));
  $where="WHERE employee_id=%d"; $params=[$u->ID];
  if(al_is_role($u,'applylunch_superadmin')||al_is_role($u,'applylaunch_superadmin')){ $where="WHERE 1=1"; $params=[]; }
  if($q!==''){ $where.=" AND (job_title LIKE %s OR company LIKE %s)"; $like='%'.$wpdb->esc_like($q).'%'; array_push($params,$like,$like); }
  list($rows,$total,$pages,$page,$per)=al_paginated_jobs($where,$params,' ORDER BY applied_date DESC, id DESC ',$page,$per);

  $since_30 = date('Y-m-d', strtotime('-30 days', current_time('timestamp')));
  if(empty($params)){
    $chart_rows = $wpdb->get_results($wpdb->prepare("SELECT applied_date, COUNT(*) as cnt FROM $t WHERE applied_date >= %s GROUP BY applied_date ORDER BY applied_date ASC", $since_30));
  } else {
    $chart_rows = $wpdb->get_results($wpdb->prepare("SELECT applied_date, COUNT(*) as cnt FROM $t WHERE employee_id=%d AND applied_date >= %s GROUP BY applied_date ORDER BY applied_date ASC", $u->ID, $since_30));
  }
  $labels=[]; $data=[];
  for($i=29;$i>=0;$i--){
    $d = date('Y-m-d', strtotime("-{$i} days", current_time('timestamp')));
    $labels[]=$d; $data[$d]=0;
  }
  foreach($chart_rows as $r){ if(isset($data[$r->applied_date])) $data[$r->applied_date]=(int)$r->cnt; }

  ob_start(); ?>
  <div class="al-grid">
    <div class="al-col">
      <div class="al-card"><h3>Add Job</h3><?php echo $notice; ?>
        <form method="post" class="al-form" id="al-add-form">
          <?php wp_nonce_field('al_add_job','al_nonce'); ?>
          <label>Customer</label>
          <select name="customer_id" id="al_customer_id" required>
            <option value="">Select Customer</option>
            <?php foreach($customers as $c): ?><option value="<?php echo $c->ID; ?>"><?php echo esc_html($c->display_name?:$c->user_login); ?></option><?php endforeach; ?>
          </select>
          <label>Job Title</label><input type="text" name="job_title" id="al_job_title" required>
          <label>Company</label><input type="text" name="company" id="al_company">
          <label>Location</label><input type="text" name="location" id="al_location">
          <label>Website</label><input type="text" name="website" id="al_website" placeholder="applylaunch.com or https://...">
          <label>Applied Date</label><input type="date" name="applied_date" id="al_applied_date">
          <label>Status</label>
          <select name="status" id="al_status">
            <option>Applied</option><option>Interview</option><option>Offer</option><option>Rejected</option><option>Ghosted</option>
          </select>
          <button class="al-btn al-sticky-add" type="submit" name="al_add_job">Save</button>
        </form>
      </div>

      <div class="al-card">
        <h3>My Activity (last 30 days)</h3>
        <?php echo al_render_chart('al_emp_chart', $labels, array_values($data), 'Applications'); ?>
      </div>
    </div>

    <div class="al-col">
      <div class="al-card">
        <div class="al-flex">
          <h3>My Applications</h3>
          <input id="al-search" type="search" value="<?php echo esc_attr($q); ?>" placeholder="Search title or company">
        </div>
        <div class="al-table-wrap">
          <table class="al-table">
            <thead><tr><th>Title</th><th>Company</th><th>Location</th><th>Website</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach($rows as $j): ?>
              <tr data-id="<?php echo $j->id; ?>">
                <td data-edit="job_title"><?php echo esc_html($j->job_title); ?></td>
                <td data-edit="company"><?php echo esc_html($j->company); ?></td>
                <td data-edit="location"><?php echo esc_html($j->location); ?></td>
                <td data-edit="website"><?php echo esc_html($j->website); ?></td>
                <td data-edit="applied_date"><?php echo esc_html($j->applied_date); ?></td>
                <td data-edit="status"><?php echo esc_html($j->status?:'Applied'); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="al-pager">
          <?php if($pages>1): for($i=1;$i<=$pages;$i++): $url=add_query_arg(['p'=>$i]); ?>
            <a class="al-page <?php echo $i==$page?'active':''; ?>" href="<?php echo esc_url($url); ?>"><?php echo $i; ?></a>
          <?php endfor; endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php return ob_get_clean();
});

/* ===== SUPERADMIN DASHBOARD (stats + charts + global table + User Manager) ===== */
add_shortcode('applylunch_superadmin_dashboard', function(){
  if(!is_user_logged_in()) return '<p>Please log in.</p>';
  $u=wp_get_current_user(); 
  if(!(al_is_role($u,'applylunch_superadmin')||al_is_role($u,'applylaunch_superadmin'))) return '<p>No permission.</p>';
  global $wpdb; $t=al_table();

  $today = date('Y-m-d', current_time('timestamp'));
  $week_start = date('Y-m-d', strtotime('monday this week', current_time('timestamp')));
  $month_start = date('Y-m-01', current_time('timestamp'));

  $total_all   = (int)$wpdb->get_var("SELECT COUNT(*) FROM $t");
  $total_week  = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE applied_date >= %s", $week_start));
  $total_month = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE applied_date >= %s", $month_start));

  $top_agents = $wpdb->get_results($wpdb->prepare("
      SELECT u.display_name as name, j.employee_id, COUNT(*) as cnt
      FROM $t j 
      JOIN {$wpdb->users} u ON u.ID=j.employee_id
      WHERE j.applied_date >= %s
      GROUP BY j.employee_id
      ORDER BY cnt DESC
      LIMIT 10
  ", $week_start));

  $top_customers = $wpdb->get_results($wpdb->prepare("
      SELECT u.display_name as name, j.customer_id, COUNT(*) as cnt
      FROM $t j 
      JOIN {$wpdb->users} u ON u.ID=j.customer_id
      WHERE j.applied_date >= %s
      GROUP BY j.customer_id
      ORDER BY cnt DESC
      LIMIT 10
  ", $week_start));

  $since_30 = date('Y-m-d', strtotime('-30 days', current_time('timestamp')));
  $per_agent = $wpdb->get_results($wpdb->prepare("
      SELECT u.display_name as name, COUNT(*) as cnt
      FROM $t j 
      JOIN {$wpdb->users} u ON u.ID=j.employee_id
      WHERE j.applied_date >= %s
      GROUP BY j.employee_id
      ORDER BY cnt DESC
  ", $since_30));
  $agent_labels = array_map(function($r){ return $r->name ? $r->name : '#'.$r->employee_id; }, $per_agent);
  $agent_counts = array_map(function($r){ return (int)$r->cnt; }, $per_agent);

  $q    = sanitize_text_field($_GET['q'] ?? '');
  $page = max(1, intval($_GET['p'] ?? 1));
  $per  = min(100, max(10, intval($_GET['per'] ?? 50)));
  $where = "WHERE 1=1"; $params = [];
  if ($q !== '') {
    $where .= " AND (job_title LIKE %s OR company LIKE %s)";
    $like = '%'.$wpdb->esc_like($q).'%';
    $params[] = $like; $params[] = $like;
  }
  list($rows,$total,$pages,$page,$per) = al_paginated_jobs($where,$params,' ORDER BY applied_date DESC, id DESC ',$page,$per);

  ob_start(); ?>
  <div class="al-grid">
    <div class="al-card">
      <h3>Overview</h3>
      <div class="al-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <div class="al-card"><strong>Total</strong><div style="font-size:1.6rem"><?php echo esc_html($total_all); ?></div></div>
        <div class="al-card"><strong>This Week</strong><div style="font-size:1.6rem"><?php echo esc_html($total_week); ?></div></div>
        <div class="al-card"><strong>This Month</strong><div style="font-size:1.6rem"><?php echo esc_html($total_month); ?></div></div>
      </div>
    </div>

    <div class="al-card">
      <div class="al-flex"><h3>Applications (last 30d) by Agent</h3></div>
      <?php echo al_render_chart('al_admin_agents_chart', $agent_labels, $agent_counts, 'By Agent'); ?>
    </div>

    <div class="al-card">
      <div class="al-grid" style="grid-template-columns:1fr 1fr">
        <div>
          <h3>Top Agents (this week)</h3>
          <table class="al-table"><thead><tr><th>Agent</th><th>Apps</th></tr></thead><tbody>
            <?php foreach($top_agents as $r): ?>
              <tr><td><?php echo esc_html($r->name ?: ('#'.$r->employee_id)); ?></td><td><?php echo (int)$r->cnt; ?></td></tr>
            <?php endforeach; ?>
          </tbody></table>
        </div>
        <div>
          <h3>Top Customers (this week)</h3>
          <table class="al-table"><thead><tr><th>Customer</th><th>Apps</th></tr></thead><tbody>
            <?php foreach($top_customers as $r): ?>
              <tr><td><?php echo esc_html($r->name ?: ('#'.$r->customer_id)); ?></td><td><?php echo (int)$r->cnt; ?></td></tr>
            <?php endforeach; ?>
          </tbody></table>
        </div>
      </div>
    </div>

    <div class="al-card">
      <div class="al-flex">
        <h3>All Applications</h3>
        <input id="al-search" type="search" value="<?php echo esc_attr($q); ?>" placeholder="Search title or company">
      </div>
      <div class="al-table-wrap">
        <table class="al-table">
          <thead><tr><th>Title</th><th>Company</th><th>Customer</th><th>Agent</th><th>Website</th><th>Date</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach($rows as $j):
              $cust = get_user_by('id', $j->customer_id);
              $emp  = get_user_by('id', $j->employee_id);
            ?>
              <tr>
                <td><?php echo esc_html($j->job_title); ?></td>
                <td><?php echo esc_html($j->company); ?></td>
                <td><?php echo esc_html($cust ? ($cust->display_name ?: $cust->user_login) : ('#'.$j->customer_id)); ?></td>
                <td><?php echo esc_html($emp ? ($emp->display_name ?: $emp->user_login) : ('#'.$j->employee_id)); ?></td>
                <td><?php if($j->website) echo '<a target="_blank" href="'.esc_url($j->website).'">Link</a>'; ?></td>
                <td><?php echo esc_html($j->applied_date); ?></td>
                <td><?php echo esc_html($j->status); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="al-pager">
        <?php if($pages>1): for($i=1;$i<=$pages;$i++): $url=add_query_arg(['p'=>$i]); ?>
          <a class="al-page <?php echo $i==$page?'active':''; ?>" href="<?php echo esc_url($url); ?>"><?php echo $i; ?></a>
        <?php endfor; endif; ?>
      </div>
    </div>

    <div class="al-card">
      <?php echo applylunch_um_render(); ?>
    </div>
  </div>
  <?php return ob_get_clean();
});
