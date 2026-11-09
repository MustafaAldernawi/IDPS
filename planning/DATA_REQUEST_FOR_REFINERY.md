# طلب بيانات تشغيلية — Template للمراجعة الداخلية

## الغرض

تجميع البيانات المطلوبة لتحليل الإنتاج والتوقفات والتنبؤ اليومي ضمن مشروع نظام دعم قرار لمصفاة الزاوية، دون طلب أي قدرة للتحكم في أنظمة التشغيل.

## الحد الأدنى المفضل (تاريخ 3 سنوات)

- Daily production by product/stage/unit (طن متري، التاريخ واليوم التشغيلي)، actual approved.
- Crude processed by crude grade and refining unit, including full and partial outages.
- Unit operating hours, runtime calendar and planned shutdown schedules with approved versions.
- Unexpected trips: affected unit, start, actual end, event type, ETA changes if historically logged.
- Fuel gas/fuel oil measured quantities; imported electricity measured kWh; loss categories and basis.
- Historical targets per product/month and revisions, if available.
- Chemicals/by-product and blend component data as supplementary data.

## Metadata needed

Field definitions, unit scale, timestamp timezone, data owner, original file provenance, review/approval status, product taxonomy (refinery vs final blend), any known missing or corrected periods. Please supply sample 30 days first (including at least one downtime interval, if permitted).

## Privacy/security

Secure internal delivery only. Anonymize staff identifiers; no credentials, IPs or control-network topology needed. DCS/SCADA commands and write access are **not requested**.

## Caveat

The existing 2021 workbook contains monthly Actual/Target; a set of three similarly formatted workbooks does not produce an adequate daily sequence. Detailed day-level data must be requested separately where possible.
