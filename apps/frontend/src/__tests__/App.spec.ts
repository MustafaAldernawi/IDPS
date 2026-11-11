import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import App from '../App.vue'

afterEach(() => { vi.unstubAllGlobals() })
const response = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })

describe('IDPS console', () => {
  it('requires sign in before showing operational data', () => {
    const wrapper = mount(App)
    expect(wrapper.find('input[type=email]').exists()).toBe(true)
    expect(wrapper.text()).toContain('تسجيل الدخول')
    expect(wrapper.text()).not.toContain('You did it!')
  })

  it('shows rejected credentials without exposing a protected console', async () => {
    vi.stubGlobal('fetch', vi.fn<(url: string, options?: RequestInit) => Promise<Response>>().mockResolvedValue(response({ message: 'بيانات الدخول غير صحيحة' }, 422)))
    const wrapper = mount(App)
    await wrapper.get('input[type=email]').setValue('engineer@example.test')
    await wrapper.get('input[type=password]').setValue('wrong-password')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('[role=alert]').text()).toContain('بيانات الدخول غير صحيحة')
    expect(wrapper.find('nav').exists()).toBe(false)
  })

  it('keeps the token out of persistent storage and displays empty approved-data state', async () => {
    const blockedStorage = { setItem: () => { throw new Error('Authentication must not persist tokens') } }
    vi.stubGlobal('localStorage', blockedStorage)
    vi.stubGlobal('sessionStorage', blockedStorage)
    const fetchMock = vi.fn<(url: string, options?: RequestInit) => Promise<Response>>().mockImplementation((url: string) => Promise.resolve(url.endsWith('/auth/login')
      ? response({ token: 'ephemeral-test-token', user: { id: 'u1', name: 'مهندس العمليات', roles: ['PROCESS_ENGINEER'], permissions: ['*'] } })
      : response({ data: {} })))
    vi.stubGlobal('fetch', fetchMock)
    const wrapper = mount(App)
    await wrapper.get('input[type=email]').setValue('engineer@example.test')
    await wrapper.get('input[type=password]').setValue('a-long-password')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('nav').exists()).toBe(true)
    expect(wrapper.text()).toContain('لا توجد مؤشرات متاحة')
    const dashboardRequest = fetchMock.mock.calls.find(([url]) => String(url).includes('/dashboard'))
    expect(new Headers(dashboardRequest?.[1]?.headers).get('Authorization')).toBe('Bearer ephemeral-test-token')
  })

  it('shows a permission error and never replaces missing results with zero', async () => {
    vi.stubGlobal('fetch', vi.fn<(url: string, options?: RequestInit) => Promise<Response>>().mockImplementation((url: string) => Promise.resolve(url.endsWith('/auth/login')
      ? response({ token: 't', user: { id: 'u1', name: 'قارئ', roles: [], permissions: [] } })
      : response({ message: 'غير مصرح بالوصول' }, 403))))
    const wrapper = mount(App)
    await wrapper.get('input[type=email]').setValue('reader@example.test')
    await wrapper.get('input[type=password]').setValue('a-long-password')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('[role=alert]').text()).toContain('غير مصرح بالوصول')
    expect(wrapper.text()).not.toContain('100%')
  })
})

