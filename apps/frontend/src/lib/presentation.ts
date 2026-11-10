export const labels: Record<string, string> = {
  email: 'البريد الإلكتروني', active: 'مفعّل', user_id: 'المستخدم', role_id: 'الدور', stage: 'مرحلة المنتج', dimension: 'البعد الفيزيائي', display_symbol: 'رمز العرض', comparisons: 'الفعلي والمستهدف', kpis: 'مؤشرات الأداء', data_status: 'حالة البيانات', generated_at: 'تاريخ التقرير', period_month: 'شهر التقرير', source_status: 'اعتماد المصدر', result_json: 'نتائج التقييم', evaluation_json: 'نتائج التقييم المستقل',
  title: 'عنوان الخطة', crude_id: 'الخام', crude_code: 'الخام', carrier_code: 'حامل الطاقة', category_code: 'تصنيف الفاقد', chemical_code: 'المادة الكيميائية', parameter_code: 'خاصية الاختبار', batch_code: 'دفعة الخلط', event_type: 'نوع الحدث', known_at: 'وقت المعرفة', components: 'مكونات الخلط', targets: 'المستهدفات', spec_version: 'إصدار المواصفة', spec_min: 'الحد الأدنى', spec_max: 'الحد الأعلى',
  id: 'المعرف', name: 'الاسم', name_ar: 'الاسم', code: 'الرمز', status: 'الحالة', period: 'الفترة', business_date: 'تاريخ العمل',
  product_code: 'المنتج', metric_code: 'المقياس', item_code: 'الصنف', quantity: 'الكمية', unit_code: 'الوحدة', actual_or_target: 'نوع القياس',
  source_reference: 'مرجع المصدر', source_file_id: 'معرف المصدر', revision_no: 'المراجعة', revision: 'المراجعة', created_at: 'تاريخ التسجيل',
  approved_at: 'تاريخ الاعتماد', available_at: 'تاريخ الإتاحة', facility_id: 'المرفق', unit_id: 'الوحدة التشغيلية', product_id: 'المنتج',
  actual: 'الفعلي المعتمد', target: 'المستهدف المعتمد', variance: 'الانحراف', achievement_pct: 'نسبة التحقيق', value: 'القيمة', value_unit: 'وحدة المؤشر',
  reason: 'السبب', notes: 'ملاحظات', type: 'النوع', kind: 'التصنيف', started_at: 'بداية الحدث', expected_end_at: 'العودة المتوقعة', actual_end_at: 'العودة الفعلية',
  dataset_id: 'مجموعة البيانات', dataset_sha256: 'بصمة البيانات', product: 'المنتج', time_granularity: 'المستوى الزمني', prediction_horizon: 'أفق التوقع',
  as_of: 'وقت المعرفة', engine_type: 'محرك التوقع', prediction: 'التوقع', quality_warning: 'تنبيه الجودة', metrics: 'نتائج التقييم', mode: 'وضع البيانات',
  actor_id: 'الفاعل', action: 'الإجراء', resource_type: 'نوع السجل', resource_id: 'السجل', occurred_at: 'وقت الإجراء',
  sha256: 'بصمة المصدر', original_name: 'اسم المصدر', row_count: 'عدد الصفوف', rows_count: 'عدد الصفوف', error_count: 'عدد الأخطاء',
  report_status: 'حالة التقرير', qa_flags: 'ملاحظات الجودة', flags: 'ملاحظات الجودة', approval_status: 'حالة الاعتماد',
  model_version: 'إصدار النموذج', horizon: 'الأفق', source_approval_status: 'اعتماد المصدر', can_deploy: 'مؤهل للتفعيل',
}
export const states: Record<string, string> = {
  draft: 'مسودة', submitted: 'بانتظار الاعتماد', approved: 'معتمد', rejected: 'مرفوض', staged: 'قيد المراجعة', flagged: 'يتطلب مراجعة',
  reviewed: 'تمت المراجعة', superseded: 'له إصدار أحدث', queued: 'في الانتظار', running: 'قيد التنفيذ', completed: 'مكتمل', failed: 'تعذر التنفيذ',
  candidate: 'مرشح للتقييم', active: 'مفعّل', archived: 'مؤرشف', not_ready: 'غير جاهز', ML_NOT_READY: 'النموذج غير جاهز',
  research_only: 'بحثي فقط', research: 'بحثي', operational: 'تشغيلي', PROVISIONAL: 'أولي غير معتمد', actual: 'فعلي', target: 'مستهدف',
  semi_actual: 'شبه فعلي', cumulative: 'تراكمي', monthly: 'شهري', month: 'شهري', daily: 'يومي', shift: 'وردية',
  statistical_baseline: 'خط أساس إحصائي', engineering_baseline: 'خط أساس هندسي', ml_model: 'نموذج تعلم آلي', scenario_estimate: 'سيناريو افتراضي',
  unavailable: 'غير متاح', insufficient_data: 'بيانات غير كافية', planned: 'مخطط', unplanned: 'غير مخطط', trip: 'توقف مفاجئ',
  pending_review: 'بانتظار التعريف الهندسي', definition_pending: 'تعريف المؤشر قيد الاعتماد', product_scope_required: 'يلزم نطاق منتج معتمد', missing_actual: 'الفعلي غير متاح', missing_target: 'المستهدف غير متاح', zero_target: 'المستهدف صفر — النسبة غير معرفة', valid: 'صالح ضمن النطاق', approved_only: 'معتمد فقط', missing_or_zero_denominator: 'المقام مفقود أو صفر', mass: 'كتلة', volume: 'حجم', energy: 'طاقة', time: 'زمن', ratio: 'نسبة', quality: 'جودة',
}
export function display(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  if (typeof value === 'boolean') return value ? 'نعم' : 'لا'
  if (typeof value === 'number') return new Intl.NumberFormat('ar-LY', { maximumFractionDigits: 4 }).format(value)
  if (Array.isArray(value)) return value.length ? value.map(display).join('، ') : '—'
  if (typeof value === 'object') return Object.entries(value).map(([k, v]) => `${labels[k] ?? k}: ${display(v)}`).join(' · ')
  return states[String(value)] ?? String(value)
}
