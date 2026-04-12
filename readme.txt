=== User Notes ===
Contributors: cartpauj
Tags: user, note, private notes, admin notes, secure notes
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Keep private notes about each of your users that only Administrators can see.

== Description ==

This plugin adds a text editor area to each User Profile in the dashboard for Administrators to keep private notes about each User. The notes are ONLY visible to Administrators -- that's the whole point! It also adds a column to the "All Users" list where you can quickly see the note for the user without having to even open their profile.

It is especially handy for [Membership Sites](http://www.memberpress.com/?aff=20 "Best membership plugin for WordPress") where you may have thousands of members to deal with and need to remember special circumstances for them.

== Installation ==

All you'll need to do to get this setup is:

1. Install and Activate the plugin
2. Visit any Users Profile page when logged in as an Administrator. There you will see a section for "User Notes". Update your notes for that user and save their profile.

That's it!

== Changelog ==
= 2.0.0 =
* Rewrote notes as a timestamped log: add multiple notes per user, each with author and timestamp
* Star notes to pin them to the top of the list
* Inline edit and permanent delete (delete requires `delete_users` capability and confirms before removing)
* Users can no longer view notes on their own profile
* Users list column now shows note count + latest excerpt, with a star indicator when any note is starred
* New `{prefix}user_notes` database table; existing notes are migrated automatically and attributed to the lowest-ID administrator
* Legacy HTML notes are converted to plain text on migration; original HTML preserved in `_user-notes-note-legacy-v1` user meta as a safety net
* Bumped minimum architecture; requires WordPress 6.0+

= 1.0.5 =
* Ensure compatibility with WP 6.9

= 1.0.4 =
* Security hardening - added CSRF protection

= 1.0.3 =
* Security hardening - improved output escaping

= 1.0.2 =
* Security patch

= 1.0.1 =
* Made "View Note" text stand out more in the column
* When hovering over a "View Note" link, you now see the first 100 characters of the note.

= 1.0.0 =
* Initial release on wordpress.org
* Blue Pencil Icon from [turbomilk](http://turbomilk.com/)
