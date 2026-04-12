<?php
if (!defined('ABSPATH')) exit;

function user_notes_note_payload($note) {
    $edited = ($note->updated_at && $note->updated_at !== $note->created_at);
    return array(
        'id'          => (int) $note->id,
        'user_id'     => (int) $note->user_id,
        'author_id'   => (int) $note->author_id,
        'author'      => user_notes_format_author($note->author_id),
        'starred'     => (int) $note->starred ? 1 : 0,
        'body_raw'    => $note->body,
        'body_html'   => wp_kses_post(wpautop($note->body)),
        'created_at'  => $note->created_at,
        'updated_at'  => $note->updated_at,
        'created_rel' => user_notes_format_time($note->created_at),
        'updated_rel' => $edited ? user_notes_format_time($note->updated_at) : '',
        'edited'      => $edited,
    );
}

add_action('wp_ajax_user_notes_add', function () {
    check_ajax_referer('user_notes_ajax', 'nonce');

    $user_id = isset($_POST['user_id']) ? (int) sanitize_text_field(wp_unslash($_POST['user_id'])) : 0;
    if (!$user_id) wp_send_json_error(array('message' => 'bad_user'), 400);
    if (!user_notes_current_user_can_edit($user_id)) wp_send_json_error(array('message' => 'forbidden'), 403);

    $body = isset($_POST['body']) ? wp_kses_post(wp_unslash($_POST['body'])) : '';
    if (trim(wp_strip_all_tags($body)) === '') wp_send_json_error(array('message' => 'empty'), 400);

    $starred = !empty($_POST['starred']) ? 1 : 0;
    $id = User_Notes_Repo::insert($user_id, get_current_user_id(), $body, $starred);
    wp_send_json_success(user_notes_note_payload(User_Notes_Repo::get($id)));
});

add_action('wp_ajax_user_notes_edit', function () {
    check_ajax_referer('user_notes_ajax', 'nonce');

    $note_id = isset($_POST['note_id']) ? (int) sanitize_text_field(wp_unslash($_POST['note_id'])) : 0;
    $note = $note_id ? User_Notes_Repo::get($note_id) : null;
    if (!$note) wp_send_json_error(array('message' => 'not_found'), 404);
    if (!user_notes_current_user_can_edit($note->user_id)) wp_send_json_error(array('message' => 'forbidden'), 403);

    $body = isset($_POST['body']) ? wp_kses_post(wp_unslash($_POST['body'])) : '';
    if (trim(wp_strip_all_tags($body)) === '') wp_send_json_error(array('message' => 'empty'), 400);

    User_Notes_Repo::update_body($note_id, $body);
    wp_send_json_success(user_notes_note_payload(User_Notes_Repo::get($note_id)));
});

add_action('wp_ajax_user_notes_toggle_star', function () {
    check_ajax_referer('user_notes_ajax', 'nonce');

    $note_id = isset($_POST['note_id']) ? (int) sanitize_text_field(wp_unslash($_POST['note_id'])) : 0;
    $note = $note_id ? User_Notes_Repo::get($note_id) : null;
    if (!$note) wp_send_json_error(array('message' => 'not_found'), 404);
    if (!user_notes_current_user_can_edit($note->user_id)) wp_send_json_error(array('message' => 'forbidden'), 403);

    User_Notes_Repo::set_starred($note_id, $note->starred ? 0 : 1);
    wp_send_json_success(user_notes_note_payload(User_Notes_Repo::get($note_id)));
});

add_action('wp_ajax_user_notes_delete', function () {
    check_ajax_referer('user_notes_ajax', 'nonce');

    $note_id = isset($_POST['note_id']) ? (int) sanitize_text_field(wp_unslash($_POST['note_id'])) : 0;
    $note = $note_id ? User_Notes_Repo::get($note_id) : null;
    if (!$note) wp_send_json_error(array('message' => 'not_found'), 404);
    if (!user_notes_current_user_can_delete($note->user_id)) wp_send_json_error(array('message' => 'forbidden'), 403);

    User_Notes_Repo::delete($note_id);
    wp_send_json_success(array('id' => $note_id));
});
