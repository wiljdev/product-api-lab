import { describe, expect, it, vi } from 'vitest'
import { ApiError, createApiClient } from './api'

function jsonResponse(status, body) {
  return new Response(body === null ? null : JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

function makeClient(...responses) {
  const fetch = vi.fn()
  for (const response of responses) {
    fetch.mockResolvedValueOnce(response)
  }

  return { fetch, client: createApiClient({ baseUrl: 'http://api.test/api/v1/', fetch }) }
}

describe('createApiClient', () => {
  it('builds URLs from the base URL and skips empty query values', async () => {
    const { fetch, client } = makeClient(jsonResponse(200, { data: [] }))

    await client.listProducts({ page: 2, perPage: 5 })
    expect(fetch.mock.calls[0][0]).toBe('http://api.test/api/v1/products?page=2&per_page=5')

    fetch.mockResolvedValueOnce(jsonResponse(200, {}))
    await client.send('GET', '/products', { query: { search: '', page: null } })
    expect(fetch.mock.calls[1][0]).toBe('http://api.test/api/v1/products')
  })

  it('exposes the Swagger docs URL of the API host', () => {
    const { client } = makeClient()

    expect(client.docsUrl).toBe('http://api.test/docs')
  })

  it('sends JSON bodies and always asks for JSON', async () => {
    const { fetch, client } = makeClient(jsonResponse(201, { data: { id: 1 } }))

    await client.send('POST', 'products', { body: { name: 'Desk' } })

    const [, init] = fetch.mock.calls[0]
    expect(init.method).toBe('POST')
    expect(init.headers.Accept).toBe('application/json')
    expect(init.headers['Content-Type']).toBe('application/json')
    expect(init.body).toBe('{"name":"Desk"}')
  })

  it('stores the token in memory after login and sends it afterwards', async () => {
    const { fetch, client } = makeClient(
      jsonResponse(200, { data: { token: '1|secret', abilities: ['products:write'] } }),
      jsonResponse(200, { data: { id: 1 } }),
    )

    const session = await client.login('demo@example.com', 'password')
    await client.me()

    expect(session.abilities).toEqual(['products:write'])
    expect(client.getToken()).toBe('1|secret')
    expect(fetch.mock.calls[0][1].headers.Authorization).toBeUndefined()
    expect(fetch.mock.calls[1][1].headers.Authorization).toBe('Bearer 1|secret')
  })

  it('clears the token on logout even if the request fails', async () => {
    const { client } = makeClient(jsonResponse(401, { message: 'Unauthenticated.' }))
    client.setToken('1|expired')

    await expect(client.logout()).rejects.toBeInstanceOf(ApiError)
    expect(client.getToken()).toBeNull()
  })

  it('lets a request override or omit the stored token', async () => {
    const { fetch, client } = makeClient(jsonResponse(200, {}), jsonResponse(200, {}))
    client.setToken('1|stored')

    await client.send('GET', 'auth/me', { token: '2|other' })
    await client.send('GET', 'products', { token: null })

    expect(fetch.mock.calls[0][1].headers.Authorization).toBe('Bearer 2|other')
    expect(fetch.mock.calls[1][1].headers.Authorization).toBeUndefined()
  })

  it('resolves send() with status, data and duration even for errors', async () => {
    const { client } = makeClient(jsonResponse(404, { message: 'Product not found.' }))

    const response = await client.send('GET', 'products/999')

    expect(response).toMatchObject({ ok: false, status: 404, data: { message: 'Product not found.' } })
    expect(response.durationMs).toBeGreaterThanOrEqual(0)
  })

  it('handles empty 204 responses', async () => {
    const { client } = makeClient(new Response(null, { status: 204 }))

    const response = await client.send('DELETE', 'products/1')

    expect(response).toMatchObject({ ok: true, status: 204, data: null })
  })

  it('fills in the status text that HTTP/2 responses omit', async () => {
    const { client } = makeClient(
      new Response('{}', { status: 401, statusText: '' }),
      new Response('{}', { status: 418, statusText: '' }),
      new Response('{}', { status: 403, statusText: 'Custom' }),
    )

    expect((await client.send('GET', 'auth/me')).statusText).toBe('Unauthorized')
    expect((await client.send('GET', 'auth/me')).statusText).toBe('')
    expect((await client.send('GET', 'auth/me')).statusText).toBe('Custom')
  })

  it('throws ApiError with validation errors from request helpers', async () => {
    const { client } = makeClient(
      jsonResponse(422, { message: 'The given data was invalid.', errors: { email: ['Invalid credentials.'] } }),
    )

    const error = await client.login('demo@example.com', 'wrong').catch((caught) => caught)

    expect(error).toBeInstanceOf(ApiError)
    expect(error.status).toBe(422)
    expect(error.message).toBe('The given data was invalid.')
    expect(error.errors.email).toEqual(['Invalid credentials.'])
    expect(error.response.status).toBe(422)
  })

  it('reports network failures as status 0 instead of throwing', async () => {
    const fetch = vi.fn().mockRejectedValue(new TypeError('Failed to fetch'))
    const client = createApiClient({ baseUrl: 'http://api.test/api/v1', fetch })

    const response = await client.send('GET', 'products')

    expect(response).toMatchObject({ ok: false, status: 0, statusText: 'Network error' })
    expect(response.data.message).toContain('http://api.test/api/v1')
  })
})
