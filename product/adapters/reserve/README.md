# ABIS Reserve Adapter

## STATUS

**IMPLEMENTED_CANDIDATE** (R4 minimum slice — HD-RESERVE-R4-01)

## PRODUCT_IMPLEMENTATION

**abis_reserve/** Python package — Common Envelope, Driver Registry, Authorization Gate, Execution Router, R05/F02 drivers, Evidence, Outcome Interpreter, Claim Guard.

Driver manifest: `abis_reserve/registry.json` (also mirrored at `registry.json` for source-tree workflows).

## RELEASE / CONFORMANCE

| Field | Value |
| --- | --- |
| release_status | NOT_RELEASED |
| conformance_status | NOT_ESTABLISHED |
| r2_contract_status | CANDIDATE_NON_NORMATIVE |
| r3_architecture_status | ARCHITECTURE_CANDIDATE |
| implementation_candidate_version | 0.1.0-rc4 |
| pep440_version | 0.1.0rc4 |

## L4 / L5

**NOT_ESTABLISHED** — architecture slots only; no business-confirmation or ABIS-verified claims in this slice.

## PUBLIC_RELEASE

**NOT_GRANTED**

## QUALIFICATION

Bounded validation and release qualification records are maintained under ABIS release governance. This public tree does not ship internal research artifacts.

## TESTS

```bash
cd product/adapters/reserve && ./scripts/run_reserve_tests.sh
```


## RELEASE CANDIDATE

**0.1.0-rc4** — distribution / build hygiene (isolated build, sdist surface) per HD-RESERVE-COMPLETION-01. **NOT_RELEASED.**

Prior: **0.1.0-rc3** @ `3be1787` (frozen historical).

---

R4 does **not** authorize production deployment, public download, or normative contract promotion.