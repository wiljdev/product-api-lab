/**
 * Endpoints available in the playground, mirroring docs/openapi.yaml.
 *
 * @typedef {object} Endpoint
 * @property {string} id
 * @property {string} group
 * @property {string} method
 * @property {string} path Path relative to /api/v1, with {product} as placeholder.
 * @property {'public'|'token'|'editor'} access
 * @property {string} description
 * @property {Record<string, string>} [query] Query parameters with their default values.
 * @property {any} [body] Example JSON body.
 */

/** @type {Endpoint[]} */
export const endpoints = [
  {
    id: 'login',
    group: 'Auth',
    method: 'POST',
    path: 'auth/login',
    access: 'public',
    description: 'Obtiene un token Bearer. Si responde 200, el playground usa ese token en las siguientes peticiones.',
    body: { email: 'demo@example.com', password: '', device_name: 'playground' },
  },
  {
    id: 'me',
    group: 'Auth',
    method: 'GET',
    path: 'auth/me',
    access: 'token',
    description: 'Devuelve el usuario dueño del token.',
  },
  {
    id: 'logout',
    group: 'Auth',
    method: 'POST',
    path: 'auth/logout',
    access: 'token',
    description: 'Revoca el token actual.',
  },
  {
    id: 'products.index',
    group: 'Productos',
    method: 'GET',
    path: 'products',
    access: 'public',
    description: 'Lista paginada de productos.',
    query: { page: '1', per_page: '5' },
  },
  {
    id: 'products.store',
    group: 'Productos',
    method: 'POST',
    path: 'products',
    access: 'editor',
    description: 'Crea un producto. Requiere un token con la ability products:write.',
    body: {
      name: 'Escritorio elevable',
      description: 'Escritorio con motor eléctrico y altura regulable.',
      price: 450,
      currency_id: 1,
      tax_cost: 85.5,
      manufacturing_cost: 210,
    },
  },
  {
    id: 'products.show',
    group: 'Productos',
    method: 'GET',
    path: 'products/{product}',
    access: 'public',
    description: 'Consulta un producto con su divisa base.',
  },
  {
    id: 'products.replace',
    group: 'Productos',
    method: 'PUT',
    path: 'products/{product}',
    access: 'editor',
    description: 'Reemplaza un producto: todos los campos son obligatorios.',
    body: {
      name: 'Teclado mecánico TKL',
      description: 'Teclado mecánico sin pad numérico con switches táctiles y retroiluminación RGB.',
      price: 89.9,
      currency_id: 1,
      tax_cost: 17.08,
      manufacturing_cost: 32.5,
    },
  },
  {
    id: 'products.update',
    group: 'Productos',
    method: 'PATCH',
    path: 'products/{product}',
    access: 'editor',
    description: 'Actualiza solo los campos enviados.',
    body: { price: 79.9 },
  },
  {
    id: 'products.destroy',
    group: 'Productos',
    method: 'DELETE',
    path: 'products/{product}',
    access: 'editor',
    description: 'Elimina un producto y sus precios.',
  },
  {
    id: 'prices.index',
    group: 'Precios',
    method: 'GET',
    path: 'products/{product}/prices',
    access: 'public',
    description: 'Precios del producto en otras divisas.',
  },
  {
    id: 'prices.store',
    group: 'Precios',
    method: 'POST',
    path: 'products/{product}/prices',
    access: 'editor',
    description: 'Registra un precio en otra divisa. No admite la divisa base ni divisas repetidas.',
    body: { currency_id: 6, price: 99.9 },
  },
  {
    id: 'currencies.index',
    group: 'Divisas',
    method: 'GET',
    path: 'currencies',
    access: 'public',
    description: 'Divisas disponibles y sus ids.',
  },
]

export const accessLabels = {
  public: 'Público',
  token: 'Requiere token',
  editor: 'Requiere rol editor',
}

/**
 * @param {Endpoint} endpoint
 */
export function needsProductId(endpoint) {
  return endpoint.path.includes('{product}')
}

/**
 * @param {Endpoint} endpoint
 */
export function hasBody(endpoint) {
  return endpoint.body !== undefined
}

/**
 * Replace the {product} placeholder with an id.
 *
 * @param {Endpoint} endpoint
 * @param {string|number} productId
 */
export function resolvePath(endpoint, productId) {
  return endpoint.path.replace('{product}', encodeURIComponent(String(productId).trim()))
}

/**
 * Group endpoints for the select box, keeping their order.
 *
 * @returns {{ group: string, endpoints: Endpoint[] }[]}
 */
export function groupedEndpoints() {
  const groups = new Map()

  for (const endpoint of endpoints) {
    if (!groups.has(endpoint.group)) {
      groups.set(endpoint.group, [])
    }
    groups.get(endpoint.group).push(endpoint)
  }

  return [...groups].map(([group, items]) => ({ group, endpoints: items }))
}

/**
 * Build the equivalent cURL command. The token is never included: it is
 * replaced by $TOKEN so the command can be shared safely.
 *
 * @param {object} request
 * @param {string} request.method
 * @param {string} request.url
 * @param {string|null} [request.body] Raw JSON body.
 * @param {boolean} [request.withToken]
 */
export function buildCurl({ method, url, body = null, withToken = false }) {
  const parts = [`curl -X ${method} '${url}'`, `  -H 'Accept: application/json'`]

  if (withToken) {
    parts.push(`  -H "Authorization: Bearer $TOKEN"`)
  }

  if (body) {
    parts.push(`  -H 'Content-Type: application/json'`)
    parts.push(`  -d '${body.replaceAll("'", "'\\''")}'`)
  }

  return parts.join(' \\\n')
}
