from __future__ import annotations

from abc import ABC, abstractmethod
from typing import Any


class BaseDriver(ABC):
    driver_id: str

    @abstractmethod
    def discover(self, context: dict[str, Any]) -> dict[str, Any]: ...

    def normalize_provider_status(self, raw: str) -> dict[str, Any]:
        """Preserve raw; never set business_confirmation."""
        return {"raw_value": raw, "business_confirmation": "NOT_ESTABLISHED"}