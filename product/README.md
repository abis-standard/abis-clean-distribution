# ABIS Public Data — Product Workspace

## Role

`ABIS Public Data/product/` is the **canonical source workspace** for publicly distributable ABIS Product components.

Examples:

- ABIS Foundation
- ABIS Reserve Adapter (when established)
- Future distributable ABIS Adapters
- Public Product tests (when migrated)
- Public Product build tooling (when migrated)

## Boundaries

### Product (`ABIS Public Data/product/`)

Product Source / Build / Product Tests for **distributable** ABIS Product modules.

### Sandbox (`[REDACTED_RESEARCH_TREE]`)

- Experiment
- Synthetic Validation
- Research
- Evidence
- Gate Records
- Governance History
- Historical Release Evidence

### ABIS Core / Protected HOW

**MUST NOT** be stored in `ABIS Public Data/product/`.

## Historical evidence

`ABIS Public Data/product/` is **not** a replacement for historical Sandbox evidence.

Historical frozen artifacts remain authoritative evidence for the releases they represent (for example Gate 39 frozen release artifacts under `[REDACTED_INTERNAL_EVIDENCE]`).

## Layout

| Path | Semantics |
| --- | --- |
| `foundation/` | Released/frozen ABIS Foundation lineage and future Foundation development source |
| `adapters/` | Optional publicly distributable Product Adapters |
| `shared/` | Code or contracts shared by multiple public Product modules (when established) |
| `tests/` | Product-level tests separated from Sandbox validation (migration pending) |
| `build/` | Future canonical Product build and channel packaging tooling |
| `migration-evidence/` | P0 workspace-establishment analysis (not release evidence) |

## Source of Truth (P0-02)

When baseline verification succeeds, Foundation v1.0 development source of truth is:

`foundation/wordpress-plugin/` (Gate 39 frozen distributable tree baseline).

Historical Foundation v1.0 **release authority** remains Gate 39 frozen evidence and `abis-platform-adapter-1.0.0.zip`.

## Lineage (P0-04)

See **`PRODUCT_LINEAGE.md`** — active Product authority, `14_v3` historical lineage (superseded as active source, preserved in place), Gate 39 release authority, WordPress.org channel separation, and adapter development rules.

## Build & test (P0-03)

```bash
cd "ABIS Public Data"
python3 product/build/foundation/build_foundation.py \
  --compare-gate39 "../[REDACTED_INTERNAL_EVIDENCE]"
python3 -m pytest product/tests -q
```

Derived output: `product/build/output/` (not Source of Truth).