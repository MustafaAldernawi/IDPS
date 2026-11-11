<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ApiError, rowsOf, type Api, type Row } from '../lib/api'
import { display } from '../lib/presentation'
import DataTable from './DataTable.vue'
import StatusBadge from './StatusBadge.vue'
const props = defineProps<{ api: Api; overview?: boolean }>()
const data = ref<Row>({}), kpis = ref<Row[]>([]), comparisons = ref<Row[]>([]), error = ref(''), loading = ref(false), period = ref(''), loadedAt = ref('')
const facilities = ref<Row[]>([]), products = ref<Row[]>([]), facility = ref(''), productId = ref('')
async function load() {
  loading.value = true; error.value = ''
  try {
    const params = new URLSearchParams()
    if (period.value) params.set('period_month', period.value)
    if (facility.value) params.set('facility_id', facility.value)
    if (productId.value) params.set('product_id', productId.value)
    const query = `?${params}`
    if (props.overview) { const result = await props.api<{ data: Row }>(`/dashboard${query}`); data.value = result.data ?? {}; kpis.value = rowsOf(data.value.kpis) }
    else { const result = await props.api(`/kpis${query}`); kpis.value = rowsOf(result); comparisons.value = rowsOf(await props.api(`/comparisons/actual-target${query}`)) }
    loadedAt.value = new Intl.DateTimeFormat('ar-LY', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Africa/Tripoli' }).format(new Date())
  } catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر تحميل التحليل.' }
  finally { loading.value = false }
}
onMounted(async () => { await load(); try { facilities.value = rowsOf(await props.api('/catalogs/facilities')); products.value = rowsOf(await props.api('/catalogs/products')) } catch { /* Read-only analytics remains available without catalog-management access. */ } })
const titles: Record<string, string> = { PRODUCTION_TARGET_ACHIEVEMENT: 'تحقيق مستهدف الإنتاج', SPECIFIC_ENERGY_CONSUMPTION: 'الاستهلاك النوعي للطاقة', FUEL_CONSUMPTION_RATIO: 'نسبة استهلاك الوقود', REFINERY_LOSS_RATE: 'معدل فاقد المصفاة', LOSS_RATE: 'معدل الفاقد' }
const registers = [
  { key: 'production_records', label: 'سجلات الإنتاج' },
  { key: 'production_target_plans', label: 'خطط الإنتاج' },
  { key: 'operational_events', label: 'أحداث التشغيل' },
  { key: 'laboratory_samples', label: 'عينات المختبر' },
]
function registerTotal(key: string) {
  return (data.value.counts as Record<string, { total: number }> | undefined)?.[key]?.total
}
</script>
<template>
  <section><div class="section-toolbar"><div class="inline-filters"><label>المرفق<select v-model="facility" @change="load"><option value="">كل المرافق المتاحة</option><option v-for="item in facilities" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label><label>المنتج<select v-model="productId" @change="load"><option value="">نطاق المنتجات المعتمد</option><option v-for="item in products" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label><label>فترة التقرير<input v-model="period" type="month" @change="load" /></label></div><button class="button secondary" :disabled="loading" @click="load">↻ تحديث المؤشرات</button></div>
    <p v-if="error" role="alert" class="notice danger">{{ error }}</p>
    <section v-if="overview && !loading && !error && data.counts" class="panel register-panel" aria-labelledby="register-title">
      <div class="panel-title"><h2 id="register-title">ملخص سجلات التشغيل</h2><span class="badge">جميع الفترات · ضمن صلاحياتك</span></div>
      <dl class="register-grid"><div v-for="register in registers" :key="register.key"><dt>{{ register.label }}</dt><dd>{{ display(registerTotal(register.key)) }}</dd></div></dl>
      <p class="register-note">تشمل الأعداد جميع حالات السجلات. المرشحات أعلاه تخص المؤشرات.</p>
    </section>
    <div v-if="loading" role="status" class="empty-state">جارٍ تحميل المؤشرات…</div>
    <div v-else-if="!error && kpis.length" class="kpi-grid"><article v-for="(kpi, index) in kpis" :key="String(kpi.code ?? index)" class="kpi-card"><div class="kpi-label">{{ titles[String(kpi.code)] ?? kpi.name ?? kpi.code }}</div><strong>{{ display(kpi.value) }} <small>{{ display(kpi.value_unit ?? kpi.unit) }}</small></strong><p>{{ display(kpi.period ?? period) }}</p><StatusBadge :value="kpi.status ?? 'unavailable'" /><p class="muted">{{ display(kpi.reason ?? kpi.quality_warning) }}</p></article></div>
    <div v-else-if="!error" class="panel empty-state"><span class="empty-symbol">◈</span><h3>لا توجد مؤشرات متاحة</h3><p>تظهر المؤشرات بعد اعتماد القياسات والمستهدفات وتعريف نطاق الحساب.</p></div>
    <div v-if="overview" class="overview-grid"><article class="panel"><div class="panel-title"><h2>مسار جودة البيانات</h2><span class="badge">مراجعة مستقلة</span></div><ol class="workflow-list"><li><span>01</span><div><h3>مصادر محفوظة</h3><p>ملفات أصلية وبصمة تحقق وسياق كل قيمة.</p></div></li><li><span>02</span><div><h3>فحص ومطابقة</h3><p>تمييز الفعلي والمستهدف والتراكمي ومراجعة الفروق.</p></div></li><li><span>03</span><div><h3>اعتماد هندسي</h3><p>بيانات بإصدارات قابلة للتتبع قبل التحليل.</p></div></li></ol></article><article class="panel context-panel"><span class="eyebrow">حوكمة التوقع</span><h2>الثقة تبدأ بجودة المصدر</h2><p>نجاح التدريب ينشئ نموذجًا مرشحًا. التفعيل يتطلب تقييمًا وموافقة مستقلة.</p><div class="context-rule"><span>الأفق الأول</span><strong>شهري M+1</strong></div><div class="context-rule"><span>البيانات التاريخية</span><strong>2021–2023 · أولية</strong></div><div class="context-rule"><span>النموذج المعتمد</span><StatusBadge :value="data.active_model_status ?? 'not_ready'" /></div></article></div>
    <div v-if="!overview" class="panel"><div class="panel-title"><h2>الفعلي مقابل المستهدف</h2><span class="muted">نفس المنتج والفترة ووحدة القياس</span></div><DataTable v-if="comparisons.length" :rows="comparisons" :columns="['period_month', 'product_code', 'actual', 'target', 'variance', 'achievement_pct']" /><div v-else class="empty-state"><h3>لا توجد مقارنة متاحة</h3><p>يلزم فعلي ومستهدف معتمدان ومتوافقان في النطاق.</p></div></div>
    <p v-if="loadedAt" class="freshness">آخر جلب: {{ loadedAt }} · توقيت طرابلس · تحديث التقرير لا يعني قياسًا مباشرًا</p>
  </section>
</template>
<style scoped>
.register-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1px; background: var(--line); margin: 0; }
.register-grid > div { background: #fff; padding: 1.1rem 1.35rem; }
.register-grid dt, .register-note { color: #536858; font-size: .8rem; }
.register-grid dd { margin: .25rem 0 0; color: var(--green); font-size: 1.65rem; font-weight: 650; font-variant-numeric: tabular-nums; }
.register-note { margin: 0; padding: .75rem 1.35rem; border-block-start: 1px solid var(--line); }
</style>

