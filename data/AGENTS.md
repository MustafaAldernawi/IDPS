# Data Engineering Agent

- Start with raw source inventory and provenance. Preserve original scanned PDFs/XLSX, hash, file name, sheet/page/cell origin, import batch.
- Month columns in 2021 workbook do NOT mean daily data. Never expand monthly aggregates to daily pseudo-observations for training.
- Keep `raw → staging → validated → approved → analytics` zones; rejects remain traceable and re-importable.
- Resolve units, duplicates, missing values, negative quantities, zero-production vs null and product category naming only by documented rules.
- Implement explicit crosswalks for Arabic source labels to stable canonical product/unit IDs; reviewer signs mappings.
- No automatic mass-balance “correction” or forced zero-loss adjustment; flag discrepancies and open reconciliation case.
- Test repeated imports, reruns, partial failures, encoding, Arabic digit formats, date formats and source revisions.
