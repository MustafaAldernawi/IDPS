# IDPS — Integrated Data and Production System

### تصميم منظومة ذكية لتكامل البيانات وتحليل الإنتاج النفطي وحساب مؤشرات الأداء

> **Unified implementation handoff v1.2 / 2026-11-09**. هذه الحزمة تجمع الوثائق كاملةً مع بيانات 2021–2023 المُنظّمة ومصدرها، وكود تجارب AI. لم تتم برمجة أو نشر منصة Laravel/Vue كاملة أو ربط FastAPI/MySQL فعليًا بعد.

## سريعًا — ماذا تجد هنا؟

| الملف / المسار | الغرض |
|---|---|
| `AGENTS.md` | تعليمات إلزامية للوكيل قبل التعديل والتوقفات والبيانات والـAI |
| `SPEC.md` | المرجع الرئيسي للمتطلبات ونطاق V1 والأدوار |
| `PLAN.md` و`TASKS.md` | خطة التنفيذ والمهام المرتبة |
| `START_HERE_AR.md` | تعليمات التهيئة والتسليم والبدء |
| `docs/00..27` | دراسات متخصصة: product, architecture, DB, API, AI, shutdowns, KPIs, security, sources, tests |
| `specs/001..009/{spec,plan,tasks}.md` | مواصفات تسع مجموعات تنفيذ |
| `planning/BACKLOG.csv` و`planning/IDPS_PROJECT_TRACKER_V1_2.xlsx` | 119 مهمة ولوحة متابعة |
| `contracts/schema.sql`, `contracts/openapi.yaml` | مخططات MySQL وAPI **مرجعية لم تُنشر** |
| `data/raw-sources/` | 12 مستندًا أصليًا مغلفًا مع SHA256، تُحفظ بلا تعديل |
| `data/curated-provisional/` | 36 شهرًا فعلية 2021–2023 مع flags وتقارير مراجعة |
| `services/ai/research_starter/` | كود تدريب تجريبي ونتائج وملفات `RESEARCH_ONLY`، وليس خدمة إنتاج |
| `apps/backend`, `apps/frontend`, `services/ai/app` | مواقع البرامج التنفيذية **لم تُبنَ بعد** |

## الحالة الحقيقية

- **Dataset:** 36 شهرًا من 2021–2023، ستة منتجات، 35 عمودًا؛ `PROVISIONAL` إلى حين اعتماد مصدر القياس والفروقات.
- **بحث ML:** Ridge/RandomForest/XGBoost + baselines تم اختبارهم سابقًا على مجموعة تجريبية محدودة؛ ملخص ومخرجات في `services/ai/research_starter/results/`، `can_deploy=false`.
- **MySQL:** مطلوب MySQL 8.4 LTS؛ `contracts/schema.sql` مسودة مرجعية، وليس migrations مطبقة؛ نسخة SQL تجريبية شهرية تحت research starter **لا تُنفذ فوق المخطط الرئيسي**.
- **V1 AI:** واجهة تنزيل Dataset/تدريب/تقييم/اعتماد/توقع وإعادة تدريب من صميم النسخة الأولى؛ ليس انتظار سنوات بيانات يومية.
- **متغيرات غير متاحة:** ساعات توقف دقيقة يوميًا، أسماء وحدات production granular، يوميات 2024–2025، قياسات كهرباء موثوقة، وينبغي عدم اختلاقها.

## من أين نبدأ؟

1. افتح `START_HERE_AR.md` ثم `AGENTS.md` ثم `SPEC.md` ثم `PLAN.md`.
2. افحص `docs/25_SOURCE_QUALITY_AND_CONFLICTS.md` و`data/audits/IDPS_DATA_AUDIT_2021_2023.md`.
3. اتبع `planning/AGENT_KICKOFF_PROMPT.md` وأرسلها إلى Codex/Antigravity.
4. ابدأ `RF-001` مع `RF-106` ثم monthly imports، وبعدها AI Studio/forecast.

**مهم:** لا تعلن أن النموذج يتوقع الأعطال مسبقًا، أو أن جداول 2024 التراكمية شهرية، أو أن الأرقام المحسوبة بدقة نموذج معتمد. النظام Decision Support فقط.
