<script setup>
import { computed } from 'vue'
import { highlightJson } from '../utils/json'

const props = defineProps({
  value: { type: [Object, Array, String, Number, Boolean], default: null },
})

const html = computed(() => highlightJson(props.value))
</script>

<template>
  <!-- highlightJson escapes the JSON before adding markup, so v-html is safe here. -->
  <pre v-if="html" class="json-viewer"><code v-html="html" /></pre>
  <p v-else class="muted small">Respuesta sin cuerpo.</p>
</template>
