"""Driver Registry — R3/R4 product implementation."""

from __future__ import annotations

import importlib.resources as importlib_resources
import json
import pathlib
from dataclasses import dataclass
from typing import Any

from abis_reserve.envelope import MatchConfidence, ProviderType
from abis_reserve.errors import ErrorClass, ReserveAdapterError


def _default_registry_path() -> pathlib.Path:
    """Package-bundled registry (wheel/sdist); legacy dev tree fallback."""
    pkg_ref = importlib_resources.files("abis_reserve").joinpath("registry.json")
    if pkg_ref.is_file():
        with importlib_resources.as_file(pkg_ref) as resolved:
            return pathlib.Path(resolved)
    legacy = pathlib.Path(__file__).resolve().parent.parent / "registry.json"
    if legacy.is_file():
        return legacy
    raise FileNotFoundError("registry.json not found in package or product tree")


@dataclass
class DriverRecord:
    driver_id: str
    provider_match: dict[str, Any]
    provider_type: ProviderType
    supported_editions: list[str]
    supported_versions: dict[str, Any]
    interaction_modes: list[str]
    capabilities: list[str]
    required_interfaces: list[str]
    authorization_requirements: list[str]
    known_limitations: list[str]


@dataclass
class DriverMatchResult:
    driver: DriverRecord | None
    match_confidence: MatchConfidence
    mutation_allowed: bool
    reason: str


def load_registry(path: pathlib.Path | None = None) -> dict[str, Any]:
    p = path or _default_registry_path()
    with open(p, encoding="utf-8") as f:
        return json.load(f)


def list_drivers(path: pathlib.Path | None = None) -> list[DriverRecord]:
    raw = load_registry(path)
    out: list[DriverRecord] = []
    for d in raw.get("drivers", []):
        out.append(
            DriverRecord(
                driver_id=d["driver_id"],
                provider_match=d["provider_match"],
                provider_type=ProviderType(d["provider_type"]),
                supported_editions=list(d.get("supported_editions", [])),
                supported_versions=dict(d.get("supported_versions", {})),
                interaction_modes=list(d.get("interaction_modes", [])),
                capabilities=list(d.get("capabilities", [])),
                required_interfaces=list(d.get("required_interfaces", [])),
                authorization_requirements=list(d.get("authorization_requirements", [])),
                known_limitations=list(d.get("known_limitations", [])),
            )
        )
    return out


def _version_match(policy: str, observed: str, reported: str) -> MatchConfidence:
    if not reported:
        return MatchConfidence.UNKNOWN
    if policy == "EXACT":
        return MatchConfidence.EXACT if reported == observed else MatchConfidence.PARTIAL
    if policy == "SUPPORTED_RANGE":
        if reported == observed:
            return MatchConfidence.EXACT
        # R4: minimal range — same major.minor prefix only
        o_parts = observed.split(".")
        r_parts = reported.split(".")
        if len(o_parts) >= 2 and o_parts[:2] == r_parts[:2]:
            return MatchConfidence.SUPPORTED_RANGE
        return MatchConfidence.PARTIAL
    return MatchConfidence.UNKNOWN


def match_driver(
    *,
    plugin_slug: str,
    edition: str | None = None,
    version: str | None = None,
    path: pathlib.Path | None = None,
) -> DriverMatchResult:
    for rec in list_drivers(path):
        pm = rec.provider_match
        if pm.get("plugin_slug") != plugin_slug:
            continue
        if edition and pm.get("edition") and pm.get("edition") != edition:
            continue
        sv = rec.supported_versions
        conf = _version_match(
            sv.get("match_policy", "UNKNOWN"),
            sv.get("observed", ""),
            version or "",
        )
        mutation_allowed = conf in (MatchConfidence.EXACT, MatchConfidence.SUPPORTED_RANGE, MatchConfidence.PARTIAL)
        return DriverMatchResult(
            driver=rec,
            match_confidence=conf,
            mutation_allowed=mutation_allowed,
            reason=f"matched {rec.driver_id} as {conf.value}",
        )
    return DriverMatchResult(
        driver=None,
        match_confidence=MatchConfidence.UNKNOWN,
        mutation_allowed=False,
        reason="no driver registered",
    )


def require_capability(driver: DriverRecord, capability: str) -> None:
    if capability not in driver.capabilities:
        raise ReserveAdapterError(
            ErrorClass.CAPABILITY_UNAVAILABLE,
            f"capability {capability} not declared for {driver.driver_id}",
        )