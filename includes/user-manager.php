<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function applylunch_assert_superadmin(){
  if(!is_user_logged_in()) return false;
  $u=wp_get_current_user();
  return in_array('applylunch_superadmin',(array)$u->roles,true) || in_array('applylaunch_superadmin',(array)$u->roles,true);
}

function applylunch_um_roles(){
  return ['applylunch_customer'=>'Customer','applylunch_employee'=>'Agent'];
}

function applylunch_um_get_users($roles){
  return get_users(['role__in'=>array_keys($roles),'orderby'=>'display_name','order'=>'ASC','number'=>500]);
}

function applylunch_um_handle_actions(){
  if(!applylunch_assert_superadmin()) return null;
  if(isset($_POST['al_um_add']) && wp_verify_nonce($_POST['al_um_nonce']??'','al_um')){
    $role=sanitize_text_field($_POST['role']??''); $email=sanitize_email($_POST['email']??'');
    $fn=sanitize_text_field($_POST['first_name']??''); $ln=sanitize_text_field($_POST['last_name']??''); $pass=(string)$_POST['password'];
    if(!$role||!$email) return ['type'=>'error','msg'=>'Role and email are required.'];
    if(email_exists($email)) return ['type'=>'error','msg'=>'Email already exists.'];
    $args=['user_login'=>$email,'user_email'=>$email,'first_name'=>$fn,'last_name'=>$ln,'role'=>$role]; if(strlen($pass)>=6) $args['user_pass']=$pass;
    $r=wp_insert_user($args); if(is_wp_error($r)) return ['type'=>'error','msg'=>$r->get_error_message()];
    return ['type'=>'success','msg'=>'User added.'];
  }
  if(isset($_POST['al_um_edit']) && wp_verify_nonce($_POST['al_um_nonce']??'','al_um')){
    $uid=intval($_POST['user_id']??0); if(!$uid) return ['type'=>'error','msg'=>'Invalid user.'];
    $email=sanitize_email($_POST['email']??''); $fn=sanitize_text_field($_POST['first_name']??''); $ln=sanitize_text_field($_POST['last_name']??''); $role=sanitize_text_field($_POST['role']??''); $pass=(string)$_POST['password'];
    $args=['ID'=>$uid,'user_email'=>$email,'first_name'=>$fn,'last_name'=>$ln]; if($role) $args['role']=$role; if(strlen($pass)>=6) $args['user_pass']=$pass;
    $r=wp_update_user($args); if(is_wp_error($r)) return ['type'=>'error','msg'=>$r->get_error_message()];
    return ['type'=>'success','msg'=>'User updated.'];
  }
  if(isset($_POST['al_um_delete']) && wp_verify_nonce($_POST['al_um_nonce']??'','al_um')){
    $uid=intval($_POST['user_id']??0); $reassign=intval($_POST['reassign_user']??0); $hint=sanitize_text_field($_POST['role_hint']??'');
    if(!$uid) return ['type'=>'error','msg'=>'Invalid user.'];
    global $wpdb; $t=$wpdb->prefix.'applylaunch_jobs';
    if($reassign>0){
      if($hint==='customer'){ $wpdb->update($t,['customer_id'=>$reassign],['customer_id'=>$uid]); }
      elseif($hint==='employee'){ $wpdb->update($t,['employee_id'=>$reassign],['employee_id'=>$uid]); }
    }
    require_once ABSPATH.'wp-admin/includes/user.php';
    if($reassign>0) wp_delete_user($uid,$reassign); else wp_delete_user($uid);
    return ['type'=>'success','msg'=>'User deleted.'];
  }
  return null;
}

function applylunch_um_render(){
  if(!applylunch_assert_superadmin()) return '<p>No permission.</p>';
  $notice=applylunch_um_handle_actions();
  $roles=applylunch_um_roles();
  $users=applylunch_um_get_users($roles);
  $by_role=['customer'=>[],'employee'=>[]];
  foreach($users as $u){
    $is_c=in_array('applylunch_customer',(array)$u->roles,true)||in_array('applylaunch_customer',(array)$u->roles,true);
    $is_e=in_array('applylunch_employee',(array)$u->roles,true)||in_array('applylaunch_employee',(array)$u->roles,true);
    if($is_c) $by_role['customer'][]=$u;
    if($is_e) $by_role['employee'][]=$u;
  }
  ob_start(); ?>
  <div>
    <h3>User Manager</h3>
    <?php if($notice): ?><div class="al-alert <?php echo $notice['type']==='success'?'al-success':''; ?>"><?php echo esc_html($notice['msg']); ?></div><?php endif; ?>
    <form method="post" class="al-form" style="margin-bottom:1rem;">
      <?php wp_nonce_field('al_um','al_um_nonce'); ?>
      <div class="al-grid">
        <div><label>Role</label><select name="role"><?php foreach($roles as $k=>$v): ?><option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option><?php endforeach; ?></select></div>
        <div><label>Email</label><input type="email" name="email" required></div>
        <div><label>First Name</label><input type="text" name="first_name"></div>
        <div><label>Last Name</label><input type="text" name="last_name"></div>
        <div><label>Password (min 6)</label><input type="password" name="password" minlength="6" placeholder="Leave blank to auto-generate"></div>
      </div>
      <button class="al-btn" name="al_um_add" value="1">Add User</button>
    </form>
    <div class="al-table-wrap"><table class="al-table">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($users as $u): $role_label=(in_array('applylunch_employee',(array)$u->roles,true)||in_array('applylaunch_employee',(array)$u->roles,true))?'Agent':'Customer'; ?>
        <tr>
          <td><?php echo esc_html($u->display_name?:$u->user_login); ?></td>
          <td><?php echo esc_html($u->user_email); ?></td>
          <td><?php echo esc_html($role_label); ?></td>
          <td style="white-space:nowrap;">
            <details><summary>Edit</summary>
              <form method="post" class="al-form" style="margin-top:.5rem;">
                <?php wp_nonce_field('al_um','al_um_nonce'); ?>
                <input type="hidden" name="user_id" value="<?php echo intval($u->ID); ?>">
                <label>Email</label><input type="email" name="email" value="<?php echo esc_attr($u->user_email); ?>" required>
                <label>First Name</label><input type="text" name="first_name" value="<?php echo esc_attr($u->first_name); ?>">
                <label>Last Name</label><input type="text" name="last_name" value="<?php echo esc_attr($u->last_name); ?>">
                <label>Role</label><select name="role"><?php foreach($roles as $k=>$v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected(in_array($k,(array)$u->roles,true)); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select>
                <label>New Password (optional)</label><input type="password" name="password" minlength="6">
                <button class="al-btn" name="al_um_edit" value="1">Save</button>
              </form>
            </details>
            <details><summary>Delete</summary>
              <form method="post" class="al-form" style="margin-top:.5rem;" onsubmit="return confirm('Delete this user?');">
                <?php wp_nonce_field('al_um','al_um_nonce'); ?>
                <input type="hidden" name="user_id" value="<?php echo intval($u->ID); ?>">
                <?php $hint=(in_array('applylunch_employee',(array)$u->roles,true)||in_array('applylaunch_employee',(array)$u->roles,true))?'employee':'customer'; ?>
                <input type="hidden" name="role_hint" value="<?php echo esc_attr($hint); ?>">
                <?php if($hint==='customer'): ?>
                  <label>Reassign customer’s jobs to</label><select name="reassign_user"><option value="0">— Do not reassign —</option><?php foreach($by_role['customer'] as $c){ if($c->ID==$u->ID) continue; echo '<option value="'.intval($c->ID).'">'.esc_html($c->display_name?:$c->user_login).'</option>'; } ?></select>
                <?php else: ?>
                  <label>Reassign agent’s jobs to</label><select name="reassign_user"><option value="0">— Do not reassign —</option><?php foreach($by_role['employee'] as $e){ if($e->ID==$u->ID) continue; echo '<option value="'.intval($e->ID).'">'.esc_html($e->display_name?:$e->user_login).'</option>'; } ?></select>
                <?php endif; ?>
                <button class="al-btn" name="al_um_delete" value="1">Delete User</button>
              </form>
            </details>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <?php return ob_get_clean();
}

add_shortcode('applylunch_user_manager', function(){ return applylunch_um_render(); });
