"""Outcome Interpreter — drivers do not set final claims."""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any

from abis_reserve.envelope import (
    BusinessConfirmationState,
    OutcomeCeiling,
    PersistenceObserved,
    ProviderType,
)


@dataclass
class InterpretationResult:
    observed_outcome: str
    candidate_outcome_ceiling: OutcomeCeiling
    blockers: list[str]
    uncertainty: list[str]


def interpret(
    *,
    provider_type: ProviderType,
    execution_kind: str,
    provider_ack_observed: bool,
    persistence: PersistenceObserved,
    provider_status_raw: str | None,
    business_confirmation_state: BusinessConfirmationState,
    driver_id: str,
    provider_raw: dict[str, Any] | None = None,
) -> InterpretationResult:
    blockers: list[str] = []
    uncertainty: list[str] = []
    ceiling = OutcomeCeiling.L0

    if provider_ack_observed:
        ceiling = OutcomeCeiling.L1
        observed = "PROVIDER_ACK_OBSERVED"
    else:
        observed = "NO_PROVIDER_ACK"
        blockers.append("PROVIDER_ACK_NOT_ESTABLISHED")

    if persistence == PersistenceObserved.BOOKING_RECORD_PERSISTED:
        ceiling = max_ceiling(ceiling, OutcomeCeiling.L2)
        observed = "BOOKING_RECORD_PERSISTED"
    elif persistence == PersistenceObserved.RECORD_PERSISTED:
        if provider_type == ProviderType.GENERIC_FORM:
            ceiling = OutcomeCeiling.L2
            observed = "RECORD_CREATED"
    elif persistence == PersistenceObserved.SUBMISSION_ACCEPTED:
        ceiling = max_ceiling(ceiling, OutcomeCeiling.L1)

    if provider_type == ProviderType.GENERIC_FORM:
        ceiling = min_ceiling(ceiling, OutcomeCeiling.L2)
        blockers.append("BUSINESS_CONFIRMATION_NOT_ESTABLISHED")
        uncertainty.append("generic_form_max_L2")

    # NO SILENT NORMALIZATION: raw "confirmed" does not raise ceiling
    if provider_status_raw and provider_status_raw.lower() in ("confirmed", "approved"):
        uncertainty.append(f'raw_status="{provider_status_raw}" not promoted to business confirmation')
        blockers.append("BUSINESS_CONFIRMATION_NOT_ESTABLISHED")

    if business_confirmation_state == BusinessConfirmationState.NOT_ESTABLISHED:
        blockers.append("BUSINESS_CONFIRMATION_NOT_ESTABLISHED")

    # L4/L5 never established in R4
    blockers.append("L4_NOT_ESTABLISHED")
    blockers.append("L5_NOT_ESTABLISHED")

    if driver_id == "F02_FLUENT_FORMS_FREE" and execution_kind == "FORM_SUBMISSION":
        uncertainty.append("operation_class_FORM_SUBMISSION_not_BOOKING_CREATE")

    return InterpretationResult(
        observed_outcome=observed,
        candidate_outcome_ceiling=ceiling,
        blockers=sorted(set(blockers)),
        uncertainty=uncertainty,
    )


def max_ceiling(a: OutcomeCeiling, b: OutcomeCeiling) -> OutcomeCeiling:
    order = [OutcomeCeiling.L0, OutcomeCeiling.L1, OutcomeCeiling.L2, OutcomeCeiling.L3, OutcomeCeiling.L4, OutcomeCeiling.L5]
    return order[max(order.index(a), order.index(b))]


def min_ceiling(a: OutcomeCeiling, b: OutcomeCeiling) -> OutcomeCeiling:
    order = [OutcomeCeiling.L0, OutcomeCeiling.L1, OutcomeCeiling.L2, OutcomeCeiling.L3, OutcomeCeiling.L4, OutcomeCeiling.L5]
    return order[min(order.index(a), order.index(b))]