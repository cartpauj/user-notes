<?php
/*
Plugin Name: User Notes
Plugin URI: http://cartpauj.com
Description: Keep private notes about each of your users that only Administrators can see.
Version: 1.0.4
Author: Cartpauj
Author URI: http://cartpauj.com
Text Domain: user-notes

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

function user_notes_get_delim($link) {
  return ((preg_match("#\?#",$link))?'&':'?');
}

function user_notes_show_field($wp_user) {
  //If not an admin -- don't show this -- that would be bad :)
  if(!current_user_can('list_users'))
    return;
  
  $notes = get_user_meta($wp_user->ID, 'user-notes-note', true);
  
  ?>
    <h3><?php _e('User Notes', 'user-notes'); ?></h3>

    <table class="form-table">
      <tbody>
        <tr>
          <td colspan="2">
            <?php wp_nonce_field('user_notes_save_' . $wp_user->ID, 'user_notes_nonce'); ?>
            <?php wp_editor($notes, 'user_notes_note', array('teeny' => true)); ?>
          </td>
        </tr>
      </tbody>
    </table>
  <?php
}
add_action('show_user_profile', 'user_notes_show_field');
add_action('edit_user_profile', 'user_notes_show_field');

function user_notes_save_note($user_id) {
  //Only admins, and only if it's set (so we don't wipe out the notes when non-admins save the profile)
  if(!current_user_can('list_users') || !isset($_POST['user_notes_note']))
    return;

  //Verify nonce for CSRF protection
  if(!isset($_POST['user_notes_nonce']) || !wp_verify_nonce($_POST['user_notes_nonce'], 'user_notes_save_' . $user_id))
    return;

  $notes = (!empty($_POST['user_notes_note']))?wp_kses_post(stripslashes($_POST['user_notes_note'])):'';

  update_user_meta($user_id, 'user-notes-note', $notes);
}
add_action('personal_options_update', 'user_notes_save_note');
add_action('edit_user_profile_update', 'user_notes_save_note');

function user_notes_add_users_column($cols) {
  $cols = array_merge($cols, array('user_notes_note' => __('Notes', 'user-notes')));
  
  return $cols;
}
add_filter('manage_users_columns', 'user_notes_add_users_column');

function user_notes_display_column($val, $col_name, $user_id) {
  if($col_name == 'user_notes_note') {
    $notes = get_user_meta($user_id, 'user-notes-note', true);
    
    //If no notes -- return none
    if(empty($notes))
      return '<a href="' . esc_url(admin_url('user-edit.php?user_id=' . $user_id . '#wp-user_notes_note-wrap')) . '">' . esc_html__('Add Note', 'user-notes') . '</a>';
    
    $notes_excerpt = strip_tags(substr($notes, 0, 100)) . ' ...';
    $notes_excerpt = trim(preg_replace('/\s+/', ' ', $notes_excerpt));
    
    $user = new WP_User($user_id);
    $title = __('Note for', 'user-notes') . ' ' . $user->user_login . "\n" . $notes_excerpt;
    
    //Get the dilimiter
    $uri = $_SERVER['REQUEST_URI'];
    $delim = user_notes_get_delim($uri);
    
    ob_start();
    
    ?>
      <div id="user_notes_thickbox_<?php echo esc_attr($user_id); ?>" style="display:none;">
        <?php echo wp_kses_post(wpautop($notes)); ?>
        <p><a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $user_id . '#wp-user_notes_note-wrap')); ?>"><?php _e('Edit Note', 'user-notes'); ?></a></p>
      </div>
      <strong><a href="#TB_inline<?php echo esc_attr($delim); ?>inlineId=user_notes_thickbox_<?php echo esc_attr($user_id); ?>" class="thickbox" title="<?php echo esc_attr($title); ?>" style="color:navy;"><?php _e('View Note', 'user-notes'); ?></a></strong>
    <?php
    
    return ob_get_clean();
  }
  
  return $val;
}
add_action('manage_users_custom_column', 'user_notes_display_column', 10, 3);

function user_notes_enqueue_thickbox($hook) {
  if($hook != 'users.php')
    return;
  
  wp_enqueue_style('thickbox');
  wp_enqueue_script('thickbox');
}
add_action('admin_enqueue_scripts', 'user_notes_enqueue_thickbox');
