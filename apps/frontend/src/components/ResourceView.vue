<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ApiError, type Api, type Envelope, type Row, rowsOf } from '../lib/api'
import { display, labels } from '../lib/presentation'
import type { Resource } from '../lib/resources'
import DataTable from './DataTable.vue'
import RecordEditor from './RecordEditor.vue'
import StatusBadge from './StatusBadge.vue'
import { useAccess } from '../lib/access'
const props = defineProps<{ resource: Resource; api: Api }>()
const can = useAccess()
const permissionPrefix = computed(() => props.resource.endpoint.startsWith('/catalogs') ? 'catalogs' : ({ planning: 'planning', events: 'events', quality: 'quality', blending: 'quality' })[props.resource.key] ?? 'production')
const mayWrite = computed(() => can(`${permissionPrefix.value}.${permissionPrefix.value === 'catalogs' ? 'manage' : 'write'}`))
const mayApprove = computed(() => can(`${permissionPrefix.value}.approve`))
const rows = ref<Row[]>([]), selected = ref<Row | null>(null), loading = ref(false), saving = ref(false), creating = ref(false), error = ref(''), success = ref(''), reason = ref(''), page = ref(1), total = ref<number>(), lastPage = ref(1)
const errors = ref<Record<string, string[]>>({}), statusFilter = ref(''), period = ref(''), loadFailed = ref(false)
const revisionOf = ref<Row | undefined>()
function revise() { if (selected.value) { revisionOf.value = selected.value; selected.value = null; creating.value = true; success.value = '' } }
const detailFields = computed(() => Object.entries(selected.value ?? {}).filter(([key]) => !['password', 'token'].includes(key)))
let requestNumber = 0
async function load() {
  const number = ++requestNumber; loading.value = true; error.value = ''; loadFailed.value = false
  const params = new URLSearchParams({ page: String(page.value) })
  if (statusFilter.value) params.set('status', statusFilter.value)
  if (period.value) params.set('period_month', period.value)
  try { const result = await props.api<Envelope<Row[]>>(`${props.resource.endpoint}?${params}`); if (number !== requestNumber) return; rows.value = rowsOf(result); total.value = result.meta?.total; lastPage.value = result.meta?.last_page ?? 1 }
  catch (e) { if (number === requestNumber) { loadFailed.value = true; total.value = undefined; lastPage.value = 1; error.value = e instanceof ApiError ? e.message : 'تعذر تحميل السجلات.' } }
  finally { if (number === requestNumber) loading.value = false }
}
watch(() => props.resource.key, () => { page.value = 1; selected.value = null; revisionOf.value = undefined; creating.value = false; success.value = ''; statusFilter.value = ''; period.value = ''; void load() }, { immediate: true })
async function save(value: Row) {
  saving.value = true; error.value = ''; errors.value = {}
  try { if (props.resource.key === 'events') value.idempotency_key = crypto.randomUUID(); await props.api(props.resource.endpoint, { method: 'POST', body: value }); creating.value = false; success.value = 'حُفظ السجل. يلزم إحالته للمراجعة قبل الاعتماد.'; await load() }
  catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر حفظ السجل.'; if (e instanceof ApiError) errors.value = e.fields }
  finally { saving.value = false }
}
async function transition(action: string) {
  if (!selected.value) return
  if (['approve', 'reject', 'revision', 'eta', 'restart'].includes(action) && !reason.value.trim()) { error.value = 'أدخل سبب الإجراء لتوثيقه في سجل التدقيق.'; return }
  saving.value = true; error.value = ''
  try {
    const payload: Row = { reason: reason.value, revision_no: selected.value.revision_no, lock_version: selected.value.lock_version }
    if (action === 'eta') payload.expected_end_at = eventTime.value ? new Date(eventTime.value).toISOString() : null
    if (action === 'restart') payload.actual_end_at = eventTime.value ? new Date(eventTime.value).toISOString() : null
    await props.api(`${props.resource.endpoint}/${selected.value.id}/${action}`, { method: 'POST', body: payload })
    selected.value = null; reason.value = ''; success.value = 'تم الإجراء وحفظ أثره التدقيقي.'; await load()
  } catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر تنفيذ الإجراء.' }
  finally { saving.value = false }
}
const eventTime = ref('')
</script>
<template>
  <section>
    <div class="section-toolbar"><div class="inline-filters"><label>الشهر<input v-model="period" type="month" @change="page = 1; load()" /></label><label>الحالة<select v-model="statusFilter" @change="page = 1; load()"><option value="">كل الحالات</option><option value="draft">مسودة</option><option value="submitted">بانتظار الاعتماد</option><option value="approved">معتمد</option><option value="flagged">يتطلب مراجعة</option><option value="rejected">مرفوض</option></select></label></div><button v-if="resource.fields && mayWrite" class="button primary" @click="creating = !creating; selected = null; revisionOf = undefined">{{ creating ? 'إغلاق النموذج' : '+ إضافة سجل' }}</button></div>
    <p v-if="error" role="alert" class="notice danger">{{ error }}</p><p v-if="success" role="status" class="notice positive">{{ success }}</p>
    <div v-if="creating && resource.fields" class="panel"><div class="panel-title"><h2>{{ revisionOf ? 'تصحيح بإصدار جديد' : 'سجل جديد' }}</h2><span class="badge">مسودة</span></div><p v-if="revisionOf" class="notice warning">القيمة الأصلية محفوظة. أدخل سبب التصحيح في الملاحظات، ثم أحل الإصدار الجديد للمراجعة.</p><RecordEditor :key="String(revisionOf?.id ?? 'new')" :initial="revisionOf" :fields="resource.fields" :api="api" :busy="saving" :errors="errors" @save="save" @cancel="creating = false" /></div>
    <div class="panel"><div class="panel-title"><h2>السجلات</h2><div class="panel-actions"><span v-if="total !== undefined" class="muted">{{ display(total) }} سجلًا</span><button class="text-button" :disabled="loading" @click="load">↻ تحديث</button></div></div>
      <div v-if="loading" role="status" class="empty-state"><span class="spinner" /> جارٍ تحميل السجلات…</div>
      <div v-else-if="loadFailed" class="empty-state"><h3>تعذر عرض السجلات</h3><button class="button secondary" @click="load">إعادة المحاولة</button></div>
      <div v-else-if="!rows.length" class="empty-state"><span class="empty-symbol">▤</span><h3>لا توجد سجلات مطابقة</h3><p>تظهر هنا السجلات المتاحة لك حسب الفترة والصلاحيات.</p></div>
      <DataTable v-else :rows="rows" :columns="resource.columns" selectable @select="selected = $event; creating = false; reason = ''; error = ''" />
      <div v-if="lastPage > 1" class="pagination"><button class="button secondary" :disabled="page <= 1 || loading" @click="page--; load()">السابق</button><span>الصفحة {{ page }} من {{ lastPage }}</span><button class="button secondary" :disabled="page >= lastPage || loading" @click="page++; load()">التالي</button></div>
    </div>
    <div v-if="selected" class="panel detail-panel"><div class="panel-title"><h2>تفاصيل السجل</h2><button class="text-button" @click="selected = null">إغلاق ×</button></div>
      <dl class="detail-grid"><template v-for="[key, value] in detailFields" :key="key"><div><dt>{{ labels[key] ?? key }}</dt><dd><StatusBadge v-if="key.includes('status')" :value="value" /><span v-else>{{ display(value) }}</span></dd></div></template></dl>
      <div v-if="resource.workflow" class="review-actions"><label>سبب الإجراء<textarea v-model="reason" placeholder="اكتب سبب الاعتماد أو الرفض أو التصحيح" rows="2" /></label>
        <div class="button-row"><button v-if="selected.status === 'approved' && mayWrite && resource.key !== 'events'" class="button secondary" @click="revise">إنشاء مراجعة تصحيحية</button><button v-if="selected.status === 'draft' && mayWrite" class="button primary" :disabled="saving" @click="transition('submit')">إحالة للمراجعة</button><template v-if="mayApprove && ['submitted', 'staged', 'flagged', 'reviewed', 'reported', 'pending'].includes(String(selected.status))"><button class="button primary" :disabled="saving" @click="transition('approve')">اعتماد السجل</button><button class="button secondary danger-text" :disabled="saving" @click="transition('reject')">رفض مع السبب</button></template>
        </div><template v-if="resource.key === 'events' && mayWrite"><label>وقت العودة<input v-model="eventTime" type="datetime-local" /></label><div class="button-row"><button class="button secondary" :disabled="saving" @click="transition('eta')">تحديث العودة المتوقعة</button><button class="button secondary" :disabled="saving || !eventTime" @click="transition('restart')">تسجيل العودة الفعلية</button></div><p class="muted">ترك الموعد فارغًا يعني أن العودة المتوقعة مجهولة. لا تُفترض مدة توقف.</p></template>
      </div>
    </div>
  </section>
</template>


