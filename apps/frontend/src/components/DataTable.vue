<script setup lang="ts">
import type { Row } from '../lib/api'
import { display, labels } from '../lib/presentation'
import StatusBadge from './StatusBadge.vue'
defineProps<{ rows: Row[]; columns: string[]; selectable?: boolean; checkable?: boolean; selectedIds?: string[] }>()
defineEmits<{ select: [row: Row]; check: [id: string] }>()
</script>
<template>
  <div class="table-scroll">
    <table><thead><tr><th v-if="checkable" scope="col">تحديد</th><th v-for="column in columns" :key="column" scope="col">{{ labels[column] ?? column }}</th><th v-if="selectable" scope="col"><span class="sr-only">التفاصيل</span></th></tr></thead>
      <tbody><tr v-for="(row, index) in rows" :key="String(row.id ?? index)">
        <td v-if="checkable"><input type="checkbox" :aria-label="`تحديد السجل ${row.period_month ?? row.id} ${row.item_code ?? ''}`" :checked="selectedIds?.includes(String(row.id))" @change="$emit('check', String(row.id))" /></td><td v-for="column in columns" :key="column"><StatusBadge v-if="column.includes('status')" :value="row[column]" /><span v-else :class="{ 'mono': column.includes('code') || column === 'quantity' || column === 'period_month' }">{{ display(row[column]) }}</span></td>
        <td v-if="selectable"><button class="text-button" @click="$emit('select', row)">التفاصيل <span aria-hidden="true">←</span></button></td>
      </tr></tbody>
    </table>
  </div>
</template>
