<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ApiError, rowsOf, type Api, type Row } from '../lib/api'
import { display } from '../lib/presentation'
import DataTable from './DataTable.vue'
import StatusBadge from './StatusBadge.vue'
const props = defineProps<{ api: Api; overview?: boolean }>()
const data = ref<Row>({}), kpis = ref<Row[]>([]), comparisons = ref<Row[]>([]), error = ref(''), loading = ref(false), period = ref(''), loadedAt = ref('')
const units = ref<Row[]>([]), unit = ref('')
const visibleUnits = computed(() => units.value.filter(row => !facility.value || row.facility_id === facility.value))
const facilities = ref<Row[]>([]), products = ref<Row[]>([]), facility = ref(''), productId = ref('')
let requestNumber = 0
async function load() {
  const request = ++requestNumber
  loading.value = true; error.value = ''
  try {
    const params = new URLSearchParams()
    if (period.value) params.set('period_month', period.value)
    if (facility.value) params.set('facility_id', facility.value)
    if (unit.value) params.set('unit_id', unit.value)
    if (productId.value) params.set('product_id', productId.value)
    const query = `?${params}`
    if (props.overview) {
      const result = await props.api<{ data: Row }>(`/dashboard${query}`)
      if (request !== requestNumber) return
      data.value = result.data ?? {}
      kpis.value = rowsOf(data.value.kpis)
      comparisons.value = rowsOf(data.value.comparisons)
    } else {
      const [indicators, comparison] = await Promise.all([props.api(`/kpis${query}`), props.api(`/comparisons/actual-target${query}`)])
      if (request !== requestNumber) return
      kpis.value = rowsOf(indicators)
      comparisons.value = rowsOf(comparison)
    }
    loadedAt.value = new Intl.DateTimeFormat('ar-LY', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Africa/Tripoli' }).format(new Date())
  } catch (e) { if (request === requestNumber) error.value = e instanceof ApiError ? e.message : 'تعذر تحميل التحليل.' }
  finally { if (request === requestNumber) loading.value = false }
}
onMounted(() => {
  void load()
  const catalogs = [
    ['/catalogs/facilities', facilities],
    ['/catalogs/units', units],
    ['/catalogs/products', products],
  ] as const
  for (const [url, destination] of catalogs) {
    void props.api(url).then(response => { destination.value = rowsOf(response) }).catch(() => {})
  }
})
const titles: Record<string, string> = { PRODUCTION_TARGET_ACHIEVEMENT: 'تحقيق مستهدف الإنتاج', SPECIFIC_ENERGY_CONSUMPTION: 'الاستهلاك النوعي للطاقة', FUEL_CONSUMPTION_RATIO: 'نسبة استهلاك الوقود', REFINERY_LOSS_RATE: 'معدل فاقد المصفاة', LOSS_RATE: 'معدل الفاقد' }
const registers = [
  { key: 'production_records', label: 'سجلات الإنتاج' },
  { key: 'production_target_plans', label: 'خطط الإنتاج' },
  { key: 'operational_events', label: 'أحداث التشغيل' },
  { key: 'laboratory_samples', label: 'عينات المختبر' },
]
function statuses(key: string) {
  return Object.entries((data.value.period_counts as Record<string, Record<string, number>> | undefined)?.[key] ?? {})
}
const comparisonColumns = ['period_month', 'facility_id', 'unit_id', 'product_code', 'unit_code', 'actual', 'target', 'variance', 'achievement_pct', 'status']
const trendRows = computed(() => rowsOf(data.value.trend))
const comparableTrend = computed(() => {
  const series = new Set(trendRows.value.map(row => JSON.stringify([row.facility_id, row.unit_id, row.product_id, row.unit_code])))
  return series.size === 1 && trendRows.value.length > 1
})
const trendMax = computed(() => Math.max(1, ...trendRows.value.flatMap(row => [Number(row.actual ?? 0), Number(row.target ?? 0)])))
function withScopeLabels(rows: Row[]) {
  return rows.map(row => {
    const facilityLabel = facilities.value.find(item => item.id === row.facility_id)
    const unitLabel = units.value.find(item => item.id === row.unit_id)
    return {
      ...row,
      facility_id: facilityLabel?.name_ar ?? facilityLabel?.code ?? row.facility_id,
      unit_id: unitLabel?.name_ar ?? unitLabel?.code ?? row.unit_id ?? 'على مستوى المرفق',
    }
  })
}
function registerTotal(key: string) {
  return (data.value.counts as Record<string, { total: number }> | undefined)?.[key]?.total
}
</script>
<template>
  <section><div class="section-toolbar"><div class="inline-filters"><label>المرفق<select v-model="facility" @change="unit = ''; load()"><option value="">كل المرافق المتاحة</option><option v-for="item in facilities" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label><label>الوحدة<select v-model="unit" @change="load"><option value="">كل الوحدات للسجلات</option><option v-for="item in visibleUnits" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label><label>المنتج<select v-model="productId" @change="load"><option value="">نطاق المنتجات المعتمد</option><option v-for="item in products" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label><label>فترة التقرير<input v-model="period" type="month" @change="load" /></label></div><button class="button secondary" :disabled="loading" @click="load">↻ تحديث المؤشرات</button></div>
    <p v-if="error" role="alert" class="notice danger">{{ error }}</p>
    <section v-if="overview && !loading && !error && data.counts" class="panel register-panel" aria-labelledby="register-title">
      <div class="panel-title"><h2 id="register-title">ملخص سجلات التشغيل</h2><span class="badge">جميع الفترات · ضمن صلاحياتك</span></div>
      <dl class="register-grid"><div v-for="register in registers" :key="register.key"><dt>{{ register.label }}</dt><dd>{{ display(registerTotal(register.key)) }}</dd></div></dl>
      <p class="register-note">تشمل الأعداد جميع حالات السجلات داخل نطاق المرفق والوحدة المحدد. عند عدم اختيار وحدة، تُحسب المؤشرات على مستوى المرفق.</p>
    </section>
    <div v-if="loading" role="status" class="empty-state">جارٍ تحميل المؤشرات…</div>
    <div v-else-if="!error && kpis.length" class="kpi-grid"><article v-for="(kpi, index) in kpis" :key="String(kpi.code ?? index)" class="kpi-card"><div class="kpi-label">{{ titles[String(kpi.code)] ?? kpi.name ?? kpi.code }}</div><strong>{{ display(kpi.value) }} <small>{{ display(kpi.value_unit ?? kpi.unit) }}</small></strong><p>{{ display(kpi.period ?? period) }}</p><StatusBadge :value="kpi.status ?? 'unavailable'" /><p class="muted">{{ display(kpi.reason ?? kpi.quality_warning) }}</p></article></div>
    <div v-else-if="!error" class="panel empty-state"><span class="empty-symbol">◈</span><h3>لا توجد مؤشرات متاحة</h3><p>تظهر المؤشرات بعد اعتماد القياسات والمستهدفات وتعريف نطاق الحساب.</p></div>
    <template v-if="overview && !loading && !error && data.period_month">
      <section class="panel">
        <div class="panel-title"><h2>توزيع السجلات حسب الحالة</h2><bdi>{{ data.period_month }}</bdi></div>
        <div class="register-grid">
          <div v-for="register in registers" :key="register.key">
            <h3>{{ register.label }}</h3>
            <ul v-if="statuses(register.key).length" class="status-list">
              <li v-for="[status, total] in statuses(register.key)" :key="status"><StatusBadge :value="status" /><strong>{{ display(total) }}</strong></li>
            </ul>
            <p v-else class="muted">لا توجد سجلات في الفترة</p>
          </div>
        </div>
      </section>
      <section class="panel">
        <div class="panel-title"><h2>الاتجاه الشهري للفعلي والمستهدف</h2><span class="muted">آخر ستة أشهر حتى الفترة المحددة</span></div>
        <div v-if="comparableTrend" class="trend-chart" aria-hidden="true">
          <div v-for="row in trendRows" :key="String(row.period_month)" class="trend-row">
            <bdi>{{ row.period_month }}</bdi>
            <div class="trend-bars">
              <div v-if="row.actual != null" class="trend-actual" :style="{ inlineSize: `${100 * Number(row.actual) / trendMax}%` }" />
              <div v-if="row.target != null" class="trend-target" :style="{ inlineSize: `${100 * Number(row.target) / trendMax}%` }" />
            </div>
          </div>
          <div class="trend-legend"><span>الفعلي</span><span>المستهدف</span></div>
        </div>
        <DataTable v-if="trendRows.length" :rows="withScopeLabels(trendRows)" :columns="comparisonColumns" />
        <p v-else class="empty-state">{{ productId ? 'لا توجد بيانات معتمدة للمنتج في هذه الفترة' : 'اختر منتجًا لعرض اتجاهه الشهري' }}</p>
      </section>
      <section class="panel">
        <div class="panel-title"><h2>الأحداث والتوقفات</h2><span class="muted">آخر عشرة أحداث تتقاطع مع الفترة</span></div>
        <p class="register-note">مدة التوقف المعتمدة للوحدة: {{ data.downtime_seconds == null ? 'غير متاحة' : `${display(data.downtime_seconds)} ثانية` }}</p>
        <DataTable v-if="rowsOf(data.events).length" :rows="withScopeLabels(rowsOf(data.events))" :columns="['unit_id', 'event_type', 'started_at', 'actual_end_at', 'status']" />
        <p v-else class="empty-state">لا توجد أحداث ضمن النطاق</p>
      </section>
    </template>
    <section v-if="!loading && !error" class="panel">
      <div class="panel-title"><h2>الفعلي مقابل المستهدف</h2><span class="muted">نفس المنتج والفترة ووحدة القياس</span></div>
      <DataTable v-if="comparisons.length" :rows="withScopeLabels(comparisons)" :columns="comparisonColumns" />
      <p v-else class="empty-state">لا توجد قياسات أو مستهدفات معتمدة ضمن النطاق</p>
    </section>
    <p v-if="loadedAt" class="freshness">آخر جلب: {{ loadedAt }} · توقيت طرابلس · تحديث التقرير لا يعني قياسًا مباشرًا</p>
  </section>
</template>
<style scoped>
.register-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1px; background: var(--line); margin: 0; }
.register-grid > div { background: #fff; padding: 1.1rem 1.35rem; }
.register-grid dt, .register-note { color: #536858; font-size: .8rem; }
.register-grid dd { margin: .25rem 0 0; color: var(--green); font-size: 1.65rem; font-weight: 650; font-variant-numeric: tabular-nums; }
.register-note { margin: 0; padding: .75rem 1.35rem; border-block-start: 1px solid var(--line); }
.register-grid h3 { margin: 0 0 .75rem; font-size: .9rem; }
.status-list { list-style: none; display: grid; gap: .65rem; padding: 0; margin: 0; }
.status-list li { display: flex; justify-content: space-between; align-items: center; gap: .6rem; }
.trend-chart { display: grid; gap: .85rem; padding: 1.25rem; }
.trend-row { display: grid; grid-template-columns: 5rem minmax(0, 1fr); align-items: center; gap: .75rem; }
.trend-bars { min-block-size: 1.3rem; display: grid; align-content: center; gap: .2rem; border-inline-start: 1px solid var(--line); }
.trend-bars > div { block-size: .55rem; border-start-end-radius: .25rem; border-end-end-radius: .25rem; }
.trend-actual { background: var(--green); }
.trend-target { background: #99753d; }
.trend-legend { display: flex; gap: 1rem; }
.trend-legend span::before { content: ''; display: inline-block; inline-size: .6rem; block-size: .6rem; margin-inline-end: .35rem; background: var(--green); }
.trend-legend span:last-child::before { background: #99753d; }
</style>

