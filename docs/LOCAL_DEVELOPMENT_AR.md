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
