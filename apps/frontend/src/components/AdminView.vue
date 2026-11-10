<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ApiError, rowsOf, type Api, type Row } from '../lib/api'
import { display } from '../lib/presentation'
import DataTable from './DataTable.vue'
import ResourceView from './ResourceView.vue'
import type { Resource } from '../lib/resources'
const props = defineProps<{ api: Api }>()
const tab = ref('users'), users = ref<Row[]>([]), roles = ref<Row[]>([]), assignments = ref<Row[]>([]), facilities = ref<Row[]>([]), units = ref<Row[]>([])
const name = ref(''), email = ref(''), password = ref(''), userId = ref(''), roleId = ref(''), facilityId = ref(''), unitId = ref(''), reason = ref(''), error = ref(''), success = ref(''), busy = ref(false)
const catalogs: Resource[] = [
  { key: 'facilities', title: 'المرافق', subtitle: '', endpoint: '/catalogs/facilities', group: '', icon: '', columns: ['code', 'name_ar'], fields: [{ key: 'code', label: 'الرمز', required: true }, { key: 'name_ar', label: 'الاسم العربي', required: true }] },
  { key: 'units', title: 'الوحدات التشغيلية', subtitle: '', endpoint: '/catalogs/units', group: '', icon: '', columns: ['code', 'name_ar', 'facility_id'], fields: [{ key: 'code', label: 'الرمز', required: true }, { key: 'name_ar', label: 'الاسم العربي', required: true }, { key: 'facility_id', label: 'المرفق', catalog: 'facilities', required: true }] },
  { key: 'products', title: 'المنتجات', subtitle: '', endpoint: '/catalogs/products', group: '', icon: '', columns: ['code', 'name_ar', 'stage'], fields: [{ key: 'code', label: 'الرمز', required: true }, { key: 'name_ar', label: 'الاسم العربي', required: true }, { key: 'stage', label: 'مرحلة المنتج', required: true, options: [['pending_review', 'بانتظار التعريف الهندسي'], ['intermediate_stream', 'تيار وسيط'], ['final_product', 'منتج نهائي'], ['blend_additive', 'إضافة خلط'], ['fuel', 'وقود'], ['byproduct', 'منتج جانبي'], ['waste', 'مخلفات'], ['other', 'أخرى']] }] },
  { key: 'crudes', title: 'الخامات', subtitle: '', endpoint: '/catalogs/crudes', group: '', icon: '', columns: ['code', 'name_ar'], fields: [{ key: 'code', label: 'الرمز', required: true }, { key: 'name_ar', label: 'الاسم العربي', required: true }] },
  { key: 'measurement-units', title: 'وحدات القياس', subtitle: '', endpoint: '/catalogs/measurement-units', group: '', icon: '', columns: ['code', 'dimension', 'display_symbol'], fields: [{ key: 'code', label: 'الرمز', required: true }, { key: 'dimension', label: 'البعد الفيزيائي', required: true, options: [['mass', 'كتلة'], ['volume', 'حجم'], ['energy', 'طاقة'], ['time', 'زمن'], ['ratio', 'نسبة'], ['quality', 'جودة']] }, { key: 'display_symbol', label: 'رمز العرض', required: true }] },
]
const catalog = computed(() => catalogs.find(item => item.key === tab.value))
async function load() {
  error.value = ''
  try {
    const result = await props.api<{ data: Row }>('/users'); users.value = rowsOf(result.data.users); roles.value = rowsOf(result.data.roles); assignments.value = rowsOf(result.data.assignments)
    facilities.value = rowsOf(await props.api('/catalogs/facilities')); units.value = rowsOf(await props.api('/catalogs/units'))
  } catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر تحميل المستخدمين والصلاحيات' }
}
onMounted(load)
async function createUser() {
  busy.value = true; error.value = ''; success.value = ''
  try { await props.api('/users', { method: 'POST', body: { name: name.value, email: email.value, password: password.value } }); password.value = ''; name.value = ''; email.value = ''; success.value = 'أُنشئ المستخدم. أسند دوره ونطاقه قبل الاستخدام.'; await load() }
  catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر إنشاء المستخدم' }
  finally { busy.value = false }
}
async function assignRole() {
  if (reason.value.trim().length < 10) { error.value = 'يلزم سبب موثق من عشرة أحرف على الأقل لإسناد الدور.'; return }
  busy.value = true; error.value = ''; success.value = ''
  try { await props.api(`/users/${userId.value}/roles`, { method: 'POST', body: { role_id: roleId.value, facility_id: facilityId.value || null, unit_id: unitId.value || null, reason: reason.value } }); success.value = 'أُسند الدور بالنطاق المحدد وحُفظ الأثر التدقيقي.'; reason.value = ''; await load() }
  catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر إسناد الدور' }
  finally { busy.value = false }
}
</script>
<template><section><div class="tabs" role="tablist" aria-label="الإدارة"><button role="tab" :aria-selected="tab === 'users'" :class="{ active: tab === 'users' }" @click="tab = 'users'">المستخدمون والصلاحيات</button><button v-for="item in catalogs" :key="item.key" role="tab" :aria-selected="tab === item.key" :class="{ active: tab === item.key }" @click="tab = item.key">{{ item.title }}</button></div><ResourceView v-if="catalog" :resource="catalog" :api="api" /><template v-else>
  <p v-if="error" class="notice danger" role="alert">{{ error }}</p><p v-if="success" class="notice positive" role="status">{{ success }}</p>
  <div class="panel"><div class="panel-title"><h2>إنشاء مستخدم</h2><span class="badge">صلاحية إدارية</span></div><form class="compact-form" @submit.prevent="createUser"><label>الاسم<input v-model="name" required autocomplete="off" /></label><label>البريد الإلكتروني<input v-model="email" type="email" required dir="ltr" autocomplete="off" /></label><label>كلمة مرور أولية<input v-model="password" type="password" required minlength="12" maxlength="128" autocomplete="new-password" /></label><button class="button primary" :disabled="busy">إنشاء المستخدم</button></form></div>
  <div class="panel"><div class="panel-title"><h2>إسناد دور ونطاق</h2></div><form class="record-form" @submit.prevent="assignRole"><div class="form-grid"><label>المستخدم<select v-model="userId" required><option value="">اختر المستخدم</option><option v-for="user in users" :key="String(user.id)" :value="String(user.id)">{{ user.name }} · {{ user.email }}</option></select></label><label>الدور<select v-model="roleId" required><option value="">اختر الدور</option><option v-for="role in roles" :key="String(role.id)" :value="String(role.id)">{{ role.name_ar ?? role.code }}</option></select></label><label>المرفق<select v-model="facilityId" @change="unitId = ''"><option value="">كل المرافق — نطاق عام</option><option v-for="item in facilities" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label><label>الوحدة التشغيلية<select v-model="unitId"><option value="">كل وحدات النطاق</option><option v-for="item in units.filter(row => row.facility_id === facilityId)" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option></select></label><label class="wide">سبب منح الصلاحية<textarea v-model="reason" required minlength="10" rows="2" /></label></div><div class="form-footer"><p>النطاق العام يمنح الوصول عبر المرافق وفق صلاحيات الدور.</p><button class="button primary" :disabled="busy">إسناد الدور</button></div></form></div>
  <div class="panel"><div class="panel-title"><h2>المستخدمون</h2><button class="text-button" @click="load">↻ تحديث</button></div><DataTable v-if="users.length" :rows="users" :columns="['name', 'email', 'active']" /><div v-else class="empty-state">لا يوجد مستخدمون متاحون.</div></div>
  <div class="panel"><div class="panel-title"><h2>الأدوار المسندة</h2><span class="muted">{{ display(assignments.length) }} إسنادًا</span></div><DataTable v-if="assignments.length" :rows="assignments" :columns="['user_id', 'role_id', 'facility_id', 'unit_id']" /><div v-else class="empty-state">لا توجد إسنادات متاحة.</div></div>
</template></section></template>
