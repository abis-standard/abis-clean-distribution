<?php
/**
 * Installation profile — no hostname guessing.
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_Installation_Profile {
    private const ALLOWED = array('production', 'synthetic', 'reference', 'sandbox');

    /**
     * @return array{profile: string, profileResolution: string}
     */
    public static function resolve(): array {
        $raw = defined('ABIS_INSTALLATION_PROFILE')
            ? strtolower(trim((string) ABIS_INSTALLATION_PROFILE))
            : 'production';

        if (!in_array($raw, self::ALLOWED, true)) {
            return array(
                'profile'            => 'production',
                'profileResolution'  => 'INVALID_DEFAULTED_TO_PRODUCTION',
            );
        }

        return array(
            'profile'           => $raw,
            'profileResolution' => 'OK',
        );
    }

    public static function is_test_material(): bool {
        return self::resolve()['profile'] !== 'production';
    }
}