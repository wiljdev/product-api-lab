<script setup>
import { ref } from 'vue'
import { ApiError } from '../services/api'
import { useSession } from '../services/session'

const { session, isAuthenticated, login, useToken, logout } = useSession()

const demoUsers = [
  { email: 'demo@example.com', label: 'Editor: puede escribir' },
  { email: 'viewer@example.com', label: 'Viewer: solo lectura (403 al escribir)' },
]

const email = ref(demoUsers[0].email)
const password = ref('')
const token = ref('')
const busy = ref(false)
const errorMessage = ref('')

async function run(action) {
  busy.value = true
  errorMessage.value = ''

  try {
    await action()
    password.value = ''
    token.value = ''
  } catch (error) {
    errorMessage.value = error instanceof ApiError ? error.message : 'Error inesperado.'
  } finally {
    busy.value = false
  }
}

function formatDate(value) {
  return new Intl.DateTimeFormat('es', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}
</script>

<template>
  <section class="card session-panel" aria-labelledby="session-title">
    <h2 id="session-title">Sesión</h2>

    <template v-if="isAuthenticated">
      <p class="session-user">
        <strong>{{ session.user.name }}</strong>
        <span class="badge" :data-role="session.user.role">{{ session.user.role }}</span>
        <br />
        <span class="muted small">{{ session.user.email }}</span>
      </p>

      <dl class="session-details small">
        <div v-if="session.abilities">
          <dt>Abilities</dt>
          <dd>{{ session.abilities.length ? session.abilities.join(', ') : 'ninguna' }}</dd>
        </div>
        <div v-if="session.expiresAt">
          <dt>Caduca</dt>
          <dd>{{ formatDate(session.expiresAt) }}</dd>
        </div>
      </dl>

      <button type="button" class="button" :disabled="busy" @click="run(logout)">Cerrar sesión</button>
    </template>

    <template v-else>
      <form class="stack" @submit.prevent="run(() => login(email, password))">
        <label class="field">
          <span>Email</span>
          <input v-model="email" type="email" list="demo-users" autocomplete="username" required />
          <datalist id="demo-users">
            <option v-for="user in demoUsers" :key="user.email" :value="user.email">{{ user.label }}</option>
          </datalist>
        </label>
        <label class="field">
          <span>Contraseña</span>
          <input v-model="password" type="password" autocomplete="current-password" required />
        </label>
        <button type="submit" class="button button-primary" :disabled="busy">Iniciar sesión</button>
      </form>

      <details class="token-form">
        <summary>Usar un token existente</summary>
        <form class="stack" @submit.prevent="run(() => useToken(token))">
          <label class="field">
            <span>Token Bearer</span>
            <input v-model="token" type="password" autocomplete="off" placeholder="1|..." required />
          </label>
          <button type="submit" class="button" :disabled="busy">Usar token</button>
        </form>
      </details>

      <p class="muted small">
        Usuarios demo: <code>demo@example.com</code> (editor) y <code>viewer@example.com</code> (viewer).
      </p>
    </template>

    <p v-if="errorMessage" class="form-error small" role="alert">{{ errorMessage }}</p>
    <p class="muted small">El token se guarda solo en memoria: se pierde al recargar o cerrar la pestaña.</p>
  </section>
</template>
