<?php
/*
Plugin Name: User Notes
Plugin URI: https://github.com/cartpauj/user-notes
Description: Keep private, timestamped notes about each of your users that only Administrators can see.
Version: 2.1.0
Author: cartpauj
Author URI: https://github.com/cartpauj
Text Domain: user-notes
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/

if (!defined('ABSPATH')) exit;

define('USER_NOTES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('USER_NOTES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('USER_NOTES_PLUGIN_FILE', __FILE__);

function user_notes_version() {
    static $v = null;
    if ($v !== null) return $v;
    $data = get_file_data(USER_NOTES_PLUGIN_FILE, array('Version' => 'Version'));
    $v = !empty($data['Version']) ? $data['Version'] : '0.0.0';
    return $v;
}

require_once USER_NOTES_PLUGIN_DIR . 'includes/class-notes-repo.php';
require_once USER_NOTES_PLUGIN_DIR . 'includes/caps.php';
require_once USER_NOTES_PLUGIN_DIR . 'includes/render.php';
require_once USER_NOTES_PLUGIN_DIR . 'includes/ajax.php';
require_once USER_NOTES_PLUGIN_DIR . 'includes/migrate.php';

register_activation_hook(__FILE__, 'user_notes_activate');

function user_notes_activate() {
    User_Notes_Repo::install_table();
    user_notes_run_migration_if_needed();
}

add_action('plugins_loaded', function () {
    // Safety: run migration on upgrade too.
    if (get_option('user_notes_db_version') !== user_notes_version()) {
        User_Notes_Repo::install_table();
        user_notes_run_migration_if_needed();
    }
});

add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, array('profile.php', 'user-edit.php', 'users.php'), true)) return;
    if (!user_notes_current_user_can_view()) return;

    wp_enqueue_style('user-notes', USER_NOTES_PLUGIN_URL . 'assets/user-notes.css', array(), user_notes_version());
    wp_enqueue_script('user-notes', USER_NOTES_PLUGIN_URL . 'assets/user-notes.js', array('jquery'), user_notes_version(), true);
    wp_localize_script('user-notes', 'UserNotes', array(
        'ajaxUrl'     => admin_url('admin-ajax.php'),
        'nonce'       => wp_create_nonce('user_notes_ajax'),
        'canDelete'   => current_user_can('delete_users'),
        'i18n'        => array(
            'confirmDelete' => __('Delete this note permanently? This cannot be undone.', 'user-notes'),
            'saving'        => __('Saving…', 'user-notes'),
            'error'         => __('Something went wrong. Please try again.', 'user-notes'),
            'edited'        => __('edited', 'user-notes'),
            'by'            => __('by', 'user-notes'),
            'addNote'       => __('Add Note', 'user-notes'),
            'addPlaceholder' => __('Add a note…', 'user-notes'),
            'starThis'      => __('Star this note', 'user-notes'),
            'noNotes'       => __('No notes yet.', 'user-notes'),
            'loading'       => __('Loading…', 'user-notes'),
            'notesFor'      => __('Notes for %s', 'user-notes'),
            'close'         => __('Close', 'user-notes'),
            'edit'          => __('Edit', 'user-notes'),
            'del'           => __('Delete', 'user-notes'),
            'save'          => __('Save', 'user-notes'),
            'cancel'        => __('Cancel', 'user-notes'),
            'toggleStar'    => __('Toggle star', 'user-notes'),
        ),
    ));
});
