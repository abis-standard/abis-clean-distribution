"""Execution Router — no provider semantics inference."""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any, Protocol

from abis_reserve.auth_gate import AuthorizationResult, GateAction
from abis_reserve.envelope import ExecutionKind, MatchConfidence
from abis_reserve.errors import ErrorClass, ReserveAdapterError
from abis_reserve.registry import DriverMatchResult, DriverRecord, require_capability


class DriverExecutor(Protocol):
    driver_id: str

    def execute(
        self,
        operation: str,
        envelope: dict[str, Any],
        context: dict[str, Any],
    ) -> dict[str, Any]: ...


# R4 operation → capability mapping (router only; drivers implement)
_OPERATION_MAP: dict[tuple[str, ExecutionKind], str] = {
    ("R05_MOTOPRESS_LITE", ExecutionKind.BOOKING_CREATE): "createBooking",
    ("F02_FLUENT_FORMS_FREE", ExecutionKind.FORM_SUBMISSION): "submitForm",
}


@dataclass
class RouteRequest:
    execution_kind: ExecutionKind
    requested_capability: str
    driver_match: DriverMatchResult
    authorization: AuthorizationResult


@dataclass
class RouteDecision:
    resolved_capability: str | None
    driver_id: str | None
    allowed: bool
    error_class: ErrorClass | None = None
    message: str = ""


def route(request: RouteRequest) -> RouteDecision:
    dm = request.driver_match
    if dm.driver is None:
        return RouteDecision(
            None,
            None,
            False,
            ErrorClass.UNKNOWN_PROVIDER,
            dm.reason,
        )

    if request.execution_kind == ExecutionKind.BOOKING_CREATE and dm.driver.provider_type.value == "GENERIC_FORM":
        return RouteDecision(
            None,
            dm.driver.driver_id,
            False,
            ErrorClass.UNSUPPORTED_OPERATION,
            "GENERIC_FORM cannot route BOOKING_CREATE",
        )

    if dm.match_confidence in (MatchConfidence.HEURISTIC, MatchConfidence.UNKNOWN):
        return RouteDecision(
            None,
            dm.driver.driver_id,
            False,
            ErrorClass.UNSUPPORTED_OPERATION,
            "version match too weak for mutation",
        )

    if not dm.mutation_allowed and request.execution_kind in (
        ExecutionKind.BOOKING_CREATE,
        ExecutionKind.FORM_SUBMISSION,
        ExecutionKind.BOOKING_MODIFY,
        ExecutionKind.BOOKING_CANCEL,
    ):
        return RouteDecision(
            None,
            dm.driver.driver_id,
            False,
            ErrorClass.UNSUPPORTED_OPERATION,
            "mutation denied by version match policy",
        )

    if request.authorization.gate_action != GateAction.ALLOW_MUTATION:
        return RouteDecision(
            None,
            dm.driver.driver_id,
            False,
            ErrorClass.AUTHORIZATION_REQUIRED,
            "; ".join(request.authorization.reasons),
        )

    key = (dm.driver.driver_id, request.execution_kind)
    resolved = _OPERATION_MAP.get(key)
    if not resolved:
        return RouteDecision(
            None,
            dm.driver.driver_id,
            False,
            ErrorClass.UNSUPPORTED_OPERATION,
            f"no route for {request.execution_kind.value}",
        )

    try:
        require_capability(dm.driver, resolved)
    except ReserveAdapterError as e:
        return RouteDecision(
            None,
            dm.driver.driver_id,
            False,
            e.error_class,
            str(e),
        )

    if request.requested_capability and request.requested_capability != resolved:
        return RouteDecision(
            resolved,
            dm.driver.driver_id,
            False,
            ErrorClass.UNSUPPORTED_OPERATION,
            "requested_capability mismatch",
        )

    return RouteDecision(resolved, dm.driver.driver_id, True, None, "routed")