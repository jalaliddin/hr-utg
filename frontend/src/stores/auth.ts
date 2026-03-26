import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/api/axios'

interface User {
  id: number
  name: string
  email: string
  role: string
  organization_id: number | null
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))

  const isAuthenticated = computed(() => !!token.value)
  const isSuperAdmin = computed(() => user.value?.role === 'super_admin')
  const isOrgAdmin = computed(() => user.value?.role === 'org_admin')
  const isHrManager = computed(() => user.value?.role === 'hr_manager')

  async function login(email: string, password: string) {
    const response = await api.post('/login', { email, password })
    token.value = response.data.token
    user.value = response.data.user
    localStorage.setItem('auth_token', token.value!)
    return response.data
  }

  async function logout() {
    await api.post('/logout')
    token.value = null
    user.value = null
    localStorage.removeItem('auth_token')
  }

  async function fetchMe() {
    const response = await api.get('/me')
    user.value = response.data.user
  }

  return { user, token, isAuthenticated, isSuperAdmin, isOrgAdmin, isHrManager, login, logout, fetchMe }
})
