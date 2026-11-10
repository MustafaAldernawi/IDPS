import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import RecordEditor from '../components/RecordEditor.vue'
import type { Api } from '../lib/api'

describe('measurement editor', () => {
  it('preserves DECIMAL input as an exact string instead of a floating-point number', async () => {
    const wrapper = mount(RecordEditor, { props: { api: (async () => ({})) as Api, busy: false, errors: {}, fields: [{ key: 'quantity', label: 'الكمية', type: 'number' }] } })
    await wrapper.get('input').setValue('123456789012.123456')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('save')?.[0]?.[0]).toEqual({ quantity: '123456789012.123456' })
  })

  it('keeps a missing measurement null while preserving an explicit zero', async () => {
    const wrapper = mount(RecordEditor, { props: { api: (async () => ({})) as Api, busy: false, errors: {}, fields: [{ key: 'quantity', label: 'الكمية', type: 'number' }] } })
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('save')?.[0]?.[0]).toEqual({ quantity: null })
    await wrapper.get('input').setValue('0')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('save')?.[1]?.[0]).toEqual({ quantity: '0' })
  })
})
