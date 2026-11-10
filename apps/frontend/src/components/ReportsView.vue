<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ApiError, rowsOf, type Api, type Row } from '../lib/api'
import { display, labels } from '../lib/presentation'
import DataTable from './DataTable.vue'
const props = defineProps<{ api: Api }>()
const period = ref(''), data = ref<Row | null>(null), busy = ref(false), error = ref(''), success = ref('')
const facilities = ref<Row[]>([]), facility = ref('')
onMounted(async () => { try { facilities.value = rowsOf(await props.api('/catalogs/facilities')) } catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر تحميل المرافق' } })
async function report(format = 'json') {
  if (!period.value || !facility.value) { error.value = 'اختر شهر التقرير والمرفق أولًا.'; return }
  error.value = ''; success.value = ''; busy.value = true
  try {
    const path = `/reports/monthly?period_month=${period.value}&facility_id=${facility.value}&format=${format}`
    if (format === 'json') { const result = await props.api<{ data: Row }>(path); data.value = result.data ?? result as Row }
    else {
      const blob = await props.api<Blob>(path, { download: true }); const url = URL.createObjectURL(blob); const link = document.createElement('a'); link.href = url; link.download = `IDPS-${period.value}.${format}`; link.click(); URL.revokeObjectURL(url); success.value = 'جُهز التقرير للتنزيل وسُجل طلب التصدير.'
    }
  } catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر إنشاء التقرير.' }
  finally { busy.value = false }
}
function columns(rows: Row[]) { const allowed = ['product_code', 'period_month', 'actual', 'target', 'quantity', 'unit_code', 'variance', 'achievement_pct', 'code', 'value', 'unit', 'status', 'reason']; return allowed.filter(key => rows.some(row => key in row)) }
</script>
<template><section><p v-if="error" role="alert" class="notice danger">{{ error }}</p><p v-if="success" role="status" class="notice positive">{{ success }}</p><div class="panel"><div class="panel-title"><h2>التقرير الشهري</h2><span class="badge">تصدير مصرح ومدقق</span></div><form class="compact-form" @submit.prevent="report()"><label>المرفق<select v-model="facility" required><option value="">اختر المرفق</option><option v-for="item in facilities" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label><label>شهر التقرير<input v-model="period" type="month" required /></label><button class="button primary" :disabled="busy">عرض التقرير</button><button v-for="format in ['csv', 'xlsx', 'pdf']" :key="format" type="button" class="button secondary" :disabled="busy || !period || !facility" @click="report(format)">تنزيل {{ format.toUpperCase() }}</button></form><p class="form-note">يرتبط التقرير بالفترة والمصادر وحالة الاعتماد. القيم غير المتاحة لا تُستبدل بأصفار.</p></div><template v-if="data"><div class="panel"><div class="panel-title"><h2>بيانات التقرير</h2><span class="badge">{{ period }}</span></div><dl class="detail-grid"><template v-for="(value, key) in data" :key="key"><div v-if="!Array.isArray(value)"><dt>{{ labels[String(key)] ?? key }}</dt><dd>{{ display(value) }}</dd></div></template></dl></div><template v-for="(value, key) in data" :key="key"><div v-if="Array.isArray(value)" class="panel"><div class="panel-title"><h2>{{ labels[String(key)] ?? key }}</h2></div><DataTable v-if="rowsOf(value).length" :rows="rowsOf(value)" :columns="columns(rowsOf(value))" /><div v-else class="empty-state">لا توجد بيانات معتمدة في هذا القسم.</div></div></template></template><div v-else class="panel empty-state"><span class="empty-symbol">▤</span><h3>التقرير يبدأ بفترة واضحة</h3><p>اختر الشهر لعرض المقارنات والمؤشرات والملاحظات المتاحة لك.</p></div></section></template>

