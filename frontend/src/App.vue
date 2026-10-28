<script setup>
import { api } from './services/api'
</script>

<template>
  <header class="site-header">
    <div class="container header-inner">
      <RouterLink to="/" class="brand">
        <span class="brand-mark" aria-hidden="true">{ }</span>
        Product API Lab
      </RouterLink>

      <nav class="site-nav" aria-label="Principal">
        <RouterLink to="/">Inicio</RouterLink>
        <a :href="api.docsUrl" target="_blank" rel="noopener">Docs API</a>
      </nav>
    </div>
  </header>

  <main class="container site-main">
    <RouterView />
  </main>

  <footer class="site-footer">
    <div class="container">
      Prueba técnica backend · Laravel 13 + Vue 3
    </div>
  </footer>
</template>
