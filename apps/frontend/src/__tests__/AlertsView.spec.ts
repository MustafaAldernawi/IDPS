import {mount,flushPromises} from '@vue/test-utils'
import {computed} from 'vue'
import {describe,it,expect,vi} from 'vitest'
import type {Api} from '../lib/api'
import AlertsView from '../components/AlertsView.vue'
describe('تنبيهات التشغيل',()=>{it('يعرض التوقف ويقرّ به مع سبب',async()=>{const api=vi.fn<(path:string,options?:unknown)=>Promise<unknown>>(async(path)=>path==='/alerts'?{data:[{id:'a',message:'توقف',status:'open',severity:'informational'}]}:{data:{id:'a',status:'acknowledged'}});const w=mount(AlertsView,{props:{api:api as unknown as Api},global:{provide:{permissions:computed(()=>['events.read'])}}});await flushPromises();expect(w.text()).toContain('توقف');await w.get('textarea').setValue('تمت مراجعة هذا التنبيه');await w.get('form').trigger('submit');await flushPromises();expect(api).toHaveBeenCalledWith('/alerts/a/acknowledge',{method:'POST',body:{reason:'تمت مراجعة هذا التنبيه'}})})})
