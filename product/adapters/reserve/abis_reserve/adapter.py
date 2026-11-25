"""Reserve Adapter facade — wires gate, router, drivers, evidence, interpreter, claim guard."""

from __future__ import annotations

from typing import Any

from abis_reserve.auth_gate import AuthorizationRequest, evaluate as auth_evaluate
from abis_reserve.claim_guard import ClaimRequest, evaluate_claim
from abis_reserve.drivers.fluent_forms_f02 import FluentFormsF02Driver
from abis_reserve.drivers.motopress_r05 import MotoPressR05Driver
from abis_reserve.drivers.unknown import UnknownProviderDriver
from abis_reserve.envelope import ExecutionKind, PersistenceObserved, ProviderType
from abis_reserve.errors import ErrorClass, ReserveAdapterError
from abis_reserve.evidence import collect as collect_evidence
from abis_reserve.interpreter import interpret
from abis_reserve.registry import match_driver
from abis_reserve.retry_policy import may_retry_mutation
from abis_reserve.router import RouteRequest, route

_DRIVERS = {
    "R05_MOTOPRESS_LITE": MotoPressR05Driver(),
    "F02_FLUENT_FORMS_FREE": FluentFormsF02Driver(),
}


class ReserveAdapter:
    """R4 implementation candidate — not released."""

    def match(self, *, plugin_slug: str, edition: str | None = None, version: str | None = None):
        return match_driver(plugin_slug=plugin_slug, edition=edition, version=version)

    def authorize_mutation(
        self,
        execution_kind: ExecutionKind,
        *,
        human: str = "NOT_ESTABLISHED",
        business: str = "NOT_ESTABLISHED",
        provider: str = "NOT_ESTABLISHED",
        abis: str = "NOT_ESTABLISHED",
    ):
        return auth_evaluate(
            AuthorizationRequest(
                requested_action="mutate",
                execution_kind=execution_kind,
                human_authorization=human,
                business_authorization=business,
                provider_authorization=provider,
                abis_authorization=abis,
            )
        )

    def run_mutation(
        self,
        *,
        plugin_slug: str,
        edition: str | None,
        version: str | None,
        execution_kind: ExecutionKind,
        capability: str,
        envelope: dict[str, Any],
        context: dict[str, Any],
        auth: dict[str, str],
    ) -> dict[str, Any]:
        if may_retry_mutation(ambiguous_outcome=context.get("ambiguous_outcome", False), is_transport=False):
            raise ReserveAdapterError(ErrorClass.UNSUPPORTED_OPERATION, "auto retry denied")

        dm = self.match(plugin_slug=plugin_slug, edition=edition, version=version)
        if dm.driver is None:
            try:
                UnknownProviderDriver().execute("mutate", envelope, context)
            except ReserveAdapterError:
                pass
            raise ReserveAdapterError(ErrorClass.UNKNOWN_PROVIDER, dm.reason)

        auth_result = self.authorize_mutation(
            execution_kind,
            human=auth.get("human", "NOT_ESTABLISHED"),
            business=auth.get("business", "NOT_ESTABLISHED"),
            provider=auth.get("provider", "NOT_ESTABLISHED"),
            abis=auth.get("abis", "NOT_ESTABLISHED"),
        )

        decision = route(
            RouteRequest(
                execution_kind=execution_kind,
                requested_capability=capability,
                driver_match=dm,
                authorization=auth_result,
            )
        )
        if not decision.allowed:
            raise ReserveAdapterError(
                decision.error_class or ErrorClass.UNSUPPORTED_OPERATION,
                decision.message,
            )

        driver = _DRIVERS.get(decision.driver_id or "")
        if not driver:
            raise ReserveAdapterError(ErrorClass.UNKNOWN_PROVIDER, "no driver implementation")

        exec_result = driver.execute(decision.resolved_capability or "", envelope, context)

        evidence = collect_evidence(
            provider_identity={"provider_id": plugin_slug, "edition": edition or ""},
            driver_id=driver.driver_id,
            operation_identity=envelope.get("operation_identity", {}),
            authorization_decision={
                "gate": auth_result.gate_action.value,
                "reasons": auth_result.reasons,
            },
            raw_provider_response=exec_result.get("raw", exec_result),
            provider_record_id=exec_result.get("provider_record_id"),
            raw_provider_status=exec_result.get("provider_status_raw"),
            persistence_observation=exec_result.get("persistence", "UNKNOWN"),
        )

        try:
            persistence = PersistenceObserved(exec_result.get("persistence", "UNKNOWN"))
        except ValueError:
            persistence = PersistenceObserved.UNKNOWN

        interp = interpret(
            provider_type=ProviderType(dm.driver.provider_type.value),
            execution_kind=execution_kind.value,
            provider_ack_observed=bool(exec_result.get("provider_ack")),
            persistence=persistence,
            provider_status_raw=exec_result.get("provider_status_raw"),
            business_confirmation_state=__import__(
                "abis_reserve.envelope", fromlist=["BusinessConfirmationState"]
            ).BusinessConfirmationState.NOT_ESTABLISHED,
            driver_id=driver.driver_id,
            provider_raw=exec_result.get("raw"),
        )

        return {
            "route": decision,
            "execution_result": exec_result,
            "evidence": evidence,
            "interpretation": interp,
            "authorization": auth_result,
        }

    def assert_claim(self, claim_type: str, level: str, ceiling: str, **kwargs):
        from abis_reserve.envelope import OutcomeCeiling

        return evaluate_claim(
            ClaimRequest(claim_type=claim_type, asserted_level=OutcomeCeiling(level), **kwargs),
            evidence_ceiling=OutcomeCeiling(ceiling),
        )