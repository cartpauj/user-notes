<?php
if (!defined('ABSPATH')) exit;

function user_notes_render_profile_section($wp_user) {
    if (!user_notes_current_user_can_view($wp_user->ID)) return;

    $notes = User_Notes_Repo::get_for_user($wp_user->ID);
    $can_delete = user_notes_current_user_can_delete($wp_user->ID);
    ?>
    <h3><?php esc_html_e('User Notes', 'user-notes'); ?></h3>

    <div id="user-notes-app" class="user-notes-app" data-user-id="<?php echo esc_attr($wp_user->ID); ?>" data-can-delete="<?php echo $can_delete ? '1' : '0'; ?>">
        <div class="user-notes-add">
            <textarea class="user-notes-new-body" rows="3" placeholder="<?php esc_attr_e('Add a note…', 'user-notes'); ?>"></textarea>
            <div class="user-notes-add-actions">
                <label><input type="checkbox" class="user-notes-new-starred" /> <?php esc_html_e('Star this note', 'user-notes'); ?></label>
                <button type="button" class="button button-primary user-notes-add-btn"><?php esc_html_e('Add Note', 'user-notes'); ?></button>
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

    return user_notes_column_html($user_id);
}, 10, 3);

/**
 * Markup for the Users-list Notes cell. Kept as a function so its output is
 * mirrored by the JS that re-renders the cell after edits in the modal.
 */
function user_notes_column_html($user_id) {
    $count = User_Notes_Repo::count_for_user($user_id);
    $edit_url = admin_url('user-edit.php?user_id=' . $user_id . '#user-notes-app');

    if (!$count) {
        return '<a class="user-notes-col-add" href="' . esc_url($edit_url) . '" data-user-id="' . esc_attr($user_id) . '">'
            . '<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>'
            . esc_html__('Add Note', 'user-notes') . '</a>';
    }

    $starred = User_Notes_Repo::count_starred_for_user($user_id);

    // Latest note as a hover tooltip — detail without cluttering the cell.
    $latest = User_Notes_Repo::latest_for_user($user_id);
    $excerpt = '';
    if ($latest) {
        $plain = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($latest->body)));
        $excerpt = function_exists('mb_substr') ? mb_substr($plain, 0, 120) : substr($plain, 0, 120);
        if (mb_strlen($plain) > 120) $excerpt .= '…';
    }

    $html  = '<a class="user-notes-col" href="' . esc_url($edit_url) . '" data-user-id="' . esc_attr($user_id) . '"'
           . ($excerpt ? ' title="' . esc_attr($excerpt) . '"' : '') . '>';

    /* translators: %s: number of notes */
    $count_label = sprintf(_n('%s note', '%s notes', $count, 'user-notes'), number_format_i18n($count));
    $html .= '<span class="user-notes-badge user-notes-badge-count" title="' . esc_attr($count_label) . '">'
           .   '<span class="dashicons dashicons-admin-comments" aria-hidden="true"></span>'
           .   '<span class="user-notes-badge-num">' . esc_html(number_format_i18n($count)) . '</span>'
           . '</span>';

    if ($starred > 0) {
        /* translators: %s: number of starred notes */
        $star_label = sprintf(_n('%s starred', '%s starred', $starred, 'user-notes'), number_format_i18n($starred));
        $html .= '<span class="user-notes-badge user-notes-badge-star" title="' . esc_attr($star_label) . '">'
               .   '<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>'
               .   '<span class="user-notes-badge-num">' . esc_html(number_format_i18n($starred)) . '</span>'
               . '</span>';
    }

    $html .= '</a>';

    return $html;
}
