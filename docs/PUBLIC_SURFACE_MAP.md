# ABIS Public Surface Map

**Documentation baseline:** `0.1.1-docs` (not a product version)  
**Status:** Public / Non-Normative / Open for Review  
**Framework identity in this repository:** Public v0.1 — Concept & Architecture Release

---

## Positioning (non-normative)

| Layer | Text |
| --- | --- |
| **Name** | **Agent Business Interaction Standard** (ABIS) |
| **Category** | Business-side Interaction Infrastructure for the Agentic Web |
| **Supporting phrase** | From AI Discovery to Business Interaction. |
| **Architecture characteristics (descriptive)** | Business-side · Existing Web retrofit · Agent-neutral |

These phrases describe positioning only. They are not conformance requirements.

---

## What each public surface is

| Label | What it is | Where |
| --- | --- | --- |
| **ABIS** | Public interoperability framework for Business Interaction and Business Outcome semantics | This repository (`abis-standard/abis`) |
| **Foundation Series v1.0** | Governance and freeze series for the Foundation product line (Vault normative; not identical to this repo version) | [Official Website](https://abis.coaretail.com) · distribution metadata |
| **Foundation Product v1.0** | Business Fact Surface, Agent Readiness, WordPress-oriented publication bridge — **product identity** | Website distribution · **`publicationStatus: NOT_RELEASED`** at time of 0.1.1-docs |
| **ABIS Platform Adapter 1.0.0** | WordPress package versioning object — **not** the same as Foundation Series or this repo’s Public v0.1 | Website / WP.org channel (when published) |
| **Public Sandbox** | Synthetic / trial demonstrator surfaces — **not** Foundation Product production execution | [abis.coaretail.com](https://abis.coaretail.com) sandbox & trial URIs |
| **Reference Runtime** | Non-production Developer Preview reference implementation | [abis-reference-runtime](https://github.com/abis-standard/abis-reference-runtime) |
| **Synthetic Reference Business** | Controlled reference business origins and pointers for validation — **not** live commerce | Reference Runtime docs · local `serve_reference_business_origin` |

**Versioning rule:** Do not treat `Foundation Series v1.0`, `Foundation Product 1.0.0`, `Platform Adapter 1.0.0`, `Public v0.1.1-docs`, and Reference Runtime semver as a single mechanical version.

---

## Reference Runtime version truth (observable)

| Axis | Value |
| --- | --- |
| **Latest GitHub Release** | [**v0.8.0**](https://github.com/abis-standard/abis-reference-runtime/releases/tag/v0.8.0) |
| **Default branch (`main`)** | Developer Preview — may advance ahead of the latest Release |
| **Historical note** | [**v0.5.0**](https://github.com/abis-standard/abis-reference-runtime/releases/tag/v0.5.0) was the previous published Release; git tag **v0.6.0** exists without a separate GitHub Release object; **v0.7.x** was not tagged |

Reference Runtime simulator or authorized non-production flows are **not** Foundation Product production capabilities.

---

## Foundation Product v1.0 — established scope (summary)

Public documentation may describe the following as **in scope for the Foundation Product line** (distribution subject to release gates):

- Business Fact Surface · WordPress-native fact collection · Manual Business Fact declaration  
- Fact Provenance · NOT_ESTABLISHED handling · Business-side publication  
- Agent-facing Declaration bridge · SEARCH · PRICE_DISCOVERY  
- Production/Synthetic `testMaterial` separation · Privacy boundary  
- No unexpected outbound network (product design intent)  
- Non-Woo manual offerings · Authorization Boundary Foundation  

Detailed freeze text and packages are **not** fully contained in this repository.

---

## NOT established by Foundation Product v1.0

The following must **not** be read as Foundation Product production capabilities:

| Capability | Status |
| --- | --- |
| Production Reservation execution | **NOT_ESTABLISHED** |
| Real Reservation Provider | **NOT_ESTABLISHED** |
| Production Purchase | **NOT_ESTABLISHED** |
| Production Checkout | **NOT_ESTABLISHED** |
| Production Payment | **NOT_ESTABLISHED** |
| Production OVR | **NOT_ESTABLISHED** |
| Production Multi-Outcome execution | **NOT_ESTABLISHED** |
| Universal Agent compatibility | **NOT_ESTABLISHED** |
| Guaranteed discovery / ranking / recommendation | **NOT_ESTABLISHED** |

Public Sandbox and Reference Runtime may expose **synthetic**, **simulated**, or **authorized non-production** reserve / order flows. Those are **reference or demonstrator** surfaces — not claims that the Foundation Product establishes production execution.

---

## Semantic boundaries (minimal)

| Distinction | Rule of thumb |
| --- | --- |
| URL Read ≠ Authorization | Reading a page or profile does not grant business authorization |
| Discovery ≠ Execution | Finding a surface does not execute a business interaction |
| Agent Surface Discovery ≠ Action Authorization | Advertised capability ≠ permission to act |
| Human Intent ≠ Business Authorization | User intent ≠ merchant-side authorization |
| Execution ≠ Business Outcome | Technical completion ≠ satisfied business outcome |
| Business Outcome ≠ ABIS Verification | Outcome disposition in Reference Runtime may be `NOT_EVALUATED` |
| Demonstrator ≠ Production Commerce | Sandbox and Reference Runtime are not production commerce |
| Synthetic Demo ≠ Live Provider Execution | Simulators and test doubles are not live providers |
| Website ≠ ABIS Core | Official site distributes metadata; core verification logic is not open-sourced here |
| Capability ≠ Authorization | Capability labels do not replace authorization boundaries |

---

## Official links

| Resource | URL |
| --- | --- |
| Official Website | https://abis.coaretail.com |
| Public Specification (this repo) | https://github.com/abis-standard/abis |
| Reference Runtime | https://github.com/abis-standard/abis-reference-runtime |
| Latest Runtime Release | https://github.com/abis-standard/abis-reference-runtime/releases/tag/v0.8.0 |
| Validation portal | https://abis.coaretail.com/ja/validation |