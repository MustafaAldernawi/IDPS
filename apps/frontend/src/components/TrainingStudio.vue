<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ApiError, rowsOf, type Api, type Row } from '../lib/api'
import { display, labels } from '../lib/presentation'
import DataTable from './DataTable.vue'
import StatusBadge from './StatusBadge.vue'
import { useAccess } from '../lib/access'
const props = defineProps<{ api: Api }>()
const can = useAccess()
const datasets = ref<Row[]>([]), runs = ref<Row[]>([]), models = ref<Row[]>([]), forecasts = ref<Row[]>([]), tab = ref('datasets'), selected = ref<Row | null>(null)
const targetMonth = ref(''), evaluationReference = ref(''), independentEvaluation = ref(false), facility = ref(''), facilities = ref<Row[]>([]), products = ref<Row[]>([])
const name = ref(''), product = ref(''), mode = ref('research_only'), datasetId = ref(''), asOf = ref(''), reason = ref(''), busy = ref(false), error = ref(''), success = ref('')
const tabs = [{ id: 'datasets', label: 'مجموعات البيانات' }, { id: 'runs', label: 'التدريب والتقييم' }, { id: 'models', label: 'سجل النماذج' }, { id: 'forecasts', label: 'التوقعات' }]
const tableRows = computed(() => ({ datasets: datasets.value, runs: runs.value, models: models.value, forecasts: forecasts.value })[tab.value] ?? [])
const columns = computed(() => ({ datasets: ['name', 'product_code', 'mode', 'status', 'created_at'], runs: ['dataset_id', 'status', 'created_at'], models: ['product_code', 'model_version', 'status', 'created_at'], forecasts: ['product_code', 'as_of', 'prediction', 'engine_type', 'status'] })[tab.value] ?? [])
async function load() {
  busy.value = true; error.value = ''
  const results = await Promise.allSettled(['/ai/datasets', '/ai/training-runs', '/ai/models', '/ai/forecasts'].map(path => props.api(path)))
  const targets = [datasets, runs, models, forecasts]
  results.forEach((result, i) => { if (result.status === 'fulfilled' && targets[i]) targets[i]!.value = rowsOf(result.value); else if (result.status === 'rejected') error.value = result.reason instanceof ApiError ? result.reason.message : 'تعذر تحميل بعض بيانات الاستوديو.' })
  busy.value = false
}
onMounted(async () => { await load(); try { facilities.value = rowsOf(await props.api('/catalogs/facilities')); products.value = rowsOf(await props.api('/catalogs/products')) } catch { /* Protected catalog selection stays empty. */ } })
async function execute(path: string, body: Row, message: string) {
  busy.value = true; error.value = ''; success.value = ''
  try { const result = await props.api<{ data: Row }>(path, { method: 'POST', body }); success.value = message; await load(); if (result.data) selected.value = result.data }
  catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر تنفيذ الطلب.' }
  finally { busy.value = false }
}
function modelAction(action: string) {
  if (!selected.value || reason.value.trim().length < 10 || !evaluationReference.value.trim() || !independentEvaluation.value) { error.value = 'أدخل سبب القرار قبل متابعة حوكمة النموذج.'; return }
  void execute(`/ai/models/${selected.value.id}/${action}`, { reason: reason.value, evaluation_reference: evaluationReference.value, confirm_independent_evaluation: independentEvaluation.value }, 'حُفظ قرار النموذج مع الأثر التدقيقي.')
}
</script>
<template><section>
  <div class="studio-header"><div><span class="eyebrow">AI TRAINING STUDIO</span><h2>تجارب قابلة للمراجعة</h2><p>بيانات محفوظة، تقييم زمني، وقرار تفعيل مستقل.</p></div><span class="badge" data-status="research_only">البحث ≠ الاعتماد التشغيلي</span></div>
  <p v-if="mode === 'research_only' || selected?.mode === 'research_only'" class="notice warning">التجربة استرجاعية بحثية وغير قابلة للترقية التشغيلية. عند غياب تواريخ الإتاحة، فإن افتراض إتاحة الأشهر السابقة غير مثبت ويُعلن صراحة؛ لا يمثل تاريخ اعتماد فعليًا.</p>
  <div class="tabs" role="tablist" aria-label="استوديو التدريب"><button v-for="item in tabs" :key="item.id" role="tab" :aria-selected="tab === item.id" :class="{ active: tab === item.id }" @click="tab = item.id; selected = null">{{ item.label }}</button></div>
  <div class="section-toolbar"><div class="inline-filters"><label>نطاق المرفق<select v-model="facility"><option value="">المرفق الوحيد المتاح</option><option v-for="item in facilities" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label></div></div><p v-if="error" role="alert" class="notice danger">{{ error }}</p><p v-if="success" role="status" class="notice positive">{{ success }}</p>
  <div v-if="tab === 'datasets' && can('dataset.write')" class="panel"><div class="panel-title"><h2>تجميد مجموعة بيانات</h2><span class="badge">Snapshot</span></div><form class="compact-form" @submit.prevent="execute('/ai/datasets', { name, product_code: product, mode, facility_id: facility || null }, 'حُفظت نسخة البيانات مع المصدر وحالة الاعتماد.')"><label>اسم التجربة<input v-model="name" required placeholder="اسم واضح للمنتج والفترة" /></label><label>المنتج<select v-model="product" required><option value="">اختر المنتج</option><option v-for="item in products" :key="String(item.code)" :value="String(item.code)">{{ item.name_ar ?? item.code }}</option></select></label><label>مصدر البيانات<select v-model="mode"><option value="research_only">بحثي — بيانات أولية معلّمة</option><option value="approved">بيانات معتمدة فقط</option></select></label><button class="button primary" :disabled="busy">إنشاء نسخة ثابتة</button></form><p class="form-note">البدء شهريًا ببيانات 2021–2023. لا تُحوّل القيم الشهرية إلى أيام أو تُملأ الفجوات بقياسات مفترضة.</p></div>
  <div v-if="tab === 'runs' && can('ai.train')" class="panel"><div class="panel-title"><h2>بدء تدريب مرشح</h2><span class="badge">مهمة خلفية</span></div><form class="compact-form" @submit.prevent="execute('/ai/training-runs', { dataset_id: datasetId }, 'سُجل طلب التدريب. تابع الحالة والنتائج بعد تنفيذ المهمة.')"><label>نسخة البيانات<select v-model="datasetId" required><option value="">اختر نسخة البيانات</option><option v-for="dataset in datasets" :key="String(dataset.id)" :value="String(dataset.id)">{{ dataset.name ?? dataset.id }} · {{ display(dataset.mode) }}</option></select></label><button class="button primary" :disabled="busy">بدء التدريب والتقييم</button></form><p class="form-note">مقارنة Baseline وRidge وRandom Forest وXGBoost على نفس التواريخ المؤهلة. اختيار المرشح من التحقق، والحفاظ على 2023 للاختبار البحثي.</p></div>
  <div v-if="tab === 'forecasts'" class="panel"><div class="panel-title"><h2>طلب توقع شهري</h2><span class="badge">M+1</span></div><form class="compact-form" @submit.prevent="execute('/ai/forecasts', { product_code: product, period_month: targetMonth, facility_id: facility || null, as_of: new Date(asOf).toISOString() }, 'حُفظ طلب التوقع ومصدره ووقت المعرفة.')"><label>المنتج<select v-model="product" required><option value="">اختر المنتج</option><option v-for="item in products" :key="String(item.code)" :value="String(item.code)">{{ item.name_ar ?? item.code }}</option></select></label><label>الشهر المستهدف<input v-model="targetMonth" type="month" required /></label><label>وقت المعرفة<input v-model="asOf" type="datetime-local" required /></label><button class="button primary" :disabled="busy">طلب توقع</button></form><p class="form-note">يظهر مصدر النتيجة بوضوح: نموذج معتمد أو خط أساس أو غير متاح. التوقعات القديمة محفوظة.</p></div>
  <div class="panel"><div class="panel-title"><h2>{{ tabs.find(item => item.id === tab)?.label }}</h2><button class="text-button" :disabled="busy" @click="load">↻ تحديث الحالة</button></div><DataTable v-if="tableRows.length" :rows="tableRows" :columns="columns" selectable @select="selected = $event" /><div v-else class="empty-state"><span class="empty-symbol">◇</span><h3>لا توجد نتائج متاحة بعد</h3><p>{{ busy ? 'جارٍ تحميل البيانات…' : 'ابدأ بإنشاء نسخة بيانات، ثم شغّل تجربة قابلة للتقييم.' }}</p></div></div>
  <div v-if="selected" class="panel"><div class="panel-title"><h2>تفاصيل {{ tab === 'models' ? 'النموذج' : 'التجربة' }}</h2><StatusBadge :value="selected.status ?? selected.mode ?? 'not_ready'" /></div><dl class="detail-grid"><div v-for="(value, key) in selected" :key="key"><dt>{{ labels[String(key)] ?? key }}</dt><dd>{{ display(value) }}</dd></div></dl><button v-if="tab === 'forecasts' && can('ai.read') && selected.status === 'completed'" class="button secondary" :disabled="busy" @click="execute(`/ai/forecasts/${selected.id}/evaluate`, {}, 'تم ربط التوقع بالفعلي المعتمد المطابق عند توفره.')">تقييم مقابل الفعلي المعتمد</button><div v-if="tab === 'models' && can('ai.promote')" class="review-actions"><p class="notice warning">لا تُفعّل ملفات البحث أو نموذجًا لم يجتز بوابة التقييم والاعتماد. فشل المرشح لا يستبدل النموذج الحالي.</p><label>مرجع التقييم المستقل<input v-model="evaluationReference" required /></label><label>سبب قرار التفعيل أو الرجوع<textarea v-model="reason" rows="3" minlength="10" /></label><label class="checkbox-label"><input v-model="independentEvaluation" type="checkbox" /> أؤكد المراجعة المستقلة لنتائج التقييم وصلاحية هذا الإصدار</label><div class="button-row"><button class="button primary" :disabled="busy" @click="modelAction('promote')">تفعيل بعد التقييم والموافقة</button><button class="button secondary" :disabled="busy" @click="modelAction('rollback')">الرجوع إلى هذا الإصدار</button></div></div></div>
</section></template>


