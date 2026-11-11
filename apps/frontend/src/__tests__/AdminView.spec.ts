import { mount, flushPromises } from '@vue/test-utils'
import { computed } from 'vue'
import { describe, it, expect, vi } from 'vitest'
import type { Api } from '../lib/api'
import AdminView from '../components/AdminView.vue'

describe('إدارة المستخدمين والصلاحيات والمرجعيات', () => {
  it('يعرض المستخدمين ويتيح تغيير الحالة وإلغاء الدور', async () => {
    const api = vi.fn<(path: string, options?: unknown) => Promise<unknown>>(async (path, options) => {
      if (path.startsWith('/users') && !options) {
        return {
          data: {
            users: [
              { id: 'u-1', name: 'أحمد علي', email: 'ahmed@zawiya.ly', active: true },
              { id: 'u-2', name: 'سالم عمر', email: 'salem@zawiya.ly', active: false },
            ],
            roles: [
              { id: 'r-1', code: 'PROCESS_ENGINEER', name_ar: 'مهندس عمليات' },
            ],
            assignments: [
              {
                id: 'as-1',
                user_id: 'u-1',
                user_name: 'أحمد علي',
                role_id: 'r-1',
                role_name_ar: 'مهندس عمليات',
                facility_id: 'f-1',
                facility_name_ar: 'مصفاة الزاوية',
                unit_id: null,
                unit_name_ar: null,
              },
            ],
          },
        }
      }
      if (path === '/catalogs/facilities') {
        return { data: [{ id: 'f-1', code: 'ZAW', name_ar: 'مصفاة الزاوية' }] }
      }
      if (path === '/catalogs/units') {
        return { data: [{ id: 'un-1', code: 'CDU1', name_ar: 'وحدة التقطير 1', facility_id: 'f-1' }] }
      }
      if (path === '/users/u-1/status') {
        return { data: { id: 'u-1', active: false } }
      }
      if (path === '/users/u-1/roles/as-1') {
        return { data: { revoked: true } }
      }
      return {}
    })

    const wrapper = mount(AdminView, {
      props: { api: api as unknown as Api },
      global: {
        provide: {
          permissions: computed(() => ['users.manage', 'catalogs.manage', 'catalogs.read']),
        },
      },
    })

    await flushPromises()
    expect(wrapper.text()).toContain('أحمد علي')
    expect(wrapper.text()).toContain('ahmed@zawiya.ly')
    expect(wrapper.text()).toContain('مهندس عمليات')
    expect(wrapper.text()).toContain('مصفاة الزاوية')

    // Click toggle status
    const deactivateBtn = wrapper.findAll('button').find(b => b.text() === 'تعطيل الحساب')
    expect(deactivateBtn).toBeDefined()
    await deactivateBtn!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('تعطيل حساب مستخدم: أحمد علي')
    const textarea = wrapper.find('textarea')
    await textarea.setValue('تعطيل مؤقت لأسباب إدارية')

    const confirmDeactivate = wrapper.findAll('button').find(b => b.text() === 'تأكيد التعطيل')
    expect(confirmDeactivate).toBeDefined()
    await confirmDeactivate!.trigger('click')
    await flushPromises()

    expect(api).toHaveBeenCalledWith('/users/u-1/status', {
      method: 'POST',
      body: { active: false, reason: 'تعطيل مؤقت لأسباب إدارية' },
    })

    // Click revoke assignment
    const revokeBtn = wrapper.findAll('button').find(b => b.text() === 'إلغاء الإسناد')
    expect(revokeBtn).toBeDefined()
    await revokeBtn!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('إلغاء إسناد الدور: مهندس عمليات')
    const revokeReason = wrapper.find('textarea')
    await revokeReason.setValue('نقل الموظف إلى قسم آخر بموافقة الإدارة')

    const confirmRevoke = wrapper.findAll('button').find(b => b.text() === 'تأكيد إلغاء الإسناد')
    expect(confirmRevoke).toBeDefined()
    await confirmRevoke!.trigger('click')
    await flushPromises()

    expect(api).toHaveBeenCalledWith('/users/u-1/roles/as-1', {
      method: 'DELETE',
      body: { reason: 'نقل الموظف إلى قسم آخر بموافقة الإدارة' },
    })
  })
})
