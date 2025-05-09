import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('token'))

  const isAuthenticated = computed(() => !!token.value)

  const login = async (credentials) => {
    try {
      const response = await axios.post('/api/login', credentials)
      token.value = response.data.token
      user.value = response.data.user
      localStorage.setItem('token', token.value)
      axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
      return response.data
    } catch (error) {
      throw error.response?.data?.message || 'Login failed'
    }
  }

  const logout = async () => {
    try {
      await axios.post('/api/logout')
    } finally {
      token.value = null
      user.value = null
      localStorage.removeItem('token')
      delete axios.defaults.headers.common['Authorization']
    }
  }

  const getUser = async () => {
    try {
      const response = await axios.get('/api/me')
      user.value = response.data
      return response.data
    } catch (error) {
      throw error.response?.data?.message || 'Failed to get user data'
    }
  }

  return {
    user,
    token,
    isAuthenticated,
    login,
    logout,
    getUser
  }
})