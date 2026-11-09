# 02 — Software Requirements Specification (Functional)

كل بند له ID قابل للربط بتذكرة/اختبار. `M` أساسي في MVP، `C` مشروط بتوفر البيانات، `L` مرحلة لاحقة.

## A. Identity, master data & governance

| ID | أولوية | المتطلب | معيار القبول الرئيسي |
|---|---|---|---|
| FR-001 | M | دخول/logout وحماية جلسة/Token | 401 عند عدم الدخول؛ لا تسريب أسرار |
| FR-002 | M | إدارة الأدوار والصلاحيات | وصول حسب action/resource ووحدة/مرفق |
| FR-003 | M | مرجع وحدات المصفاة وحالاتها | تاريخي/تعطيل دون محو السجلات |
| FR-004 | M | مرجع المنتجات والخامات ووحدات القياس | تسمية Canonical مع mapping عربية للمصدر |
| FR-005 | M | جدول الورديات والتقويم التشغيلي | تاريخ محلي واضح وساعات UTC محفوظة |
| FR-006 | M | سجل تدقيق غير قابل للتعديل من API العامة | قبل/بعد/فاعل/وقت وسبب تعديل |
| FR-007 | M | مسار اعتماد رسمي Draft→Submitted→Approved/Rejected | لا تدخل بيانات غير معتمدة للنماذج |
| FR-008 | M | تصحيح معتمد بإنشاء Revision | الأصل محفوظ + السبب والمراجع |

## B. Plans, inputs and quality

| ID | أولوية | المتطلب | معيار القبول الرئيسي |
|---|---|---|---|
| FR-009 | M | خطة Targets شهرية لكل منتج/وحدة | Version, validity, approver؛ منع تعارض نسخ فعالة |
| FR-010 | M | استيراد Excel/CSV شهري/يومي بقوالب معتمدة | Preflight → preview → mapping → validate → approve |
| FR-011 | M | منع ازدواجية الاستيراد | Same fingerprint idempotent، تعديل يتطلب Revision |
| FR-012 | M | شاشة أخطاء الاستيراد (Rejects) | صف/عمود/رسالة/رمز/قابلية إعادة التحميل |
| FR-013 | M | تسجيل الخام حسب المصدر والوحدة واليوم/الوردية | كميات غير سالبة، مصدر، وقت التوفر |
| FR-014 | M | تسجيل إنتاج كل منتج | product_id + quantity + unit + period + approval |
| FR-015 | M | تسجيل الوقود والكهرباء والفواقد | لا دمج fuel مع loss تلقائيًا |
| FR-016 | M | تسجيل المواد الكيميائية والمستهدفات | اختلاف وحدة المادة وتوثيق الزيادة |
| FR-017 | C | إدخال quality/blend batch | Component quantities/specs، فصل blend عن stream |
| FR-018 | M | مراقبة اكتمال المدخلات | Data-quality badges وتقارير gap |

## C. Operations and shutdowns

| ID | أولوية | المتطلب | معيار القبول الرئيسي |
|---|---|---|---|
| FR-019 | M | تسجيل حالة الوحدة عبر timeline | تشغيل/توقف/صيانة/بدء وتواريخ فعالة |
| FR-020 | M | إنشاء توقف مخطط وإصدارات ETA | ينعكس فقط في توقعات بعد `known_at` |
| FR-021 | M | تسجيل Trip مفاجئ | timestamp, unit, category, source, idempotency |
| FR-022 | M | تحديث ETA/سبب/تعليق | تاريخ التعديل محفوظ؛ Reforecast job جديد |
| FR-023 | M | إغلاق التوقف عند restart | actual_end ومدة حقيقية ومعالجة اختلاف ETA |
| FR-024 | M | حساب downtime مع منع double count | Union of intervals حسب الوحدة والفترة |
| FR-025 | M | حالة ETA مجهول | Scenario ranges واضحة ولا رقم دقيق مصطنع |
| FR-026 | M | إلغاء/تصحيح Event بأثر تدقيقي | Forecast القديم يبقى محفوظًا |
| FR-027 | M | تنبيهات لحالات Trip وtarget risk | severity + acknowledge + owner + escalation |

## D. Analysis and reports

| ID | أولوية | المتطلب | معيار القبول الرئيسي |
|---|---|---|---|
| FR-028 | M | Actual vs Target حسب منتج/وحدة/فترة | Decimal consistent، variance/achievement |
| FR-029 | M | Production Achievement KPI | scope & denominator visible، no division by zero |
| FR-030 | M | SEC / Fuel consumption KPI | GJ/t only with approved factors؛ fallback fuel ratio separately |
| FR-031 | M | Loss Rate KPI | approved denominator, scope, fuel separate, reconciliation |
| FR-032 | M | كشف تجاوز استهلاك Chemical | planned/actual, threshold versioned |
| FR-033 | M | Charts Trends وفلاتر وحدات/فترات | baseline data freshness واضحة |
| FR-034 | M | Daily/Monthly Reports | traceable sources + approved version |
| FR-035 | M | PDF/Excel export حسب الدور | تحكم الصلاحية + audit event |
| FR-036 | M | سجل ملاحظات هندسة العمليات | attach evidence/reason/owner |

## E. AI and ongoing learning

| ID | أولوية | المتطلب | معيار القبول الرئيسي |
|---|---|---|---|
| FR-037 | C | إنشاء curated daily training dataset | لا monthly pseudo-rows، completeness gate |
| FR-038 | C | Feature extraction بنوافذ lag/rolling | point-in-time correctness tests |
| FR-039 | C | Baseline forecasting | per-product metrics on time-split |
| FR-040 | C | مقارنة Random Forest وXGBoost | same folds/features/horizon/metrics |
| FR-041 | C | Forecast لكل منتج مع period/as_of/version | immutable run + input snapshot |
| FR-042 | C | End-of-month forecast = actual-to-date + future forecast | aggregation avoids future actual leakage |
| FR-043 | C | Target achievement risk من توقع المنتج | target version explicitly linked |
| FR-044 | C | Event-aware reforecast | trigger on trip/restart/ETA/planned edits; no overwrite |
| FR-045 | C | Forecast scenario when restart unknown | assumptions recorded; not AI certainty |
| FR-046 | C | Model metrics MAE/RMSE/WAPE & downtime slices | reporting no hiding poor subgroups |
| FR-047 | C | SHAP/feature explanations | associations not causal claims |
| FR-048 | C | Model registry/versioning/checksums | rollback + reproducibility |
| FR-049 | C | Monthly retrain on newly approved data | manual approval gate before promotion |
| FR-050 | C | Drift/error monitoring | alerts threshold configurable with alerts audit |
| FR-051 | M | AI Not Ready / Degraded states | dashboards remain usable without model |

## F. Admin, audit and operational continuity

| ID | أولوية | المتطلب | معيار القبول الرئيسي |
|---|---|---|---|
| FR-052 | M | سجل مصادر وتقارير مستوردة | file hash + row/cell/page + batch + reviewed by |
| FR-053 | M | سجل موافقات وتعديلات | no silent destructive update |
| FR-054 | M | سجل Jobs مع Retry وdead-letter | async status + retries idempotent |
| FR-055 | M | تعديل عتبات التنبيه بإصدار | historical evaluation references exact version |
| FR-056 | M | health/readiness/degraded indicators | DB/queue/AI individually displayed |
| FR-057 | L | DCS قراءة فقط عبر قناة آمنة | OT approval required and isolated, no writes |
| FR-058 | L | Predictive Maintenance Model | separate sensors/labeled failures/spec and approval |

## قواعد الأعمال الحاكمة

**BR-01:** لا يمكن اعتماد إنتاج بلا وحدة قياس ومصدر وتاريخ ونطاق منتج واضح.

**BR-02:** سجل هدف Target بعد اعتماده لا يُعدّل في مكانه؛ تُنشأ نسخة هدف جديدة وتحتفظ التوقعات القديمة بالإصدار الأصلي.

**BR-03:** إذا Target=0 فإن Achievement لا يُعرض كنسبة؛ يُعرض `N/A` مع سبب.

**BR-04:** المنتجات الوسيطة والمخلوطة لا تُجمع في إجمالي واحد بلا تعريف scope يمنع double count.

**BR-05:** توليد Forecast يجب أن يحفظ وقت الطلب، المتغيرات المتاحة حينها، نسخة النموذج ونسخة الخطة.

**BR-06:** Trip لا يغير التوقعات التاريخية المصدرة؛ Reforecast يحمل parent_run_id.

**BR-07:** المواد ناقصة الجودة/المستقبلية أو المصححة دون موافقة لا تُستخدم في Training labels.

**BR-08:** عند تعطل AI Service، تبقى عمليات إدخال وتأكيد البيانات والتقارير الحسابية متاحة؛ لا تُعرض قيمة وهمية.

**BR-09:** Planned shutdown له planning version و`known_at`؛ لو أُدرج بأثر رجعي، لا يجوز إتاحته لنموذج تاريخي وقتها.

**BR-10:** ETA مجهول → سيناريوهات bounds؛ لا تُقدم central estimate بوصفها حقيقة مؤكدة.

## ربط الخصائص بالاختبارات

`FR-001–008 → SEC/IDENT`, `FR-009–018 → DATA/IMPORT`, `FR-019–027 → EVENT`, `FR-028–036 → KPI/REPORT`, `FR-037–051 → ML`, `FR-052–058 → OPS`. معايير تفصيلية في `docs/12_TESTING_AND_ACCEPTANCE.md` وملفات `specs/`.
