<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ApiError, rowsOf, type Api, type Row } from '../lib/api'
import { display } from '../lib/presentation'
import ResourceView from './ResourceView.vue'
import StatusBadge from './StatusBadge.vue'
import type { Resource } from '../lib/resources'

const props = defineProps<{ api: Api }>()
const tab = ref('users'), users = ref<Row[]>([]), roles = ref<Row[]>([]), assignments = ref<Row[]>([]), facilities = ref<Row[]>([]), units = ref<Row[]>([])
const name = ref(''), email = ref(''), password = ref(''), userId = ref(''), roleId = ref(''), facilityId = ref(''), unitId = ref(''), reason = ref('')
const error = ref(''), success = ref(''), busy = ref(false), userSearch = ref('')
const actionUser = ref<Row | null>(null), actionAssignment = ref<Row | null>(null), actionReason = ref('')

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
    const query = userSearch.value.trim() ? `?q=${encodeURIComponent(userSearch.value.trim())}` : ''
    const result = await props.api<{ data: { users: Row; roles: Row[]; assignments: Row[] } }>(`/users${query}`)
    users.value = rowsOf(result.data.users)
    roles.value = rowsOf(result.data.roles)
    assignments.value = rowsOf(result.data.assignments)
    facilities.value = rowsOf(await props.api('/catalogs/facilities'))
    units.value = rowsOf(await props.api('/catalogs/units'))
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'تعذر تحميل المستخدمين والصلاحيات'
  }
}

onMounted(load)

async function createUser() {
  busy.value = true; error.value = ''; success.value = ''
  try {
    await props.api('/users', { method: 'POST', body: { name: name.value, email: email.value, password: password.value } })
    password.value = ''; name.value = ''; email.value = ''
    success.value = 'أُنشئ المستخدم بنجاح. أسند دوره ونطاقه قبل الاستخدام.'
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'تعذر إنشاء المستخدم'
  } finally {
    busy.value = false
  }
}

async function assignRole() {
  if (reason.value.trim().length < 10) {
    error.value = 'يلزم سبب موثق من عشرة أحرف على الأقل لإسناد الدور.'
    return
  }
  busy.value = true; error.value = ''; success.value = ''
  try {
    await props.api(`/users/${userId.value}/roles`, {
      method: 'POST',
      body: { role_id: roleId.value, facility_id: facilityId.value || null, unit_id: unitId.value || null, reason: reason.value },
    })
    success.value = 'أُسند الدور بالنطاق المحدد وحُفظ الأثر التدقيقي.'
    reason.value = ''
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'تعذر إسناد الدور'
  } finally {
    busy.value = false
  }
}

async function toggleUserStatus() {
  if (!actionUser.value || actionReason.value.trim().length < 10) {
    error.value = 'أدخل سبب تغيير حالة الحساب (10 أحرف على الأقل).'
    return
  }
  busy.value = true; error.value = ''; success.value = ''
  try {
    const newStatus = !actionUser.value.active
    await props.api(`/users/${actionUser.value.id}/status`, {
      method: 'POST',
      body: { active: newStatus, reason: actionReason.value },
    })
    success.value = newStatus ? 'تم تفعيل حساب المستخدم.' : 'تم تعطيل حساب المستخدم وحفظ السبب في سجل التدقيق.'
    actionUser.value = null
    actionReason.value = ''
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'تعذر تغيير حالة المستخدم'
  } finally {
    busy.value = false
  }
}

async function revokeAssignment() {
  if (!actionAssignment.value || actionReason.value.trim().length < 10) {
    error.value = 'أدخل سبب إلغاء إسناد الدور (10 أحرف على الأقل).'
    return
  }
  busy.value = true; error.value = ''; success.value = ''
  try {
    await props.api(`/users/${actionAssignment.value.user_id}/roles/${actionAssignment.value.id}`, {
      method: 'DELETE',
      body: { reason: actionReason.value },
    })
    success.value = 'تم إلغاء إسناد الدور وتوثيق السبب في سجل التدقيق.'
    actionAssignment.value = null
    actionReason.value = ''
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'تعذر إلغاء الإسناد'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <section>
    <div class="tabs" role="tablist" aria-label="الإدارة">
      <button role="tab" :aria-selected="tab === 'users'" :class="{ active: tab === 'users' }" @click="tab = 'users'">المستخدمون والصلاحيات</button>
      <button v-for="item in catalogs" :key="item.key" role="tab" :aria-selected="tab === item.key" :class="{ active: tab === item.key }" @click="tab = item.key">{{ item.title }}</button>
    </div>

    <ResourceView v-if="catalog" :resource="catalog" :api="api" />

    <template v-else>
      <p v-if="error" class="notice danger" role="alert">{{ error }}</p>
      <p v-if="success" class="notice positive" role="status">{{ success }}</p>

      <!-- User Status / Revoke Dialog -->
      <div v-if="actionUser" class="panel record-form">
        <div class="panel-title">
          <h2>{{ actionUser.active ? 'تعطيل حساب مستخدم' : 'تفعيل حساب مستخدم' }}: {{ actionUser.name }}</h2>
          <button class="text-button" @click="actionUser = null">إلغاء ×</button>
        </div>
        <p class="notice warning">تغيير حالة الحساب يمنع أو يسمح للمستخدم بتسجيل الدخول ويتم توثيقه في سجل التدقيق.</p>
        <label>سبب الإجراء (مطلوب)
          <textarea v-model="actionReason" required minlength="10" rows="2" placeholder="اكتب سبب تغيير حالة الحساب..." />
        </label>
        <div class="button-row">
          <button class="button primary" :disabled="busy || actionReason.trim().length < 10" @click="toggleUserStatus">
            {{ actionUser.active ? 'تأكيد التعطيل' : 'تأكيد التفعيل' }}
          </button>
          <button class="button secondary" @click="actionUser = null">إلغاء</button>
        </div>
      </div>

      <div v-if="actionAssignment" class="panel record-form">
        <div class="panel-title">
          <h2>إلغاء إسناد الدور: {{ actionAssignment.role_name_ar ?? actionAssignment.role_code }} للمستخدم {{ actionAssignment.user_name }}</h2>
          <button class="text-button" @click="actionAssignment = null">إلغاء ×</button>
        </div>
        <p class="notice danger">سيتم سحب جميع صلاحيات هذا الدور من المستخدم على النطاق المحدد فورًا.</p>
        <label>سبب إلغاء الصلاحية (مطلوب)
          <textarea v-model="actionReason" required minlength="10" rows="2" placeholder="اكتب سبب إلغاء الإسناد..." />
        </label>
        <div class="button-row">
          <button class="button secondary danger-text" :disabled="busy || actionReason.trim().length < 10" @click="revokeAssignment">
            تأكيد إلغاء الإسناد
          </button>
          <button class="button secondary" @click="actionAssignment = null">إلغاء</button>
        </div>
      </div>

      <div class="panel">
        <div class="panel-title"><h2>إنشاء مستخدم جديد</h2><span class="badge">صلاحية إدارية</span></div>
        <form class="compact-form" @submit.prevent="createUser">
          <label>الاسم الكامل<input v-model="name" required autocomplete="off" /></label>
          <label>البريد الإلكتروني الرسمي<input v-model="email" type="email" required dir="ltr" autocomplete="off" /></label>
          <label>كلمة مرور أولية<input v-model="password" type="password" required minlength="12" maxlength="128" autocomplete="new-password" /></label>
          <button class="button primary" :disabled="busy">إنشاء المستخدم</button>
        </form>
      </div>

      <div class="panel">
        <div class="panel-title"><h2>إسناد دور ونطاق تشغيلي</h2></div>
        <form class="record-form" @submit.prevent="assignRole">
          <div class="form-grid">
            <label>المستخدم
              <select v-model="userId" required>
                <option value="">اختر المستخدم</option>
                <option v-for="user in users" :key="String(user.id)" :value="String(user.id)">{{ user.name }} · {{ user.email }}</option>
              </select>
            </label>
            <label>الدور الوظيفي
              <select v-model="roleId" required>
                <option value="">اختر الدور</option>
                <option v-for="role in roles" :key="String(role.id)" :value="String(role.id)">{{ role.name_ar ?? role.code }}</option>
              </select>
            </label>
            <label>نطاق المرفق
              <select v-model="facilityId" @change="unitId = ''">
                <option value="">كل المرافق — نطاق عام</option>
                <option v-for="item in facilities" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option>
              </select>
            </label>
            <label>نطاق الوحدة التشغيلية
              <select v-model="unitId">
                <option value="">كل وحدات النطاق المختار</option>
                <option v-for="item in units.filter(row => !facilityId || row.facility_id === facilityId)" :key="String(item.id)" :value="String(item.id)">{{ item.name_ar ?? item.code }}</option>
              </select>
            </label>
            <label class="wide">سبب منح الصلاحية والنطاق
              <textarea v-model="reason" required minlength="10" rows="2" placeholder="اكتب سبب إسناد هذا الدور والنطاق لتوثيقه في سجل التدقيق..." />
            </label>
          </div>
          <div class="form-footer">
            <p>النطاق العام يمنح الوصول عبر جميع المرافق وفق صلاحيات الدور المحدد.</p>
            <button class="button primary" :disabled="busy">إسناد الدور</button>
          </div>
        </form>
      </div>

      <div class="panel">
        <div class="panel-title">
          <h2>المستخدمون المسجلون</h2>
          <div class="panel-actions">
            <form class="inline-filters" @submit.prevent="load">
              <input v-model="userSearch" type="search" placeholder="بحث بالاسم أو البريد..." />
              <button class="button secondary" type="submit">بحث</button>
            </form>
            <button class="text-button" @click="load">↻ تحديث</button>
          </div>
        </div>
        <div v-if="!users.length" class="empty-state">لا يوجد مستخدمون مطابقون.</div>
        <div v-else class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>الاسم</th>
                <th>البريد الإلكتروني</th>
                <th>الحالة</th>
                <th>الإجراء</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in users" :key="String(user.id)">
                <td>{{ user.name }}</td>
                <td dir="ltr">{{ user.email }}</td>
                <td><StatusBadge :value="user.active ? 'active' : 'inactive'" /></td>
                <td>
                  <button class="text-button" :disabled="busy" @click="actionUser = user; actionReason = ''; actionAssignment = null">
                    {{ user.active ? 'تعطيل الحساب' : 'تفعيل الحساب' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel">
        <div class="panel-title">
          <h2>الأدوار والصلاحيات المسندة</h2>
          <span class="muted">{{ display(assignments.length) }} إسنادًا</span>
        </div>
        <div v-if="!assignments.length" class="empty-state">لا توجد إسنادات أدوار مسجلة.</div>
        <div v-else class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>المستخدم</th>
                <th>الدور</th>
                <th>المرفق</th>
                <th>الوحدة</th>
                <th>الإجراء</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="assignment in assignments" :key="String(assignment.id)">
                <td>{{ assignment.user_name ?? assignment.user_id }}</td>
                <td>{{ assignment.role_name_ar ?? assignment.role_code ?? assignment.role_id }}</td>
                <td>{{ assignment.facility_name_ar ?? assignment.facility_code ?? (assignment.facility_id ? assignment.facility_id : 'نطاق عام') }}</td>
                <td>{{ assignment.unit_name_ar ?? assignment.unit_code ?? (assignment.unit_id ? assignment.unit_id : 'كل الوحدات') }}</td>
                <td>
                  <button class="text-button danger-text" :disabled="busy" @click="actionAssignment = assignment; actionReason = ''; actionUser = null">
                    إلغاء الإسناد
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.danger-text { color: var(--danger, #dc2626); }
</style>
