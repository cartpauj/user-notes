(function ($) {
    'use strict';

    /* ---------------------------------------------------------------------
     * Note management — works inside any ".user-notes-app" container, so the
     * same code drives both the profile section and the Users-list modal.
     * Each container carries data-user-id and data-can-delete.
     * ------------------------------------------------------------------- */

    $(function () {
        $(document)
            .on('click', '.user-notes-add-btn', onAdd)
            .on('click', '.user-notes-star', onToggleStar)
            .on('click', '.user-notes-edit', onEditStart)
            .on('click', '.user-notes-delete', onDelete)
            // Users-list column → open modal
            .on('click', '.user-notes-col, .user-notes-col-add', onOpenModal);
    });

    function i18n(key, fallback) {
        return (UserNotes.i18n && UserNotes.i18n[key]) || fallback;
    }

    function post(action, data) {
        return $.post(UserNotes.ajaxUrl, $.extend({
            action: action,
            nonce: UserNotes.nonce
        }, data));
    }

    function appOf(el) { return $(el).closest('.user-notes-app'); }
    function userIdOf($app) { return $app.data('user-id'); }
    function canDeleteOf($app) { return String($app.data('can-delete')) === '1'; }

    /* ---- actions -------------------------------------------------------- */

    function onAdd() {
        var $btn = $(this);
        var $app = appOf($btn);
        var userId = userIdOf($app);
        var $ta = $app.find('.user-notes-new-body');
        var body = $.trim($ta.val());
        if (!body) return;
        var starred = $app.find('.user-notes-new-starred').is(':checked') ? 1 : 0;

        $btn.prop('disabled', true).text(i18n('saving', 'Saving…'));
        post('user_notes_add', { user_id: userId, body: body, starred: starred })
            .done(function (res) {
                if (!res || !res.success) return alert(i18n('error', 'Error'));
                $ta.val('');
                $app.find('.user-notes-new-starred').prop('checked', false);
                $app.find('.user-notes-empty').remove();
                renderAndInsert($app, res.data);
                syncColumn($app);
            })
            .fail(function () { alert(i18n('error', 'Error')); })
            .always(function () { $btn.prop('disabled', false).text(i18n('addNote', 'Add Note')); });
    }

    function onToggleStar(e) {
        e.preventDefault();
        var $app = appOf(this);
        var $li = $(this).closest('.user-notes-item');
        var id = $li.data('note-id');
        $li.addClass('is-busy');
        post('user_notes_toggle_star', { note_id: id })
            .done(function (res) {
                if (!res || !res.success) return alert(i18n('error', 'Error'));
                replaceItem($app, $li, res.data);
                resort($app);
                syncColumn($app);
            })
            .fail(function () { alert(i18n('error', 'Error')); })
            .always(function () { $li.removeClass('is-busy'); });
    }

    function onEditStart(e) {
        e.preventDefault();
        var $app = appOf(this);
        var $li = $(this).closest('.user-notes-item');
        if ($li.find('.user-notes-edit-area').length) return;

        var raw = $li.find('.user-notes-raw').text();
        var $body = $li.find('.user-notes-body').hide();
        var $area = $('<div class="user-notes-edit-area"></div>');
        var $ta = $('<textarea rows="3"></textarea>').val(raw);
        var $save = $('<button type="button" class="button button-primary"></button>').text(i18n('save', 'Save'));
        var $cancel = $('<button type="button" class="button"></button>').text(i18n('cancel', 'Cancel'));
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
                    if (!res || !res.success) return alert(i18n('error', 'Error'));
                    $area.remove();
                    replaceItem($app, $li, res.data);
                })
                .fail(function () { alert(i18n('error', 'Error')); })
                .always(function () { $li.removeClass('is-busy'); });
        });
    }

    function onDelete(e) {
        e.preventDefault();
        var $app = appOf(this);
        if (!canDeleteOf($app)) return;
        if (!window.confirm(i18n('confirmDelete', 'Delete this note?'))) return;

        var $li = $(this).closest('.user-notes-item');
        $li.addClass('is-busy');
        post('user_notes_delete', { note_id: $li.data('note-id') })
            .done(function (res) {
                if (!res || !res.success) return alert(i18n('error', 'Error'));
                $li.slideUp(150, function () {
                    $(this).remove();
                    if (!$app.find('.user-notes-item').length) {
                        $app.find('.user-notes-list').after('<p class="user-notes-empty">' + escHtml(i18n('noNotes', 'No notes yet.')) + '</p>');
                    }
                    syncColumn($app);
                });
            })
            .fail(function () { alert(i18n('error', 'Error')); });
    }

    /* ---- rendering ------------------------------------------------------ */

    function buildItem(n, canDelete) {
        var star = n.starred ? 'dashicons-star-filled' : 'dashicons-star-empty';
        var edited = n.edited ? '<span class="user-notes-edited" title="' + escAttr(n.updated_at) + '">(' + escHtml(i18n('edited', 'edited') + ' ' + n.updated_rel) + ')</span>' : '';
        var delLink = canDelete ? ' | <a href="#" class="user-notes-delete">' + escHtml(i18n('del', 'Delete')) + '</a>' : '';

        var html = ''
            + '<li class="user-notes-item ' + (n.starred ? 'is-starred' : '') + '" data-note-id="' + n.id + '">'
            + '  <div class="user-notes-meta">'
            + '    <button type="button" class="user-notes-star" title="' + escAttr(i18n('toggleStar', 'Toggle star')) + '"><span class="dashicons ' + star + '"></span></button>'
            + '    <span class="user-notes-author">' + escHtml(n.author) + '</span>'
            + '    <span class="user-notes-time" title="' + escAttr(n.created_at) + '">' + escHtml(n.created_rel) + '</span>'
            + '    ' + edited
            + '    <span class="user-notes-actions"><a href="#" class="user-notes-edit">' + escHtml(i18n('edit', 'Edit')) + '</a>' + delLink + '</span>'
            + '  </div>'
            + '  <div class="user-notes-body">' + n.body_html + '</div>'
            + '  <div class="user-notes-raw" style="display:none;"></div>'
            + '</li>';
        var $el = $(html);
        $el.find('.user-notes-raw').text(n.body_raw);
        return $el;
    }

    function renderAndInsert($app, n) {
        var $new = buildItem(n, canDeleteOf($app));
        $app.find('.user-notes-list').prepend($new);
        resort($app);
    }

    function replaceItem($app, $li, n) {
        $li.replaceWith(buildItem(n, canDeleteOf($app)));
    }

    // Starred first, then newest (highest id) first — mirrors the server query.
    function resort($app) {
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

    /* ---- Users-list column sync ---------------------------------------- */

    // Rebuild the Users-list cell for this user to match server output.
    // No-op on the profile page (no matching row exists there).
    function syncColumn($app) {
        var userId = userIdOf($app);
        var $cell = $('#user-' + userId + ' .column-user_notes_note, #user-' + userId + ' td.user_notes_note');
        if (!$cell.length) return;

        var total = $app.find('.user-notes-item').length;
        var starred = $app.find('.user-notes-item.is-starred').length;
        $cell.html(columnHtml(userId, total, starred));
    }

    function columnHtml(userId, total, starred) {
        if (!total) {
            return '<a class="user-notes-col-add" href="#" data-user-id="' + userId + '">'
                + '<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>'
                + escHtml(i18n('addNote', 'Add Note')) + '</a>';
        }
        var html = '<a class="user-notes-col" href="#" data-user-id="' + userId + '">'
            + '<span class="user-notes-badge user-notes-badge-count">'
            +   '<span class="dashicons dashicons-admin-comments" aria-hidden="true"></span>'
            +   '<span class="user-notes-badge-num">' + total + '</span>'
            + '</span>';
        if (starred > 0) {
            html += '<span class="user-notes-badge user-notes-badge-star">'
                +   '<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>'
                +   '<span class="user-notes-badge-num">' + starred + '</span>'
                + '</span>';
        }
        html += '</a>';
        return html;
    }

    /* ---- Modal ---------------------------------------------------------- */

    var $modal, $lastTrigger;

    function ensureModal() {
        if ($modal) return $modal;
        $modal = $(''
            + '<div id="user-notes-modal" class="user-notes-modal" aria-hidden="true">'
            + '  <div class="user-notes-modal-overlay"></div>'
            + '  <div class="user-notes-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="user-notes-modal-title">'
            + '    <div class="user-notes-modal-header">'
            + '      <img class="user-notes-modal-avatar" src="" alt="" />'
            + '      <h2 id="user-notes-modal-title" class="user-notes-modal-title"></h2>'
            + '      <button type="button" class="user-notes-modal-close" aria-label="' + escAttr(i18n('close', 'Close')) + '"><span class="dashicons dashicons-no-alt"></span></button>'
            + '    </div>'
            + '    <div class="user-notes-modal-body"></div>'
            + '  </div>'
            + '</div>'
        ).appendTo(document.body);

        $modal.on('click', '.user-notes-modal-close, .user-notes-modal-overlay', closeModal);
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $modal && $modal.hasClass('is-open')) closeModal();
        });
        return $modal;
    }

    function appShell() {
        return ''
            + '<div class="user-notes-app" data-user-id="" data-can-delete="0">'
            + '  <div class="user-notes-add">'
            + '    <textarea class="user-notes-new-body" rows="3" placeholder="' + escAttr(i18n('addPlaceholder', 'Add a note…')) + '"></textarea>'
            + '    <div class="user-notes-add-actions">'
            + '      <label><input type="checkbox" class="user-notes-new-starred" /> ' + escHtml(i18n('starThis', 'Star this note')) + '</label>'
            + '      <button type="button" class="button button-primary user-notes-add-btn">' + escHtml(i18n('addNote', 'Add Note')) + '</button>'
            + '    </div>'
            + '  </div>'
            + '  <ul class="user-notes-list"></ul>'
            + '  <p class="user-notes-empty" hidden>' + escHtml(i18n('noNotes', 'No notes yet.')) + '</p>'
            + '</div>';
    }

    function onOpenModal(e) {
        e.preventDefault();
        var userId = $(this).data('user-id');
        if (!userId) return;
        $lastTrigger = $(this);
        openModal(userId);
    }

    function openModal(userId) {
        var $m = ensureModal();
        $m.find('.user-notes-modal-title').text(i18n('loading', 'Loading…'));
        $m.find('.user-notes-modal-avatar').attr('src', '').hide();
        $m.find('.user-notes-modal-body').html('<div class="user-notes-modal-loading"><span class="spinner is-active"></span></div>');
        $m.addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('user-notes-modal-open');

        post('user_notes_list', { user_id: userId })
            .done(function (res) {
                if (!res || !res.success) { closeModal(); return alert(i18n('error', 'Error')); }
                var d = res.data;
                $m.find('.user-notes-modal-title').text(i18n('notesFor', 'Notes for %s').replace('%s', d.user_name));
                if (d.avatar) $m.find('.user-notes-modal-avatar').attr('src', d.avatar).attr('alt', d.user_name).show();

                var $body = $m.find('.user-notes-modal-body').html(appShell());
                var $app = $body.find('.user-notes-app')
                    .attr('data-user-id', d.user_id)
                    .attr('data-can-delete', d.can_delete ? '1' : '0');

                if (!d.can_edit) $app.find('.user-notes-add').remove();

                var $list = $app.find('.user-notes-list');
                if (d.notes && d.notes.length) {
                    $.each(d.notes, function (_, n) { $list.append(buildItem(n, !!d.can_delete)); });
                } else {
                    $app.find('.user-notes-empty').prop('hidden', false);
                }
                $app.find('.user-notes-new-body').focus();
            })
            .fail(function () { closeModal(); alert(i18n('error', 'Error')); });
    }

    function closeModal() {
        if (!$modal) return;
        $modal.removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('user-notes-modal-open');
        if ($lastTrigger && $lastTrigger.length) { $lastTrigger.focus(); }
    }

    /* ---- utils ---------------------------------------------------------- */

    function escHtml(s) { return $('<div/>').text(s == null ? '' : s).html(); }
    function escAttr(s) { return escHtml(s).replace(/"/g, '&quot;'); }
})(jQuery);
