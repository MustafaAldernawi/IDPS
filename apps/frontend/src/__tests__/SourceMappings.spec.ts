import {mount,flushPromises} from '@vue/test-utils'
import {computed} from 'vue'
import {describe,it,expect,vi} from 'vitest'
import type {Api} from '../lib/api'
import SourceMappings from '../components/SourceMappings.vue'
describe('مراجعة ربط المصادر',()=>{it('يطلب سياق العمود ويصدره للرفع دون تخمين',async()=>{
 const api=vi.fn<(path:string)=>Promise<unknown>>(async()=>({data:[]}))
 const w=mount(SourceMappings,{props:{api:api as unknown as Api,facility:'f'},global:{provide:{permissions:computed(()=>['imports.write'])}}});await flushPromises()
 await w.get('input[name="alias"]').setValue('زيت ديزل');await w.get('input[name="source_scope"]').setValue('actual');await w.get('select[name="product_stage"]').setValue('intermediate_stream');await w.get('select[name="unit_code"]').setValue('t');await w.get('form[data-test="add-context"]').trigger('submit')
 expect(w.emitted('contexts')?.[0]?.[0]).toEqual([{alias:'زيت ديزل',source_scope:'actual',unit_code:'t',granularity:'monthly',product_stage:'intermediate_stream',locator:{row:1,column:'زيت ديزل'}}])
})})
