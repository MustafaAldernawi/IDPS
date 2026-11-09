# PLAN.md — IDPS Master Execution Plan (v1.2)

المنهج **vertical slices**؛ لا نعلن أطرًا زمنية دون حجم الفريق وبيئة المصفاة. يلتزم الوكيل بخطة التعديل قبل التنفيذ، وباختبارات MySQL/الـAI وبالترحيل الآمن.

| المسار | الترتيب | المُخرجات | شرط الإنجاز |
|---|---|---|---|
| F0 — Repo & governance | 1 | Laravel/Vue/FastAPI skeleton, MySQL local, CI, RBAC, Audit | login/permissions/rollback tests |
| D0 — Historical data provenance | بالتوازي | File hashes, 36 monthly staged records, source mapping, anomalies | replay بدون ازدواجية + unresolved flagged |
| D1 — MySQL ingestion & approvals | بعد F0 | source_files/import_batches/import_rows/monthly_observations + مراجعة | approve/reject/revision audit |
| O1 — Production/targets | بعد D1 | Actual feed, products, targets, events, shutdowns | قسم الإنتاج → هندسة العمليات → التخطيط |
| K1 — Analytics & KPIs | بعد O1 | achievement, energy ratio, loss ratio, reports | reconciliation with approved reference |
| AI1 — Dataset Studio (V1) | بعد D1 بالتوازي | CSV/XLSX upload, granularity, validation, snapshot, Train Queue | تجربة ملف monthly 2021–2023 عبر واجهة |
| AI2 — ML experimentation (V1) | بعد AI1 | Baseline/Ridge/RF/XGB, time split, metrics, registry | research-only candidate، عدم تفعيل تلقائي |
| E1 — Event-aware forecast | بعد O1+AI1 | planned/unplanned event, ETA scenarios, immutable reforecast | old vs new comparison, no future leakage |
| M1 — Model governance | بعد AI2 | retrain approved actuals, champion/challenger, rollback, drift | preserve winner + audit |
| U1 — UI & acceptance | مع المراحل | Arabic RTL, reports, UAT, monitoring/security | توقيع قبول ميداني وتجارب backup |

**البدء الآن:** أنجز `RF-001..004` من foundation أولًا، وفي مسار مستقل `RF-106..109` لتحضير البيانات 2021–2023 على أن تطبق imports بعد تجهيز MySQL/security. بعدها `RF-110..116` لتوصيل الـAI التجريبي بالمنظومة، ثم الأحداث والأقسام.

## بوابات صارمة

- `G-DATA`: مصدر كل رقم محفوظ، التعارضات معلّقة لا مخفية.
- `G-KPI`: الصيغ والمقام/الفترة/التعريف معتمد.
- `G-ML-RESEARCH`: training runs قابلة للتكرار وملفات البحث غير مفعّلة.
- `G-ML-PRODUCTION`: تحقق خارجي زمني كافٍ ومقارنة مع baseline وموافقة هندسية.
- `G-EVENT`: لا استخدام لتوقف مستقبل مجهول أو أحداث لم تكن معروفة عند `as_of`.
- `G-SECURITY`: لا نشر قبل الصلاحيات والـAudit والاسترجاع.

استخدم `docs/00_MASTER_IMPLEMENTATION_PLAN.md` و`planning/DELIVERY_ROADMAP.md` للتفصيل، و`planning/BACKLOG.csv` كمرجع IDs وحالة العمل.
