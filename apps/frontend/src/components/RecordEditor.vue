<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ApiError, rowsOf, type Api, type Row } from '../lib/api'
import type { Field } from '../lib/resources'
const props = defineProps<{ fields: Field[]; api: Api; busy: boolean; errors: Record<string, string[]>; initial?: Row }>()
const emit = defineEmits<{ save: [value: Row]; cancel: [] }>()
const values = reactive<Record<string, string>>({})
const catalogs = ref<Record<string, Row[]>>({})
const catalogError = ref('')
const components = reactive([{ product_id: '', quantity: '', unit_code: 't' }])
if (props.initial) {
  for (const f of props.fields) {
    const value = props.initial[f.key]
    if (value !== undefined && value !== null && f.type !== 'json') values[f.key] = String(value)
  }
  const children = props.initial.targets ?? props.initial.components
  if (Array.isArray(children) && children.length) components.splice(0, 1, ...children.map(c => ({ product_id: String(c.product_id), quantity: String(c.quantity), unit_code: String(c.unit_code) })))
}
onMounted(async () => {
  const names = [...new Set(props.fields.flatMap(f => f.catalog ? [f.catalog] : f.type === 'json' ? ['products'] : []))]
  await Promise.all(names.map(async name => {
    try { catalogs.value[name] = rowsOf(await props.api(`/catalogs/${name}`)) }
    catch (e) { catalogError.value = e instanceof ApiError ? e.message : 'تعذر تحميل المرجعيات' }
  }))
})
function submit() {
  const payload: Row = {}
  if (props.initial?.id) payload.replaces_id = props.initial.id
  for (const f of props.fields) {
    const value = values[f.key]
    payload[f.key] = f.type === 'json' ? components.map(c => ({ ...c }))
      : value === '' || value === undefined ? null
        : f.type === 'datetime-local' ? new Date(value).toISOString() : value
  }
  emit('save', payload)
}
function optionValue(row: Row, field: Field) { return String(field.key.endsWith('_code') ? row.code : row.id ?? row.code) }
</script>
<template>
  <form class="record-form" @submit.prevent="submit">
    <p v-if="catalogError" class="notice warning">{{ catalogError }}. أعد المحاولة قبل الحفظ.</p>
    <div class="form-grid">
      <label v-for="field in fields" :key="field.key" :class="{ wide: field.type === 'textarea' || field.type === 'json' }">
        <span>{{ field.label }} <span v-if="field.required" class="required" aria-label="مطلوب">*</span></span>
        <template v-if="field.type === 'json'">
          <div v-for="(component, index) in components" :key="index" class="component-row">
            <select v-model="component.product_id" aria-label="المنتج أو المكون" required><option value="">اختر المنتج</option><option v-for="product in catalogs.products ?? []" :key="String(product.id)" :value="String(product.id)">{{ product.name_ar ?? product.code }}</option></select>
            <input :value="component.quantity" aria-label="كمية مكون الخلط" placeholder="الكمية" type="number" min="0" step="any" required @input="component.quantity = ($event.target as HTMLInputElement).value" />
            <input v-model="component.unit_code" aria-label="وحدة مكون الخلط" placeholder="الوحدة" required />
            <button v-if="components.length > 1" type="button" class="text-button" @click="components.splice(index, 1)">إزالة</button>
          </div>
          <button type="button" class="text-button" @click="components.push({ product_id: '', quantity: '', unit_code: 't' })">+ إضافة منتج أو مكون</button>
        </template>
        <select v-else-if="field.options || field.catalog" v-model="values[field.key]" :required="field.required" :aria-invalid="!!errors[field.key]">
          <option value="">اختر {{ field.label }}</option>
          <option v-for="option in field.options ?? []" :key="option[0]" :value="option[0]">{{ option[1] }}</option>
          <option v-for="row in field.catalog ? catalogs[field.catalog] ?? [] : []" :key="String(row.id ?? row.code)" :value="optionValue(row, field)">{{ row.name_ar ?? row.name ?? row.code }}</option>
        </select>
        <textarea v-else-if="field.type === 'textarea'" v-model="values[field.key]" rows="3" :required="field.required" :aria-invalid="!!errors[field.key]" />
        <input v-else :value="values[field.key]" :type="field.type ?? 'text'" :required="field.required" :min="field.type === 'number' ? 0 : undefined" :step="field.type === 'number' ? 'any' : undefined" :aria-invalid="!!errors[field.key]" @input="values[field.key] = ($event.target as HTMLInputElement).value" />
        <small v-if="field.help">{{ field.help }}</small><small v-if="errors[field.key]" class="field-error">{{ errors[field.key]?.join('، ') }}</small>
      </label>
    </div>
    <div class="form-footer"><p>يُحفظ السجل كمسودة للمراجعة قبل الاعتماد.</p><button type="button" class="button secondary" @click="emit('cancel')">إلغاء</button><button class="button primary" :disabled="busy">{{ busy ? 'جارٍ الحفظ…' : 'حفظ السجل' }}</button></div>
  </form>
</template>
