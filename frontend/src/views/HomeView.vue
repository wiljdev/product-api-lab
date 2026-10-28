<script setup>
import ApiStatus from '../components/ApiStatus.vue'
import { api } from '../services/api'

const repositoryUrl = import.meta.env.VITE_REPOSITORY_URL

const stack = ['Laravel 13', 'PHP 8.3', 'MySQL', 'Sanctum', 'PHPUnit', 'OpenAPI 3.1', 'Vue 3', 'Vite']

const features = [
  {
    title: 'Productos y precios por divisa',
    text: 'CRUD de productos con divisa base, impuestos y costo de fabricación, y precios explícitos en otras divisas.',
  },
  {
    title: 'Seguridad',
    text: 'Lecturas públicas, escrituras con tokens Bearer de Sanctum y roles editor y viewer. Rate limiting en toda la API.',
  },
  {
    title: 'Respuestas consistentes',
    text: 'JSON siempre, validación con Form Requests, API Resources e importes como string para no perder precisión.',
  },
  {
    title: 'Documentada y probada',
    text: 'Especificación OpenAPI con Swagger UI, colección de Postman y una suite de tests que cubre cada endpoint.',
  },
]
</script>

<template>
  <section class="hero">
    <p class="eyebrow">Prueba técnica backend</p>
    <h1>API REST de productos, divisas y precios</h1>
    <p class="lead">
      Una API en Laravel para gestionar productos y registrar sus precios en distintas divisas.
      Esta web la consume en vivo para explorar los datos y probar los endpoints.
    </p>

    <div class="actions">
      <RouterLink :to="{ name: 'products' }" class="button button-primary">Ver catálogo</RouterLink>
      <a :href="api.docsUrl" class="button" target="_blank" rel="noopener">Documentación de la API</a>
      <a v-if="repositoryUrl" :href="repositoryUrl" class="button" target="_blank" rel="noopener">Repositorio</a>
    </div>

    <ul class="chips" aria-label="Tecnologías">
      <li v-for="item in stack" :key="item">{{ item }}</li>
    </ul>
  </section>

  <ApiStatus />

  <section class="features" aria-label="Características">
    <article v-for="feature in features" :key="feature.title" class="card">
      <h2>{{ feature.title }}</h2>
      <p>{{ feature.text }}</p>
    </article>
  </section>
</template>
