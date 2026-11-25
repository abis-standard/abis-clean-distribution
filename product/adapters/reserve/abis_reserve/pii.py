"""PII / secret boundary for evidence artifacts."""

from __future__ import annotations

import re
from typing import Any

SECRET_KEYS = frozenset(
    {
        "password",
        "pass",
        "api_key",
        "apikey",
        "secret",
        "token",
        "nonce",
        "authorization",
        "cookie",
    }
)

ALLOWLIST_TOP_KEYS = frozenset(
    {
        "status",
        "code",
        "message",
        "error",
        "data",
        "success",
        "booking_id",
        "entry_id",
        "insert_id",
        "provider_status",
        "raw_value",
    }
)

_EMAIL = re.compile(r"[\w.+-]+@[\w-]+\.[\w.-]+")
_PHONE = re.compile(r"\b\d{10,11}\b")


def redact_value(key: str, value: Any) -> Any:
    if key.lower() in SECRET_KEYS:
        return "[REDACTED]"
    if isinstance(value, str):
        if key.lower() in ("email", "phone", "customer_name"):
            return "[REDACTED_PII]"
        if key.lower() not in ALLOWLIST_TOP_KEYS and _EMAIL.search(value):
            return "[REDACTED_EMAIL]"
    return value


def sanitize_evidence(payload: dict[str, Any], *, allow_nonce_in_driver: bool = False) -> dict[str, Any]:
    """Drop secrets from persisted evidence; optional nonce stays in runtime only when False default."""

    out: dict[str, Any] = {}
    for k, v in payload.items():
        if k.lower() in SECRET_KEYS and not (allow_nonce_in_driver and k == "nonce"):
            continue
        if isinstance(v, dict):
            out[k] = sanitize_evidence(v, allow_nonce_in_driver=allow_nonce_in_driver)
        elif isinstance(v, list):
            out[k] = [
                sanitize_evidence(x, allow_nonce_in_driver=allow_nonce_in_driver)
                if isinstance(x, dict)
                else redact_value(k, x)
                for x in v
            ]
        else:
            out[k] = redact_value(k, v)
    return out