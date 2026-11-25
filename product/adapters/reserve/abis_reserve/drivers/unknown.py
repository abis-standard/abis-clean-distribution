"""ADR-008 — Unknown provider: discovery/read OK, mutation DENY."""

from __future__ import annotations

from typing import Any

from abis_reserve.envelope import ProviderType
from abis_reserve.errors import ErrorClass, ReserveAdapterError


class UnknownProviderDriver:
    driver_id = "UNKNOWN_PROVIDER"

    def discover(self, context: dict[str, Any]) -> dict[str, Any]:
        return {
            "provider_id": context.get("plugin_slug", "unknown"),
            "provider_type": ProviderType.UNKNOWN.value,
            "discovered": True,
        }

    def execute(self, operation: str, envelope: dict[str, Any], context: dict[str, Any]) -> dict[str, Any]:
        if operation in ("discover", "observe", "classify"):
            return self.discover(context)
        raise ReserveAdapterError(ErrorClass.UNKNOWN_PROVIDER, "mutation denied for unknown provider")