"""Claim Guard — ASSERTED_CLAIM <= EVIDENCE-SUPPORTED CEILING."""

from __future__ import annotations

from dataclasses import dataclass

from abis_reserve.envelope import Claim, OutcomeCeiling


_CEILING_RANK = {
    OutcomeCeiling.L0: 0,
    OutcomeCeiling.L1: 1,
    OutcomeCeiling.L2: 2,
    OutcomeCeiling.L3: 3,
    OutcomeCeiling.L4: 4,
    OutcomeCeiling.L5: 5,
}

_FORBIDDEN_CLAIMS = {
    ("FORM_SUBMISSION", "RESERVATION_CONFIRMED"),
    ("ENTRY_CREATED", "BUSINESS_CONFIRMED"),
    ("AVAILABILITY_OBSERVED", "BOOKING_GUARANTEED"),
    ("MAIL_SENT", "BUSINESS_RECEIVED"),
}


@dataclass
class ClaimRequest:
    claim_type: str
    asserted_level: OutcomeCeiling
    context_tag: str = ""
    provider_status_raw: str | None = None


@dataclass
class ClaimDecision:
    allowed: bool
    claim: Claim | None
    violations: list[str]


def evaluate_claim(
    request: ClaimRequest,
    *,
    evidence_ceiling: OutcomeCeiling,
) -> ClaimDecision:
    violations: list[str] = []

    if _CEILING_RANK[request.asserted_level] > _CEILING_RANK[evidence_ceiling]:
        violations.append(
            f"asserted {request.asserted_level.value} exceeds evidence ceiling {evidence_ceiling.value}"
        )

    if request.claim_type == "BUSINESS_CONFIRMED" and evidence_ceiling.value not in ("L4", "L5"):
        violations.append("BUSINESS_CONFIRMED requires L4 evidence (not established in R4)")

    if request.provider_status_raw and request.claim_type == "BUSINESS_CONFIRMED":
        violations.append("cannot derive BUSINESS_CONFIRMED from raw provider status alone")

    key = (request.context_tag, request.claim_type)
    if key in _FORBIDDEN_CLAIMS:
        violations.append(f"forbidden claim pattern {key}")

    if request.context_tag == "FORM_SUBMISSION" and request.claim_type == "RESERVATION_CONFIRMED":
        violations.append("FORM_SUBMISSION must not claim RESERVATION_CONFIRMED")

    if violations:
        return ClaimDecision(
            False,
            Claim(
                claim_type=request.claim_type,
                maximum_assertable_level=evidence_ceiling,
                blockers=violations,
            ),
            violations,
        )

    capped = (
        request.asserted_level
        if _CEILING_RANK[request.asserted_level] <= _CEILING_RANK[evidence_ceiling]
        else evidence_ceiling
    )
    return ClaimDecision(
        True,
        Claim(
            claim_type=request.claim_type,
            maximum_assertable_level=capped,
        ),
        [],
    )