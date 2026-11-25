<?php
/**
 * Local Manual Business Fact storage.
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_Business_Fact_Settings {
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array {
        return array(
            'schemaVersion' => ABIS_Constants::SETTINGS_SCHEMA_VERSION,
            'fields'        => array(),
            'manualOfferings' => array(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(): array {
        $stored = get_option(ABIS_Constants::SETTINGS_OPTION, array());
        if (!is_array($stored)) {
            return self::defaults();
        }
        return array_merge(self::defaults(), $stored);
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function save(array $settings): bool {
        $settings['schemaVersion'] = ABIS_Constants::SETTINGS_SCHEMA_VERSION;
        return update_option(ABIS_Constants::SETTINGS_OPTION, $settings, false);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get_field(string $path): ?array {
        $settings = self::get();
        $fields = isset($settings['fields']) && is_array($settings['fields']) ? $settings['fields'] : array();
        if (!isset($fields[$path]) || !is_array($fields[$path])) {
            return null;
        }
        return $fields[$path];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function published_manual_offerings(): array {
        $settings = self::get();
        $rows = isset($settings['manualOfferings']) && is_array($settings['manualOfferings'])
            ? $settings['manualOfferings']
            : array();
        $out = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (empty($row['publish'])) {
                continue;
            }
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            if ($name === '') {
                continue;
            }
            $out[] = $row;
        }
        return $out;
    }
}