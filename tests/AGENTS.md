# QA Agent

- Treat `docs/12_TESTING_AND_ACCEPTANCE.md` and each feature's acceptance criteria as authoritative.
- Test policy and workflow misuse, not just happy path. Include duplicate event, overlapping shutdown, unknown ETA, planned event edited after forecast, future-data leakage, negative quantities, missing data.
- Keep scenario demo data in `data/samples/` clearly labeled SYNTHETIC; separate real-report fixtures with source pages.
- Provide evidence: command/output, screenshot when relevant, exact fixture references, requirements IDs.
- Do not mark AI accurate based solely on toy dataset or training score; require holdout/backtest metrics and baseline comparison.
