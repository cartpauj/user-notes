<?php
if (!defined('ABSPATH')) exit;

function user_notes_render_profile_section($wp_user) {
    if (!user_notes_current_user_can_view($wp_user->ID)) return;

    $notes = User_Notes_Repo::get_for_user($wp_user->ID);
    $can_delete = user_notes_current_user_can_delete($wp_user->ID);
    ?>
    <h3><?php esc_html_e('User Notes', 'user-notes'); ?></h3>

    <div id="user-notes-app" data-user-id="<?php echo esc_attr($wp_user->ID); ?>" data-can-delete="<?php echo $can_delete ? '1' : '0'; ?>">
        <div class="user-notes-add">
            <textarea id="user-notes-new-body" rows="3" placeholder="<?php esc_attr_e('Add a note…', 'user-notes'); ?>"></textarea>
            <div class="user-notes-add-actions">
                <label><input type="checkbox" id="user-notes-new-starred" /> <?php esc_html_e('Star this note', 'user-notes'); ?></label>
                <button type="button" class="button button-primary" id="user-notes-add-btn"><?php esc_html_e('Add Note', 'user-notes'); ?></button>
            </div>
        </div>

        <ul class="user-notes-list">
            <?php foreach ($notes as $note): ?>
                <?php user_notes_render_note_item($note, $can_delete); ?>
            <?php endforeach; ?>
        </ul>
        <?php if (empty($notes)): ?>
            <p class="user-notes-empty"><?php esc_html_e('No notes yet.', 'user-notes'); ?></p>
        <?php endif; ?>
    </div>
    <?php
}
add_action('show_user_profile', 'user_notes_render_profile_section');
add_action('edit_user_profile', 'user_notes_render_profile_section');

function user_notes_render_note_item($note, $can_delete) {
    $edited = ($note->updated_at && $note->updated_at !== $note->created_at);
    ?>
    <li class="user-notes-item <?php echo $note->starred ? 'is-starred' : ''; ?>" data-note-id="<?php echo esc_attr($note->id); ?>">
        <div class="user-notes-meta">
            <button type="button" class="user-notes-star" title="<?php esc_attr_e('Toggle star', 'user-notes'); ?>">
                <span class="dashicons <?php echo $note->starred ? 'dashicons-star-filled' : 'dashicons-star-empty'; ?>"></span>
            </button>
            <span class="user-notes-author"><?php echo esc_html(user_notes_format_author($note->author_id)); ?></span>
            <span class="user-notes-time" title="<?php echo esc_attr($note->created_at); ?>"><?php echo esc_html(user_notes_format_time($note->created_at)); ?></span>
            <?php if ($edited): ?>
                <span class="user-notes-edited" title="<?php echo esc_attr($note->updated_at); ?>">(<?php echo esc_html(__('edited', 'user-notes') . ' ' . user_notes_format_time($note->updated_at)); ?>)</span>
            <?php endif; ?>
            <span class="user-notes-actions">
                <a href="#" class="user-notes-edit"><?php esc_html_e('Edit', 'user-notes'); ?></a>
                <?php if ($can_delete): ?>
                    | <a href="#" class="user-notes-delete"><?php esc_html_e('Delete', 'user-notes'); ?></a>
                <?php endif; ?>
            </span>
        </div>
        <div class="user-notes-body"><?php echo wp_kses_post(wpautop($note->body)); ?></div>
        <div class="user-notes-raw" style="display:none;"><?php echo esc_textarea($note->body); ?></div>
    </li>
    <?php
}

/* Users list column */

add_filter('manage_users_columns', function ($cols) {
    $cols['user_notes_note'] = __('Notes', 'user-notes');
    return $cols;
});

add_action('manage_users_custom_column', function ($val, $col_name, $user_id) {
    if ($col_name !== 'user_notes_note') return $val;
    if (!user_notes_current_user_can_view($user_id)) return '—';

    $count = User_Notes_Repo::count_for_user($user_id);
    $edit_url = admin_url('user-edit.php?user_id=' . $user_id . '#user-notes-app');

    if (!$count) {
        return '<a href="' . esc_url($edit_url) . '">' . esc_html__('Add Note', 'user-notes') . '</a>';
    }

    $latest = User_Notes_Repo::latest_for_user($user_id);
    $starred = User_Notes_Repo::any_starred_for_user($user_id);
    $excerpt = '';
    if ($latest) {
        $plain = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($latest->body)));
        $excerpt = function_exists('mb_substr') ? mb_substr($plain, 0, 80) : substr($plain, 0, 80);
        if (mb_strlen($plain) > 80) $excerpt .= '…';
    }

    $star_icon = $starred ? '<span class="dashicons dashicons-star-filled" style="color:#d4a017;"></span> ' : '';
    /* translators: %d: number of notes */
    $label = sprintf(_n('%d note', '%d notes', $count, 'user-notes'), $count);

    return $star_icon . '<a href="' . esc_url($edit_url) . '"><strong>' . esc_html($label) . '</strong></a>'
        . ($excerpt ? '<br><span style="color:#666;">' . esc_html($excerpt) . '</span>' : '');
}, 10, 3);
