import { computed, reactive, readonly } from 'vue'
import { api } from './api'

/**
 * Who is using the playground. Like the token, it only lives in memory.
 */
const state = reactive({
  user: null,
  abilities: null,
  expiresAt: null,
})

/**
 * Store the session returned by POST /auth/login.
 *
 * @param {{ token: string, abilities: string[], expires_at: string, user: object }} data
 */
function adopt(data) {
  api.setToken(data.token)
  state.user = data.user
  state.abilities = data.abilities
  state.expiresAt = data.expires_at
}

function reset() {
  api.clearToken()
  state.user = null
  state.abilities = null
  state.expiresAt = null
}

async function login(email, password) {
  adopt(await api.login(email, password))
}

/**
 * Use a token obtained elsewhere (Postman, curl...). Its abilities are not
 * exposed by the API, so only the user is known.
 */
async function useToken(token) {
  api.setToken(token.trim())

  try {
    const { data } = await api.me()
    state.user = data
    state.abilities = null
    state.expiresAt = null
  } catch (error) {
    reset()
    throw error
  }
}

async function logout() {
  try {
    await api.logout()
  } finally {
    reset()
  }
}

const isAuthenticated = computed(() => state.user !== null)

export function useSession() {
  return {
    session: readonly(state),
    isAuthenticated,
    adopt,
    reset,
    login,
    useToken,
    logout,
  }
}
