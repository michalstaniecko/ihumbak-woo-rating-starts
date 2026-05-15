<?php
/**
 * Update Service.
 *
 * Handles automatic plugin updates from GitHub releases.
 */

if (!defined('ABSPATH')) {
    exit;
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class Ihumbak_WRS_Update_Service {

    const DEFAULT_REPOSITORY_URL = 'https://github.com/michalstaniecko/ihumbak-woo-rating-starts/';
    const PLUGIN_SLUG            = 'ihumbak-woo-rating-stars';

    /**
     * Update checker instance.
     *
     * @var object|null
     */
    private $update_checker = null;

    /**
     * Whether updates are enabled.
     *
     * @return bool
     */
    public function is_enabled() {
        if (defined('IHUMBAK_WRS_DISABLE_UPDATES') && IHUMBAK_WRS_DISABLE_UPDATES) {
            return false;
        }

        if (!class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
            return false;
        }

        return (bool) apply_filters('ihumbak_wrs_updates_enabled', true);
    }

    /**
     * Initialize the update checker.
     *
     * @return void
     */
    public function init() {
        if (!$this->is_enabled()) {
            return;
        }

        $repository_url = $this->get_repository_url();
        $plugin_file    = $this->get_plugin_file();

        $this->update_checker = PucFactory::buildUpdateChecker(
            $repository_url,
            $plugin_file,
            self::PLUGIN_SLUG
        );

        // Download release assets (the ZIP attached to a GitHub release)
        // instead of the auto-generated source archive.
        $api = $this->update_checker->getVcsApi();
        if (method_exists($api, 'enableReleaseAssets')) {
            $api->enableReleaseAssets();
        }

        $token = $this->get_github_access_token();
        if (!empty($token)) {
            $this->update_checker->setAuthentication($token);
        }

        $this->update_checker->addFilter(
            'request_info_result',
            array($this, 'filter_update_info')
        );
    }

    /**
     * Force check for updates.
     *
     * @return object|null
     */
    public function check_for_updates() {
        if (!$this->update_checker) {
            return null;
        }

        return $this->update_checker->checkForUpdates();
    }

    /**
     * Repository URL.
     *
     * @return string
     */
    public function get_repository_url() {
        return apply_filters('ihumbak_wrs_update_repository_url', self::DEFAULT_REPOSITORY_URL);
    }

    /**
     * GitHub access token (for private repos / higher rate limits).
     *
     * @return string
     */
    public function get_github_access_token() {
        if (defined('IHUMBAK_WRS_GITHUB_ACCESS_TOKEN') && is_string(IHUMBAK_WRS_GITHUB_ACCESS_TOKEN)) {
            return IHUMBAK_WRS_GITHUB_ACCESS_TOKEN;
        }

        return (string) apply_filters('ihumbak_wrs_github_access_token', '');
    }

    /**
     * Main plugin file path.
     *
     * @return string
     */
    public function get_plugin_file() {
        if (defined('IHUMBAK_WRS_PLUGIN_FILE')) {
            return IHUMBAK_WRS_PLUGIN_FILE;
        }

        return dirname(__DIR__) . '/ihumbak-woo-rating-stars.php';
    }

    /**
     * Filter update info before it's used.
     *
     * @param object|null $info Update info object.
     * @return object|null
     */
    public function filter_update_info($info) {
        if (null === $info) {
            return $info;
        }

        return apply_filters('ihumbak_wrs_update_info', $info);
    }

    /**
     * Get the update checker instance.
     *
     * @return object|null
     */
    public function get_update_checker() {
        return $this->update_checker;
    }
}
