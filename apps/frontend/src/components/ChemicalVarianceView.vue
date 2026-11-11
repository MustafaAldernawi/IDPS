<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ApiError, rowsOf, type Api, type Row } from '../lib/api'
import { useAccess } from '../lib/access'

const props = defineProps<{ api: Api }>()
const can = useAccess()
const facilities = ref<Row[]>([]), units = ref<Row[]>([]), measures = ref<Row[]>([]), targets = ref<Row[]>([]), result = ref<Row[] | null>(null)
const facility = ref(''), unit = ref(''), month = ref(''), chemical = ref(''), measure = ref(''), quantity = ref(''), source = ref(''), available = ref(''), replaces = ref(''), reason = ref(''), error = ref(''), busy = ref(false)
const scopedUnits = computed(() => units.value.filter(row => row.facility_id === facility.value))
const message = (e: unknown) => e instanceof ApiError ? e.message : 'تعذر إتمام العملية. تحقق من الاتصال والصلاحيات.'
async function refreshTargets() { if (can('planning.read')) targets.value = rowsOf(await props.api('/chemical-targets')) }
onMounted(async () => {
  try {
    const [f, u, m] = await Promise.all([props.api('/catalogs/facilities'), props.api('/catalogs/units'), props.api('/catalogs/measurement-units')])
    facilities.value = rowsOf(f); units.value = rowsOf(u); measures.value = rowsOf(m); await refreshTargets()
  } catch (e) { error.value = message(e) }
})
async function save() {
  error.value = ''; busy.value = true
  try {
    await props.api('/chemical-targets', { method: 'POST', body: { facility_id: facility.value, unit_id: unit.value || null, period_month: month.value, chemical_code: chemical.value, unit_code: measure.value, quantity: quantity.value, source_reference: source.value, available_at: new Date(available.value).toISOString(), replaces_id: replaces.value || null } })
    await refreshTargets(); result.value = null
  } catch (e) { error.value = message(e) } finally { busy.value = false }
}
async function approve(id: unknown) {
  error.value = ''; busy.value = true
  try { await props.api(`/chemical-targets/${id}/approve`, { method: 'POST', body: { reason: reason.value } }); await refreshTargets(); result.value = null }
  catch (e) { error.value = message(e) } finally { busy.value = false }
}
async function report() {
  error.value = ''; busy.value = true; result.value = null
  try { result.value = rowsOf(await props.api(`/engineering/chemical-variance?${new URLSearchParams({ facility_id: facility.value, period_month: month.value })}`)) }
  catch (e) { error.value = message(e) } finally { busy.value = false }
}
const statusText: Record<string, string> = { valid: 'مقارنة مكتملة', missing_target: 'المستهدف المعتمد غير متاح', missing_actual: 'الفعلي المعتمد غير متاح', ambiguous_actual: 'سجلات فعلية متعددة تحتاج مراجعة', draft: 'مسودة', approved: 'معتمد', superseded: 'استُبدل بإصدار' }
</script>

<template>
  <section>
    <p class="notice">يُقارن المستهدف المعتمد بالفعلي الشهري المعتمد للنطاق ووحدة القياس نفسيهما. السجلات اليومية أو المتعددة تحتاج مراجعة؛ لا تُجمع تلقائيًا.</p>
    <p v-if="error" role="alert" class="notice danger">{{ error }}</p>
    <form class="panel compact-form" @submit.prevent="report">
      <label>المرفق<select v-model="facility" required @change="unit = ''; result = null"><option value="">اختر المرفق</option><option v-for="item in facilities" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label>
      <label>الشهر<input v-model="month" type="month" required @change="result = null" /></label>
      <button v-if="can('engineering.read')" class="button primary" :disabled="busy">عرض المقارنة</button>
    </form>
    <div v-if="result" class="panel"><h2>الفروقات الشهرية</h2><p v-if="!result.length">لا توجد بيانات معتمدة ضمن النطاق.</p><table v-else><thead><tr><th>المادة</th><th>الوحدة التشغيلية</th><th>وحدة القياس</th><th>المستهدف</th><th>الفعلي</th><th>الفرق</th><th>الحالة</th></tr></thead><tbody><tr v-for="(row, i) in result" :key="i"><td>{{ row.chemical_code }}</td><td>{{ units.find(u => u.id === row.unit_id)?.name_ar ?? 'المرفق' }}</td><td>{{ row.unit_code }}</td><td>{{ row.target ?? 'غير متاح' }}</td><td>{{ row.actual ?? 'غير متاح' }}</td><td>{{ row.variance ?? 'غير متاح' }}</td><td>{{ statusText[String(row.status)] }}</td></tr></tbody></table></div>
    <form v-if="can('planning.write')" class="panel compact-form" @submit.prevent="save"><h2>إدخال مستهدف مستقل</h2><p>اختر المرفق والشهر أعلاه أولًا. الإتاحة بتوقيت جهازك وتحفظ UTC.</p>
      <label>الوحدة التشغيلية<select v-model="unit"><option value="">المرفق</option><option v-for="item in scopedUnits" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label>
      <label>رمز المادة<input v-model="chemical" required maxlength="64" /></label><label>وحدة القياس<select v-model="measure" required><option value="">اختر الوحدة</option><option v-for="item in measures" :key="String(item.code)" :value="String(item.code)">{{ item.display_symbol ?? item.code }}</option></select></label>
      <label>المستهدف<input v-model="quantity" required inputmode="decimal" pattern="[0-9]{1,14}(\.[0-9]{1,6})?" /></label><label>مرجع الخطة<input v-model="source" required maxlength="500" /></label><label>تاريخ إتاحة الخطة<input v-model="available" type="datetime-local" required /></label>
      <label>مراجعة مستهدف سابق<select v-model="replaces"><option value="">مستهدف جديد</option><option v-for="item in targets.filter(t => t.status === 'approved')" :key="String(item.id)" :value="String(item.id)">{{ item.chemical_code }} — {{ item.period_month }} — {{ item.unit_code }}</option></select></label><button class="button primary" :disabled="busy || !facility || !month">حفظ المسودة</button>
    </form>
    <div v-if="can('planning.read')" class="panel"><h2>سجل المستهدفات</h2><label v-if="can('planning.approve')">سبب الاعتماد<input v-model="reason" maxlength="2000" /></label><p v-if="!targets.length">لا توجد مستهدفات في نطاق صلاحيتك.</p><table v-else><thead><tr><th>الفترة</th><th>المادة</th><th>المستهدف</th><th>القياس</th><th>الإصدار</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody><tr v-for="row in targets" :key="String(row.id)"><td>{{ row.period_month }}</td><td>{{ row.chemical_code }}</td><td>{{ row.quantity }}</td><td>{{ row.unit_code }}</td><td>{{ row.revision_no }}</td><td>{{ statusText[String(row.status)] }}</td><td><button v-if="can('planning.approve') && row.status === 'draft'" :disabled="busy || !reason.trim()" @click="approve(row.id)">اعتماد مستقل</button></td></tr></tbody></table></div>
  </section>
</template>
