# التشغيل المحلي — IDPS

Laravel في `apps/backend`، Vue في `apps/frontend`، Python في `services/ai`؛ MySQL 8.4 من Laragon. لا تُعد إنشاء المشاريع.

تُحفظ الأسرار في `.env` فقط. قواعد `idps_dev` و`idps_test` منفصلة؛ لا تستخدم قاعدة أخرى للاختبارات. لا تشغّل `contracts/schema.sql` مباشرة. بيانات المصفاة ومخرجات التدريب محلية مستبعدة من Git.

أوامر التحقق من الجذر:

```powershell
python -B scripts/verify_blueprint.py
python -B scripts/verify_mysql_reference.py
```

داخل Backend: `php vendor/bin/phpunit --do-not-cache-result`.
داخل Frontend: `npm run build` و`npm run test:unit -- --run`.

نجاح هذه الفحوص لا يعني اكتمال وظائف IDPS أو اعتماد البيانات أو النماذج. راجع `docs/PROGRESS.md` للأدلة وحدود كل دفعة.

## تشغيل وإيقاف الخدمات

من جذر المشروع باستخدام PowerShell 7، بعد التأكد من وجود الاعتماديات و`.env` ومفتاح `IDPS_AI_SERVICE_KEY` المحلي:

```powershell
./scripts/local-runtime.ps1 -Action start
./scripts/local-runtime.ps1 -Action status
./scripts/local-runtime.ps1 -Action stop
```

يشغّل السكربت MySQL الخاص بـLaragon عند عدم وجود مستمع على 3306، ثم Laravel وVue وFastAPI وعامل Queue وScheduler. عند شغل أحد منافذ الويب يستخدم المستمع الموجود ويطبع تنبيهًا؛ تحقق من هوية العملية و`http://127.0.0.1:8000/api/v1/health`. السجلات وبيانات تعريف العمليات تحفظ في `.local/runtime/`، ولا تطبع المفاتيح. الإيقاف يستهدف العمليات التي أطلقها السكربت فقط بعد مطابقة PID ومسار البرنامج ووقت البداية؛ MySQL مشترك مع Laragon ويظل قيد التشغيل. بعد إعادة تشغيل Windows قد تحتاج إلى إعادة تشغيل السكربت.

إذا ظهرت خدمة `down` فتحقق من السجل المناسب والاعتماديات والمنفذ. إذا كانت AI `unavailable` فتحقق من تساوي مفتاح الخدمة المحلي بين Laravel وFastAPI. إذا تعلقت المهام، تحقق من عامل queue ومن إعداد `DB_QUEUE_RETRY_AFTER` (300 ثانية) وعدم تشغيل عامل بتوقيت انتهاء أطول منه. يجب استمرار Scheduler لتفريغ outbox دوريًا.

## نسخة قاعدة البيانات واستعادتها للاختبار

```powershell
./scripts/local-backup.ps1 -Action create
./scripts/local-backup.ps1 -Action test-restore -BackupPath 'E:\IDPS\.local\backups\اسم-النسخة.dpapi'
```

النسخة تضم قاعدة `idps_dev` فقط وتُشفر باستخدام Windows DPAPI لحساب Windows الحالي؛ لذلك لا يمكن افتراض إمكانية فكها بحساب أو جهاز آخر. تُخزن في `.local/backups` المستبعدة من Git. نسخة قاعدة البيانات وحدها ليست نسخة كاملة للمنظومة؛ أنشئ أيضًا نسخة الملفات:

```powershell
./scripts/local-files-backup.ps1 -Action create
./scripts/local-files-backup.ps1 -Action test-restore -BackupPath 'E:\IDPS\.local\backups\اسم-نسخة-الملفات.dpapi'
```

تشمل نسخة الملفات `data/raw-sources` ومرفقات `apps/backend/storage/app/private` وملفات `.local/ai-artifacts` الموجودة عند النسخ. تحتوي على manifest داخلي ببصمات SHA256، وتُشفر باستخدام DPAPI للحساب الحالي. يعيد الاختبار كل ملف إلى مجلد عشوائي تحت `.local/restore-files`، ويتحقق من البصمات ويرفض مسارات الخروج من المجلد؛ لا يستبدل الأصول. توقف عن رفع الملفات وتوليد artifacts أثناء النسخ: نسختا قاعدة البيانات والملفات منفصلتان وليستا snapshot ذرية، لذا يلزم مطابقة بصمات الملفات المشار إليها في قاعدة الاستعادة قبل اعتبار الحزمة متسقة. لا تشمل النسخة ملفات الأسرار أو إعدادات البيئة؛ تتطلب المفاتيح وخطة نقلها واسترجاعها إجراءات معتمدة مستقلة. السكربت يحجز حتى 256 MiB من الملفات في الذاكرة قبل التشفير؛ عند تجاوز الحد استخدم حل نسخ مشفرًا يسمح بالبث.

اختبار الاستعادة ينشئ قاعدة جديدة باسم عشوائي `idps_restore_...`، ويتحقق من الجداول ومن سلامة مراجع مصدر القياسات. يترك قاعدة الاختبار للمراجعة؛ لا يحذف أو يستبدل قاعدة موجودة. يتطلب صلاحية إنشاء قاعدة من حساب إعداد Laragon (`IDPS_SETUP_USER` و`IDPS_SETUP_PASSWORD` عند الحاجة)، ولا يطبعها. نجاح الفحص لا يغني عن مقارنة نتائج الأعمال واستعادة الملفات والنماذج واختبار الاسترجاع الدوري في البيئة المعتمدة.
