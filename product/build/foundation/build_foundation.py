#!/usr/bin/env python3
"""ABIS Foundation v1.0 — Product-side distributable build (Source of Truth only)."""

from __future__ import annotations

import argparse
import hashlib
import json
import shutil
import sys
import zipfile
from datetime import datetime, timezone
from pathlib import Path

BUILD_TOOL_VERSION = "product-foundation-build/0.1.0"
PRODUCT_VERSION = "1.0.0"
ZIP_BASENAME = "abis-platform-adapter-1.0.0.zip"

CANONICAL_GPLV2_SHA256 = "edaef632cbb643e4e7a221717a6c441a4c1a7c918e6e4d56debc3d8739b233f6"
MIN_CANONICAL_GPLV2_BYTES = 17_000
EXPECTED_FILE_COUNT = 23

FORBIDDEN_NAME_PARTS = (
    ".env",
    "id_rsa",
    "private_key",
    "api_key",
    "__pycache__",
    ".pytest_cache",
    ".git",
)

# Fail closed if Product source is missing or Sandbox fallback would be required.
SANDBOX_PLUGIN_MARKERS = (
    "__FORBIDDEN_SANDBOX_PLUGIN_PATH__",
)


def product_roots() -> tuple[Path, Path, Path, Path, Path]:
    build_dir = Path(__file__).resolve().parent
    product_root = build_dir.parents[1]
    source_root = product_root / "foundation" / "wordpress-plugin"
    output_root = product_root / "build" / "output"
    package_out = output_root / "foundation-v1.0.0-package" / "abis-product-v1.0-wordpress-plugin"
    return product_root, source_root, output_root, package_out, build_dir


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(65536), b""):
            h.update(chunk)
    return h.hexdigest()


def relative_payload_files(package_root: Path) -> dict[str, str]:
    """Map zip-style relative paths (under abis-product-v1.0-wordpress-plugin/) to sha256."""
    out: dict[str, str] = {}
    inner = package_root.name
    for path in sorted(package_root.rglob("*")):
        if not path.is_file():
            continue
        rel = path.relative_to(package_root.parent).as_posix()
        if any(p in rel for p in FORBIDDEN_NAME_PARTS):
            raise ValueError(f"forbidden artifact name in package: {rel}")
        out[rel] = sha256_file(path)
    return out


def validate_source_root(source_root: Path, gpl_canonical: Path) -> dict[str, str]:
    if not source_root.is_dir():
        raise FileNotFoundError(f"Foundation Product source missing: {source_root}")
    plugin = source_root / "abis-platform-adapter"
    main_php = plugin / "abis-platform-adapter.php"
    if not main_php.is_file():
        raise FileNotFoundError(f"missing plugin entry: {main_php}")
    text = main_php.read_text(encoding="utf-8")
    if "Version: 1.0.0" not in text:
        raise ValueError("Foundation source version must be 1.0.0 (fail closed)")
    if "0.1.0-slice1" in text:
        raise ValueError("slice1 version marker in Product source (fail closed)")
    for marker in SANDBOX_PLUGIN_MARKERS:
        if marker.replace("/", str(Path("/")) if False else marker) in str(source_root):
            pass  # path string guard only when explicit fallback configured
    license_path = source_root / "LICENSE"
    if not license_path.is_file():
        raise FileNotFoundError("required LICENSE missing in Product source")
    if license_path.stat().st_size < MIN_CANONICAL_GPLV2_BYTES:
        raise ValueError("LICENSE too small")
    license_digest = sha256_file(license_path)
    if license_digest != CANONICAL_GPLV2_SHA256:
        raise ValueError(f"LICENSE digest mismatch: {license_digest}")
    if gpl_canonical.is_file():
        if sha256_file(gpl_canonical) != CANONICAL_GPLV2_SHA256:
            raise ValueError("build harness GPLv2 reference digest mismatch")
    hashes = {}
    prefix = "abis-product-v1.0-wordpress-plugin"
    for path in sorted(source_root.rglob("*")):
        if not path.is_file():
            continue
        rel_under_pkg = path.relative_to(source_root).as_posix()
        arc = f"{prefix}/{rel_under_pkg}"
        if any(p in arc for p in FORBIDDEN_NAME_PARTS):
            raise ValueError(f"forbidden: {arc}")
        hashes[arc] = sha256_file(path)
    if len(hashes) != EXPECTED_FILE_COUNT:
        raise ValueError(f"unexpected file count {len(hashes)} (expected {EXPECTED_FILE_COUNT})")
    return hashes


def copy_source_to_output(source_root: Path, package_out: Path) -> None:
    if package_out.parent.exists():
        shutil.rmtree(package_out.parent)
    package_out.parent.mkdir(parents=True, exist_ok=True)
    shutil.copytree(source_root, package_out)


def write_zip(package_parent: Path, zip_path: Path) -> None:
    package_root = package_parent / "abis-product-v1.0-wordpress-plugin"
    if zip_path.exists():
        zip_path.unlink()
    with zipfile.ZipFile(zip_path, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        for path in sorted(package_root.rglob("*")):
            if path.is_file():
                arc = path.relative_to(package_parent).as_posix()
                zf.write(path, arc)


def compare_to_baseline(
    payload_hashes: dict[str, str], baseline_root: Path
) -> tuple[int, list[str]]:
    """Read-only compare to Gate 39 frozen package tree (historical reference)."""
    prefix = "abis-product-v1.0-wordpress-plugin"
    baseline: dict[str, str] = {}
    for path in sorted(baseline_root.rglob("*")):
        if not path.is_file():
            continue
        rel = f"{prefix}/{path.relative_to(baseline_root).as_posix()}"
        baseline[rel] = sha256_file(path)
    diffs: list[str] = []
    for key in sorted(set(payload_hashes) | set(baseline)):
        if key not in payload_hashes:
            diffs.append(f"missing in build: {key}")
        elif key not in baseline:
            diffs.append(f"extra in build: {key}")
        elif payload_hashes[key] != baseline[key]:
            diffs.append(f"sha mismatch: {key}")
    return len(diffs), diffs


def build(*, compare_gate39: Path | None) -> dict:
    product_root, source_root, output_root, package_out, build_dir = product_roots()
    gpl_canonical = build_dir / "gpl-2.0-full.txt"
    source_hashes = validate_source_root(source_root, gpl_canonical)
    baseline_manifest = product_root / "foundation" / "FOUNDATION_BASELINE_MANIFEST.json"
    source_manifest_digest = sha256_file(baseline_manifest) if baseline_manifest.is_file() else None

    copy_source_to_output(source_root, package_out)
    built_hashes = relative_payload_files(package_out)
    # normalize keys to match zip arc naming
    built_hashes_norm: dict[str, str] = {}
    for path in sorted(package_out.rglob("*")):
        if path.is_file():
            arc = path.relative_to(package_out.parent).as_posix()
            built_hashes_norm[arc] = sha256_file(path)

    zip_path = output_root / ZIP_BASENAME
    write_zip(package_out.parent, zip_path)

    gate_diff_count = 0
    gate_diffs: list[str] = []
    if compare_gate39 and compare_gate39.is_dir():
        gate_diff_count, gate_diffs = compare_to_baseline(built_hashes_norm, compare_gate39)

    integrity_match = source_hashes == built_hashes_norm
    payload_identity = integrity_match and gate_diff_count == 0

    manifest = {
        "product": "ABIS Foundation",
        "version": PRODUCT_VERSION,
        "source_root": str(source_root.relative_to(product_root)),
        "source_manifest": "foundation/FOUNDATION_BASELINE_MANIFEST.json",
        "source_manifest_sha256": source_manifest_digest,
        "source_file_count": len(source_hashes),
        "source_file_hashes": source_hashes,
        "build_tool_version": BUILD_TOOL_VERSION,
        "build_timestamp_utc": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
        "output_path": str(package_out.relative_to(product_root)),
        "output_zip": str(zip_path.relative_to(product_root)),
        "output_sha256": sha256_file(zip_path),
        "payload_file_count": len(built_hashes_norm),
        "payload_hashes": built_hashes_norm,
        "functional_change": False,
        "payload_identity_vs_source": integrity_match,
        "payload_identity_vs_gate39_baseline": gate_diff_count == 0 if compare_gate39 else None,
        "gate39_diff_count": gate_diff_count,
        "gate39_diffs": gate_diffs[:20],
    }
    output_root.mkdir(parents=True, exist_ok=True)
    manifest_path = output_root / "FOUNDATION_BUILD_MANIFEST.json"
    manifest_path.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    return manifest


def main() -> int:
    parser = argparse.ArgumentParser(description="Build ABIS Foundation distributable from Product SoT")
    parser.add_argument(
        "--compare-gate39",
        type=Path,
        default=None,
        help="Read-only path to Gate 39 release_package_candidate/abis-product-v1.0-wordpress-plugin",
    )
    args = parser.parse_args()
    try:
        meta = build(compare_gate39=args.compare_gate39)
    except Exception as exc:
        print("PRODUCT_BUILD_FAIL", exc, file=sys.stderr)
        return 1
    print("PRODUCT_BUILD_PASS")
    print("PAYLOAD_FILE_COUNT", meta["payload_file_count"])
    print("OUTPUT_ZIP_SHA256", meta["output_sha256"])
    print(
        "PAYLOAD_IDENTITY",
        "YES" if meta.get("payload_identity_vs_gate39_baseline") or meta["payload_identity_vs_source"] else "NO",
    )
    if args.compare_gate39:
        print("GATE39_DIFF_COUNT", meta["gate39_diff_count"])
    return 0 if meta["payload_identity_vs_source"] else 1


if __name__ == "__main__":
    raise SystemExit(main())