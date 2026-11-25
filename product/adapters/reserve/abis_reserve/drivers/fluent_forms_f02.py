"""F02 Fluent Forms Free — FORM_SUBMISSION only."""

from __future__ import annotations

import json
import re
import urllib.error
import urllib.parse
import urllib.request
from typing import Any

from abis_reserve.envelope import ExecutionKind, PersistenceObserved, ProviderType
from abis_reserve.errors import ErrorClass, ReserveAdapterError


class FluentFormsF02Driver:
    driver_id = "F02_FLUENT_FORMS_FREE"

    def discover(self, context: dict[str, Any]) -> dict[str, Any]:
        return {
            "provider_id": "fluentform",
            "provider_type": ProviderType.GENERIC_FORM.value,
            "form_id": context.get("form_id"),
            "operation_class": ExecutionKind.FORM_SUBMISSION.value,
            "reservation_semantics": False,
        }

    def classify_form_semantics(self, _context: dict[str, Any]) -> dict[str, Any]:
        return {
            "semantic_class": "GENERIC_LEAD_CAPTURE",
            "booking_create": False,
        }

    def submit_form(self, context: dict[str, Any], fields: dict[str, Any]) -> dict[str, Any]:
        if context.get("transport_mode") == "mock":
            return self._mock_submit(fields)
        base = context.get("base_url", "").rstrip("/")
        form_id = context.get("form_id")
        if not base or not form_id:
            raise ReserveAdapterError(ErrorClass.VALIDATION_FAILED, "base_url and form_id required")
        form_nonce = context.get("form_nonce")
        if not form_nonce:
            page_path = context.get("form_page", "/?page_id=4")
            try:
                html = urllib.request.urlopen(f"{base}{page_path}", timeout=15).read().decode()
                m = re.search(rf'name="_fluentform_{form_id}_fluentformnonce"\s+value="([^"]+)"', html)
                if m:
                    form_nonce = m.group(1)
            except urllib.error.URLError:
                pass

        # R1 SubmissionHandler: parse_str(request.get('data')) — urlencoded field string, not JSON.
        inner_fields = {k: v for k, v in fields.items() if not str(k).startswith("_")}
        inner_fields.setdefault("__fluent_form_embded_post_id", str(context.get("embedded_post_id", 4)))
        inner_fields.setdefault("_wp_http_referer", context.get("form_page", "/?page_id=4"))
        data_payload = urllib.parse.urlencode(inner_fields)
        post_fields: dict[str, Any] = {
            "action": "fluentform_submit",
            "form_id": str(form_id),
            "data": data_payload,
        }
        if form_nonce:
            post_fields[f"_fluentform_{form_id}_fluentformnonce"] = form_nonce

        data = urllib.parse.urlencode(post_fields).encode()
        req = urllib.request.Request(
            f"{base}/wp-admin/admin-ajax.php",
            data=data,
            method="POST",
            headers={"Content-Type": "application/x-www-form-urlencoded"},
        )
        try:
            with urllib.request.urlopen(req, timeout=15) as resp:
                raw_text = resp.read().decode()
            try:
                raw = json.loads(raw_text)
            except json.JSONDecodeError:
                raw = {"body": raw_text}
            return self._interpret_submit(raw)
        except urllib.error.HTTPError as e:
            body = e.read().decode()
            try:
                raw = json.loads(body)
            except json.JSONDecodeError:
                raw = {"http_code": e.code, "body": body[:500]}
            raw["http_code"] = e.code
            if e.code in (423, 403, 400):
                return {
                    "result_class": "FORM_SUBMISSION",
                    "operation_class": ExecutionKind.FORM_SUBMISSION.value,
                    "provider_ack": False,
                    "persistence": PersistenceObserved.NONE_OBSERVED.value,
                    "provider_record_id": None,
                    "raw": raw,
                    "business_confirmation": "NOT_ESTABLISHED",
                    "max_outcome_ceiling": "L2",
                }
            raise ReserveAdapterError(ErrorClass.TRANSPORT_FAILED, str(e)) from e
        except urllib.error.URLError as e:
            raise ReserveAdapterError(ErrorClass.TRANSPORT_FAILED, str(e)) from e

    def _interpret_submit(self, raw: dict[str, Any]) -> dict[str, Any]:
        success = raw.get("success") in (True, "1", 1) or raw.get("status") == "success"
        data_obj = raw.get("data") if isinstance(raw.get("data"), dict) else {}
        entry_id = (
            raw.get("insert_id")
            or raw.get("entry_id")
            or data_obj.get("insert_id")
            or (data_obj.get("result") or {}).get("insert_id")
        )
        persistence = (
            PersistenceObserved.RECORD_PERSISTED.value
            if entry_id
            else PersistenceObserved.SUBMISSION_ACCEPTED.value if success else PersistenceObserved.NONE_OBSERVED.value
        )
        return {
            "result_class": "FORM_SUBMISSION",
            "operation_class": ExecutionKind.FORM_SUBMISSION.value,
            "provider_ack": bool(success),
            "persistence": persistence,
            "provider_record_id": str(entry_id) if entry_id else None,
            "raw": raw,
            "business_confirmation": "NOT_ESTABLISHED",
            "max_outcome_ceiling": "L2",
        }

    def _mock_submit(self, fields: dict[str, Any]) -> dict[str, Any]:
        if fields.get("force_no_persist"):
            return {
                "result_class": "FORM_SUBMISSION",
                "operation_class": ExecutionKind.FORM_SUBMISSION.value,
                "provider_ack": True,
                "persistence": PersistenceObserved.SUBMISSION_ACCEPTED.value,
                "provider_record_id": None,
                "raw": {"success": True},
                "business_confirmation": "NOT_ESTABLISHED",
                "max_outcome_ceiling": "L2",
            }
        return {
            "result_class": "FORM_SUBMISSION",
            "operation_class": ExecutionKind.FORM_SUBMISSION.value,
            "provider_ack": True,
            "persistence": PersistenceObserved.RECORD_PERSISTED.value,
            "provider_record_id": "mock-entry-7",
            "raw": {"success": True, "insert_id": 7},
            "business_confirmation": "NOT_ESTABLISHED",
            "max_outcome_ceiling": "L2",
        }

    def execute(self, operation: str, envelope: dict[str, Any], context: dict[str, Any]) -> dict[str, Any]:
        if operation == "submitForm":
            return self.submit_form(context, envelope.get("execution_request", {}))
        if operation == "discover":
            return self.discover(context)
        raise ReserveAdapterError(ErrorClass.UNSUPPORTED_OPERATION, operation)