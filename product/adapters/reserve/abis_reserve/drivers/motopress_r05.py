"""R05 MotoPress Lite — R1 observed surfaces only."""

from __future__ import annotations

import json
import re
import urllib.error
import urllib.parse
import urllib.request
from typing import Any

from abis_reserve.envelope import PersistenceObserved, ProviderType
from abis_reserve.errors import ErrorClass, ReserveAdapterError


class MotoPressR05Driver:
    driver_id = "R05_MOTOPRESS_LITE"

    @staticmethod
    def _api_urls(base: str, path: str) -> list[str]:
        base = base.rstrip("/")
        path = path.lstrip("/")
        return [
            f"{base}/index.php?rest_route=/{path}",
            f"{base}/wp-json/{path}",
        ]

    def _get_json(self, url: str) -> dict[str, Any]:
        with urllib.request.urlopen(url, timeout=15) as resp:
            return json.loads(resp.read().decode())

    def discover(self, context: dict[str, Any]) -> dict[str, Any]:
        base = context.get("base_url", "").rstrip("/")
        if not base:
            return {
                "provider_id": "motopress-appointment",
                "provider_type": ProviderType.NATIVE_RESERVATION.value,
                "discovered": False,
            }
        last_err: Exception | None = None
        try:
            for url in self._api_urls(base, "motopress/appointment/v1/settings"):
                try:
                    body = self._get_json(url)
                    break
                except (json.JSONDecodeError, urllib.error.HTTPError, urllib.error.URLError) as e:
                    last_err = e
                    body = None
            if body is None:
                raise ReserveAdapterError(ErrorClass.DISCOVERY_ERROR, str(last_err))
            return {
                "provider_id": "motopress-appointment",
                "provider_type": ProviderType.NATIVE_RESERVATION.value,
                "discovered": True,
                "raw": body,
            }
        except (urllib.error.URLError, TimeoutError, json.JSONDecodeError) as e:
            raise ReserveAdapterError(ErrorClass.DISCOVERY_ERROR, str(e)) from e

    def observe_availability(self, context: dict[str, Any], slot: dict[str, Any]) -> dict[str, Any]:
        """Published/observed availability — not a booking guarantee."""
        return {
            "observed_slots": [slot],
            "guaranteed_at_create": False,
            "limitation": "published_availability_not_guaranteed_at_create",
        }

    @staticmethod
    def _fetch_mpa_create_nonce(base: str, booking_page: str) -> str:
        html = urllib.request.urlopen(f"{base.rstrip('/')}{booking_page}", timeout=15).read().decode()
        decoded = urllib.parse.unquote(html)
        m = re.search(r'"mpa_create_booking":"([a-f0-9]+)"', decoded)
        if not m:
            raise ReserveAdapterError(ErrorClass.AUTHENTICATION_REQUIRED, "mpa_create_booking nonce not found")
        return m.group(1)

    @staticmethod
    def build_r1_order(payload: dict[str, Any], context: dict[str, Any]) -> dict[str, Any]:
        """Shape per R1 LAB-R05 trace + BookingService.createReservation requireFields."""
        item = {
            "service_id": int(payload.get("service_id", context.get("service_id", 7))),
            "date": str(payload.get("date", context.get("date", "2030-06-01"))),
            "time": str(payload.get("time", context.get("time", "23:00 - 23:30"))),
            "employee_id": int(payload.get("employee_id", context.get("employee_id", 5))),
            "location_id": int(payload.get("location_id", context.get("location_id", 4))),
        }
        customer = payload.get("customer")
        if not isinstance(customer, dict):
            customer = {
                "name": str(payload.get("customer_name", "Synthetic Gate")),
                "email": str(payload.get("email", "synthetic-gate-r05@abis.invalid")),
                "phone": str(payload.get("phone", "")),
            }
        return {"items": [item], "customer": customer}

    def create_booking(self, context: dict[str, Any], payload: dict[str, Any]) -> dict[str, Any]:
        base = context.get("base_url", "").rstrip("/")
        if context.get("transport_mode") == "mock":
            return self._mock_create(payload)
        order = self.build_r1_order(payload, context)
        nonce = context.get("nonce") or self._fetch_mpa_create_nonce(
            base, context.get("booking_page", "/?page_id=8")
        )
        order["nonce"] = str(nonce)
        data = json.dumps(order).encode()
        headers = {"Content-Type": "application/json"}
        last_raw: dict[str, Any] | None = None
        last_code: int | None = None
        # R1 live lab uses plain permalinks; wp-json POST may return HTML on this install.
        booking_urls = [f"{base}/index.php?rest_route=/motopress/appointment/v1/bookings"]
        for url in booking_urls:
            req = urllib.request.Request(url, data=data, headers=headers, method="POST")
            try:
                with urllib.request.urlopen(req, timeout=15) as resp:
                    text = resp.read().decode().strip()
                    try:
                        raw = json.loads(text) if text else {"message": "empty_response"}
                    except json.JSONDecodeError:
                        raw = {"message": text[:500], "non_json": True}
                return self._interpret_create_response(raw)
            except urllib.error.HTTPError as e:
                body = e.read().decode()
                try:
                    last_raw = json.loads(body)
                except json.JSONDecodeError:
                    last_raw = {"message": body, "code": e.code}
                last_code = e.code
        if last_raw:
            return self._interpret_create_response(last_raw, http_code=last_code)
        raise ReserveAdapterError(ErrorClass.TRANSPORT_FAILED, "booking request failed")

    def _interpret_create_response(self, raw: dict[str, Any], http_code: int | None = None) -> dict[str, Any]:
        code = str(raw.get("code", raw.get("error", "")))
        msg = str(raw.get("message", ""))
        if (
            code in ("mpa_slot_unavailable", "SLOT_CONFLICT", "slot_unavailable")
            or "already booked" in msg.lower()
        ):
            return {
                "result_class": "SLOT_CONFLICT_REJECTED",
                "idempotent": False,
                "provider_ack": False,
                "persistence": PersistenceObserved.NONE_OBSERVED.value,
                "raw": raw,
                "http_code": http_code,
            }
        booking_id = raw.get("id") or raw.get("booking_id") or (raw.get("data") or {}).get("id")
        if booking_id:
            status_raw = str(raw.get("status")) if raw.get("status") is not None else None
            return {
                "result_class": "BOOKING_CREATED",
                "provider_ack": True,
                "persistence": PersistenceObserved.BOOKING_RECORD_PERSISTED.value,
                "provider_record_id": str(booking_id),
                "provider_status_raw": status_raw,
                "raw": raw,
                "business_confirmation": "NOT_ESTABLISHED",
            }
        return {
            "result_class": "PROVIDER_REJECTED",
            "provider_ack": False,
            "persistence": PersistenceObserved.NONE_OBSERVED.value,
            "raw": raw,
            "http_code": http_code,
        }

    def _mock_create(self, payload: dict[str, Any]) -> dict[str, Any]:
        if payload.get("force_conflict"):
            return {
                "result_class": "SLOT_CONFLICT_REJECTED",
                "idempotent": False,
                "provider_ack": False,
                "persistence": PersistenceObserved.NONE_OBSERVED.value,
                "raw": {"code": "mpa_slot_unavailable"},
            }
        return {
            "result_class": "BOOKING_CREATED",
            "provider_ack": True,
            "persistence": PersistenceObserved.BOOKING_RECORD_PERSISTED.value,
            "provider_record_id": "mock-42",
            "provider_status_raw": "pending",
            "raw": {"id": 42, "status": "pending"},
            "business_confirmation": "NOT_ESTABLISHED",
        }

    def execute(self, operation: str, envelope: dict[str, Any], context: dict[str, Any]) -> dict[str, Any]:
        if operation == "createBooking":
            return self.create_booking(context, envelope.get("execution_request", {}))
        if operation == "discover":
            return self.discover(context)
        raise ReserveAdapterError(ErrorClass.UNSUPPORTED_OPERATION, operation)