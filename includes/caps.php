<?php
if (!defined('ABSPATH')) exit;

/**
 * Can the current user view/manage notes for $target_user_id?
 * Users can NEVER see notes on their own profile.
 */
function user_notes_current_user_can_view($target_user_id = 0) {
    if (!current_user_can('list_users')) return false;
    if ($target_user_id && get_current_user_id() == $target_user_id) return false;
    return true;
}

function user_notes_current_user_can_edit($target_user_id = 0) {
    return user_notes_current_user_can_view($target_user_id);
}

function user_notes_current_user_can_delete($target_user_id = 0) {
    if (!current_user_can('delete_users')) return false;
    if ($target_user_id && get_current_user_id() == $target_user_id) return false;
    return true;
}

function user_notes_format_author($author_id) {
    if (!$author_id) return __('System', 'user-notes');
    $u = get_userdata($author_id);
    /* translators: %d: numeric user ID */
    return $u ? $u->display_name : sprintf(__('User #%d', 'user-notes'), $author_id);
}

function user_notes_format_time($mysql_dt) {
    $ts = strtotime(get_gmt_from_date($mysql_dt) . ' UTC');
    if (!$ts) return esc_html($mysql_dt);
    $diff = time() - $ts;
    if ($diff < DAY_IN_SECONDS) {
        /* translators: %s: human-readable time difference, e.g. "3 hours" */
        return sprintf(__('%s ago', 'user-notes'), human_time_diff($ts));
    }
    return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $ts + (get_option('gmt_offset') * HOUR_IN_SECONDS));
}
