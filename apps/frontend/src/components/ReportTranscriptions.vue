<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ApiError, rowsOf, type Api, type Row } from '../lib/api'
import { useAccess } from '../lib/access'

const props = defineProps<{ api: Api; userId: string }>()
const can = useAccess()
const facilities = ref<Row[]>([]), entries = ref<Row[]>([])
const facility = ref(''), page = ref(1), lastPage = ref(1), sourcePage = ref(1), locator = ref(''), sourceText = ref('')
const file = ref<File | null>(null), fileKey = ref(0), busy = ref(false), error = ref(''), notice = ref('')
const selected = ref<Row | null>(null), reason = ref('')
const statuses: Record<string, string> = { pending: 'بانتظار المراجعة', verified: 'تم التحقق من النسخ', rejected: 'مرفوض' }
const writable = computed(() => can('imports.write'))

function failure(err: unknown) {
  error.value = err instanceof ApiError ? err.message : 'تعذّر إكمال العملية.'
}

onMounted(async () => {
  try {
    facilities.value = rowsOf(await props.api('/catalogs/facilities'))
  } catch (err) {
    failure(err)
  }
})

async function fetchEntries() {
  const query = new URLSearchParams({ facility_id: facility.value, page: String(page.value) })
  const response = await props.api<{ data: Row[]; last_page: number }>(`/report-transcriptions?${query}`)
  entries.value = response.data
  lastPage.value = response.last_page
}

async function load() {
  selected.value = null
  entries.value = []
  error.value = ''
  if (!facility.value) return
  busy.value = true
  try { await fetchEntries() } catch (err) { failure(err) } finally { busy.value = false }
}

async function submit() {
  if (!file.value || !facility.value) return
  busy.value = true
  error.value = notice.value = ''
  const body = new FormData()
  body.set('facility_id', facility.value)
  body.set('source', file.value)
  body.set('page', String(sourcePage.value))
  body.set('locator', locator.value)
  body.set('source_text', sourceText.value)
  try {
    await props.api('/report-transcriptions', { method: 'POST', body })
    notice.value = 'حُفظ النص للمراجعة المستقلة.'
    file.value = null
    fileKey.value++
    locator.value = sourceText.value = ''
    page.value = 1
    await fetchEntries()
  } catch (err) { failure(err) } finally { busy.value = false }
}

async function review(decision: string) {
  if (!selected.value || !reason.value.trim()) return
  busy.value = true
  error.value = notice.value = ''
  try {
    const response = await props.api<{ data: Row }>(`/report-transcriptions/${selected.value.id}/review`, { method: 'POST', body: { decision, reason: reason.value } })
    selected.value = response.data
    reason.value = ''
    notice.value = 'حُفظ قرار المراجعة.'
    await fetchEntries()
  } catch (err) { failure(err) } finally { busy.value = false }
}

async function download() {
  if (!selected.value) return
  busy.value = true
  error.value = ''
  try {
    const blob = await props.api<Blob>(`/report-transcriptions/${selected.value.id}/source`, { download: true })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = String(selected.value.original_name)
    link.click()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (err) { failure(err) } finally { busy.value = false }
}
</script>

<template>
  <section>
    <label>المرفق
      <select v-model="facility" :disabled="busy" @change="page = 1; notice = ''; load()">
        <option value="">اختر المرفق</option>
        <option v-for="item in facilities" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option>
      </select>
    </label>
    <p v-if="error" role="alert" class="notice danger">{{ error }}</p>
    <p v-if="notice" role="status" class="notice positive">{{ notice }}</p>
    <div v-if="facility" class="transcription-workspace">
      <form v-if="writable" class="panel record-form" @submit.prevent="submit">
        <h2>نسخ نص من تقرير</h2>
        <div class="form-grid">
          <label>ملف التقرير
            <input :key="fileKey" type="file" accept="application/pdf" required :disabled="busy" @change="file = ($event.target as HTMLInputElement).files?.[0] ?? null" />
          </label>
          <label>رقم الصفحة في الملف<input v-model.number="sourcePage" type="number" min="1" max="10000" required /></label>
          <label class="wide">مرجع الجدول أو الخانة<input v-model="locator" maxlength="160" required /></label>
          <label class="wide">النص كما يظهر في المصدر<textarea v-model="sourceText" maxlength="10000" rows="5" required /></label>
        </div>
        <div class="form-footer"><button class="button primary" :disabled="busy">إرسال للمراجعة</button></div>
      </form>
      <section class="panel">
        <div class="panel-title"><h2>سجل النسخ والمراجعة</h2></div>
        <p v-if="!entries.length" class="empty-state">{{ busy ? 'جارٍ التحميل…' : 'لا توجد إدخالات لهذا المرفق.' }}</p>
        <div v-else class="table-scroll">
          <table>
            <thead><tr><th>المصدر</th><th>الصفحة</th><th>الموضع</th><th>الحالة</th><th>التفاصيل</th></tr></thead>
            <tbody>
              <tr v-for="entry in entries" :key="String(entry.id)">
                <td>{{ entry.original_name }}</td><td>{{ entry.page }}</td><td>{{ entry.locator }}</td>
                <td>{{ statuses[String(entry.status)] }}</td>
                <td><button class="text-button" :disabled="busy" @click="selected = entry; reason = ''">عرض</button></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="lastPage > 1" class="pagination">
          <button class="button secondary" :disabled="busy || page <= 1" @click="page--; load()">السابق</button>
          <span>{{ page }} / {{ lastPage }}</span>
          <button class="button secondary" :disabled="busy || page >= lastPage" @click="page++; load()">التالي</button>
        </div>
      </section>
      <section v-if="selected" class="panel record-form">
        <h2>النص المنسوخ</h2>
        <p>{{ selected.original_name }} · الصفحة {{ selected.page }} · {{ selected.locator }}</p>
        <p class="source-text">{{ selected.source_text }}</p>
        <dl class="detail-grid">
          <div><dt>المدخل</dt><dd>{{ selected.author_name }}</dd></div>
          <div><dt>المراجع</dt><dd>{{ selected.reviewer_name ?? '—' }}</dd></div>
          <div><dt>وقت المراجعة (UTC)</dt><dd>{{ selected.reviewed_at ?? '—' }}</dd></div>
          <div><dt>سبب القرار</dt><dd>{{ selected.review_reason ?? '—' }}</dd></div>
          <div><dt>بصمة الملف</dt><dd><bdi>{{ selected.sha256 }}</bdi></dd></div>
        </dl>
        <button class="button secondary" :disabled="busy" @click="download">تنزيل المصدر الأصلي</button>
        <div v-if="selected.status === 'pending' && can('imports.approve') && selected.created_by !== userId" class="review-actions">
          <label>سبب القرار<textarea v-model="reason" maxlength="2000" rows="2" required /></label>
          <div class="button-row">
            <button class="button primary" :disabled="busy || !reason.trim()" @click="review('verified')">تأكيد مطابقة النص للمصدر</button>
            <button class="button secondary" :disabled="busy || !reason.trim()" @click="review('rejected')">رفض النسخ</button>
          </div>
          <p>تأكيد المطابقة يوثّق النسخ البشري فقط. اعتماد القياسات له مسار مستقل.</p>
        </div>
      </section>
    </div>
  </section>
</template>

<style scoped>
.source-text { white-space: pre-wrap; overflow-wrap: anywhere; }
.transcription-workspace { margin-block-start: 1rem; }
</style>
