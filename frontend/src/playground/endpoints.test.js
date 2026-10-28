import { describe, expect, it } from 'vitest'
import { buildCurl, endpoints, groupedEndpoints, hasBody, needsProductId, resolvePath } from './endpoints'

const byId = (id) => endpoints.find((endpoint) => endpoint.id === id)

describe('endpoints', () => {
  it('covers every API operation once', () => {
    const operations = endpoints.map((endpoint) => `${endpoint.method} /${endpoint.path}`)

    expect(new Set(operations).size).toBe(operations.length)
    expect(operations).toEqual(
      expect.arrayContaining([
        'POST /auth/login',
        'POST /auth/logout',
        'GET /auth/me',
        'GET /products',
        'POST /products',
        'GET /products/{product}',
        'PUT /products/{product}',
        'PATCH /products/{product}',
        'DELETE /products/{product}',
        'GET /products/{product}/prices',
        'POST /products/{product}/prices',
        'GET /currencies',
      ]),
    )
  })

  it('never ships a demo password', () => {
    expect(byId('login').body.password).toBe('')
  })

  it('groups endpoints keeping their order', () => {
    expect(groupedEndpoints().map((group) => group.group)).toEqual(['Auth', 'Productos', 'Precios', 'Divisas'])
  })

  it('resolves the product placeholder', () => {
    const endpoint = byId('prices.store')

    expect(needsProductId(endpoint)).toBe(true)
    expect(hasBody(endpoint)).toBe(true)
    expect(resolvePath(endpoint, ' 7 ')).toBe('products/7/prices')
    expect(resolvePath(endpoint, '1/../2')).toBe('products/1%2F..%2F2/prices')
  })

  it('knows which endpoints have no body or id', () => {
    const endpoint = byId('currencies.index')

    expect(needsProductId(endpoint)).toBe(false)
    expect(hasBody(endpoint)).toBe(false)
  })
})

describe('buildCurl', () => {
  it('builds a GET command without token', () => {
    expect(buildCurl({ method: 'GET', url: 'http://api.test/api/v1/currencies' })).toBe(
      "curl -X GET 'http://api.test/api/v1/currencies' \\\n  -H 'Accept: application/json'",
    )
  })

  it('uses a $TOKEN placeholder instead of the real token', () => {
    const command = buildCurl({ method: 'DELETE', url: 'http://api.test/api/v1/products/1', withToken: true })

    expect(command).toContain('-H "Authorization: Bearer $TOKEN"')
  })

  it('escapes single quotes in the body', () => {
    const command = buildCurl({
      method: 'POST',
      url: 'http://api.test/api/v1/products',
      body: '{"name":"Kid\'s desk"}',
    })

    expect(command).toContain("-H 'Content-Type: application/json'")
    expect(command).toContain(`-d '{"name":"Kid'\\''s desk"}'`)
  })
})
