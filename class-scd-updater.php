<?php
defined('ABSPATH') || exit;
/** Uses only published stable releases and the packaged ZIP in this repository. */
class SCD_Updater {
    const REPOSITORY = 'https://github.com/chirag99047/smart-category-divider';
    const API = 'https://api.github.com/repos/chirag99047/smart-category-divider/releases/latest';
    private $file;
    public function __construct($file) {
        $this->file = plugin_basename($file);
        add_filter('update_plugins_github.com', array($this, 'check'), 10, 4);
        add_filter('plugins_api', array($this, 'info'), 10, 3);
    }
    public function release() {
        $cached = get_transient('scd_github_release');
        if ($cached !== false) { return $cached; }
        $response = wp_remote_get(self::API, array('timeout' => 15, 'limit_response_size' => 262144, 'headers' => array('Accept' => 'application/vnd.github+json')));
        $release = array();
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $release = self::parse(json_decode(wp_remote_retrieve_body($response), true));
        }
        set_transient('scd_github_release', $release, $release ? HOUR_IN_SECONDS : 300);
        return $release;
    }
    public static function parse($data) {
        if (!is_array($data) || !empty($data['draft']) || !empty($data['prerelease']) || empty($data['tag_name']) || !preg_match('/^v?(\d+\.\d+\.\d+)$/D', $data['tag_name'], $match)) { return array(); }
        $expected = self::REPOSITORY . '/releases/download/' . $data['tag_name'] . '/smart-category-divider.zip';
        foreach (isset($data['assets']) && is_array($data['assets']) ? $data['assets'] : array() as $asset) {
            if (isset($asset['name'], $asset['browser_download_url']) && $asset['name'] === 'smart-category-divider.zip' && $asset['browser_download_url'] === $expected) {
                return array('version' => $match[1], 'package' => $expected, 'url' => self::REPOSITORY . '/releases/tag/' . $data['tag_name']);
            }
        }
        return array();
    }
    public function check($update, $plugin_data, $plugin_file, $locales) {
        if ($plugin_file !== $this->file) { return $update; }
        $release = $this->release();
        if (!$release) { return $update; }
        return array_merge($release, array('id' => self::REPOSITORY, 'slug' => 'smart-category-divider', 'requires' => '6.0', 'requires_php' => '7.4'));
    }
    public function info($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'smart-category-divider') { return $result; }
        $release = $this->release();
        if (!$release) { return $result; }
        return (object) array('name' => 'Smart Category Divider', 'slug' => 'smart-category-divider', 'version' => $release['version'], 'homepage' => self::REPOSITORY, 'requires' => '6.0', 'requires_php' => '7.4', 'download_link' => $release['package'], 'sections' => array('description' => 'Review category suggestions, select posts and customize categories before applying changes.', 'changelog' => '<a href="' . esc_url($release['url']) . '">View release notes on GitHub</a>'));
    }
}
