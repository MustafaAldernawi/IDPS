<script setup lang="ts">
import { computed, provide, ref } from 'vue'
import { ApiError, createApi, type User } from './lib/api'
import { resources } from './lib/resources'
import AnalyticsView from './components/AnalyticsView.vue'
import ResourceView from './components/ResourceView.vue'
import ImportsView from './components/ImportsView.vue'
import TrainingStudio from './components/TrainingStudio.vue'
import AdminView from './components/AdminView.vue'
import ReportsView from './components/ReportsView.vue'
import DefinitionsView from './components/DefinitionsView.vue'
import ReferencesView from './components/ReferencesView.vue'
import DailyReadiness from './components/DailyReadiness.vue'
import MonitoringView from './components/MonitoringView.vue'
import './style.css'

const token = ref<string | null>(null), user = ref<User | null>(null), email = ref(''), password = ref(''), error = ref(''), busy = ref(false), active = ref('overview'), menuOpen = ref(false)
const api = createApi(() => token.value, () => { token.value = null; user.value = null; error.value = 'انتهت الجلسة. سجل الدخول مجددًا.' })
const navigation = [
  { key: 'overview', title: 'نظرة عامة', subtitle: 'مؤشرات موثقة وقراءة موحدة للأداء', group: 'الرئيسية', icon: '▦' },
  ...resources,
  { key: 'engineering', title: 'المؤشرات والتحليل', subtitle: 'الفعلي والمستهدف والطاقة والفواقد ضمن نطاق معتمد', group: 'هندسة العمليات', icon: '⌁' },
  { key: 'imports', title: 'تكامل البيانات', subtitle: 'مصادر محفوظة وفحوص جودة واعتماد قابل للتتبع', group: 'البيانات والحوكمة', icon: '↥' },
  { key: 'ai', title: 'استوديو التدريب والتوقع', subtitle: 'من نسخة البيانات إلى نموذج مرشح وقرار موثق', group: 'الذكاء الاصطناعي', icon: '✧' },
  { key: 'reports', title: 'التقارير والتصدير', subtitle: 'تقارير شهرية مرتبطة بالمصدر والفترة وحالة الاعتماد', group: 'البيانات والحوكمة', icon: '▧' },
  { key: 'admin', title: 'المستخدمون والمرجعيات', subtitle: 'إدارة الحسابات والأدوار ونطاق المرافق والوحدات', group: 'البيانات والحوكمة', icon: '⚙' },
  { key: 'definitions', title: 'تعريف المؤشرات', subtitle: 'نطاقات ومعاملات موثقة بإصدارات واعتماد مستقل', group: 'هندسة العمليات', icon: '∑' },
  { key: 'references', title: 'الورديات والحالة والجودة', subtitle: 'تقويم العمل وحالة الوحدات ومواصفات الجودة المعتمدة', group: 'العمليات والتخطيط', icon: '◷' },
  { key: 'daily-readiness', title: 'جاهزية البيانات اليومية', subtitle: 'تغطية القياسات الفعلية وحالات النقص', group: 'البيانات والحوكمة', icon: '▤' },
  { key: 'model-monitoring', title: 'مراقبة النماذج', subtitle: 'التقييمات وسياسات إعادة التدريب المقيدة', group: 'الذكاء الاصطناعي', icon: '◉' },
]
const groups = ['الرئيسية', 'العمليات والتخطيط', 'هندسة العمليات', 'البيانات والحوكمة', 'الذكاء الاصطناعي']
provide('permissions', computed(() => user.value?.permissions ?? []))
const permissionMap: Record<string, string[]> = { overview: ['engineering.read'], engineering: ['engineering.read'], production: ['production.write', 'production.read', 'engineering.read'], feed: ['production.write', 'production.read', 'engineering.read'], energy: ['production.write', 'engineering.read'], losses: ['production.write', 'engineering.read'], chemicals: ['production.write', 'engineering.read'], planning: ['planning.write', 'planning.read', 'engineering.read'], events: ['events.write', 'events.read', 'events.approve', 'engineering.read'], quality: ['quality.write', 'quality.read', 'engineering.read'], blending: ['quality.write', 'quality.read', 'engineering.read'], monthly: ['production.read'], audit: ['audit.read'], imports: ['imports.read', 'imports.write', 'imports.approve'], ai: ['ai.read', 'dataset.read', 'ai.train'], reports: ['reports.export'], admin: ['users.manage', 'catalogs.manage'] }
permissionMap.definitions = ['engineering.read', 'production.approve', 'planning.approve']
permissionMap.annotations = ['engineering.read', 'production.approve']
permissionMap.references = ['catalogs.read', 'quality.read', 'production.read', 'engineering.read']
permissionMap['daily-readiness'] = ['engineering.read']
permissionMap['model-monitoring'] = ['ai.read']
const visibleNavigation = computed(() => navigation.filter(item => user.value?.permissions.includes('*') || permissionMap[item.key]?.some(permission => user.value?.permissions.includes(permission))))
const current = computed(() => navigation.find(item => item.key === active.value) ?? navigation[0]!)
const resource = computed(() => resources.find(item => item.key === active.value))
async function login() {
  busy.value = true; error.value = ''
  try {
    const result = await api<{ token: string; user: User }>('/auth/login', { method: 'POST', body: { email: email.value, password: password.value } })
    token.value = result.token; user.value = result.user; password.value = ''; active.value = visibleNavigation.value[0]?.key ?? 'overview'
  } catch (e) { error.value = e instanceof ApiError ? e.message : 'تعذر تسجيل الدخول.' }
  finally { busy.value = false }
}
async function logout() {
  try { await api('/auth/logout', { method: 'POST' }) } finally { token.value = null; user.value = null; error.value = '' }
}
function navigate(key: string) { active.value = key; menuOpen.value = false }
</script>

<template>
  <div v-if="!user" class="login-layout" dir="rtl">
    <section class="login-story"><div class="brand brand-light"><img :src="'/zawiya-refinery-logo.png'" alt="شعار مصفاة الزاوية" /><div><strong>مصفاة الزاوية</strong><span>AZZAWIYA OIL REFINING COMPANY</span></div></div><div class="story-copy"><span class="eyebrow">INTEGRATED DATA & PRODUCTION SYSTEM</span><h1>بيانات موثّقة.<br />رؤية أوضح للإنتاج.</h1><p>مساحة عمل موحدة للإنتاج والتخطيط وهندسة العمليات، تربط مصدر القياس بمؤشرات الأداء والتوقع.</p><div class="story-divider" /><div class="story-points"><span>تكامل البيانات</span><span>تحليل الأداء</span><span>توقع مسؤول</span></div></div><p class="story-footer">IDPS · نظام دعم قرار الإنتاج</p></section>
    <main class="login-main"><div class="login-card"><span class="product-name">IDPS<span class="product-dot" /></span><span class="eyebrow">بوابة منظومة الإنتاج</span><h2>مرحبًا بعودتك</h2><p class="muted">سجّل الدخول إلى مساحة العمل الخاصة بك.</p><p v-if="error" role="alert" class="notice danger">{{ error }}</p><form @submit.prevent="login"><label>البريد الإلكتروني<input v-model="email" type="email" autocomplete="username" dir="ltr" placeholder="name@company.ly" required /></label><label>كلمة المرور<input v-model="password" type="password" autocomplete="current-password" dir="ltr" required /></label><button class="button primary login-button" :disabled="busy">{{ busy ? 'جارٍ تسجيل الدخول…' : 'تسجيل الدخول' }} <span aria-hidden="true">←</span></button></form><div class="login-note"><span aria-hidden="true">◈</span><p>الوصول حسب الصلاحية، والإجراءات قابلة للتتبع والمراجعة.</p></div></div><p class="login-footer">تصميم منظومة ذكية لتكامل البيانات وتحليل الإنتاج النفطي وحساب مؤشرات الأداء</p></main>
  </div>
  <div v-else class="app-layout" dir="rtl">
    <a class="skip-link" href="#main-content">انتقل إلى المحتوى</a>
    <aside class="sidebar" :class="{ open: menuOpen }"><div class="brand"><img :src="'/zawiya-refinery-logo.png'" alt="شعار مصفاة الزاوية" /><div><strong>مصفاة الزاوية</strong><span>منظومة البيانات والإنتاج</span></div></div><div class="workspace-label"><span class="workspace-dot" /> مساحة العمل <strong>IDPS</strong></div><nav aria-label="القائمة الرئيسية"><div v-for="group in groups" :key="group" class="nav-group"><div class="nav-group-label">{{ group }}</div><button v-for="item in visibleNavigation.filter(item => item.group === group)" :key="item.key" :class="{ active: active === item.key }" :aria-current="active === item.key ? 'page' : undefined" @click="navigate(item.key)"><span class="nav-icon" aria-hidden="true">{{ item.icon }}</span>{{ item.title }}<span v-if="active === item.key" class="nav-active-dot" /></button></div></nav><div class="sidebar-footer"><span>منظومة دعم قرار</span><strong>إصدار التطوير المحلي</strong></div></aside>
    <div v-if="menuOpen" class="menu-backdrop" @click="menuOpen = false" />
    <div class="workspace"><header class="topbar"><div class="breadcrumb"><button class="menu-button" aria-label="فتح القائمة" :aria-expanded="menuOpen" @click="menuOpen = !menuOpen">☰</button><span>مساحة العمل</span><span class="separator">/</span><strong>{{ current.title }}</strong></div><div class="user-area"><span class="local-chip"><i /> محلي</span><div class="user-avatar">{{ user.name?.slice(0, 1) ?? 'م' }}</div><div class="user-name"><strong>{{ user.name }}</strong><span>{{ user.roles?.join(' · ') || 'مستخدم المنظومة' }}</span></div><button class="text-button logout-button" @click="logout">خروج</button></div></header>
      <main id="main-content" class="main-content"><div class="page-heading"><div><span class="eyebrow">{{ current.group }}</span><h1>{{ current.title }}</h1><p>{{ current.subtitle }}</p></div><span class="page-version">V1 <span>منظومة الإنتاج</span></span></div>
        <AnalyticsView v-if="active === 'overview'" key="overview" :api="api" overview />
        <AnalyticsView v-else-if="active === 'engineering'" key="engineering" :api="api" />
        <ImportsView v-else-if="active === 'imports'" :api="api" />
        <TrainingStudio v-else-if="active === 'ai'" :api="api" />
        <AdminView v-else-if="active === 'admin'" :api="api" />
        <ReportsView v-else-if="active === 'reports'" :api="api" />
        <DefinitionsView v-else-if="active === 'definitions'" :api="api" />
        <ReferencesView v-else-if="active === 'references'" :api="api" />
        <DailyReadiness v-else-if="active === 'daily-readiness'" :api="api" />
        <MonitoringView v-else-if="active === 'model-monitoring'" :api="api" />
        <ResourceView v-else-if="resource" :resource="resource" :api="api" />
      </main><footer class="workspace-footer"><span>IDPS — Integrated Data and Production System</span><span>مصفاة الزاوية · Africa/Tripoli</span></footer>
    </div>
  </div>
</template>


