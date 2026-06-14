<?php
if (!defined('ABSPATH')) exit;

class User_Notes_Repo {

    const CACHE_GROUP = 'user_notes';

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'user_notes';
    }

    public static function install_table() {
        global $wpdb;
        $table = self::table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            author_id BIGINT UNSIGNED NOT NULL,
            body LONGTEXT NOT NULL,
            starred TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_sort (user_id, starred, created_at)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    protected static function invalidate_user($user_id) {
        wp_cache_delete('list_' . (int) $user_id, self::CACHE_GROUP);
        wp_cache_delete('count_' . (int) $user_id, self::CACHE_GROUP);
        wp_cache_delete('latest_' . (int) $user_id, self::CACHE_GROUP);
        wp_cache_delete('starred_' . (int) $user_id, self::CACHE_GROUP);
    }

    protected static function invalidate_note($id) {
        wp_cache_delete('note_' . (int) $id, self::CACHE_GROUP);
    }

    public static function get_for_user($user_id) {
        $user_id = (int) $user_id;
        $key = 'list_' . $user_id;
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if (false !== $cached) return $cached;

        global $wpdb;
        $table = self::table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table; $table built from $wpdb->prefix.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d ORDER BY starred DESC, created_at DESC, id DESC", $user_id));
        wp_cache_set($key, $rows, self::CACHE_GROUP, 300);
        return $rows;
    }

    public static function get($id) {
        $id = (int) $id;
        $key = 'note_' . $id;
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if (false !== $cached) return $cached;

        global $wpdb;
        $table = self::table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table.
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        wp_cache_set($key, $row, self::CACHE_GROUP, 300);
        return $row;
    }

    public static function insert($user_id, $author_id, $body, $starred = 0, $created_at = null) {
        global $wpdb;
        $now = $created_at ?: current_time('mysql');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; cache invalidated below.
        $wpdb->insert(self::table(), array(
            'user_id'    => (int) $user_id,
            'author_id'  => (int) $author_id,
            'body'       => $body,
            'starred'    => $starred ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ), array('%d', '%d', '%s', '%d', '%s', '%s'));
        $id = (int) $wpdb->insert_id;
        self::invalidate_user($user_id);
        return $id;
    }

    public static function update_body($id, $body) {
        global $wpdb;
        $note = self::get($id);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; cache invalidated below.
        $r = $wpdb->update(self::table(),
            array('body' => $body, 'updated_at' => current_time('mysql')),
            array('id' => (int) $id),
            array('%s', '%s'),
            array('%d')
        );
        self::invalidate_note($id);
        if ($note) self::invalidate_user($note->user_id);
        return $r;
    }

    public static function set_starred($id, $starred) {
        global $wpdb;
        $note = self::get($id);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; cache invalidated below.
        $r = $wpdb->update(self::table(),
            array('starred' => $starred ? 1 : 0, 'updated_at' => current_time('mysql')),
            array('id' => (int) $id),
            array('%d', '%s'),
            array('%d')
        );
        self::invalidate_note($id);
        if ($note) self::invalidate_user($note->user_id);
        return $r;
    }

    public static function delete($id) {
        global $wpdb;
        $note = self::get($id);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; cache invalidated below.
        $r = $wpdb->delete(self::table(), array('id' => (int) $id), array('%d'));
        self::invalidate_note($id);
        if ($note) self::invalidate_user($note->user_id);
        return $r;
    }

    public static function count_for_user($user_id) {
        $user_id = (int) $user_id;
        $key = 'count_' . $user_id;
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if (false !== $cached) return (int) $cached;

        global $wpdb;
        $table = self::table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table.
        $n = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id = %d", $user_id));
        wp_cache_set($key, $n, self::CACHE_GROUP, 300);
        return $n;
    }

    public static function latest_for_user($user_id) {
        $user_id = (int) $user_id;
        $key = 'latest_' . $user_id;
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if (false !== $cached) return $cached;

        global $wpdb;
        $table = self::table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table.
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d ORDER BY starred DESC, created_at DESC, id DESC LIMIT 1", $user_id));
        wp_cache_set($key, $row, self::CACHE_GROUP, 300);
        return $row;
    }

    public static function count_starred_for_user($user_id) {
        $user_id = (int) $user_id;
        $key = 'starred_' . $user_id;
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if (false !== $cached) return (int) $cached;

        global $wpdb;
        $table = self::table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table.
        $n = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id = %d AND starred = 1", $user_id));
        wp_cache_set($key, $n, self::CACHE_GROUP, 300);
        return $n;
    }

    public static function any_starred_for_user($user_id) {
        return self::count_starred_for_user($user_id) > 0;
    }
}
