(function ($) {
    'use strict';

    var $app, userId, canDelete;

    $(function () {
        $app = $('#user-notes-app');
        if (!$app.length) return;
        userId = $app.data('user-id');
        canDelete = String($app.data('can-delete')) === '1';

        $app.on('click', '#user-notes-add-btn', onAdd);
        $app.on('click', '.user-notes-star', onToggleStar);
        $app.on('click', '.user-notes-edit', onEditStart);
        $app.on('click', '.user-notes-delete', onDelete);
    });

    function post(action, data) {
        return $.post(UserNotes.ajaxUrl, $.extend({
            action: action,
            nonce: UserNotes.nonce
        }, data));
    }

    function onAdd() {
        var $btn = $(this);
        var $ta = $('#user-notes-new-body');
        var body = $.trim($ta.val());
        if (!body) return;
        var starred = $('#user-notes-new-starred').is(':checked') ? 1 : 0;

        $btn.prop('disabled', true).text(UserNotes.i18n.saving);
        post('user_notes_add', { user_id: userId, body: body, starred: starred })
            .done(function (res) {
                if (!res || !res.success) return alert(UserNotes.i18n.error);
                $ta.val('');
                $('#user-notes-new-starred').prop('checked', false);
                $app.find('.user-notes-empty').remove();
                renderAndInsert(res.data);
            })
            .fail(function () { alert(UserNotes.i18n.error); })
            .always(function () { $btn.prop('disabled', false).text('Add Note'); });
    }

    function onToggleStar(e) {
        e.preventDefault();
        var $li = $(this).closest('.user-notes-item');
        var id = $li.data('note-id');
        $li.addClass('is-busy');
        post('user_notes_toggle_star', { note_id: id })
            .done(function (res) {
                if (!res || !res.success) return alert(UserNotes.i18n.error);
                replaceItem($li, res.data);
                resort();
            })
            .fail(function () { alert(UserNotes.i18n.error); })
            .always(function () { $li.removeClass('is-busy'); });
    }

    function onEditStart(e) {
        e.preventDefault();
        var $li = $(this).closest('.user-notes-item');
        if ($li.find('.user-notes-edit-area').length) return;

        var raw = $li.find('.user-notes-raw').text();
        var $body = $li.find('.user-notes-body').hide();
        var $area = $('<div class="user-notes-edit-area"></div>');
        var $ta = $('<textarea rows="3"></textarea>').val(raw);
        var $save = $('<button type="button" class="button button-primary">Save</button>');
        var $cancel = $('<button type="button" class="button">Cancel</button>');
        var $actions = $('<div class="user-notes-edit-actions"></div>').append($save, $cancel);
        $area.append($ta, $actions);
        $body.after($area);
        $ta.focus();

        $cancel.on('click', function () { $area.remove(); $body.show(); });
        $save.on('click', function () {
            var val = $.trim($ta.val());
            if (!val) return;
            $li.addClass('is-busy');
            post('user_notes_edit', { note_id: $li.data('note-id'), body: val })
                .done(function (res) {
                    if (!res || !res.success) return alert(UserNotes.i18n.error);
                    $area.remove();
                    replaceItem($li, res.data);
                })
                .fail(function () { alert(UserNotes.i18n.error); })
                .always(function () { $li.removeClass('is-busy'); });
        });
    }

    function onDelete(e) {
        e.preventDefault();
        if (!canDelete) return;
        if (!window.confirm(UserNotes.i18n.confirmDelete)) return;

        var $li = $(this).closest('.user-notes-item');
        $li.addClass('is-busy');
        post('user_notes_delete', { note_id: $li.data('note-id') })
            .done(function (res) {
                if (!res || !res.success) return alert(UserNotes.i18n.error);
                $li.slideUp(150, function () {
                    $(this).remove();
                    if (!$app.find('.user-notes-item').length) {
                        $app.find('.user-notes-list').after('<p class="user-notes-empty">No notes yet.</p>');
                    }
                });
            })
            .fail(function () { alert(UserNotes.i18n.error); });
    }

    function buildItem(n) {
        var star = n.starred ? 'dashicons-star-filled' : 'dashicons-star-empty';
        var edited = n.edited ? '<span class="user-notes-edited" title="' + escAttr(n.updated_at) + '">(' + escHtml(UserNotes.i18n.edited + ' ' + n.updated_rel) + ')</span>' : '';
        var delLink = canDelete ? ' | <a href="#" class="user-notes-delete">Delete</a>' : '';

        var html = ''
            + '<li class="user-notes-item ' + (n.starred ? 'is-starred' : '') + '" data-note-id="' + n.id + '">'
            + '  <div class="user-notes-meta">'
            + '    <button type="button" class="user-notes-star" title="Toggle star"><span class="dashicons ' + star + '"></span></button>'
            + '    <span class="user-notes-author">' + escHtml(n.author) + '</span>'
            + '    <span class="user-notes-time" title="' + escAttr(n.created_at) + '">' + escHtml(n.created_rel) + '</span>'
            + '    ' + edited
            + '    <span class="user-notes-actions"><a href="#" class="user-notes-edit">Edit</a>' + delLink + '</span>'
            + '  </div>'
            + '  <div class="user-notes-body">' + n.body_html + '</div>'
            + '  <div class="user-notes-raw" style="display:none;"></div>'
            + '</li>';
        var $el = $(html);
        $el.find('.user-notes-raw').text(n.body_raw);
        return $el;
    }

    function renderAndInsert(n) {
        var $new = buildItem(n);
        var $list = $app.find('.user-notes-list');
        $list.prepend($new);
        resort();
    }

    function replaceItem($li, n) {
        var $new = buildItem(n);
        $li.replaceWith($new);
    }

    function resort() {
        var $list = $app.find('.user-notes-list');
        var items = $list.children('.user-notes-item').get();
        items.sort(function (a, b) {
            var as = $(a).hasClass('is-starred') ? 1 : 0;
            var bs = $(b).hasClass('is-starred') ? 1 : 0;
            if (as !== bs) return bs - as;
            return $(b).data('note-id') - $(a).data('note-id');
        });
        $.each(items, function (_, el) { $list.append(el); });
    }

    function escHtml(s) { return $('<div/>').text(s == null ? '' : s).html(); }
    function escAttr(s) { return escHtml(s).replace(/"/g, '&quot;'); }
})(jQuery);
