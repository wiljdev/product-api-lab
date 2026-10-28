<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ApiError, api } from '../services/api'
import { formatMoney } from '../utils/format'

const PER_PAGE = 6

const route = useRoute()
const page = computed(() => Math.max(Number.parseInt(route.query.page, 10) || 1, 1))

const state = ref('loading')
const products = ref([])
const meta = ref(null)
const errorMessage = ref('')

async function load() {
  state.value = 'loading'

  try {
    const response = await api.listProducts({ page: page.value, perPage: PER_PAGE })
    products.value = response.data
    meta.value = response.meta
    state.value = response.data.length ? 'ready' : 'empty'
  } catch (error) {
    errorMessage.value = error instanceof ApiError ? error.message : 'Error inesperado.'
    state.value = 'error'
  }
}

watch(page, load, { immediate: true })
</script>

<template>
  <section>
    <header class="page-header">
      <h1>Catálogo</h1>
      <p class="muted">
        Productos obtenidos en vivo de <code>GET /products</code>, con su precio en la divisa base.
      </p>
    </header>

    <div v-if="state === 'loading'" class="product-grid" aria-busy="true">
      <div v-for="n in PER_PAGE" :key="n" class="card skeleton" />
    </div>

    <div v-else-if="state === 'error'" class="card notice notice-error" role="alert">
      <p>No se pudo cargar el catálogo: {{ errorMessage }}</p>
      <button type="button" class="button" @click="load">Reintentar</button>
    </div>

    <div v-else-if="state === 'empty'" class="card notice">
      <p>No hay productos en esta página.</p>
      <RouterLink :to="{ name: 'products' }" class="button">Ir a la primera página</RouterLink>
    </div>

    <template v-else>
      <ul class="product-grid">
        <li v-for="product in products" :key="product.id">
          <RouterLink :to="{ name: 'product', params: { id: product.id } }" class="card product-card">
            <h2>{{ product.name }}</h2>
            <p class="product-description">{{ product.description }}</p>
            <p class="product-price">{{ formatMoney(product.price, product.currency.name) }}</p>
          </RouterLink>
        </li>
      </ul>

      <nav v-if="meta.last_page > 1" class="pagination" aria-label="Paginación">
        <RouterLink
          v-if="meta.current_page > 1"
          :to="{ name: 'products', query: { page: meta.current_page - 1 } }"
          class="button"
        >
          ← Anterior
        </RouterLink>
        <span class="muted">Página {{ meta.current_page }} de {{ meta.last_page }} · {{ meta.total }} productos</span>
        <RouterLink
          v-if="meta.current_page < meta.last_page"
          :to="{ name: 'products', query: { page: meta.current_page + 1 } }"
          class="button"
        >
          Siguiente →
        </RouterLink>
      </nav>
    </template>
  </section>
</template>
