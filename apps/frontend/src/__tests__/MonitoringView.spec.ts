import { mount,flushPromises } from '@vue/test-utils'
import { computed } from 'vue'
import { describe,it,expect,vi } from 'vitest'
import type { Api } from '../lib/api'
import MonitoringView from '../components/MonitoringView.vue'
describe('مراقبة النماذج',()=>{it('يعرض نقص العينات دون ادعاء الانحراف',async()=>{
 const api=vi.fn<(path:string)=>Promise<unknown>>(async(path:string)=>path==='/catalogs/facilities'?{data:[{id:'f',name_ar:'مرفق'}]}:path==='/catalogs/products'?{data:[{code:'DIESEL',name_ar:'ديزل'}]}:{data:{status:'insufficient_samples',sample_count:1,minimum_samples:12,drift:null}})
 const wrapper=mount(MonitoringView,{props:{api:api as unknown as Api},global:{provide:{permissions:computed(()=>['ai.read'])}}});await flushPromises()
 await wrapper.get('select[name="facility_id"]').setValue('f');await wrapper.get('select[name="product_code"]').setValue('DIESEL');await wrapper.get('form').trigger('submit');await flushPromises()
 expect(wrapper.text()).toContain('عينات غير كافية');expect(wrapper.text()).toContain('لا يوجد حكم معتمد على الانحراف')
})})
