import { computed, inject, type Ref } from 'vue'
export function useAccess() {
  const permissions = inject<Ref<string[]>>('permissions', computed(() => ['*']))
  return (permission: string) => permissions.value.includes('*') || permissions.value.includes(permission)
}
