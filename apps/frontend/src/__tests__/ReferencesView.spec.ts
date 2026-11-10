import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import { computed } from 'vue'
import type { Api } from '../lib/api'
import ReferencesView from '../components/ReferencesView.vue'
describe('المرجعيات', () => {
 it('shows empty data and hides create without permission', async () => {
  const api = vi.fn<() => Promise<{data: never[]}>>(async () => ({ data: [] }))
  const wrapper = mount(ReferencesView, { props: { api: api as unknown as Api }, global: { provide: { permissions: computed(() => ['catalogs.read']) } } })
  await flushPromises()
  expect(wrapper.text()).toContain('لا توجد سجلات')
  expect(wrapper.text()).not.toContain('حفظ السجل')
 })
 it('submits a shift with explicit local clock times', async () => {
  const api = vi.fn<(path: string) => Promise<{data: {id:string;name_ar:string}[]}>>(async (path: string) => path.includes('/catalogs/facilities') ? { data: [{ id: 'f1', name_ar: 'مرفق' }] } : { data: [] })
  const wrapper = mount(ReferencesView, { props: { api: api as unknown as Api }, global: { provide: { permissions: computed(() => ['catalogs.read','catalogs.manage']) } } })
  await flushPromises()
  await wrapper.get('select[aria-label="المرفق"]').setValue('f1')
  await wrapper.get('input[name="code"]').setValue('A')
  await wrapper.get('input[name="name_ar"]').setValue('أ')
  await wrapper.get('input[name="start_time"]').setValue('08:00')
  await wrapper.get('input[name="end_time"]').setValue('16:00')
  await wrapper.get('input[name="effective_from"]').setValue('2026-01-01')
  await wrapper.get('form[data-test="reference-form"]').trigger('submit')
  await flushPromises()
  expect(api).toHaveBeenCalledWith('/references/shifts', expect.objectContaining({ method: 'POST', body: expect.objectContaining({ facility_id: 'f1', start_time: '08:00' }) }))
 })
})
