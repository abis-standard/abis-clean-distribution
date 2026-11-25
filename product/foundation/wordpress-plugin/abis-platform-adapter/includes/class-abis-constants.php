<?php
/**
 * ABIS Product v1.0 constants.
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_Constants {
    public const SCHEMA_EXPORT = 'abis.host.slice1.export/v1';
    public const SCHEMA_BUSINESS_FACT = 'abis.host.business-fact/v1';
    public const BUSINESS_FACT_SCHEMA_REVISION = '1.0.0-manual';
    public const CANONICAL_REST_ROUTE = 'abis/v1/declaration';
    public const BUSINESS_FACT_REST_ROUTE = 'abis/v1/business-fact';
    public const WELL_KNOWN_PATH = '.well-known/abis';
    public const READINESS_DECLARATION_ONLY = true;

    public const CAPABILITY_SEARCH = 'SEARCH';
    public const CAPABILITY_PRICE_DISCOVERY = 'PRICE_DISCOVERY';

    public const SETTINGS_OPTION = 'abis_business_fact_settings';
    public const SETTINGS_SCHEMA_VERSION = '1.0.0';

    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 50;

    public const PROVENANCE_WORDPRESS_NATIVE = 'WORDPRESS_NATIVE';
    public const PROVENANCE_WOOCOMMERCE_NATIVE = 'WOOCOMMERCE_NATIVE';
    public const PROVENANCE_BUSINESS_DECLARED = 'BUSINESS_DECLARED';
    public const PROVENANCE_NOT_ESTABLISHED = 'NOT_ESTABLISHED';

    public const FIELD_MODE_AUTO = 'AUTO';
    public const FIELD_MODE_MANUAL = 'MANUAL';
    public const FIELD_MODE_NOT_PUBLISHED = 'NOT_PUBLISHED';
}