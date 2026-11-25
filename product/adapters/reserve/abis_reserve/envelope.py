"""Common Envelope — R2/R3 mapped product types."""

from __future__ import annotations

from dataclasses import dataclass, field
from enum import Enum
from typing import Any


class ProviderType(str, Enum):
    NATIVE_RESERVATION = "NATIVE_RESERVATION"
    GENERIC_FORM = "GENERIC_FORM"
    UNKNOWN = "UNKNOWN"


class MatchConfidence(str, Enum):
    EXACT = "EXACT"
    SUPPORTED_RANGE = "SUPPORTED_RANGE"
    PARTIAL = "PARTIAL"
    HEURISTIC = "HEURISTIC"
    UNKNOWN = "UNKNOWN"


class PersistenceObserved(str, Enum):
    NONE_OBSERVED = "NONE_OBSERVED"
    SUBMISSION_ACCEPTED = "SUBMISSION_ACCEPTED"
    RECORD_PERSISTED = "RECORD_PERSISTED"
    BOOKING_RECORD_PERSISTED = "BOOKING_RECORD_PERSISTED"
    UNKNOWN = "UNKNOWN"


class BusinessConfirmationState(str, Enum):
    NOT_ESTABLISHED = "NOT_ESTABLISHED"
    OBSERVED = "OBSERVED"


class OutcomeCeiling(str, Enum):
    L0 = "L0"
    L1 = "L1"
    L2 = "L2"
    L3 = "L3"
    L4 = "L4"
    L5 = "L5"


class ExecutionKind(str, Enum):
    FORM_SUBMISSION = "FORM_SUBMISSION"
    RESERVATION_REQUEST = "RESERVATION_REQUEST"
    BOOKING_CREATE = "BOOKING_CREATE"
    BOOKING_MODIFY = "BOOKING_MODIFY"
    BOOKING_CANCEL = "BOOKING_CANCEL"


@dataclass
class ProviderIdentity:
    provider_id: str
    provider_type: ProviderType
    edition: str = ""
    provider_version: str = ""
    platform_version: str = ""
    runtime_configuration: dict[str, Any] = field(default_factory=dict)


@dataclass
class AuthorizationSnapshot:
    human_authorization: str = "NOT_ESTABLISHED"
    business_authorization: str = "NOT_ESTABLISHED"
    provider_authorization: str = "NOT_ESTABLISHED"
    abis_authorization: str = "NOT_ESTABLISHED"
    gate_decision: str = "DENY"


@dataclass
class EvidenceRef:
    source_class: str
    provider: str
    interface_classification: str = "OBSERVED_RUNTIME"
    timestamp: str = ""
    integrity_reference: str = ""
    interpretation: str = ""
    limitations: list[str] = field(default_factory=list)
    redacted: bool = True


@dataclass
class ProviderStatus:
    raw_value: str
    provider_id: str
    observed_at: str = ""
    interpreted_class: str | None = None


@dataclass
class ProviderAck:
    observed: bool = False
    ack_types: list[str] = field(default_factory=list)
    raw_status: str | None = None
    record_id: str | None = None


@dataclass
class BusinessConfirmation:
    state: BusinessConfirmationState = BusinessConfirmationState.NOT_ESTABLISHED
    source: str | None = None
    observed_at: str | None = None


@dataclass
class OperationIdentity:
    operation_id: str = ""
    intent_id: str = ""
    attempt_id: str = ""
    correlation_id: str = ""
    provider_operation_id: str | None = None


@dataclass
class Claim:
    claim_type: str
    maximum_assertable_level: OutcomeCeiling
    blockers: list[str] = field(default_factory=list)
    uncertainty: list[str] = field(default_factory=list)


@dataclass
class CommonEnvelope:
    """Normalized envelope; provider raw payloads live in driver_payload / raw_evidence."""

    provider_identity: ProviderIdentity
    capability: str = ""
    business_semantics: str = ""
    reservation_intent: dict[str, Any] = field(default_factory=dict)
    authorization: AuthorizationSnapshot = field(default_factory=AuthorizationSnapshot)
    availability: list[dict[str, Any]] = field(default_factory=list)
    execution_request: dict[str, Any] = field(default_factory=dict)
    execution_result: dict[str, Any] = field(default_factory=dict)
    provider_ack: ProviderAck | None = None
    provider_status: ProviderStatus | None = None
    persistence: PersistenceObserved = PersistenceObserved.UNKNOWN
    business_confirmation: BusinessConfirmation = field(default_factory=BusinessConfirmation)
    evidence: list[EvidenceRef] = field(default_factory=list)
    outcome_ceiling: OutcomeCeiling = OutcomeCeiling.L0
    claims: list[Claim] = field(default_factory=list)
    operation_identity: OperationIdentity = field(default_factory=OperationIdentity)
    driver_payload: dict[str, Any] = field(default_factory=dict)
    raw_evidence: dict[str, Any] = field(default_factory=dict)