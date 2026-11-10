<script setup lang="ts">
import { display } from '../lib/presentation'
defineProps<{ value: unknown }>()
</script>
<template><span class="badge" :data-status="String(value)">{{ display(value) }}</span></template>
