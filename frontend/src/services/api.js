/**
 * Single entry point for every call to the Laravel API.
 *
 * The Bearer token lives only in memory: it is never written to localStorage,
 * so it disappears when the tab is closed.
 */

/**
 * HTTP/2 responses carry no reason phrase, so fetch() leaves statusText empty.
 */
const STATUS_TEXTS = {
  200: 'OK',
  201: 'Created',
  204: 'No Content',
  401: 'Unauthorized',
  403: 'Forbidden',
  404: 'Not Found',
  405: 'Method Not Allowed',
  422: 'Unprocessable Content',
  429: 'Too Many Requests',
  500: 'Internal Server Error',
}

export class ApiError extends Error {
  /**
   * @param {ApiResponse} response
   */
  constructor(response) {
    super(response.data?.message ?? `Request failed with status ${response.status}`)
    this.name = 'ApiError'
    this.status = response.status
    this.errors = response.data?.errors ?? {}
    this.response = response
  }
}

/**
 * @typedef {object} ApiResponse
 * @property {boolean} ok
 * @property {number} status HTTP status, or 0 when the API could not be reached.
 * @property {string} statusText
 * @property {any} data Parsed JSON body, or null for empty responses.
 * @property {number} durationMs
 * @property {string} url
 */

/**
 * @param {object} options
 * @param {string} options.baseUrl Base URL including the version prefix, e.g. http://localhost:8000/api/v1
 * @param {typeof fetch} [options.fetch]
 * @param {() => number} [options.now]
 */
export function createApiClient({ baseUrl, fetch = globalThis.fetch.bind(globalThis), now = () => performance.now() }) {
  const root = baseUrl.replace(/\/+$/, '')
  let token = null

  function buildUrl(path, query = {}) {
    const url = new URL(`${root}/${path.replace(/^\/+/, '')}`)

    for (const [key, value] of Object.entries(query)) {
      if (value !== undefined && value !== null && value !== '') {
        url.searchParams.set(key, value)
      }
    }

    return url.toString()
  }

  /**
   * Send a request and always resolve with the response, whatever its status.
   *
   * @param {string} method
   * @param {string} path Path relative to the base URL, e.g. "products/1".
   * @param {object} [options]
   * @param {Record<string, any>} [options.query]
   * @param {any} [options.body]
   * @param {string|null} [options.token] Overrides the stored token; null sends no token.
   * @returns {Promise<ApiResponse>}
   */
  async function send(method, path, { query, body, token: tokenOverride } = {}) {
    const url = buildUrl(path, query)
    const headers = { Accept: 'application/json' }
    const bearer = tokenOverride === undefined ? token : tokenOverride

    if (bearer) {
      headers.Authorization = `Bearer ${bearer}`
    }

    if (body !== undefined) {
      headers['Content-Type'] = 'application/json'
    }

    const startedAt = now()

    try {
      const response = await fetch(url, {
        method,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
      })
      const text = await response.text()

      return {
        ok: response.ok,
        status: response.status,
        statusText: response.statusText || STATUS_TEXTS[response.status] || '',
        data: text ? parseJson(text) : null,
        durationMs: Math.round(now() - startedAt),
        url,
      }
    } catch {
      return {
        ok: false,
        status: 0,
        statusText: 'Network error',
        data: { message: `No se pudo conectar con la API en ${root}.` },
        durationMs: Math.round(now() - startedAt),
        url,
      }
    }
  }

  /**
   * Send a request and return its body, throwing ApiError when it fails.
   */
  async function request(method, path, options) {
    const response = await send(method, path, options)

    if (!response.ok) {
      throw new ApiError(response)
    }

    return response.data
  }

  return {
    baseUrl: root,
    docsUrl: `${new URL(root).origin}/docs`,

    send,
    request,

    getToken: () => token,
    setToken: (value) => {
      token = value || null
    },
    clearToken: () => {
      token = null
    },

    async login(email, password) {
      const { data } = await request('POST', 'auth/login', {
        body: { email, password, device_name: 'frontend' },
        token: null,
      })
      token = data.token

      return data
    },
    async logout() {
      try {
        await request('POST', 'auth/logout')
      } finally {
        token = null
      }
    },
    me: () => request('GET', 'auth/me'),

    listProducts: ({ page = 1, perPage = 15 } = {}) =>
      request('GET', 'products', { query: { page, per_page: perPage } }),
    getProduct: (id) => request('GET', `products/${id}`),
    listProductPrices: (id) => request('GET', `products/${id}/prices`),
    listCurrencies: () => request('GET', 'currencies'),
  }
}

function parseJson(text) {
  try {
    return JSON.parse(text)
  } catch {
    return text
  }
}

export const api = createApiClient({ baseUrl: import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api/v1' })
