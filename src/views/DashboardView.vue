<template>
  <div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
      
      <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Today's Activity -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">Today's Activity</h2>
            <div v-if="dailyReport" class="mt-4">
              <div class="grid grid-cols-2 gap-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                  <p class="text-sm text-gray-500">Work Time</p>
                  <p class="text-lg font-semibold">{{ dailyReport.total_work_time?.formatted || '00:00' }}</p>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                  <p class="text-sm text-gray-500">Status</p>
                  <p class="text-lg font-semibold capitalize">{{ dailyReport.status }}</p>
                </div>
              </div>
            </div>
            <div v-else class="mt-4 text-gray-500">
              No activity recorded today
            </div>
          </div>
        </div>

        <!-- Weekly Summary -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">Weekly Summary</h2>
            <div v-if="weeklyReport" class="mt-4">
              <div class="grid grid-cols-2 gap-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                  <p class="text-sm text-gray-500">Total Hours</p>
                  <p class="text-lg font-semibold">{{ weeklyReport.summary?.work_time?.formatted || '00:00' }}</p>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                  <p class="text-sm text-gray-500">Productivity</p>
                  <p class="text-lg font-semibold">{{ weeklyReport.summary?.productivity || 0 }}%</p>
                </div>
              </div>
            </div>
            <div v-else class="mt-4 text-gray-500">
              No data available
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <CheckInButton />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import CheckInButton from '@/components/CheckInButton.vue'
import axios from 'axios'

const authStore = useAuthStore()
const dailyReport = ref(null)
const weeklyReport = ref(null)

const fetchReports = async () => {
  if (!authStore.user?.employeeRecords?.length) return
  
  try {
    const [dailyRes, weeklyRes] = await Promise.all([
      axios.get(`/api/worklogs/daily-report/${authStore.user.employeeRecords[0].id}`),
      axios.get(`/api/worklogs/weekly-report/${authStore.user.employeeRecords[0].id}`)
    ])
    
    dailyReport.value = dailyRes.data
    weeklyReport.value = weeklyRes.data
  } catch (error) {
    console.error('Error fetching reports:', error)
  }
}

onMounted(async () => {
  await fetchReports()
  // Update reports every 5 minutes
  setInterval(fetchReports, 300000)
})
</script>