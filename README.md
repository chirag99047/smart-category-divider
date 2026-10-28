# Smart Category Divider

WordPress plugin for reviewing category suggestions before changing posts.

## Use

1. Install `smart-category-divider.zip` from a release and activate it.
2. Click **Settings** beside the plugin, or open **Tools > Category Divider**.
3. Click **Start new scan**. Each batch checks up to 50 posts.
4. Use **Select all**, individual checkboxes, or search and **Select search results**.
5. Choose categories per post, or use **Set category for selected posts**.
6. Review the selected distribution, then apply. **Next batch** remembers your place.

Only selected posts change. Ambiguous matches need a category choice. The plugin
does not force equal category sizes and can optionally categorize new posts on save.
Existing rules and undo history remain when upgrading.

## Automatic plugin updates

Enable **Auto-updates** for Smart Category Divider on WordPress's Plugins page.
The plugin checks this repository's latest published stable GitHub release.
It accepts only the `smart-category-divider.zip` asset from that release.
No GitHub token is needed. Post content is never sent to GitHub.

Future versions must be developed, tested, and published here. WordPress handles
installation on its normal scheduled checks; hosting cron/network restrictions
can delay it. Auto-updates do not generate new plugin versions.

## Publish a future release

1. Update the PHP version header, script version and readme stable tag together.
2. Test the PHP and JavaScript, including preview, selection, apply and undo.
3. ZIP the plugin files inside one folder named `smart-category-divider`.
4. Create a stable GitHub release with a tag such as `v1.2.0` and attach the ZIP
   named exactly `smart-category-divider.zip`. Do not use GitHub's generated source
   ZIP as the WordPress package.

Requires WordPress 6.0+ and PHP 7.4+. Licensed GPL-2.0-or-later.
See `readme.txt` for matching, permissions, privacy and retention details.

Automatic categorization (1.2.0): Enable the checkbox in Tools > Category Divider.
Clear matches on new posts are assigned on save. Posts > All Posts shows the
Auto category status. Uncertain matches stay unchanged for review.
