"""ADR-006 — AUTO_RETRY_MUTATION: DENY."""

AUTO_RETRY_MUTATION = False
AUTO_RETRY_BUSINESS_OPERATION = False
TRANSPORT_RETRY_ALLOWED = True  # idempotent GET only at transport layer; not used for ambiguous POST in R4


def may_retry_mutation(*, ambiguous_outcome: bool, is_transport: bool) -> bool:
    if ambiguous_outcome:
        return False
    if not is_transport:
        return False
    return False  # R4: deny all mutation retry paths