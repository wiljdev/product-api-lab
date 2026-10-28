<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../services/api'

const state = ref('loading')
const summary = ref({ products: 0, currencies: 0, durationMs: 0 })
const errorMessage = ref('')

async function check() {
  state.value = 'loading'

  const [products, currencies] = await Promise.all([
    api.send('GET', 'products', { query: { per_page: 1 } }),
    api.send('GET', 'currencies'),
  ])

  if (!products.ok || !currencies.ok) {
    state.value = 'error'
    errorMessage.value = (products.ok ? currencies : products).data?.message ?? 'Unknown error.'
    return
  }

  summary.value = {
    products: products.data.meta.total,
    currencies: currencies.data.data.length,
    durationMs: products.durationMs,
  }
  state.value = 'online'
}

onMounted(check)
</script>

<template>
  <section class="card api-status" :data-state="state" aria-live="polite">
    <div class="api-status-header">
      <span class="status-dot" aria-hidden="true" />
      <h2 v-if="state === 'loading'">Conectando con la API…</h2>
      <h2 v-else-if="state === 'online'">API en línea</h2>
      <h2 v-else>API no disponible</h2>
    </div>

    <dl v-if="state === 'online'" class="stats">
      <div>
        <dt>Productos</dt>
        <dd>{{ summary.products }}</dd>
      </div>
      <div>
        <dt>Divisas</dt>
        <dd>{{ summary.currencies }}</dd>
      </div>
      <div>
        <dt>Respuesta</dt>
        <dd>{{ summary.durationMs }} ms</dd>
      </div>
    </dl>

    <div v-else-if="state === 'error'" class="api-status-error">
      <p>{{ errorMessage }}</p>
      <button type="button" class="button" @click="check">Reintentar</button>
    </div>

    <p class="muted endpoint"><code>{{ api.baseUrl }}</code></p>
  </section>
</template>
