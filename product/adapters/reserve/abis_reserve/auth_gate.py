"""Authorization Gate — fail-closed; mutation requires explicit allows."""

from __future__ import annotations

from dataclasses import dataclass
from enum import Enum

from abis_reserve.envelope import AuthorizationSnapshot, ExecutionKind


class GateAction(str, Enum):
    ALLOW_READ = "ALLOW_READ"
    ALLOW_MUTATION = "ALLOW_MUTATION"
    DENY = "DENY"


@dataclass
class AuthorizationRequest:
    requested_action: str
    execution_kind: ExecutionKind | None = None
    human_authorization: str = "NOT_ESTABLISHED"
    business_authorization: str = "NOT_ESTABLISHED"
    provider_authorization: str = "NOT_ESTABLISHED"
    abis_authorization: str = "NOT_ESTABLISHED"


@dataclass
class AuthorizationResult:
    gate_action: GateAction
    snapshot: AuthorizationSnapshot
    reasons: list[str]


MUTATION_DEFAULT = "DENY"

# R4 minimal: human + abis for any mutation; provider nonce for native booking create
_MUTATION_REQUIRES = {
    ExecutionKind.BOOKING_CREATE: {"human_authorization", "abis_authorization", "provider_authorization"},
    ExecutionKind.FORM_SUBMISSION: {"human_authorization", "abis_authorization"},
    ExecutionKind.RESERVATION_REQUEST: {"human_authorization"},
}


def evaluate(request: AuthorizationRequest) -> AuthorizationResult:
    snap = AuthorizationSnapshot(
        human_authorization=request.human_authorization,
        business_authorization=request.business_authorization,
        provider_authorization=request.provider_authorization,
        abis_authorization=request.abis_authorization,
        gate_decision="DENY",
    )
    reasons: list[str] = []

    if request.requested_action in ("discover", "observe", "read_evidence"):
        snap.gate_decision = "ALLOW_READ"
        return AuthorizationResult(GateAction.ALLOW_READ, snap, ["read-only path"])

    if request.requested_action == "mutate":
        kind = request.execution_kind
        if kind is None:
            reasons.append("execution_kind required for mutation")
            return AuthorizationResult(GateAction.DENY, snap, reasons)

        required = _MUTATION_REQUIRES.get(kind, set())
        fields = {
            "human_authorization": request.human_authorization,
            "business_authorization": request.business_authorization,
            "provider_authorization": request.provider_authorization,
            "abis_authorization": request.abis_authorization,
        }
        for key in required:
            val = fields.get(key, "NOT_ESTABLISHED")
            if val != "ESTABLISHED":
                reasons.append(f"{key} is {val}, mutation denied")
                return AuthorizationResult(GateAction.DENY, snap, reasons)

        snap.gate_decision = "ALLOW_MUTATION"
        return AuthorizationResult(GateAction.ALLOW_MUTATION, snap, ["mutation authorized"])

    reasons.append(f"unsupported requested_action: {request.requested_action}")
    return AuthorizationResult(GateAction.DENY, snap, reasons)