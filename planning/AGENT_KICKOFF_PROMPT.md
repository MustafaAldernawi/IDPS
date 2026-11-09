# IDPS / Codex–Antigravity — kickoff prompt v1.2

أنت الوكيل البرمجي المسؤول عن تنفيذ **IDPS — Integrated Data and Production System** (مصفاة الزاوية). المرجع الإلزامي `AGENTS.md`, `SPEC.md`, `PLAN.md`, `TASKS.md` ثم `docs/22..26`. قاعدة البيانات **MySQL 8.4 LTS** ولا يجوز استخدام PostgreSQL. التقنية Laravel + Vue 3 TS + FastAPI/Python. نطاق V1 يشمل AI Dataset Studio وTraining/Evaluation/Registry/Forecast/Retraining بصورة حقيقية، ليس مجرد placeholder.

**المتوفر فعلًا:** `data/raw-sources` (المصادر الأصلية)، `data/curated-provisional/IDPS_monthly_2021_2023_PROVISIONAL.csv` (36 صفًا شهريًا، 35 عمودًا)، flags/audit؛ `services/ai/research_starter` كود بحثي ونتائج `RESEARCH_ONLY` غير معتمدة للتشغيل. لا توجد بيانات يومية تاريخية كافية مؤكدة، ولا أريد تأجيل تدريب المرشحين البحثيين أو واجهة Training Studio إلى V2. لا تفترض وجود Backend/DB deployed.

**المطلوب الآن وقبل أي تنفيذ:**
1. افحص شجرة الملفات والعقود والمصادر الحالية، وقدّم تقريرًا بأجزاء التطبيق القائمة والناقصة.
2. أعطني **خطة تفصيلية قبل كتابة أي كود**: التذاكر، الملفات، MySQL migrations والتراجع، الـAPIs، الواجهات، اختيار حالة البيانات، مراقبة التكرارات والتعارضات، اختبارات وحدة/دمج.
3. أول مسار تنفيذي: أساس Laravel MySQL + auth/RBAC ثم استيراد 2021–2023 إلى staging بمصدر وrevision وQA؛ لا تعتمد الملف `PROVISIONAL` تلقائيًا؛ أعطني تقرير reconciliation.
4. المسار التالي: Dataset Studio يستقبل Excel/CSV وTraining Studio يربط Python runner ويقارن Ridge/RF/XGB بالـbaselines على monthly chronological split، ويسجل metrics/registry مع `research_only` حتى قرار الترقية.
5. لاحقًا: Actuals جديدة مع approval ثم retraining gated، event-aware forecasts وأساس قسم الإنتاج وهندسة العمليات والتخطيط.

**قيود:** لا تولّد أيامًا من مجاميع أشهر، لا تفترض ساعات توقف 2022-12-28، لا تخلط annual/cumulative 2024/2025 مع الشهري، لا تفعّل نموذج بحثي كتشغيلي، لا DCS write controls. كل تعديل لازم اختبارات وdiff وصيغة مراجعة.

ابدأ برد **الخطة والملفات التي ستعدلها والتبعيات فقط**، وبعد الموافقة نفذ أول vertical slice. لا تنفذ نشرًا على سيرفر الإنتاج.
