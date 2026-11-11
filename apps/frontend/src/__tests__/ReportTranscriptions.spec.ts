import { mount, flushPromises } from '@vue/test-utils'
import { computed } from 'vue'
import { describe, it, expect, vi } from 'vitest'
import type { Api } from '../lib/api'
import ReportTranscriptions from '../components/ReportTranscriptions.vue'

describe('نسخ التقارير الممسوحة', () => {
  it('يعرض قائمة التقارير ويتحقق من النسخ', async () => {
    const api = vi.fn<(path: string, options?: unknown) => Promise<unknown>>(async (path) => {
      if (path === '/catalogs/facilities') {
        return { data: [{ id: 'fac-1', name_ar: 'مصفاة الزاوية' }] }
      }
      if (path.startsWith('/report-transcriptions?')) {
        return {
          data: [
            {
              id: 'tx-1',
              facility_id: 'fac-1',
              original_name: 'report_2025.pdf',
              page: 2,
              locator: 'جدول الإنتاج، سطر 3',
              source_text: 'إنتاج الديزل 5000 طن',
              status: 'pending',
              created_by: 'user-author',
              author_name: 'مهندس الإدخال',
              reviewer_name: null,
              sha256: 'abc123sha',
            },
          ],
          last_page: 1,
        }
      }
      if (path === '/report-transcriptions/tx-1/review') {
        return {
          data: {
            id: 'tx-1',
            status: 'verified',
            review_reason: 'مطابق للأصل',
          },
        }
      }
      return {}
    })

    const wrapper = mount(ReportTranscriptions, {
      props: { api: api as unknown as Api, userId: 'reviewer-user' },
      global: {
        provide: {
          permissions: computed(() => ['imports.read', 'imports.write', 'imports.approve']),
        },
      },
    })

    await flushPromises()
    expect(wrapper.text()).toContain('مصفاة الزاوية')

    const select = wrapper.get('select')
    await select.setValue('fac-1')
    await select.trigger('change')
    await flushPromises()

    expect(wrapper.text()).toContain('report_2025.pdf')
    expect(wrapper.text()).toContain('جدول الإنتاج، سطر 3')

    // Click details button
    const viewButton = wrapper.findAll('button').find(b => b.text() === 'عرض')
    expect(viewButton).toBeDefined()
    await viewButton!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('إنتاج الديزل 5000 طن')

    // Fill review reason and verify
    const textareas = wrapper.findAll('textarea')
    const reasonInput = textareas[textareas.length - 1]
    expect(reasonInput).toBeDefined()
    await reasonInput!.setValue('مطابق للأصل تماماً')
    const verifyButton = wrapper.findAll('button').find(b => b.text().includes('تأكيد مطابقة'))
    expect(verifyButton).toBeDefined()
    await verifyButton!.trigger('click')
    await flushPromises()

    expect(api).toHaveBeenCalledWith('/report-transcriptions/tx-1/review', {
      method: 'POST',
      body: { decision: 'verified', reason: 'مطابق للأصل تماماً' },
    })
  })
})
