# SPEC.md — IDPS Master Product & Engineering Specification (v1.2)

**اسم المشروع:** تصميم منظومة ذكية لتكامل البيانات وتحليل الإنتاج النفطي وحساب مؤشرات الأداء  
**IDPS — Integrated Data and Production System**  
**تاريخ النسخة:** 2026-11-09 | **قاعدة البيانات:** MySQL 8.4 LTS | **حالة التطوير:** Design + historical data audit + research AI starter؛ لا يوجد نشر إنتاجي.

## 1. المنتج ومشكلة الأعمال

منظومة دعم قرار لمصفاة الزاوية توحّد الخام والإنتاج والمستهدفات والوقود والفواقد والكيماويات والجودة والتوقفات في سجل مصدره معروف، وتنتج مقارنة Actual/Target، وKPIs، وتقارير، وتوقعات يمكن تقييمها ومراجعتها، من دون التحكم في أنظمة التشغيل الصناعية.

## 2. الأدوار

| الجهة | في النظام | حدود التعديل |
|---|---|---|
| قسم الإنتاج | إدخال الخام والإنتاج اليومي/الشهري وحالة الوحدات والتوقفات والأحداث | مسودات وإحالة للمراجعة؛ لا تغيير السجلات المعتمدة صامتًا |
| هندسة العمليات | مراجعة الأرقام وموازنة الكتلة وYield وEnergy/Losses/Chemicals/Blending وKPIs وتحليل التوقعات | اعتماد القياسات وفق الصلاحية وتوثيق أسباب الانحراف |
| قسم التخطيط | المستهدفات الشهرية/السنوية وخطط التوقف والتحميل وسيناريوهات الأفق القادم | Plan versioned ومواعيد معلنة |
| إدارة التشغيل | لوحة متابعة واعتماد قرارات/تقارير وتلقي تنبيهات | لا وصول مباشر لكتابة DCS/PLC |
| المختبر/الصيانة/HSE | نتائج الجودة وأحداث الصيانة والسلامة والملاحظات حسب الصلاحية | إدارة سجلات نطاق تخصصها فقط |
| مسؤول AI | رفع Dataset وإجراء التجارب والتقييم ومراقبة النماذج | لا تفعيل دون موافقة أو تجاوز اعتماد البيانات |

## 3. الوحدات الإلزامية ضمن V1

**Platform/RBAC**, **Master Data**, **Source Register/Imports**, **Actual Production/Feed**, **Targets & Planning**, **Operational Events & Shutdowns**, **Yield/Blending/Quality/Chemicals**, **Energy & Losses**, **3 KPIs**, **Reports/Alerts**, **AI Dataset Studio**, **Training/Evaluation/Model Registry**, **Forecast/Scenario/Reforecast**, **Monitoring/Retraining**, **Audit**.

### 3 KPIs الرئيسية

1. `Production Target Achievement = actual_products / target_products * 100` ضمن نفس المجال والفترة وتعريف المنتجات؛ `target=0`→ N/A.
2. `Specific Energy Consumption (GJ/t)` إذا تم توثيق قيمة حرارة الوقود/الكهرباء؛ وإلا `Fuel Consumption Ratio (t fuel / t crude)` باسم واضح ولا تسمَّه GJ/t.
3. `Refinery Loss Rate = approved loss / approved processed crude * 100` بعد التحقق من حدود الفاقد والفlaring وموازنة الكتلة.

التعريفات النهائية تعتمدها هندسة العمليات؛ لا تدمج منتجات البيع بعد blending مع streams الداخلية في KPI واحد دون قاعدة معتمدة.

## 4. البيانات الفعلية المؤكدة حاليًا

- **2021 و2022 و2023**: 3 Excel شهري، **36 صفًا × 35 عمودًا** في `data/curated-provisional/IDPS_monthly_2021_2023_PROVISIONAL.csv`، تغطي الخام الفعلي والمستهدف و6 منتجات، الوقود والفواقد. حالة البيانات `PROVISIONAL` حتى اعتمادها هندسيًا.
- **2022 Dec**: تقرير إنتاج شهري يذكر Trip بسبب انقطاع الكهرباء بتاريخ 2022-12-28 لكن **المدة غير موثقة كقيمة يمكن إدخالها في ML**.
- **2023**: مستهدفات شهرية مستقلة؛ اختلاف بين بعض أرقام Excel والتقرير السنوي `semi-actual` يتطلب reconciliation.
- **2024**: عرض سنوي (aggregated) لا يضاف كـ 12 صفًا؛ **2025**: تراكمي حتى أغسطس + تقرير ديسمبر، وليس سنة شهرية كاملة.
- **Assays** للشرارة/الحمادة: مرجع نوعي ثابت وفق تاريخ أخذ عينة التقرير، لا يُضاعف مع كل شهر كقيمة متغيرة دون توثيق.

## 5. دورة بيانات النظام

`raw file (SHA) → import batch → field mapping → staging rows → quality flags/reconciliation → engineering approval → immutable curated snapshot → KPI reporting / eligible AI training`. في V1 يجب أن يقدر الوكيل تحميل ملف جديد بنفس السلسلة والتعامل مع فجوات 2024–2025 كـ missing وليس zero.

## 6. الذكاء الاصطناعي ضمن V1 (غير مؤجل)

**AI Training Studio موجود وقابل للاستخدام:** dataset upload CSV/XLSX، mapping، profiling، validation، snapshot SHA256، target/granularity/horizon selection، feature building دون تسرب، baselines، Ridge/RandomForest/XGBoost، rolling/time split، MAE/RMSE/WAPE، model registry، comparison، manual promotion، forecast API، retraining on approved new observations.

**الآن:** توجد تجارب محلية بحثية على 36 شهرًا، نتائجها `RESEARCH_ONLY` في `services/ai/research_starter/results/`. ليست نماذج مفعّلة للتشغيل؛ لا تدّع أن XGBoost متفوق دائمًا. التوقع الأولي شهري، وبديل Baseline هندسي/إحصائي صريح عند عدم تأهل ML. الأيام/الورديات مستقبلًا حسب ما يرد من قياسات حقيقية.

## 7. Dynamic Reforecasting

الخطط المعروفة مسبقًا تؤخذ كـ assumptions، والتوقفات المفاجئة تحدث event عند اكتشافها؛ بعدها إصدار forecast جديد لا يمس القديم. ETA مجهولة → سيناريوهات متعددة موضحة. التوقف غير المسجّل لا يُتنبأ به بشكل موثوق دون مستشعرات وصيانة تنبؤية؛ Predictive Maintenance خارج V1.

## 8. التقنيات والعقود

- Laravel + MySQL 8.4 InnoDB + Vue 3/TS + Python FastAPI.
- Canonical SQL blueprint: `contracts/schema.sql` **مرجعي، غير مجرّب على instance حي ولا يغني عن Laravel migrations**.
- API draft: `contracts/openapi.yaml`، يحتاج تحديثًا وتأكيدًا أثناء التنفيذ. لا تتغير حقول الأعمال دون versioning/consent.
- `services/ai/research_starter` كود تجريبي، **ليس تطبيق FastAPI متصلًا**.

## 9. شروط قبول V1

- مصدر كل قيمة وحالة مراجعتها وتاريخها قابل للتتبع؛ import idempotent وتصحيحات revisioned.
- اختبار KPI متطابق مع تقارير معتمدة، مع سياسة معالجة فروق التجميع وNull/zero ووحدات القياس.
- واجهات عمل الأقسام، موافقات وحجب وصول 401/403 وaudit.
- Train/eval على Dataset حقيقية بعد اعتمادها مع تجميد Test وعدم تسرب؛ small data غير كافية = Research mode وليس deployment.
- Workflow توقف مخطط/مفاجئ/ETA مجهول يسجل Reforecast محفوظًا وسيناريو واضحًا.
- إطلاق تقرير شهري وإمكانية تصدير البيانات بعد الصلاحية، مع التشغيل الآمن والتراجع والنسخ الاحتياطي.

## 10. وثائق ملزمة

`PLAN.md`, `TASKS.md`, `docs/22_DATASET_AI_V1_2_DECISIONS.md`, `docs/23_REAL_DATA_INTEGRATION_SPEC.md`, `docs/24_AI_TRAINING_REAL_DATA_SPEC.md`, `docs/25_SOURCE_QUALITY_AND_CONFLICTS.md`, `planning/BACKLOG.csv`, `specs/*`, `services/ai/research_starter/README.md`.
