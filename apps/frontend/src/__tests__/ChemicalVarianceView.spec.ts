import { flushPromises, mount } from '@vue/test-utils'
import { computed } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import ChemicalVarianceView from '../components/ChemicalVarianceView.vue'
import type { Api } from '../lib/api'

describe('مقارنة استهلاك المواد الكيميائية', () => {
  it('يعرض غياب الفعلي دون تحويله إلى صفر ويحجب إدخال الخطة عن القارئ', async () => {
    const api = vi.fn<(path: string) => Promise<unknown>>(async (path: string) => {
      if (path === '/catalogs/facilities') return { data: [{ id: 'f', name_ar: 'المرفق' }] }
      if (path.startsWith('/engineering/chemical-variance')) return { data: [{ chemical_code: 'CHEM', unit_code: 'kg', target: '0.000000', actual: null, variance: null, status: 'missing_actual' }] }
      return { data: [] }
    })
    const wrapper = mount(ChemicalVarianceView, { props: { api: api as unknown as Api }, global: { provide: { permissions: computed(() => ['engineering.read']) } } })
    await flushPromises()
    await wrapper.get('select').setValue('f')
    await wrapper.get('input[type="month"]').setValue('2026-01')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('الفعلي المعتمد غير متاح')
    expect(wrapper.text()).toContain('0.000000')
    expect(wrapper.text()).not.toContain('إدخال مستهدف مستقل')
  })
})
