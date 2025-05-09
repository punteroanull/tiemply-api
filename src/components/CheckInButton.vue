<template>
  <div class="fixed bottom-4 right-4 flex flex-col gap-2">
    <div v-if="status" class="text-sm font-medium mb-2 text-center bg-white p-2 rounded-lg shadow">
      Last activity: {{ status.last_activity?.time || 'No activity' }}
    </div>
    <button
      @click="handleCheckInOut"
      :class="[
        'px-6 py-3 rounded-full text-white font-semibold shadow-lg transform transition-transform hover:scale-105',
        status?.status === 'in' ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600'
      ]"
      :disabled="isLoading"
    >
      <span v-if="isLoading">Processing...</span>
      <span v-else>{{ status?.status === 'in' ? 'Check Out' : 'Check In' }}</span>
    </button>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import axios from 'axios'

const authStore = useAuthStore()
const status = ref(null)
const isLoading = ref(false)

const fetchStatus = async () => {
  try {
    const response = await axios.get(`/api/worklogs/status/${authStore.user.employeeRecords[0].id}`)
    status.value = response.data
  } catch (error) {
    console.error('Error fetching status:', error)
  }
}

const handleCheckInOut = async () => {
  isLoading.value = true
  try {
    const endpoint = status.value?.status === 'in' ? 'check-out' : 'check-in'
    await axios.post(`/api/worklogs/${endpoint}`, {
      employee_id: authStore.user.employeeRecords[0].id
    })
    await fetchStatus()
  } catch (error) {
    console.error('Error during check in/out:', error)
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  if (authStore.user?.employeeRecords?.length) {
    await fetchStatus()
    // Update status every minute
    setInterval(fetchStatus, 60000)
  }
})
</script>