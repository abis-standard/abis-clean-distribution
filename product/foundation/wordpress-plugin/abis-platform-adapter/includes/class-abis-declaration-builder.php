<?php
/**
 * Agent Readiness declaration (Business Fact referenced, not embedded).
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_Declaration_Builder {
    /**
     * @return array<string, mixed>
     */
    public function build_payload(): array {
        $profile = ABIS_Installation_Profile::resolve();

        return array(
            'abisSchema'                => ABIS_Constants::SCHEMA_EXPORT,
            'adapterId'                 => ABIS_ADAPTER_ID,
            'adapterVersion'            => ABIS_ADAPTER_VERSION,
            'pluginVersion'             => ABIS_PLATFORM_ADAPTER_VERSION,
            'declarationOnly'           => true,
            'discoverableSemantic'      => 'DISCOVERY_SURFACE_DECLARED',
            'canonicalSurface'          => 'rest:' . ABIS_Constants::CANONICAL_REST_ROUTE,
            'businessFactSurfaceRef'    => ABIS_Business_Fact_Surface::canonical_rest_ref(),
            'businessFactCanonicalUrl'  => ABIS_Business_Fact_Surface::canonical_url(),
            'installationProfile'       => $profile['profile'],
            'profileResolution'         => $profile['profileResolution'],
            'capabilityDeclaration'     => array(
                'declarationId' => 'abis.host.slice1.capability-declaration.v1',
                'capabilities'  => array(
                    ABIS_Constants::CAPABILITY_SEARCH,
                    ABIS_Constants::CAPABILITY_PRICE_DISCOVERY,
                ),
                'capabilityNotes' => array(
                    ABIS_Constants::CAPABILITY_SEARCH => 'Read-only discovery of Business-owned published Business Fact and catalog information.',
                    ABIS_Constants::CAPABILITY_PRICE_DISCOVERY => 'Read-only observation of publicly published price information; not quote execution; not purchase authorization.',
                ),
            ),
            'readinessFacet'            => $this->readiness_facet(),
            'adapterContract'           => $this->adapter_contract(),
            'networkPolicy'             => 'LOCAL_FIRST_NO_OUTBOUND',
            'commerceExecution'         => 'NOT_GRANTED_SLICE1',
            'testMaterial'              => ABIS_Installation_Profile::is_test_material(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readiness_facet(): array {
        return array(
            'facetId'             => 'abis.readiness.facet.wp.slice1.v1',
            'discoverable'        => true,
            'declarationOnly'     => true,
            'discoverySurfaceRef' => 'abis.ref.discovery.surface/wp-rest-readonly',
            'protocolBindings'    => array(
                array(
                    'bindingId'            => 'abis.binding.wp.rest.readonly.v1',
                    'transportClass'       => 'REST',
                    'exposedOperationRefs' => array('abis.slice1.operation.vertical-probe'),
                    'metadataSurfaceRef'   => 'abis.ref.metadata.surface/wp-json-abis-v1',
                    'authBoundaryRef'      => 'abis.ref.auth.boundary/public-readonly',
                    'businessFactSurfaceRef' => ABIS_Business_Fact_Surface::canonical_rest_ref(),
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function adapter_contract(): array {
        return array(
            'adapterId'           => ABIS_ADAPTER_ID,
            'adapterVersion'      => ABIS_ADAPTER_VERSION,
            'platformClassRef'    => 'abis.ref.platform.class/host-cms-a',
            'capabilityRefs'      => array(
                ABIS_Constants::CAPABILITY_SEARCH,
                ABIS_Constants::CAPABILITY_PRICE_DISCOVERY,
            ),
            'operationBindings'   => array(
                array(
                    'commonOperationRef' => 'abis.slice1.operation.vertical-probe',
                    'nativeOperationRef' => 'abis.native.op.host/read-site-metadata/v1',
                ),
            ),
            'protocolBindingRefs' => array('abis.binding.wp.rest.readonly.v1'),
            'readinessRef'        => 'abis.readiness.facet.wp.slice1.v1',
            'authBoundaryRef'     => 'abis.ref.auth.boundary/public-readonly',
            'businessFactSurfaceRef' => ABIS_Business_Fact_Surface::canonical_rest_ref(),
        );
    }
}