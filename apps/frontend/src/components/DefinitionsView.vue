<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ApiError, rowsOf, type Api, type Row } from '../lib/api'
import { useAccess } from '../lib/access'
import { display, labels } from '../lib/presentation'
import DataTable from './DataTable.vue'
const props = defineProps<{ api: Api }>()
const can = useAccess(), definitions = ref<Row[]>([]), facilities = ref<Row[]>([]), units = ref<Row[]>([]), products = ref<Row[]>([]), measurementUnits = ref<Row[]>([]), selected = ref<Row | null>(null)
const model = reactive({ facility_id: '', unit_id: '', code: '', title: '', effective_from: '', effective_until: '', source_reference: '', reason: '', loss_category_code: '' })
const productIds = ref<string[]>([]), approvalReason = ref(''), busy = ref(false), error = ref(''), success = ref('')
const factors = reactive([{ carrier_code: '', input_unit_code: '', gj_per_unit: '', basis: '', source_reference: '' }])
const formulaCodes: Record<string, string> = { PRODUCTION_TARGET_ACHIEVEMENT: 'production_achievement_pct', SPECIFIC_ENERGY_CONSUMPTION: 'specific_energy_gj_per_t', REFINERY_LOSS_RATE: 'unaccounted_loss_pct' }
const selectedProducts = computed(() => products.value.filter(p => productIds.value.includes(String(p.id))))
async function load() { try { definitions.value = rowsOf(await props.api('/engineering/kpi-definitions')) } catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر تحميل تعريفات المؤشرات' } }
onMounted(async () => { await load(); for (const [key, target] of [['facilities', facilities], ['units', units], ['products', products], ['measurement-units', measurementUnits]] as const) { try { target.value = rowsOf(await props.api(`/catalogs/${key}`)) } catch { /* Denied catalogs must stay empty. */ } } })
async function save() {
  if (model.reason.trim().length < 10) { error.value = 'أدخل سببًا موثقًا للتعريف من عشرة أحرف على الأقل.'; return }
  busy.value = true; error.value = ''; success.value = ''
  const body: Row = { ...model, unit_id: model.unit_id || null, effective_until: model.effective_until || null, formula_code: formulaCodes[model.code] }
  if (model.code === 'PRODUCTION_TARGET_ACHIEVEMENT') body.product_ids = productIds.value
  if (model.code === 'SPECIFIC_ENERGY_CONSUMPTION') body.energy_factors = factors.map(factor => ({ ...factor }))
  try { await props.api('/engineering/kpi-definitions', { method: 'POST', body }); success.value = 'حُفظ تعريف جديد بانتظار اعتماد مستقل. لم تتغير الحسابات المعتمدة تلقائيًا.'; await load() }
  catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر حفظ التعريف' }
  finally { busy.value = false }
}
async function approve() {
  if (!selected.value || approvalReason.value.trim().length < 10) { error.value = 'وثّق سبب الموافقة المستقلة أولًا.'; return }
  busy.value = true
  try { await props.api(`/engineering/kpi-definitions/${selected.value.id}/approve`, { method: 'POST', body: { reason: approvalReason.value } }); selected.value = null; success.value = 'حُفظ اعتماد التعريف وإصداره والأثر التدقيقي.'; await load() }
  catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر اعتماد التعريف' }
  finally { busy.value = false }
}
</script>
<template><section><p v-if="error" class="notice danger" role="alert">{{ error }}</p><p v-if="success" class="notice positive" role="status">{{ success }}</p>
  <div v-if="can('production.approve')" class="panel"><div class="panel-title"><h2>اقتراح تعريف هندسي بإصدار جديد</h2><span class="badge">اعتماد مستقل مطلوب</span></div><form class="record-form" @submit.prevent="save"><div class="form-grid"><label>المرفق<select v-model="model.facility_id" required @change="model.unit_id = ''"><option value="">اختر المرفق</option><option v-for="item in facilities" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label><label>الوحدة التشغيلية<select v-model="model.unit_id"><option value="">نطاق المرفق</option><option v-for="item in units.filter(row => row.facility_id === model.facility_id)" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label><label>المؤشر<select v-model="model.code" required><option value="">اختر المؤشر</option><option value="PRODUCTION_TARGET_ACHIEVEMENT">تحقيق مستهدف الإنتاج</option><option value="SPECIFIC_ENERGY_CONSUMPTION">الاستهلاك النوعي للطاقة GJ/t</option><option value="REFINERY_LOSS_RATE">معدل فاقد المصفاة</option></select></label><label>اسم التعريف<input v-model="model.title" required /></label><label>ساري من<input v-model="model.effective_from" type="date" required /></label><label>ساري حتى — اختياري<input v-model="model.effective_until" type="date" /></label><label class="wide">مرجع التعريف الهندسي<input v-model="model.source_reference" required /></label>
  <div v-if="model.code === 'PRODUCTION_TARGET_ACHIEVEMENT'" class="wide"><h3>مجموعة المنتجات غير المتداخلة</h3><p class="muted">حدد المجموعة التي اعتمد أصحاب الاختصاص إمكانية جمعها دون تكرار تيارات الخلط.</p><div class="choice-grid"><label v-for="item in products" :key="String(item.id)" class="checkbox-label"><input v-model="productIds" type="checkbox" :value="String(item.id)" />{{ item.name_ar ?? item.code }}</label></div><small class="muted">{{ selectedProducts.length }} منتجات محددة</small></div>
  <label v-if="model.code === 'REFINERY_LOSS_RATE'">تصنيف الفاقد المعتمد<input v-model="model.loss_category_code" required placeholder="رمز الفاقد وفق القاموس المعتمد" /></label>
  <div v-if="model.code === 'SPECIFIC_ENERGY_CONSUMPTION'" class="wide"><h3>معاملات تحويل الطاقة الموثقة</h3><div v-for="(factor, index) in factors" :key="index" class="factor-grid"><label>حامل الطاقة<input v-model="factor.carrier_code" required /></label><label>وحدة المدخل<select v-model="factor.input_unit_code" required><option value="">اختر الوحدة</option><option v-for="unit in measurementUnits" :key="String(unit.code)" :value="String(unit.code)">{{ unit.display_symbol ?? unit.code }}</option></select></label><label>GJ لكل وحدة<input :value="factor.gj_per_unit" type="number" step="any" min="0" required @input="factor.gj_per_unit = ($event.target as HTMLInputElement).value" /></label><label>أساس التحويل<select v-model="factor.basis" required><option value="">اختر الأساس</option><option value="LHV">القيمة الحرارية الدنيا LHV</option><option value="HHV">القيمة الحرارية العليا HHV</option><option value="site_electricity">كهرباء — طاقة الموقع</option><option value="source_electricity">كهرباء — طاقة المصدر</option></select></label><label>مرجع معامل التحويل<input v-model="factor.source_reference" required /></label><button v-if="factors.length > 1" class="text-button" type="button" @click="factors.splice(index, 1)">إزالة المعامل</button></div><button type="button" class="text-button" @click="factors.push({ carrier_code: '', input_unit_code: '', gj_per_unit: '', basis: '', source_reference: '' })">+ إضافة حامل طاقة</button></div>
  <label class="wide">مبرر التعريف أو تحديثه<textarea v-model="model.reason" required minlength="10" rows="3" /></label></div><div class="form-footer"><p>لا تطبق المعاملات قبل مراجعة المصادر والاعتماد المستقل.</p><button class="button primary" :disabled="busy">إرسال التعريف للمراجعة</button></div></form></div>
  <div class="panel"><div class="panel-title"><h2>التعريفات والإصدارات</h2><button class="text-button" @click="load">↻ تحديث</button></div><DataTable v-if="definitions.length" :rows="definitions" :columns="['title', 'code', 'version', 'effective_from', 'status']" selectable @select="selected = $event" /><div v-else class="empty-state"><h3>لا توجد تعريفات هندسية معتمدة بعد</h3><p>تظل المؤشرات غير المعرفة معلّمة بالحاجة إلى اعتماد، دون معاملات افتراضية.</p></div></div>
  <div v-if="selected" class="panel"><div class="panel-title"><h2>مراجعة تعريف المؤشر</h2></div><dl class="detail-grid"><div v-for="(value, key) in selected" :key="key"><dt>{{ labels[String(key)] ?? key }}</dt><dd>{{ display(value) }}</dd></div></dl><form v-if="selected.status === 'pending' && can('planning.approve')" class="review-actions" @submit.prevent="approve"><label>سبب الاعتماد المستقل<textarea v-model="approvalReason" required minlength="10" rows="3" /></label><button class="button primary" :disabled="busy">اعتماد هذا الإصدار</button></form></div>
</section></template>
