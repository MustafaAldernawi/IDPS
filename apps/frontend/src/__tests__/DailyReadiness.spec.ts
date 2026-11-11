import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import { computed } from 'vue'
import type { Api } from '../lib/api'
import DailyReadiness from '../components/DailyReadiness.vue'

describe('جاهزية البيانات اليومية', () => {
  it('يعرض التغطية والصفر والتواريخ المفقودة دون اعتبارها جاهزية للنموذج', async () => {
    const api = vi.fn<(path: string) => Promise<unknown>>(async (path: string) => {
      if (path === '/catalogs/facilities') return { data: [{ id: 'f', name_ar: 'المرفق' }] }
      if (path === '/catalogs/products') return { data: [{ id: 'p', name_ar: 'الديزل' }] }
      if (path === '/catalogs/units') return { data: [] }
      return { data: { expected_days: 3, observed_days: 1, zero_days: 1, missing_days: 1, missing_dates: ['2026-01-02'], ambiguous_days: 1, ambiguous_dates: ['2026-01-03'], status: 'DAILY_MODEL_NOT_READY' } }
    })
    const wrapper = mount(DailyReadiness, { props: { api: api as unknown as Api }, global: { provide: { permissions: computed(() => ['engineering.read']) } } })
    await flushPromises()
    await wrapper.get('select[name="facility_id"]').setValue('f')
    await wrapper.get('select[name="product_id"]').setValue('p')
    await wrapper.get('input[name="from"]').setValue('2026-01-01')
    await wrapper.get('input[name="to"]').setValue('2026-01-03')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(api).toHaveBeenCalledWith('/data/readiness/daily?facility_id=f&product_id=p&from=2026-01-01&to=2026-01-03')
    expect(wrapper.text()).toContain('2026-01-02')
    expect(wrapper.text()).toContain('2026-01-03')
    expect(wrapper.text()).toContain('تواريخ تتطلب مراجعة لتعدد السجلات')
    expect(wrapper.text()).toContain('الأيام ذات القيمة صفر')
    expect(wrapper.text()).toContain('التغطية وحدها لا تثبت جاهزية نموذج الذكاء الاصطناعي')
  })
  it('يحجب الواجهة دون صلاحية هندسية', () => {
    const api = vi.fn<() => Promise<unknown>>(async () => ({ data: [] }))
    const wrapper = mount(DailyReadiness, { props: { api: api as unknown as Api }, global: { provide: { permissions: computed(() => []) } } })
    expect(wrapper.text()).toContain('ليس لديك تصريح')
    expect(wrapper.find('form').exists()).toBe(false)
    expect(api).not.toHaveBeenCalled()
  })
})
