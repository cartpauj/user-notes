<?php
if (!defined('ABSPATH')) exit;

function user_notes_run_migration_if_needed() {
    if (get_option('user_notes_db_version') === user_notes_version()) return;

    global $wpdb;

    // Pick lowest-ID admin/super-admin as author for migrated notes.
    $author_id = user_notes_pick_migration_author();
    $now = current_time('mysql');

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time activation migration across all users.
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value <> ''",
        'user-notes-note'
    ));

    if ($rows) {
        foreach ($rows as $row) {
            $body = user_notes_html_to_plaintext($row->meta_value);
            if ($body === '') continue;
            User_Notes_Repo::insert((int) $row->user_id, (int) $author_id, $body, 0, $now);
            // Keep original HTML in a backup meta so nothing is lost.
            update_user_meta((int) $row->user_id, '_user-notes-note-legacy-v1', $row->meta_value);
            delete_user_meta((int) $row->user_id, 'user-notes-note');
        }
    }

    update_option('user_notes_db_version', user_notes_version());
}

function user_notes_html_to_plaintext($html) {
    $s = (string) $html;
    // Turn block/line-break tags into newlines before stripping.
    $s = preg_replace('#<br\s*/?>#i', "\n", $s);
    $s = preg_replace('#</(p|div|li|h[1-6]|tr|blockquote)>#i', "\n\n", $s);
    $s = wp_strip_all_tags($s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset'));
    // Collapse 3+ newlines to 2, trim lines and whole string.
    $s = preg_replace("/\n{3,}/", "\n\n", $s);
    $s = preg_replace("/[ \t]+\n/", "\n", $s);
    return trim($s);
}

function user_notes_pick_migration_author() {
    global $wpdb;

    // Super admins first (multisite).
    if (is_multisite()) {
        $supers = get_super_admins();
        if (!empty($supers)) {
            $ids = array();
            foreach ($supers as $login) {
                $u = get_user_by('login', $login);
                if ($u) $ids[] = (int) $u->ID;
            }
            if ($ids) return min($ids);
        }
    }

    $admins = get_users(array(
        'role'    => 'administrator',
        'fields'  => 'ID',
        'orderby' => 'ID',
        'order'   => 'ASC',
        'number'  => 1,
    ));
    if (!empty($admins)) return (int) $admins[0];

    return 0;
}
