<?php
/**
 * ABIS → Business Fact settings (manage_options).
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_Admin_Business_Fact_Page {
    public function register_hooks(): void {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_post_abis_save_business_fact', array($this, 'handle_save'));
    }

    public function register_menu(): void {
        add_menu_page(
            __('ABIS Business Fact', 'abis-platform-adapter'),
            __('ABIS', 'abis-platform-adapter'),
            'manage_options',
            'abis-business-fact',
            array($this, 'render_page'),
            'dashicons-building',
            81
        );
        add_submenu_page(
            'abis-business-fact',
            __('Business Fact', 'abis-platform-adapter'),
            __('Business Fact', 'abis-platform-adapter'),
            'manage_options',
            'abis-business-fact',
            array($this, 'render_page')
        );
    }

    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'abis-platform-adapter'));
        }
        $native = (new ABIS_WordPress_Fact_Collector())->collect_native();
        $settings = ABIS_Business_Fact_Settings::get();
        $saved = isset($_GET['abis_saved']) && $_GET['abis_saved'] === '1';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('ABIS Business Fact', 'abis-platform-adapter'); ?></h1>
            <p><strong><?php echo esc_html__('Privacy notice:', 'abis-platform-adapter'); ?></strong>
                <?php echo esc_html__('Saved fields marked for publication may be read anonymously by external AI Agents as Business Facts. Never use WordPress admin email here.', 'abis-platform-adapter'); ?></p>
            <?php if ($saved) : ?>
                <div class="notice notice-success"><p><?php echo esc_html__('Business Fact settings saved.', 'abis-platform-adapter'); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('abis_bf_settings', 'abis_bf_nonce'); ?>
                <input type="hidden" name="action" value="abis_save_business_fact" />
                <h2><?php echo esc_html__('Identity', 'abis-platform-adapter'); ?></h2>
                <?php $this->field_row('businessIdentity.displayName', __('Business Display Name', 'abis-platform-adapter'), $native['businessIdentity']['displayName'] ?? '', $settings); ?>
                <?php $this->field_row('businessIdentity.description', __('Business Description', 'abis-platform-adapter'), (string) ($native['businessIdentity']['description'] ?? ''), $settings); ?>
                <?php $this->field_row('businessIdentity.businessType', __('Business Type', 'abis-platform-adapter'), '', $settings, true); ?>
                <h2><?php echo esc_html__('Contact (manual only)', 'abis-platform-adapter'); ?></h2>
                <?php $this->field_row('contact.publicPhone', __('Public Phone', 'abis-platform-adapter'), '', $settings, true); ?>
                <?php $this->field_row('contact.publicEmail', __('Public Email', 'abis-platform-adapter'), '', $settings, true); ?>
                <?php $this->field_row('contact.contactUrl', __('Contact URL', 'abis-platform-adapter'), '', $settings, true); ?>
                <h2><?php echo esc_html__('Location (manual only)', 'abis-platform-adapter'); ?></h2>
                <?php $this->field_row('location.streetAddress', __('Street Address', 'abis-platform-adapter'), '', $settings, true); ?>
                <?php $this->field_row('location.locality', __('Locality', 'abis-platform-adapter'), '', $settings, true); ?>
                <?php $this->field_row('location.region', __('Region', 'abis-platform-adapter'), '', $settings, true); ?>
                <?php $this->field_row('location.postalCode', __('Postal Code', 'abis-platform-adapter'), '', $settings, true); ?>
                <?php $this->field_row('location.country', __('Country', 'abis-platform-adapter'), '', $settings, true); ?>
                <?php $this->field_row('location.locationUrl', __('Public Location URL', 'abis-platform-adapter'), '', $settings, true); ?>
                <h2><?php echo esc_html__('Operations (manual only)', 'abis-platform-adapter'); ?></h2>
                <?php $this->field_row('operations.openingHours', __('Opening Hours', 'abis-platform-adapter'), '', $settings, true); ?>
                <?php $this->field_row('operations.specialHoursNote', __('Holiday / Special Hours', 'abis-platform-adapter'), '', $settings, true); ?>
                <?php $this->field_row('operations.serviceArea', __('Service Area', 'abis-platform-adapter'), '', $settings, true); ?>
                <h2><?php echo esc_html__('Manual service offerings (non-WooCommerce)', 'abis-platform-adapter'); ?></h2>
                <?php
                $offer = isset($settings['manualOfferings'][0]) && is_array($settings['manualOfferings'][0])
                    ? $settings['manualOfferings'][0]
                    : array();
                ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Service name', 'abis-platform-adapter'); ?></th>
                        <td><input type="text" name="manualOfferings[0][name]" class="regular-text" value="<?php echo esc_attr((string) ($offer['name'] ?? '')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Short description', 'abis-platform-adapter'); ?></th>
                        <td><input type="text" name="manualOfferings[0][shortDescription]" class="regular-text" value="<?php echo esc_attr((string) ($offer['shortDescription'] ?? '')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Public URL', 'abis-platform-adapter'); ?></th>
                        <td><input type="url" name="manualOfferings[0][publicUrl]" class="regular-text" value="<?php echo esc_attr((string) ($offer['publicUrl'] ?? '')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Category / type', 'abis-platform-adapter'); ?></th>
                        <td><input type="text" name="manualOfferings[0][categoryOrType]" class="regular-text" value="<?php echo esc_attr((string) ($offer['categoryOrType'] ?? '')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Publish', 'abis-platform-adapter'); ?></th>
                        <td><label><input type="checkbox" name="manualOfferings[0][publish]" value="1" <?php checked(!empty($offer['publish'])); ?> /> <?php echo esc_html__('Publish to Agents', 'abis-platform-adapter'); ?></label></td>
                    </tr>
                </table>
                <?php submit_button(__('Save Business Facts', 'abis-platform-adapter')); ?>
            </form>
            <p><?php echo esc_html__('Auto-detected canonical URL and homepage remain WordPress-native (not manually overridden in v1.0).', 'abis-platform-adapter'); ?></p>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function field_row(string $path, string $label, string $auto, array $settings, bool $manual_only = false): void {
        $row = isset($settings['fields'][$path]) && is_array($settings['fields'][$path]) ? $settings['fields'][$path] : array();
        $mode = $row['mode'] ?? ABIS_Constants::FIELD_MODE_AUTO;
        $value = $row['value'] ?? '';
        $publish = !empty($row['publish']);
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="<?php echo esc_attr($path); ?>"><?php echo esc_html($label); ?></label></th>
                <td>
                    <p class="description"><?php
                    /* translators: %s: auto-detected value for this field, or an em dash when none is available. */
                    echo esc_html(sprintf(__('Auto-detected: %s', 'abis-platform-adapter'), $auto !== '' ? $auto : '—')); ?></p>
                    <input name="fields[<?php echo esc_attr($path); ?>][value]" id="<?php echo esc_attr($path); ?>" type="text" class="regular-text" value="<?php echo esc_attr((string) $value); ?>" />
                    <p>
                        <label><input type="radio" name="fields[<?php echo esc_attr($path); ?>][mode]" value="<?php echo esc_attr(ABIS_Constants::FIELD_MODE_AUTO); ?>" <?php checked($mode, ABIS_Constants::FIELD_MODE_AUTO); ?> /> <?php echo esc_html__('Use auto', 'abis-platform-adapter'); ?></label>
                        <label><input type="radio" name="fields[<?php echo esc_attr($path); ?>][mode]" value="<?php echo esc_attr(ABIS_Constants::FIELD_MODE_MANUAL); ?>" <?php checked($mode, ABIS_Constants::FIELD_MODE_MANUAL); ?> /> <?php echo esc_html__('Manual override', 'abis-platform-adapter'); ?></label>
                        <label><input type="radio" name="fields[<?php echo esc_attr($path); ?>][mode]" value="<?php echo esc_attr(ABIS_Constants::FIELD_MODE_NOT_PUBLISHED); ?>" <?php checked($mode, ABIS_Constants::FIELD_MODE_NOT_PUBLISHED); ?> /> <?php echo esc_html__('Not published', 'abis-platform-adapter'); ?></label>
                    </p>
                    <label><input type="checkbox" name="fields[<?php echo esc_attr($path); ?>][publish]" value="1" <?php checked($publish); ?> /> <?php echo esc_html__('Publish to Agents', 'abis-platform-adapter'); ?></label>
                    <?php if ($manual_only) : ?>
                        <p class="description"><?php echo esc_html__('Manual-only field in v1.0.', 'abis-platform-adapter'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    public function handle_save(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'abis-platform-adapter'));
        }
        check_admin_referer('abis_bf_settings', 'abis_bf_nonce');
        $input = array(
            'fields' => isset($_POST['fields']) && is_array($_POST['fields']) ? wp_unslash($_POST['fields']) : array(),
            'manualOfferings' => isset($_POST['manualOfferings']) && is_array($_POST['manualOfferings']) ? wp_unslash($_POST['manualOfferings']) : array(),
        );
        $sanitized = ABIS_Business_Fact_Sanitizer::sanitize_settings($input);
        ABIS_Business_Fact_Settings::save($sanitized);
        wp_safe_redirect(admin_url('admin.php?page=abis-business-fact&abis_saved=1'));
        exit;
    }
}