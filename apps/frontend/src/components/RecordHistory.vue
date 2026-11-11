<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ApiError, type Api } from '../lib/api'
import StatusBadge from './StatusBadge.vue'

interface Version {
  id: string
  replaces_id: string | null
  revision_no: number
  status: string
  source_reference: string
  created_at: string
}
interface Decision {
  resource_id: string
  action: string
  created_at: string
  reason: string | null
  actor_name: string | null
}
const props = defineProps<{ api: Api; endpoint: string; id: string }>()
const history = ref<{ versions: Version[]; decisions: Decision[] }>()
const error = ref('')
const loading = ref(false)
const actions: Record<string, string> = { submit: 'إحالة للمراجعة', approve: 'اعتماد', reject: 'رفض', supersede: 'استبدال بإصدار' }
const formatter = new Intl.DateTimeFormat('ar-LY', { timeZone: 'Africa/Tripoli', dateStyle: 'medium', timeStyle: 'short' })
function dateTime(value: string) {
  return formatter.format(new Date(value.replace(' ', 'T') + 'Z'))
}
function revision(id: string | null) {
  return history.value?.versions.find(item => item.id === id)?.revision_no
}
async function load() {
  loading.value = true
  error.value = ''
  try {
    history.value = (await props.api<{ data: NonNullable<typeof history.value> }>(props.endpoint + '/' + encodeURIComponent(props.id) + '/history')).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'تعذر تحميل سجل المراجعات.'
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>

<template>
  <section class="record-history">
    <p v-if="loading" role="status">جارٍ تحميل سجل المراجعات…</p>
    <div v-else-if="error" role="alert">{{ error }} <button type="button" class="button secondary" @click="load">إعادة المحاولة</button></div>
    <template v-else-if="history">
      <h3>تاريخ الإصدارات</h3>
      <div class="table-scroll">
        <table>
          <thead><tr><th>الإصدار</th><th>الإصدار السابق</th><th>مرجع المصدر</th><th>وقت الإنشاء</th><th>الحالة</th></tr></thead>
          <tbody>
            <tr v-for="version in history.versions" :key="version.id">
              <td>{{ version.revision_no }} <small v-if="version.id === id">المعروض</small></td>
              <td>{{ revision(version.replaces_id) ?? '—' }}</td>
              <td>{{ version.source_reference }}</td><td>{{ dateTime(version.created_at) }}</td>
              <td><StatusBadge :value="version.status" /></td>
            </tr>
          </tbody>
        </table>
      </div>
      <h3>سجل قرارات المراجعة · توقيت طرابلس</h3>
      <div v-if="history.decisions.length" class="table-scroll">
        <table>
          <thead><tr><th>الإصدار</th><th>الإجراء</th><th>المسؤول</th><th>الوقت</th><th>السبب</th></tr></thead>
          <tbody>
            <tr v-for="(decision, index) in history.decisions" :key="index">
              <td>{{ revision(decision.resource_id) }}</td><td>{{ actions[decision.action] }}</td>
              <td>{{ decision.actor_name ?? '—' }}</td><td>{{ dateTime(decision.created_at) }}</td><td>{{ decision.reason ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="muted">لا توجد قرارات مراجعة مسجلة.</p>
    </template>
  </section>
</template>

<style scoped>
.record-history { padding: 1.25rem; border-block-start: 1px solid var(--line); }
.record-history h3 { margin-block: .75rem; }
.record-history td { white-space: normal; }
</style>
