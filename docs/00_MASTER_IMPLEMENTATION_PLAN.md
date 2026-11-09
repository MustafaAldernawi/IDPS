# 00 — خطة التنفيذ الرئيسية (Master Implementation Plan)

## 1. الهدف والحدود

تنفيذ نظام **Decision Support** لإدارة الإنتاج والتخطيط وهندسة العمليات وإدارة التشغيل بمصفاة الزاوية، عبر سجل مركزي موثق، 3 مؤشرات رئيسية، تنبيهات، وتوقع إنتاج متغير حسب الأحداث (Event-aware) مع حوكمة تدريب النماذج. لا يُسمح باستعمال النظام للتحكم الفعلي في أنظمة السلامة أو وحدات التكرير.

**قيمة المنتج:** تسريع إعداد التقارير وشفافية المقارنة Actual/Target وإظهار مخاطر عدم تحقيق المستهدف قبل نهاية الشهر. **لا ندعي تحسنًا كميًا حتى القياس التجريبي**.

## 2. منهجية العمل

- تسليم **Vertical slices** تبدأ من إدخال البيانات واعتمادها ثم KPI ثم Forecast؛ لا نبني Dashboard فوق بيانات تجريبية ونسمي المشروع مكتملًا.
- كل ميزة تمر عبر: `Spec approval → Schema/API → Backend → Frontend → Tests → UAT → Documentation`.
- أي معادلة أو نوع منتج يحتاج موافقة أصحاب التخصص قبل التنفيذ الإنتاجي.
- **بوابات (Gates)** تمنع الانتقال إلى ما يلي إذا كانت جودة البيانات أو الموافقات غير كافية.
- استخدم `planning/BACKLOG.csv` كمصدر التذاكر؛ `specs/` مصدر تنفيذ كل Epic.

## 3. المراحل والتبعيات

| الرمز | المرحلة | الاعتمادات | مخرجات قابلة للتسليم | بوابة الخروج |
|---|---|---|---|---|
| P0 | دراسة الواقع + Data Audit | مصادر 2021–2023 الشهرية الفعلية + تقارير 2024/2025 التراكمية، لقاءات الأقسام | قاموس معتمد، عينات، فجوات المصدر، معايير KPI | توقيع Domain Owner على القياسات ومصدرها |
| P1 | Foundation + Security | P0 أساسيات الأدوار | Repo، CI، RBAC، Audit، هيكل التطبيقات | اختبارات وصول/منع ناجحة |
| P2 | Master Data & Imports | P1، Mapping | وحدات/منتجات/خام/ورديات، Imports شهرية، معالجة أخطاء | إعادة استيراد دون ازدواجية |
| P3 | Production/Operations | P2 | إنتاج، خام، اعتماد، أحداث، توقفات | تدفق إدخال/اعتماد كامل واختبارات Event |
| P4 | Process Analytics & KPIs | P3، تعريف معتمد | Actual/Target، Energy، Loss، Chemicals، Reports | مطابقة عينات التقرير ضمن سياسة التقريب |
| P5 | Dataset Management & Training Studio **V1** | P1 + P2 (fixtures accepted initially) | upload/mapping, snapshot, experiment runner, baselines, Train/Eval UI | tested on deterministic fixtures; source quality gate per dataset |
| P6 | Model Training & Comparison **V1** | P5 | RF/XGBoost pipelines monthly/daily, metrics and registry | training code exercised; deployment only with real valid holdout |
| P7 | Event-aware Forecasting | P3 + P5 baseline; P6 model optional | Reforecast، ETA، Scenario، أثر حدث | اختبار مخطط/مفاجئ/مجهول/تعافي |
| P8 | Retraining & Governance **V1** | P5 + P6 + P7 | Registry، Metrics، Drift، Champion–Challenger | نسخ قابلة للتتبع والرجوع |
| P9 | Security, UAT, Hardening | P1–P8 | اختبارات شاملة وتوثيق وتشغيل | محضر UAT ونشر تجريبي آمن |

> **التقدير الزمني:** لا نضع وعدًا بمدة قبل الاطلاع على الفريق والبيانات. يمكن تقسيم هذه المراحل إلى 10–14 Sprint قصيرة، مع تنفيذ واجهات ومحرك P5–P8 في V1؛ **نشر نموذج ML حقيقي فقط** هو المشروط بكفاية وتقييم البيانات.

## 4. Critical path

`تسمية الوحدات/المنتجات → تعريف اليوم التشغيلي والقياسات → Import & approval → Monthly 2021–2023 first / genuine Daily later → Feature availability → Baseline → Model backtest → Reforecast → UAT`.

مسار المنصة P1–P4 يمكن تنفيذه بينما المصفاة تجهز البيانات اليومية؛ لا نجعل غياب بيانات التدريب يوقف بناء النظام كله.

## 5. Deliverable لكل Sprint

- Scope + FR/AC IDs + Git commit/reference.
- DB migration + rollback أو `N/A`.
- API contract diff + server validation.
- UI demo يستخدم Test Fixtures واضحة.
- الاختبارات: Unit, Integration, Permission, Regression.
- تحديث README وOpen Decisions ولقطة جودة.
- **Sprint Review** مع أحد ممثلي الإنتاج/الهندسة/التخطيط حسب الميزة.

## 6. مؤشرات نجاح التنفيذ (Project Success Criteria)

| محور | الدليل المطلوب |
|---|---|
| صحة البيانات | تمييز null وzero، ملف rejects، source lineage، import replay دون duplicates |
| سير العمل | Plan → Actual → Approval → Analytics → Forecast → Alert قابل للعرض |
| KPI | ناتج منشأ من مدخلات موثقة ومعادلة معتمدة، يشمل المقام/المجال |
| Forecast | Sealed holdout + baseline comparison + error breakdown لكل منتج |
| Event | Reforecast بعد تسجيل الحدث، حفظ forecast السابق كدليل، سيناريو ETA مجهول |
| أمان | 403 عند محاولة تجاوز الصلاحية + Audit على التعديلات |
| UX | عربي RTL، هاتف/حاسوب، حالة نقص بيانات، مؤشرات البيانات التجريبية |
| تشغيل | نسخ احتياطي، استرجاع مجرّب، إعادة نشر/rollback، monitoring |

## 7. ما الذي نفعله الآن بالتتابع؟

1. P0.1: جرد 2021–2023 شهريًا (36 شهرًا) و2024 سنويًا و2025 حتى أغسطس + ديسمبر مع trace لكل قيمة.
2. P0.2: اتفاق على تعريف حجم الإنتاج ومتى نعتبره Stream مقابل Blend مع MTBE.
3. P0.3: اعتماد 36 شهرًا من Excel 2021–2023، وطلب بيانات يومية إضافية إن توفرت دون تعطيل تدريب V1 الشهري البحثي.
4. P1: إنشاء المستودع والاختبارات والصلاحيات وaudit أولًا.
5. P2–P3: بناء الاستيراد والموافقات وسجلات التشغيل.
6. P4: اعتماد KPI ومطابقة مثال ديسمبر 2025.
7. P5: نفّذ Pipeline التدريب والتحميل والتقييم في V1 باستخدام Fixtures موسومة بوضوح. جرّب Monthly Baselines على البيانات الشهرية المتاحة. لو فشل تقييم Dataset حقيقي، أظهر `ML_NOT_READY` مع توقع إحصائي موثّق منفصل.
8. P6: اختبر RF/XGBoost لكل Dataset صالحة وبنفس المستوى الزمني، واحفظ إصدارات النماذج ونتائجها؛ لا تنشر نموذجًا قبل اجتياز بوابة التقييم.
9. P7–P9 وفق نجاح المراحل السابقة.

## 8. التغيير والتحكم بالنطاق

كل طلب جديد يدرج في backlog، وله: السبب، الأثر على data/API/AI، الاختبارات، التقدير، المالك، والقرار. إضافات التنبؤ بأعطال المعدات أو DCS live integration **ليست ضمن MVP**.
