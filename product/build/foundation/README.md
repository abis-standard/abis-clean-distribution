# Foundation Product build

## Product build command

From `ABIS Public Data` repository root:

```bash
cd "$(git rev-parse --show-toplevel)"
python3 product/build/foundation/build_foundation.py \
  --compare-gate39 "../[REDACTED_INTERNAL_EVIDENCE]"
```

- **Input (only):** `product/foundation/wordpress-plugin/`
- **Output (derived):** `product/build/output/foundation-v1.0.0-package/` and `product/build/output/abis-platform-adapter-1.0.0.zip`
- **Manifest:** `product/build/output/FOUNDATION_BUILD_MANIFEST.json`

Gate 39 comparison is **read-only** historical reference. Product build does not read `14_v3`.

## Historical builder (unchanged)

`[REDACTED_INTERNAL_EVIDENCE]`

**STATUS:** NOT_MIGRATED (Sandbox copy retained)