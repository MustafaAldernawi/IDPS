<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ApiError, rowsOf, type Api, type Row } from '../lib/api'
import { display, labels } from '../lib/presentation'
import DataTable from './DataTable.vue'
import StatusBadge from './StatusBadge.vue'
import SourceMappings from './SourceMappings.vue'
import { useAccess } from '../lib/access'
const props = defineProps<{ api: Api }>()
const can = useAccess()
const batches = ref<Row[]>([]), preview = ref<Row | null>(null), selectedId = ref(''), file = ref<File>(), busy = ref(false), error = ref(''), success = ref(''), reason = ref('')
const facilities = ref<Row[]>([]), facility = ref(''), year = ref(''), caseResolution = ref<Record<string, string>>({})
const selectedIds = ref<string[]>([])
const mappingContexts = ref<Row[]>([])
function toggle(id: string) { selectedIds.value = selectedIds.value.includes(id) ? selectedIds.value.filter(value => value !== id) : [...selectedIds.value, id] }
async function load() { try { batches.value = rowsOf(await props.api('/imports')) } catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر تحميل الاستيرادات' } }
onMounted(async () => { await load(); try { facilities.value = rowsOf(await props.api('/catalogs/facilities')) } catch { /* Upload remains unavailable without an authorized facility. */ } })
async function upload() {
  if (!file.value) return
  busy.value = true; error.value = ''; success.value = ''
  const body = new FormData(); body.append('file', file.value); body.append('facility_id', facility.value); if (year.value) body.append('year', year.value)
  mappingContexts.value.forEach((entry,index)=>{for(const key of ['alias','source_scope','unit_code','granularity','product_stage']) body.append(`mapping_context[${index}][${key}]`,String(entry[key])); const locator=entry.locator as Row; body.append(`mapping_context[${index}][locator][row]`,String(locator.row)); body.append(`mapping_context[${index}][locator][column]`,String(locator.column))})
  try { const result = await props.api<{ data: Row }>('/imports', { method: 'POST', body }); success.value = 'حُفظ المصدر للاستيراد والمراجعة؛ لم تُعتمد القيم تلقائيًا.'; await load(); if (result.data?.id) await show(result.data) }
  catch (e) { error.value = e instanceof ApiError && e.status === 409 && mappingContexts.value.length ? 'أُحيل العمود غير المعروف إلى قائمة المراجعة. حدّث القائمة، ثم أرسل اقتراح الربط لاعتماد مستقل.' : e instanceof ApiError ? e.message : 'تعذر رفع المصدر' }
  finally { busy.value = false }
}
async function show(row: Row) { selectedId.value = String(row.id); error.value = ''; try { const result = await props.api<{ data: Row }>(`/imports/${row.id}/preview`); preview.value = result.data ?? result as Row } catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر عرض المعاينة' } }
async function approve() {
  if (!selectedIds.value.length) { error.value = 'حدد القيم التي راجعتها قبل الاعتماد.'; return }
  if (!reason.value.trim()) { error.value = 'أدخل نتيجة المراجعة وسبب الاعتماد.'; return }
  busy.value = true
  try { const batch = preview.value?.batch as Row | undefined; await props.api(`/imports/${selectedId.value}/approve`, { method: 'POST', body: { reason: reason.value, lock_version: batch?.lock_version, observation_ids: selectedIds.value } }); success.value = 'حُفظ قرار اعتماد القيم المحددة وسجل التدقيق.'; preview.value = null; selectedIds.value = []; await load() }
  catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر الاعتماد' }
  finally { busy.value = false }
}
async function resolveCase(row: Row) {
  const resolution = caseResolution.value[String(row.id)] ?? ''
  if (resolution.trim().length < 10) { error.value = 'وثّق قرار المراجعة بعشرة أحرف على الأقل.'; return }
  busy.value = true
  try { await props.api(`/reconciliation-cases/${row.id}/resolve`, { method: 'POST', body: { resolution } }); await show({ id: selectedId.value }); success.value = 'حُفظ قرار المراجعة دون تغيير القيمة الأصلية.' }
  catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر حفظ قرار المراجعة' }
  finally { busy.value = false }
}
</script>
<template><section>
  <p v-if="error" role="alert" class="notice danger">{{ error }}</p><p v-if="success" role="status" class="notice positive">{{ success }}</p>
  <div v-if="can('imports.write')" class="panel"><div class="panel-title"><h2>إضافة مصدر بيانات</h2><span class="badge">CSV / Excel</span></div><form class="upload-zone" @submit.prevent="upload"><span class="upload-symbol" aria-hidden="true">↥</span><h3>ابدأ بالملف الأصلي</h3><p>يُحفظ المصدر كما هو وتُفحص قيمه قبل المراجعة والاعتماد.</p><div class="inline-filters"><label>المرفق<select v-model="facility" required><option value="">اختر المرفق</option><option v-for="item in facilities" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label><label>سنة ملف Excel إن لزم<input v-model="year" type="number" min="2000" max="2100" placeholder="2021" /></label></div><label class="file-label">اختر ملفًا<input type="file" accept=".csv,.xlsx" required @change="file = ($event.target as HTMLInputElement).files?.[0]" /></label><span v-if="file" class="muted">{{ file.name }}</span><button class="button primary" :disabled="busy || !file || !facility">{{ busy ? 'جارٍ الرفع والفحص…' : 'رفع وفحص المصدر' }}</button></form></div>
  <SourceMappings v-if="facility" :api="api" :facility="facility" @contexts="mappingContexts = $event" />
  <div class="panel"><div class="panel-title"><h2>دفعات الاستيراد</h2><button class="text-button" @click="load">↻ تحديث</button></div><DataTable v-if="batches.length" :rows="batches" :columns="['original_name', 'status', 'row_count', 'created_at']" selectable @select="show" /><div v-else class="empty-state"><h3>لا توجد دفعات متاحة</h3><p>ارفع مصدرًا لبدء المراجعة.</p></div></div>
  <div v-if="preview" class="panel"><div class="panel-title"><h2>معاينة وفحص الجودة</h2><StatusBadge :value="(preview.batch as Row)?.status ?? 'staged'" /></div><dl class="detail-grid"><div v-for="(value, key) in preview.batch as Row" :key="key"><dt>{{ labels[String(key)] ?? key }}</dt><dd>{{ display(value) }}</dd></div></dl><DataTable v-if="rowsOf(preview.rows ?? preview.observations).length" :rows="rowsOf(preview.rows ?? preview.observations)" :columns="['period_month', 'metric_code', 'item_code', 'quantity', 'status', 'qa_flags']" checkable :selected-ids="selectedIds" @check="toggle" /><div v-if="rowsOf(preview.reconciliation_cases).length" class="review-actions"><h3>حالات المطابقة والمراجعة</h3><div v-for="item in rowsOf(preview.reconciliation_cases)" :key="String(item.id)" class="reconciliation-item"><div><strong>{{ item.rule_code }}</strong><StatusBadge :value="item.status" /></div><p>{{ display(item.details) }}</p><template v-if="item.status === 'open' && can('imports.approve')"><label>قرار المراجعة<textarea v-model="caseResolution[String(item.id)]" rows="2" /></label><button class="button secondary" :disabled="busy" @click="resolveCase(item)">حفظ قرار المراجعة</button></template><p v-else>{{ display(item.resolution) }}</p></div></div><div v-if="can('imports.approve')" class="review-actions"><p class="notice warning">تحتاج الفروق والأسماء غير المحسومة إلى مراجعة هندسة العمليات. نجاح الفحص لا يعني اعتماد المصدر.</p><label>قرار المراجع وسببه<textarea v-model="reason" rows="3" minlength="10" /></label><button class="button primary" :disabled="busy" @click="approve">اعتماد القيم المحددة ({{ selectedIds.length }})</button></div></div>
</section></template>


