# Frontend Agent — Vue/TypeScript

- Read root AGENTS and `docs/11_UI_UX_SPECIFICATION.md`.
- Arabic RTL is primary, English labels technical; centralize design tokens, typography, translations, and number/date formatting.
- Build responsive professional industrial enterprise UI, not a decorative marketing site. Consistent 8px spacing grid, accessible status palette, visual hierarchy.
- Source-of-truth: backend contracts. Never calculate authoritative production KPI or invent AI figures client-side.
- Forecast cards must show timestamp/as_of, horizon, model version, inputs completeness, and an unmistakable scenario/estimate label.
- Include table filter/export only where authorized; keyboard navigation, visible focus, aria labels, chart textual summaries.
- Provide empty, loading, error, stale, pending approval, and partial dataset states.
- Avoid fake real-time labels if data imports are batch/monthly; show accurate data freshness.
- Interactive simulator uses marked synthetic numbers and must not be confused with the trained forecasting engine.
- Test responsive, RTL, forms, timezone, pagination and permission visibility.
