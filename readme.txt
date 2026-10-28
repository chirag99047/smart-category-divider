=== Smart Category Divider ===
Contributors: custom-site-tools
Tags: categories, bulk editing, content organization
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reviewable category suggestions for WordPress posts written in HTML.

== Installation ==
1. Plugins > Add New > Upload Plugin. Choose smart-category-divider.zip.
2. Activate, then open Tools > Category Divider.
3. Expand Configure category rules. Review the phrases for every category.
4. Save rules. Click Start new scan.
5. Review evidence and distribution. Adjust dropdowns and tick approved posts.
6. Choose Add to preserve existing categories or Replace for one chosen category.
7. Apply selected assignments. Click Next batch; your place is remembered automatically.

== Matching ==
Uses existing categories only. Category names seed the initial phrases. Enter
synonyms or more specific phrases separated by commas or newlines. Any phrase
can match. A title hit earns 10 points, heading hit 6, body-only hit 1. Each phrase
is counted once, so repeated wording cannot inflate its score. At least one
title/heading match is required. If a competing category reaches 65 percent of
the top score, the post requires review. Strongly matching descendants supersede
matching ancestors. Exclusions block matches anywhere in the article.

Descriptions display for reference but are not semantically analyzed. It is a
local rule-based tool, not an AI semantic classifier. Non-Latin exact matching
works; case normalization is best with PHP mbstring. Manual choices do not train
a model. Improve future scans by explicitly updating matching phrases.

== Distribution ==
Shows suggested counts for the current batch, not the whole website. Warns if
one category receives at least 70 percent of five or more matched posts. The
warning does not force unrelated posts into other categories. Suggested counts show the initial matches. Selected distribution updates as you
choose posts and change categories. Select all covers the entire current batch.

== Safety and operation ==
No API key, subscription, post-body editing, or new categories. Update checks
contact api.github.com; plugin ZIP downloads come from GitHub releases. No post
content or category data is sent. GitHub receives normal connection metadata.
Nothing is applied until selected and submitted. Existing categories are retained
by default, except the default category is removed when another is assigned.
Replace mode deliberately replaces every existing category with your selection.
Only administrators or users allowed to manage/assign categories and edit the
individual post can apply changes. Nonces protect form submissions. Changed post
text, status, or categories invalidate the corresponding preview row.

Scans up to 50 records per request using stable post IDs; filtered batches can be
empty. Continue with the next batch until the end. Draft, pending, scheduled,
private and published posts are included, subject to edit permissions. Preview
expires in one hour, is private to your user account, and is replaced by each scan.
This release handles posts and the built-in category taxonomy, not pages,
WooCommerce products, or custom taxonomies. Automatic categorization can be enabled separately for newly added posts. It
waits for title and body, preserves non-default categories, and marks unclear
matches for review. Existing posts are not enrolled. Unresolved new posts retry
on their next save. Automatic assignments are not in batch Undo; use the post
editor to change them. Per-post _scd_auto_status metadata is retained.

Undo retains the last 20 changed batches per user. Later category edits block
undo for that post; unresolved conflicts are retained in the latest batch.
Undo is not a backup and does not restore deleted categories. Try a small batch
on a staging site first. External plugins that change terms on hooks can affect
the outcome; avoid running concurrent category editing jobs.

Data remains on deactivation: scd_rules option, user scd_history metadata, and
expiring per-user preview transients. No uninstall deletion is performed.

== Automatic version updates ==
Enable auto-updates for this plugin on Plugins > Installed Plugins. WordPress
checks published stable releases at https://github.com/chirag99047/smart-category-divider
on its normal update schedule. Releases must include smart-category-divider.zip.
Checks are cached for one hour. Server cron/network settings can delay updates.
The separate automatic categorization setting controls new posts. Version updates do not create future plugin versions.

== Changelog ==
= 1.1.0 =
Select all, individual and search-result selection, bulk category choices, live
selected distribution, automatic scan continuation, Settings shortcut, and GitHub
release updates using the native WordPress auto-update setting.

= 1.0.0 =
Initial release: configurable rules, hierarchical matching, review queue,
distribution warnings, controlled batch application and conflict-aware undo.

Automatic categorization (1.2.0): Enable the checkbox in Tools > Category Divider.
Clear matches on new posts are assigned on save. Posts > All Posts shows the
Auto category status. Uncertain matches stay unchanged for review.
