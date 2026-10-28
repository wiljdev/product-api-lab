import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('./api', () => ({
  api: {
    login: vi.fn(),
    logout: vi.fn(),
    me: vi.fn(),
    setToken: vi.fn(),
    clearToken: vi.fn(),
  },
}))

const { api } = await import('./api')
const { useSession } = await import('./session')

const loginData = {
  token: '1|secret',
  abilities: ['products:write'],
  expires_at: '2026-10-29T12:00:00.000000Z',
  user: { id: 1, name: 'Demo User', email: 'demo@example.com', role: 'editor' },
}

describe('useSession', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    useSession().reset()
  })

  it('stores the user and token after login', async () => {
    api.login.mockResolvedValue(loginData)
    const { session, isAuthenticated, login } = useSession()

    await login('demo@example.com', 'password')

    expect(api.setToken).toHaveBeenCalledWith('1|secret')
    expect(isAuthenticated.value).toBe(true)
    expect(session.user.role).toBe('editor')
    expect(session.abilities).toEqual(['products:write'])
  })

  it('accepts an existing token after checking it with /auth/me', async () => {
    api.me.mockResolvedValue({ data: loginData.user })
    const { session, useToken } = useSession()

    await useToken('  2|pasted  ')

    expect(api.setToken).toHaveBeenCalledWith('2|pasted')
    expect(session.user.email).toBe('demo@example.com')
    expect(session.abilities).toBeNull()
  })

  it('forgets an invalid pasted token', async () => {
    api.me.mockRejectedValue(new Error('Unauthenticated.'))
    const { isAuthenticated, useToken } = useSession()

    await expect(useToken('bad')).rejects.toThrow('Unauthenticated.')

    expect(api.clearToken).toHaveBeenCalled()
    expect(isAuthenticated.value).toBe(false)
  })

  it('clears the session even when logout fails', async () => {
    api.login.mockResolvedValue(loginData)
    api.logout.mockRejectedValue(new Error('Network error'))
    const { isAuthenticated, login, logout } = useSession()
    await login('demo@example.com', 'password')

    await expect(logout()).rejects.toThrow('Network error')

    expect(isAuthenticated.value).toBe(false)
    expect(api.clearToken).toHaveBeenCalled()
  })

  it('exposes the session as read only', () => {
    const { session } = useSession()

    session.user = { name: 'Intruder' }

    expect(session.user).toBeNull()
  })
})
