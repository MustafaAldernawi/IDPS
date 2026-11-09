# IDPS — AI Monthly Training Starter / المرحلة التنفيذية الأولى

**المشروع:** تصميم منظومة ذكية لتكامل البيانات وتحليل الإنتاج النفطي وحساب مؤشرات الأداء — IDPS (Integrated Data and Production System)

**الوضع:** Proof-of-implementation محلي حقيقي على بيانات 2021–2023 الشهرية، وليس نموذج تشغيل معتمدًا.

- بيانات خام: `data/monthly_2021_2023_PROVISIONAL.csv` — 36 شهرًا و6 منتجات، مستمدة من الملفات التي وفرتها المصفاة. لم تُنشأ أي أشهر أو قياسات يومية اصطناعية.
- الكود: `idps_ai/pipeline.py` يتضمن فحص البيانات، هندسة ميزات متاحة زمنيًا، تدريب Ridge / Random Forest / XGBoost، واختبارات زمنية وبوابات قبول.
- النتائج: `results/research_report.json` و`results/holdout_2023_predictions.csv`، مع ملفات ML غير معتمدة `*_RESEARCH_ONLY.joblib`.
- التعلم لاحقًا: `cli.py stage` للأشهر الجديدة دون إقرار؛ `cli.py retrain-approved` للتصدير المحكوم بالصلاحيات بعد اعتماد البيانات.
- تصميم MySQL: `sql/01_monthly_observation_staging_mysql84.sql` مسودة إضافية إلى الحزمة الأصلية. مخطط IDPS المركزي في الحزمة السابقة يضم `dataset_snapshots`, `model_versions`, `training_runs`, `forecast_runs`.
- خطة الربط: `docs/IMPLEMENTATION_PLAN_AR.md`.
- تعليمات الوكيل: `AGENTS.md`.

## التشغيل
```bash
python -m pip install -r requirements.txt
pytest -q
python cli.py train
```

لإضافة شهر جديد حقيقي:
```bash
python cli.py stage --new /path/to/new_approved_candidate_month.csv
```

بعد موافقة إدارة هندسة العمليات وتنفيذ تصدير آمن من MySQL، يمكن تشغيل اختبار إعادة التدريب:
```bash
python cli.py retrain-approved --dataset /secure/approved_months_export.csv
```

**لا توجد قاعدة MySQL حية أو Laravel UI/API/Queue مشغلة في هذه الحزمة.** الربط آلية تنفيذ لاحقة في مستودع IDPS الرئيسي. لا يمكن اعتبار كلمة `approved` في CSV وحدها إثبات موافقة؛ المسار التشغيلي يجب أن يعتمد تسجيل الدخول والصلاحيات والـAudit.

**التنبؤ الحالي:** Next-month monthly forecasting research only. استخدمنا فقط الإنتاج والخام السابقين والأشهر التقويمية، ولم نستخدم Actual من شهر التنبؤ نفسه. لذلك النتائج لا تدعي معرفة التوقفات المفاجئة قبل حدوثها.

**دقة النماذج:** الأرقام في `research_report.json` تجريبية على 12 شهرًا من 2023. اختيار مرشح في 2022 لا يثبت تفوقه لاحقًا. لا تنقل ملفات النماذج إلى Production بلا مراجعة هندسية وتقييم موسع.
