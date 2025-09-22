<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('init', function(){
  $pairs = [
    ['applylunch_superadmin_dashboard','applylaunch_superadmin_dashboard'],
    ['applylunch_employee_dashboard','applylaunch_employee_dashboard'],
    ['applylunch_customer_dashboard','applylaunch_customer_dashboard'],
  ];
  foreach ($pairs as $pair) {
    list($from, $to) = $pair;
    if (!shortcode_exists($from) && shortcode_exists($to)) {
      add_shortcode($from, function() use ($to) { return do_shortcode('['.$to.']'); });
    }
    if (!shortcode_exists($to) && shortcode_exists($from)) {
      add_shortcode($to, function() use ($from) { return do_shortcode('['.$from.']'); });
    }
  }
});

function al_has_any_role($roles){
  if(!is_user_logged_in()) return false;
  $u = wp_get_current_user(); $ur=(array)$u->roles;
  foreach($roles as $r){ if(in_array($r,$ur,true)) return true; }
  return false;
}

add_shortcode('applylunch_dashboard', function(){
  if(!is_user_logged_in()) return '<p>Please log in.</p>';
  $tabs=[];
  if(al_has_any_role(['applylunch_superadmin','applylaunch_superadmin'])) $tabs['superadmin']=do_shortcode('[applylunch_superadmin_dashboard]');
  if(al_has_any_role(['applylunch_employee','applylaunch_employee']))   $tabs['agent']=do_shortcode('[applylunch_employee_dashboard]');
  if(al_has_any_role(['applylunch_customer','applylaunch_customer']))   $tabs['customer']=do_shortcode('[applylunch_customer_dashboard]');

  if(empty($tabs)) return '<p>No dashboard available.</p>';
  if(count($tabs)===1) return reset($tabs);
  ob_start(); ?>
  <style>.al-tabs{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid #e5e7eb;padding:.5rem;margin-bottom:.5rem}.al-tab{border:1px solid var(--stm-primary-color,#2563eb);background:#fff;color:var(--stm-primary-color,#2563eb);padding:.4rem .8rem;border-radius:999px;margin-right:.5rem;cursor:pointer}.al-tab.active{background:var(--stm-primary-color,#2563eb);color:#fff}.al-tab-content{display:none}</style>
  <div class="al-tabs"><?php foreach($tabs as $k=>$html): ?><button class="al-tab" data-role="<?php echo esc_attr($k); ?>"><?php echo ucfirst($k); ?></button><?php endforeach; ?></div>
  <div class="al-tab-contents"><?php foreach($tabs as $k=>$html): ?><div class="al-tab-content" id="al-tab-<?php echo esc_attr($k); ?>"><?php echo $html; ?></div><?php endforeach; ?></div>
  <script>
    document.addEventListener('DOMContentLoaded',function(){
      const tabs=[...document.querySelectorAll('.al-tab')], panes=[...document.querySelectorAll('.al-tab-content')];
      function show(role){ tabs.forEach(t=>t.classList.toggle('active',t.dataset.role===role)); panes.forEach(p=>p.style.display=(p.id==='al-tab-'+role)?'block':'none'); }
      const def=tabs.some(t=>t.dataset.role==='superadmin')?'superadmin':tabs[0].dataset.role; show(def);
      tabs.forEach(t=>t.addEventListener('click',()=>show(t.dataset.role)));
    });
  </script>
  <?php return ob_get_clean();
});
