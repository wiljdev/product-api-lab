<script setup>
import { computed, ref, watch } from 'vue'
import JsonViewer from '../components/JsonViewer.vue'
import SessionPanel from '../components/SessionPanel.vue'
import {
  accessLabels,
  buildCurl,
  endpoints,
  groupedEndpoints,
  hasBody,
  needsProductId,
  resolvePath,
} from '../playground/endpoints'
import { api } from '../services/api'
import { useSession } from '../services/session'

const { isAuthenticated, adopt, reset } = useSession()

const groups = groupedEndpoints()
const selectedId = ref('products.index')
const productId = ref('1')
const query = ref({})
const bodyText = ref('')
const sendToken = ref(true)
const bodyError = ref('')
const loading = ref(false)
const response = ref(null)
const copied = ref(false)

const endpoint = computed(() => endpoints.find((item) => item.id === selectedId.value))
const path = computed(() => resolvePath(endpoint.value, productId.value))

const url = computed(() => {
  const params = new URLSearchParams(Object.entries(query.value).filter(([, value]) => value !== ''))
  const search = params.toString()

  return `${api.baseUrl}/${path.value}${search ? `?${search}` : ''}`
})

const curl = computed(() =>
  buildCurl({
    method: endpoint.value.method,
    url: url.value,
    body: hasBody(endpoint.value) ? bodyText.value : null,
    withToken: sendToken.value && isAuthenticated.value,
  }),
)

const statusClass = computed(() => {
  const status = response.value?.status ?? 0
  if (status >= 200 && status < 300) return 'status-success'
  if (status >= 400 && status < 500) return 'status-warning'
  return 'status-danger'
})

watch(
  endpoint,
  (current) => {
    query.value = { ...(current.query ?? {}) }
    bodyText.value = hasBody(current) ? JSON.stringify(current.body, null, 2) : ''
    bodyError.value = ''
    response.value = null
  },
  { immediate: true },
)

async function execute() {
  bodyError.value = ''
  let body

  if (hasBody(endpoint.value)) {
    try {
      body = JSON.parse(bodyText.value || '{}')
    } catch {
      bodyError.value = 'El cuerpo no es un JSON válido.'
      return
    }
  }

  loading.value = true
  copied.value = false

  const result = await api.send(endpoint.value.method, path.value, {
    query: query.value,
    body,
    token: sendToken.value ? undefined : null,
  })

  if (result.ok && endpoint.value.id === 'login') {
    adopt(result.data.data)
  }
  if (result.ok && endpoint.value.id === 'logout') {
    reset()
  }

  response.value = result
  loading.value = false
}

async function copyCurl() {
  await navigator.clipboard.writeText(curl.value)
  copied.value = true
}
</script>

<template>
  <section>
    <header class="page-header">
      <h1>API Playground</h1>
      <p class="muted">
        Elige un endpoint, ajusta los parámetros y ejecuta la petición contra la API real.
        Para las escrituras inicia sesión con el usuario editor; con el viewer verás el <code>403</code>.
      </p>
    </header>

    <div class="playground-layout">
      <SessionPanel />

      <div class="stack-lg">
        <form class="card stack" @submit.prevent="execute">
          <label class="field">
            <span>Endpoint</span>
            <select v-model="selectedId">
              <optgroup v-for="group in groups" :key="group.group" :label="group.group">
                <option v-for="item in group.endpoints" :key="item.id" :value="item.id">
                  {{ item.method }} /{{ item.path }}
                </option>
              </optgroup>
            </select>
          </label>

          <p class="endpoint-meta small">
            <span class="method" :data-method="endpoint.method">{{ endpoint.method }}</span>
            <span class="badge">{{ accessLabels[endpoint.access] }}</span>
            <span class="muted">{{ endpoint.description }}</span>
          </p>

          <div v-if="needsProductId(endpoint) || Object.keys(query).length" class="field-row">
            <label v-if="needsProductId(endpoint)" class="field">
              <span>Id del producto</span>
              <input v-model="productId" type="text" inputmode="numeric" />
            </label>
            <label v-for="(value, key) in query" :key="key" class="field">
              <span>{{ key }}</span>
              <input v-model="query[key]" type="text" inputmode="numeric" />
            </label>
          </div>

          <label v-if="hasBody(endpoint)" class="field">
            <span>Cuerpo JSON</span>
            <textarea v-model="bodyText" rows="8" spellcheck="false" class="code-input" />
            <span v-if="bodyError" class="form-error small" role="alert">{{ bodyError }}</span>
          </label>

          <label class="checkbox">
            <input v-model="sendToken" type="checkbox" />
            <span>
              Enviar token
              <span v-if="!isAuthenticated" class="muted small">(no hay sesión iniciada)</span>
            </span>
          </label>

          <p class="request-url small"><code>{{ endpoint.method }} {{ url }}</code></p>

          <button type="submit" class="button button-primary" :disabled="loading">
            {{ loading ? 'Enviando…' : 'Ejecutar' }}
          </button>
        </form>

        <section v-if="response" class="card stack" aria-live="polite">
          <div class="response-header">
            <span class="status-pill" :class="statusClass">{{ response.status || 'Error' }} {{ response.statusText }}</span>
            <span class="muted small">{{ response.durationMs }} ms</span>
          </div>
          <JsonViewer :value="response.data" />
        </section>

        <details class="card curl-panel">
          <summary>Equivalente en cURL</summary>
          <pre class="json-viewer"><code>{{ curl }}</code></pre>
          <button type="button" class="button" @click="copyCurl">{{ copied ? 'Copiado' : 'Copiar' }}</button>
        </details>
      </div>
    </div>
  </section>
</template>
