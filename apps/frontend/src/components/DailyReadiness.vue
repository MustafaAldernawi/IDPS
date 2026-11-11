<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ApiError, rowsOf, type Api, type Row } from '../lib/api'
import { useAccess } from '../lib/access'

const props = defineProps<{ api: Api }>()
const can = useAccess()
const facilities = ref<Row[]>([]), products = ref<Row[]>([]), units = ref<Row[]>([])
const facility = ref(''), product = ref(''), unit = ref(''), from = ref(''), to = ref('')
const loading = ref(false), catalogLoading = ref(false), error = ref(''), result = ref<Row | null>(null)
const matchingUnits = computed(() => units.value.filter(item => item.facility_id === facility.value))
let requestSerial = 0
watch([facility, product, unit, from, to], () => { result.value = null; error.value = ''; requestSerial++ })
watch(facility, () => { unit.value = '' })
onMounted(async () => {
  if (!can('engineering.read')) return
  catalogLoading.value = true
  try {
    const [facilityResponse, productResponse, unitResponse] = await Promise.all([
      props.api('/catalogs/facilities'), props.api('/catalogs/products'), props.api('/catalogs/units'),
    ])
    facilities.value = rowsOf(facilityResponse); products.value = rowsOf(productResponse); units.value = rowsOf(unitResponse)
  } catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر تحميل المرجعيات. تحقق من الاتصال وصلاحيات العرض.' }
  finally { catalogLoading.value = false }
})
async function inspect() {
  result.value = null; error.value = ''
  if (!facility.value || !product.value || !from.value || !to.value) { error.value = 'اختر المرفق والمنتج وفترة الفحص.'; return }
  if (from.value > to.value) { error.value = 'يجب أن يكون تاريخ البداية قبل النهاية أو مساويًا لها.'; return }
  const params = new URLSearchParams({ facility_id: facility.value, product_id: product.value, from: from.value, to: to.value })
  if (unit.value) params.set('unit_id', unit.value)
  const serial = ++requestSerial
  loading.value = true
  try {
    const response = await props.api<{ data: Row }>(`/data/readiness/daily?${params}`)
    if (serial === requestSerial) result.value = response.data
  } catch (e) {
    if (serial === requestSerial) error.value = e instanceof ApiError ? e.message : 'تعذر فحص البيانات اليومية.'
  } finally { loading.value = false }
}
</script>

<template>
  <section>
    <h2>جاهزية البيانات اليومية</h2>
    <p class="notice">التغطية وحدها لا تثبت جاهزية نموذج الذكاء الاصطناعي. يجب مراجعة توقيت الإتاحة وجودة القياسات وتعريف اليوم التشغيلي.</p>
    <p v-if="!can('engineering.read')" role="alert">ليس لديك تصريح لعرض جاهزية البيانات اليومية.</p>
    <template v-else>
      <p v-if="error" role="alert" class="notice danger">{{ error }}</p>
      <p v-if="catalogLoading" role="status">جارٍ تحميل المرجعيات…</p>
      <form class="panel compact-form" @submit.prevent="inspect">
        <label>المرفق<select v-model="facility" name="facility_id" required><option value="">اختر المرفق</option><option v-for="item in facilities" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label>
        <label>المنتج<select v-model="product" name="product_id" required><option value="">اختر المنتج</option><option v-for="item in products" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label>
        <label>الوحدة<select v-model="unit" name="unit_id"><option value="">المرفق كاملًا</option><option v-for="item in matchingUnits" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label>
        <label>من<input v-model="from" name="from" type="date" required /></label>
        <label>إلى<input v-model="to" name="to" type="date" required /></label>
        <button class="button primary" :disabled="loading || catalogLoading">فحص التغطية</button>
      </form>
      <p v-if="loading" role="status">جارٍ فحص السجلات المعتمدة…</p>
      <div v-if="result" class="panel" aria-live="polite">
        <h3>نتائج الفحص</h3>
        <dl class="detail-grid">
          <div><dt>الأيام المتوقعة</dt><dd>{{ result.expected_days }}</dd></div>
          <div><dt>الأيام المرصودة</dt><dd>{{ result.observed_days }}</dd></div>
          <div><dt>الأيام ذات القيمة صفر</dt><dd>{{ result.zero_days }}</dd></div>
          <div><dt>الأيام المفقودة</dt><dd>{{ result.missing_days }}</dd></div>
          <div><dt>الأيام ذات السجلات الملتبسة</dt><dd>{{ result.ambiguous_days ?? 'غير متاح' }}</dd></div>
          <div><dt>نسبة التغطية</dt><dd>{{ result.coverage_pct ?? 'غير متاحة' }}٪</dd></div>
          <div><dt>حالة النموذج</dt><dd>{{ result.status === 'DAILY_MODEL_NOT_READY' ? 'النموذج اليومي غير جاهز' : String(result.status ?? 'غير متاحة') }}</dd></div>
        </dl>
        <p>تُحسب الأيام من السجلات اليومية المعتمدة فقط. سجلات الورديات تحتاج مراجعة اكتمال مستقلة.</p>
        <div v-if="Array.isArray(result.ambiguous_dates) && result.ambiguous_dates.length"><h4>تواريخ تتطلب مراجعة لتعدد السجلات</h4><ul><li v-for="day in result.ambiguous_dates" :key="String(day)">{{ day }}</li></ul></div>
        <div v-if="Array.isArray(result.missing_dates) && result.missing_dates.length"><h4>التواريخ المفقودة</h4><ul><li v-for="day in result.missing_dates" :key="String(day)">{{ day }}</li></ul></div>
        <p v-else>لا توجد تواريخ مفقودة ضمن الفترة المعروضة.</p>
      </div>
      <div v-else-if="!loading" class="panel empty-state">اختر نطاقًا ثم افحص البيانات اليومية المتاحة.</div>
    </template>
  </section>
</template>
