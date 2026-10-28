<?php
/**
 * Plugin Name: Smart Category Divider
 * Description: Preview and review specific category matches for HTML posts, with distribution checks and batch undo.
 * Version: 1.2.0
 * Update URI: https://github.com/chirag99047/smart-category-divider
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Custom Site Tools
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/class-scd-matcher.php';
require_once __DIR__ . '/class-scd-updater.php';
new SCD_Updater(__FILE__);
class SCD_Plugin {
    private $notice = '';
    public function __construct() {
        add_action('wp_after_insert_post', array($this, 'auto_categorize'), 20, 4);
        add_filter('manage_post_posts_columns', array($this, 'auto_column'));
        add_action('manage_post_posts_custom_column', array($this, 'auto_column_value'), 10, 2);
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_init', array($this, 'dispatch'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'settings_link'));
        add_action('admin_enqueue_scripts', array($this, 'assets'));
    }
    public function auto_column($columns) {
        $columns['scd_auto'] = 'Auto category'; return $columns;
    }
    public function auto_column_value($column, $id) {
        if ($column !== 'scd_auto') { return; }
        $status = get_post_meta($id, '_scd_auto_status', true);
        $labels = array('pending' => 'Waiting for content', 'review' => 'Needs review', 'assigned' => 'Automatically assigned', 'manual' => 'Existing category kept');
        if (isset($labels[$status])) { echo esc_html($labels[$status]); }
    }
    public function auto_categorize($id, $post, $update, $before) {
        if (!get_option('scd_auto_enabled', false) || $post->post_type !== 'post' || wp_is_post_revision($id) || wp_is_post_autosave($id)) { return; }
        $status = get_post_meta($id, '_scd_auto_status', true);
        // Enroll only new posts, including editor auto-drafts. Never sweep old posts.
        if (!$status && (!$update || ($before && $before->post_status === 'auto-draft'))) {
            $status = 'pending'; update_post_meta($id, '_scd_auto_status', $status);
        }
        if (!in_array($status, array('pending', 'review'), true) || !in_array($post->post_status, array('draft', 'pending', 'future', 'publish', 'private'), true)) { return; }
        $cats = $this->cats($id);
        if (array_diff($cats, array((int)get_option('default_category')))) {
            update_post_meta($id, '_scd_auto_status', 'manual'); return;
        }
        // Wait until the article has both a title and body, avoiding premature matches.
        if (SCD_Matcher::clean($post->post_title) === '' || SCD_Matcher::clean($post->post_content) === '') { return; }
        $result = SCD_Matcher::suggest($post->post_title, $post->post_content, $this->rules());
        if (!$result['category'] || !term_exists($result['category'], 'category')) {
            update_post_meta($id, '_scd_auto_status', 'review'); return;
        }
        $written = wp_set_post_categories($id, array($result['category']), false);
        $ok = !is_wp_error($written) && $written !== false && $this->cats($id) === array((int)$result['category']);
        update_post_meta($id, '_scd_auto_status', $ok ? 'assigned' : 'review');
    }
    public function settings_link($links) {
        if ($this->permitted()) { array_unshift($links, '<a href="' . esc_url(admin_url('tools.php?page=scd')) . '">Settings</a>'); }
        return $links;
    }
    public function assets($hook) {
        if ($hook === 'tools_page_scd') {
            wp_enqueue_script('scd-admin', plugins_url('admin.js', __FILE__), array(), '1.2.0', true);
        }
    }
    public function dispatch() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'scd' || $_SERVER['REQUEST_METHOD'] !== 'POST') { return; }
        if (!$this->permitted()) { wp_die('You do not have permission to assign and manage categories.'); }
        $this->handle();
        set_transient($this->key('notice'), $this->notice, 60);
        wp_safe_redirect(admin_url('tools.php?page=scd'));
        exit;
    }
    public function menu() {
        add_management_page('Smart Category Divider', 'Category Divider', 'manage_categories', 'scd', array($this, 'page'));
    }
    private function permitted() {
        $taxonomy = get_taxonomy('category');
        return current_user_can('manage_categories') && current_user_can($taxonomy->cap->assign_terms);
    }
    private function terms() {
        $terms = get_terms(array('taxonomy' => 'category', 'hide_empty' => false));
        return is_wp_error($terms) ? array() : $terms;
    }
    private function label($term) {
        $names = array($term->name);
        foreach (get_ancestors($term->term_id, 'category', 'taxonomy') as $id) {
            $parent = get_term($id, 'category');
            if ($parent && !is_wp_error($parent)) { array_unshift($names, $parent->name); }
        }
        return implode(' / ', $names);
    }
    private function rules() {
        $saved = get_option('scd_rules', array()); $rules = array();
        foreach ($this->terms() as $term) {
            $id = (int)$term->term_id;
            $rules[$id] = isset($saved[$id]) ? $saved[$id] : array('enabled' => $id !== (int)get_option('default_category'), 'include' => $term->name, 'exclude' => '');
            $rules[$id]['ancestors'] = array_map('intval', get_ancestors($id, 'category', 'taxonomy'));
        }
        return $rules;
    }
    private function cats($id) {
        $cats = wp_get_post_categories($id); $cats = is_wp_error($cats) ? array() : array_map('intval', $cats); sort($cats); return $cats;
    }
    private function fingerprint($post) { return hash('sha256', $post->post_title . '|' . $post->post_content . '|' . $post->post_status); }
    private function key($type) { return 'scd_' . $type . '_' . get_current_user_id(); }
    private function valid_ids($ids) {
        foreach ($ids as $id) { if (!term_exists((int)$id, 'category')) { return false; } }
        return true;
    }
    private function scan() {
        $after = 0;
        $progress = get_transient($this->key('progress'));
        if (!empty($_POST['continue'])) {
            if (!$progress || empty($progress['more'])) { $this->notice = 'Start a new scan to continue.'; return; }
            $after = $progress['after'];
        }
        $scope = isset($_POST['scope']) && $_POST['scope'] === 'all' ? 'all' : 'uncategorized';
        if (!empty($_POST['continue'])) { $scope = $progress['scope']; }
        global $wpdb;
        // Stable ID cursor: category changes do not shift the next batch.
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status IN ('publish','draft','pending','future','private') AND ID > %d ORDER BY ID ASC LIMIT 50", $after));
        $rows = array(); $rules = $this->rules();
        foreach ($ids as $id) {
            $id = (int)$id; $post = get_post($id); $cats = $this->cats($id);
            if (!current_user_can('edit_post', $id)) { continue; }
            if ($scope === 'uncategorized' && array_diff($cats, array((int)get_option('default_category')))) { continue; }
            $result = SCD_Matcher::suggest($post->post_title, $post->post_content, $rules);
            $rows[$id] = array('title' => $post->post_title, 'before' => $cats, 'fingerprint' => $this->fingerprint($post), 'result' => $result);
        }
        $preview = array('token' => wp_generate_uuid4(), 'rows' => $rows, 'after' => $ids ? (int)end($ids) : $after, 'more' => count($ids) === 50, 'scope' => $scope);
        set_transient($this->key('preview'), $preview, HOUR_IN_SECONDS);
        set_transient($this->key('progress'), array('after' => $preview['after'], 'more' => $preview['more'], 'scope' => $scope), DAY_IN_SECONDS);
        $this->notice = 'Preview ready. Scanned ' . count($ids) . ' posts; ' . count($rows) . ' eligible. No posts changed.';
    }
    private function apply() {
        $preview = get_transient($this->key('preview'));
        if (!$preview || !isset($_POST['token']) || !hash_equals($preview['token'], sanitize_text_field(wp_unslash($_POST['token'])))) { $this->notice = 'Preview expired or replaced. Scan again.'; return; }
        $selected = isset($_POST['selected']) ? array_map('absint', (array)$_POST['selected']) : array();
        $choices = isset($_POST['choice']) ? (array)$_POST['choice'] : array();
        $replace = isset($_POST['mode']) && $_POST['mode'] === 'replace';
        if (!$selected) { $this->notice = 'Select at least one post in the preview. Nothing changed.'; return; }
        $log = array(); $skipped = 0;
        foreach (array_unique($selected) as $id) {
            if (!isset($preview['rows'][$id]) || !current_user_can('edit_post', $id)) { $skipped++; continue; }
            $row = $preview['rows'][$id]; $post = get_post($id); $choice = isset($choices[$id]) ? absint($choices[$id]) : 0;
            if (!$post || $post->post_type !== 'post' || !$choice || !term_exists($choice, 'category') || $this->cats($id) !== $row['before'] || $this->fingerprint($post) !== $row['fingerprint']) { $skipped++; continue; }
            $after = $replace ? array($choice) : array_values(array_unique(array_merge($row['before'], array($choice))));
            if ($choice !== (int)get_option('default_category')) { $after = array_values(array_diff($after, array((int)get_option('default_category')))); }
            sort($after);
            if ($after === $row['before']) { continue; }
            $result = wp_set_post_categories($id, $after, false);
            if (is_wp_error($result) || $result === false || $this->cats($id) !== $after) { $skipped++; continue; }
            $log[$id] = array('before' => $row['before'], 'after' => $after);
        }
        if ($log) {
            $history = get_user_meta(get_current_user_id(), 'scd_history', true);
            $history = is_array($history) ? $history : array();
            array_unshift($history, array('time' => current_time('mysql'), 'rows' => $log));
            update_user_meta(get_current_user_id(), 'scd_history', array_slice($history, 0, 20));
        }
        delete_transient($this->key('preview'));
        $this->notice = count($log) . ' posts updated; ' . $skipped . ' skipped (missing choice, changed post, or permission/error). Use Next batch to continue, or Start new scan to begin again.';
    }
    private function undo() {
        $history = get_user_meta(get_current_user_id(), 'scd_history', true);
        if (!$history || !is_array($history)) { $this->notice = 'No batch to undo.'; return; }
        $batch = array_shift($history); $restored = 0; $remaining = array();
        foreach ($batch['rows'] as $id => $row) {
            if (!get_post($id) || !current_user_can('edit_post', $id) || $this->cats($id) !== $row['after'] || !$this->valid_ids($row['before'])) { $remaining[$id] = $row; continue; }
            $result = wp_set_post_categories($id, $row['before'], false);
            if (is_wp_error($result) || $result === false || $this->cats($id) !== $row['before']) { $remaining[$id] = $row; continue; }
            $restored++;
        }
        if ($remaining) { $batch['rows'] = $remaining; array_unshift($history, $batch); }
        update_user_meta(get_current_user_id(), 'scd_history', $history);
        $this->notice = $restored . ' posts restored. ' . count($remaining) . ' conflicts retained for review; later category edits are never overwritten by Undo.';
    }
    private function handle() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['scd_action'])) { return; }
        check_admin_referer('scd_manage');
        $action = sanitize_key(wp_unslash($_POST['scd_action']));
        if ($action === 'auto_settings') {
            update_option('scd_auto_enabled', !empty($_POST['auto_enabled']), false);
            $this->notice = 'Automatic categorization ' . (!empty($_POST['auto_enabled']) ? 'enabled for new posts.' : 'disabled.');
        } elseif ($action === 'save') {
            if (empty($_POST['rules_complete'])) { $this->notice = 'Rules not saved: the form was truncated by your server. Increase PHP max_input_vars and try again.'; return; }
            $rules = array(); $input = isset($_POST['rules']) ? (array)wp_unslash($_POST['rules']) : array();
            foreach ($this->terms() as $term) {
                $id = (int)$term->term_id; $row = isset($input[$id]) && is_array($input[$id]) ? $input[$id] : array();
                $rules[$id] = array('enabled' => !empty($row['enabled']), 'include' => isset($row['include']) ? sanitize_textarea_field($row['include']) : '', 'exclude' => isset($row['exclude']) ? sanitize_textarea_field($row['exclude']) : '');
            }
            update_option('scd_rules', $rules, false); delete_transient($this->key('preview')); $this->notice = 'Rules saved. Scan to see their effect.';
        } elseif ($action === 'scan') { $this->scan(); }
        elseif ($action === 'apply') { $this->apply(); }
        elseif ($action === 'undo') { $this->undo(); }
    }
    private function form($action) {
        echo '<form method="post" action="' . esc_url(admin_url('tools.php?page=scd')) . '">';
        wp_nonce_field('scd_manage'); echo '<input type="hidden" name="scd_action" value="' . esc_attr($action) . '">';
    }
    public function page() {
        if (!$this->permitted()) { wp_die('You do not have permission to assign and manage categories.'); }
        $this->notice = get_transient($this->key('notice'));
        delete_transient($this->key('notice'));
        $terms = $this->terms(); $rules = $this->rules(); $labels = array();
        foreach ($terms as $term) { $labels[$term->term_id] = $this->label($term); }
        echo '<div class="wrap"><h1>Smart Category Divider</h1><p>Specific matches. Clear evidence. You control every change.</p>';
        if ($this->notice) { echo '<div class="notice notice-info"><p>' . esc_html($this->notice) . '</p></div>'; }
        echo '<p>This local, rule-based plugin uses titles, headings and visible article text. It does not use AI or force equal category sizes. Weak or competing matches require review. Your HTML is never rewritten.</p>';
        echo '<h2>Automatic categorization for new posts</h2>';
        $this->form('auto_settings');
        echo '<p><label><input type="checkbox" name="auto_enabled" value="1" ' . checked(get_option('scd_auto_enabled', false), true, false) . '> Automatically categorize newly added posts</label></p><p>When a new post has a title and article text, a clear match from your category rules is assigned on save, including drafts, scheduled posts and standard WordPress imports. Existing non-default categories are kept. Uncertain matches are marked Needs review in Posts; they are checked again on the next save. Already categorized and older posts are not automatically changed.</p>';
        submit_button('Save automatic categorization'); echo '</form><hr>';
        echo '<details><summary><strong>Category rules (advanced)</strong> — open before your first scan</summary><p>Use specific phrases separated by commas or new lines. Any phrase can match. Exclusion phrases block a category if found anywhere in the article. Disable broad categories you never want suggested. Names are initial rules only; descriptions are not automatically interpreted.</p>';
        $this->form('save');
        echo '<table class="widefat striped"><thead><tr><th>Use</th><th>Category</th><th>Matching phrases</th><th>Exclude phrases</th></tr></thead><tbody>';
        foreach ($terms as $term) {
            $id = (int)$term->term_id; $r = $rules[$id];
            echo '<tr><td><input aria-label="Enable ' . esc_attr($labels[$id]) . '" type="checkbox" name="rules[' . $id . '][enabled]" value="1" ' . checked($r['enabled'], true, false) . '></td><td><strong>' . esc_html($labels[$id]) . '</strong><br>' . esc_html(wp_strip_all_tags($term->description)) . '</td><td><textarea rows="3" class="large-text" aria-label="Match phrases for ' . esc_attr($labels[$id]) . '" name="rules[' . $id . '][include]">' . esc_textarea($r['include']) . '</textarea></td><td><textarea rows="3" class="large-text" aria-label="Exclude phrases for ' . esc_attr($labels[$id]) . '" name="rules[' . $id . '][exclude]">' . esc_textarea($r['exclude']) . '</textarea></td></tr>';
        }
        echo '</tbody></table><input type="hidden" name="rules_complete" value="1">'; submit_button('Save category rules'); echo '</form></details><hr><h2>1. Find posts to organize</h2>';
        $preview = get_transient($this->key('preview'));
        $this->form('scan');
        echo '<p><label>Which posts? <select name="scope"><option value="all">All posts</option><option value="uncategorized">Posts with only the default category</option></select></label></p><p>Click Start new scan, choose posts in the preview, then apply your choices. We remember your place automatically. Each batch checks up to 50 posts, including drafts and scheduled posts.</p>';
        submit_button('Start new scan', 'primary'); echo '</form>';
        $progress = get_transient($this->key('progress'));
        if (!$preview && $progress && !empty($progress['more'])) {
            $this->form('scan'); echo '<input type="hidden" name="continue" value="1">'; submit_button('Next batch', 'secondary'); echo '</form>';
        } elseif (!$preview && $progress) { echo '<p><strong>Scan complete.</strong> Start a new scan whenever you add posts.</p>'; }
        if ($preview) { $this->preview($preview, $labels); }
        $history = get_user_meta(get_current_user_id(), 'scd_history', true);
        echo '<hr><h2>Undo recent batches</h2><p>Stores your latest 20 changed batches. Undo restores original categories only when nobody has changed them since.</p>';
        if (is_array($history) && $history) {
            echo '<p>Latest batch: ' . esc_html($history[0]['time']) . ' · ' . count($history[0]['rows']) . ' posts.</p>';
            $this->form('undo'); submit_button('Undo latest batch', 'secondary'); echo '</form>';
        } else { echo '<p>No changes recorded for your account.</p>'; }
        echo '<hr><h2>Plugin updates</h2><p>New versions come from <a href="https://github.com/chirag99047/smart-category-divider/releases" target="_blank" rel="noopener noreferrer">GitHub releases</a>. Enable auto-updates beside Smart Category Divider on the <a href="' . esc_url(admin_url('plugins.php')) . '">Plugins page</a>. WordPress checks on its normal schedule. Automatic categorization of new posts is controlled separately above.</p></div>';
    }
    private function preview($preview, $labels) {
        $counts = array(); $review = 0;
        foreach ($preview['rows'] as $row) { $id = $row['result']['category']; if ($id) { $counts[$id] = isset($counts[$id]) ? $counts[$id] + 1 : 1; } else { $review++; } }
        echo '<h2>2. Choose posts and categories</h2><p>' . ($preview['more'] ? 'More posts may remain.' : 'End of scan reached.') . ' Preview expires in one hour.</p>';
        echo '<p><strong>Suggested distribution for this batch:</strong> ';
        foreach ($counts as $id => $count) { echo esc_html(isset($labels[$id]) ? $labels[$id] : 'Deleted category') . ': ' . (int)$count . ' &nbsp; '; }
        echo 'Needs review: ' . (int)$review . '</p>';
        if (array_sum($counts) >= 5 && max($counts) / array_sum($counts) >= 0.7) { echo '<div class="notice notice-warning inline"><p>At least 70% of matched posts point to one category. Check broad phrases and competing categories before applying. This is a warning, not a quota.</p></div>'; }
        $this->form('apply'); echo '<input type="hidden" name="token" value="' . esc_attr($preview['token']) . '">';
        echo '<p><label>Assignment mode <select name="mode"><option value="add">Add chosen category; preserve existing categories</option><option value="replace">Replace ALL existing categories with chosen category</option></select></label><br>The site default category is removed when a different category is assigned. Choose Replace for one-category classification.</p>';
        echo '<div id="scd-controls"><p><button type="button" class="button" id="scd-all">Select all</button> <button type="button" class="button" id="scd-none">Clear selection</button> <button type="button" class="button" id="scd-ready">Select suggested posts</button> <span id="scd-count" role="status" aria-live="polite">0 selected</span></p><p><label>Search this batch <input type="search" id="scd-search"></label> <button type="button" class="button" id="scd-visible">Select search results</button></p><p><label>Set category for selected posts <select id="scd-bulk"><option value="">Choose a category</option>';
        foreach ($labels as $cat => $label) { echo '<option value="' . (int)$cat . '">' . esc_html($label) . '</option>'; }
        echo '</select></label> <button type="button" class="button" id="scd-set">Set selected category</button></p><p>You can tick individual posts and choose a different category for each. Select all includes every post in this batch, including hidden search results. Search does not clear selections.</p><p id="scd-live-distribution" aria-live="polite"></p></div>';
        echo '<table id="scd-preview" class="widefat striped"><thead><tr><th>Apply</th><th>Post / current categories</th><th>Chosen category</th><th>Evidence</th></tr></thead><tbody>';
        foreach ($preview['rows'] as $id => $row) {
            $r = $row['result'];
            echo '<tr><td><input type="checkbox" aria-label="Apply to ' . esc_attr($row['title']) . '" name="selected[]" value="' . (int)$id . '"></td><td><a href="' . esc_url(get_edit_post_link($id)) . '">' . esc_html($row['title'] ?: '(Untitled)') . '</a><br><small>ID ' . (int)$id . ' · ';
            $current = array(); foreach ($row['before'] as $cat) { if (isset($labels[$cat])) { $current[] = $labels[$cat]; } }
            echo esc_html(implode(', ', $current)) . '</small></td><td><select style="max-width:260px" aria-label="Category for ' . esc_attr($row['title']) . '" name="choice[' . (int)$id . ']"><option value="0">Needs review — choose category</option>';
            foreach ($labels as $cat => $label) { echo '<option value="' . (int)$cat . '" ' . selected($r['category'], $cat, false) . '>' . esc_html($label) . '</option>'; }
            echo '</select></td><td>' . esc_html($r['reason']);
            foreach (array_slice($r['candidates'], 0, 3) as $candidate) { echo '<br><small>' . esc_html(isset($labels[$candidate['id']]) ? $labels[$candidate['id']] : 'Deleted category') . ': ' . (int)$candidate['score'] . ' points</small>'; }
            echo '</td></tr>';
        }
        echo '</tbody></table><p>Tick only the posts you approve. Scores show rule evidence, not probability. Manual dropdown choices do not train a model; save new phrases in category rules to improve future scans.</p>';
        submit_button('Apply selected assignments', 'primary', 'scd-apply'); echo '</form>';
        if ($preview['more']) {
            $this->form('scan'); echo '<input type="hidden" name="continue" value="1">'; submit_button('Next batch (discard this preview)', 'secondary'); echo '</form>';
        }
    }
}
new SCD_Plugin();
