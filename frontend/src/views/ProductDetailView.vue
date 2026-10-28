<script setup>
import { computed, ref, watch } from 'vue'
import { ApiError, api } from '../services/api'
import { formatMoney, formatRate } from '../utils/format'

const props = defineProps({
  id: { type: String, required: true },
})

const state = ref('loading')
const product = ref(null)
const prices = ref([])
const errorMessage = ref('')

const priceRows = computed(() => {
  if (!product.value) {
    return []
  }

  const base = {
    id: 'base',
    currency: product.value.currency,
    price: product.value.price,
    isBase: true,
  }

  return [base, ...prices.value]
})

async function load() {
  state.value = 'loading'

  try {
    const [productResponse, pricesResponse] = await Promise.all([
      api.getProduct(props.id),
      api.listProductPrices(props.id),
    ])
    product.value = productResponse.data
    prices.value = pricesResponse.data
    state.value = 'ready'
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      state.value = 'not-found'
      return
    }

    errorMessage.value = error instanceof ApiError ? error.message : 'Error inesperado.'
    state.value = 'error'
  }
}

watch(() => props.id, load, { immediate: true })
</script>

<template>
  <section>
    <RouterLink :to="{ name: 'products' }" class="back-link">← Volver al catálogo</RouterLink>

    <div v-if="state === 'loading'" class="detail-layout" aria-busy="true">
      <div class="card skeleton skeleton-tall" />
      <div class="card skeleton skeleton-tall" />
    </div>

    <div v-else-if="state === 'not-found'" class="card notice">
      <h1>Producto no encontrado</h1>
      <p class="muted">No existe un producto con id {{ id }}. La API respondió <code>404</code>.</p>
    </div>

    <div v-else-if="state === 'error'" class="card notice notice-error" role="alert">
      <p>No se pudo cargar el producto: {{ errorMessage }}</p>
      <button type="button" class="button" @click="load">Reintentar</button>
    </div>

    <template v-else>
      <header class="page-header">
        <h1>{{ product.name }}</h1>
        <p class="lead">{{ product.description }}</p>
      </header>

      <div class="detail-layout">
        <section class="card">
          <h2>Costos en divisa base ({{ product.currency.name }})</h2>
          <dl class="cost-list">
            <div>
              <dt>Precio</dt>
              <dd>{{ formatMoney(product.price, product.currency.name) }}</dd>
            </div>
            <div>
              <dt>Costo de impuestos</dt>
              <dd>{{ formatMoney(product.tax_cost, product.currency.name) }}</dd>
            </div>
            <div>
              <dt>Costo de fabricación</dt>
              <dd>{{ formatMoney(product.manufacturing_cost, product.currency.name) }}</dd>
            </div>
          </dl>
          <p class="muted small">
            Datos de <code>GET /products/{{ product.id }}</code>
          </p>
        </section>

        <section class="card">
          <h2>Precios por divisa</h2>
          <div class="table-wrapper">
            <table class="price-table">
              <thead>
                <tr>
                  <th scope="col">Divisa</th>
                  <th scope="col" class="numeric">Precio</th>
                  <th scope="col" class="numeric">Tasa (1 USD)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in priceRows" :key="row.id">
                  <td>
                    {{ row.currency.name }} <span class="muted">{{ row.currency.symbol }}</span>
                    <span v-if="row.isBase" class="badge">Base</span>
                  </td>
                  <td class="numeric">{{ formatMoney(row.price, row.currency.name) }}</td>
                  <td class="numeric muted">{{ formatRate(row.currency.exchange_rate) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-if="!prices.length" class="muted small">Este producto aún no tiene precios en otras divisas.</p>
          <p class="muted small">
            Datos de <code>GET /products/{{ product.id }}/prices</code>
          </p>
        </section>
      </div>
    </template>
  </section>
</template>
