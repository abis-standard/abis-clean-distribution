"""Evidence Collector — raw vs interpreted separation."""

from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime, timezone
from typing import Any

from abis_reserve.envelope import EvidenceRef
from abis_reserve.pii import sanitize_evidence


@dataclass
class EvidenceBundle:
    provider_identity: dict[str, Any]
    driver_identity: dict[str, Any]
    runtime_version: dict[str, Any]
    interface_classification: str
    timestamp: str
    operation_identity: dict[str, Any]
    authorization_decision: dict[str, Any]
    raw_provider_response: dict[str, Any] = field(default_factory=dict)
    provider_record_id: str | None = None
    raw_provider_status: str | None = None
    persistence_observation: str = "UNKNOWN"
    limitations: list[str] = field(default_factory=list)
    interpretation: dict[str, Any] = field(default_factory=dict)


def collect(
    *,
    provider_identity: dict[str, Any],
    driver_id: str,
    operation_identity: dict[str, Any],
    authorization_decision: dict[str, Any],
    raw_provider_response: dict[str, Any],
    provider_record_id: str | None = None,
    raw_provider_status: str | None = None,
    persistence_observation: str = "UNKNOWN",
    limitations: list[str] | None = None,
    runtime_version: dict[str, Any] | None = None,
    interface_classification: str = "OBSERVED_RUNTIME",
) -> EvidenceBundle:
    safe_raw = sanitize_evidence(raw_provider_response)
    ts = datetime.now(timezone.utc).isoformat()
    return EvidenceBundle(
        provider_identity=dict(provider_identity),
        driver_identity={"driver_id": driver_id},
        runtime_version=runtime_version or {},
        interface_classification=interface_classification,
        timestamp=ts,
        operation_identity=dict(operation_identity),
        authorization_decision=dict(authorization_decision),
        raw_provider_response=safe_raw,
        provider_record_id=provider_record_id,
        raw_provider_status=raw_provider_status,
        persistence_observation=persistence_observation,
        limitations=list(limitations or []),
        interpretation={},
    )


def to_evidence_refs(bundle: EvidenceBundle) -> list[EvidenceRef]:
    return [
        EvidenceRef(
            source_class="PROVIDER_RAW_RESPONSE",
            provider=bundle.provider_identity.get("provider_id", "UNKNOWN"),
            interface_classification=bundle.interface_classification,
            timestamp=bundle.timestamp,
            integrity_reference="",
            interpretation="",
            limitations=list(bundle.limitations),
            redacted=True,
        )
    ]