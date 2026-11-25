<?php
/**
 * Legacy envelope — delegates to Business Fact builder (v1.0).
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_Business_Fact {
    /**
     * @return array<string, mixed>
     */
    public function build(): array {
        return (new ABIS_Business_Fact_Builder())->build();
    }
}