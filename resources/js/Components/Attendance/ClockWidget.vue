<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

type AttendanceRecord = {
  id: number
  clock_in_at: string
  clock_out_at: string | null
  status: string
}

const props = defineProps<{
  current?: AttendanceRecord | null
  organizationSlug: string
}>()

const page = usePage<any>()
const loading = ref(false)
const elapsedSeconds = ref(0)
let intervalId: number | null = null

// Route helper
const r = (name: string, params: any = {}) => {
  const routeGlobal = (window as any).route
  return routeGlobal ? routeGlobal(name, params) : '#'
}

const isClockedIn = computed(() => {
  return props.current && !props.current.clock_out_at
})

const clockInTime = computed(() => {
  if (!props.current?.clock_in_at) return null
  return new Date(props.current.clock_in_at)
})

// Calculate elapsed time
const updateElapsedTime = () => {
  if (clockInTime.value && isClockedIn.value) {
    const now = new Date()
    elapsedSeconds.value = Math.floor((now.getTime() - clockInTime.value.getTime()) / 1000)
  }
}

// Format elapsed time as HH:MM:SS
const formattedElapsed = computed(() => {
  const hours = Math.floor(elapsedSeconds.value / 3600)
  const minutes = Math.floor((elapsedSeconds.value % 3600) / 60)
  const seconds = elapsedSeconds.value % 60
  return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
})

// Start/stop timer
onMounted(() => {
  if (isClockedIn.value) {
    updateElapsedTime()
    intervalId = window.setInterval(updateElapsedTime, 1000)
  }
})

onUnmounted(() => {
  if (intervalId !== null) {
    clearInterval(intervalId)
  }
})

// Get geolocation
const getGeolocation = (): Promise<{ lat: number; lng: number }> => {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('Geolocation not supported'))
      return
    }
    
    navigator.geolocation.getCurrentPosition(
      (position) => {
        resolve({
          lat: position.coords.latitude,
          lng: position.coords.longitude,
        })
      },
      (error) => {
        console.warn('Geolocation error:', error)
        // Resolve with null values if user denies or error occurs
        resolve({ lat: 0, lng: 0 })
      },
      { timeout: 10000, enableHighAccuracy: true }
    )
  })
}

// Clock In
const handleClockIn = async () => {
  loading.value = true
  
  try {
    const geo = await getGeolocation()
    
    router.post(
      r('attendance.clockIn', { organization: props.organizationSlug }),
      {
        lat: geo.lat,
        lng: geo.lng,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          // Restart timer
          if (intervalId !== null) {
            clearInterval(intervalId)
          }
          updateElapsedTime()
          intervalId = window.setInterval(updateElapsedTime, 1000)
        },
        onFinish: () => {
          loading.value = false
        },
      }
    )
  } catch (error) {
    console.error('Clock in error:', error)
    loading.value = false
  }
}

// Clock Out
const handleClockOut = async () => {
  loading.value = true
  
  try {
    const geo = await getGeolocation()
    
    router.post(
      r('attendance.clockOut', { organization: props.organizationSlug }),
      {
        lat: geo.lat,
        lng: geo.lng,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          // Stop timer
          if (intervalId !== null) {
            clearInterval(intervalId)
            intervalId = null
          }
          elapsedSeconds.value = 0
        },
        onFinish: () => {
          loading.value = false
        },
      }
    )
  } catch (error) {
    console.error('Clock out error:', error)
    loading.value = false
  }
}
</script>

<template>
  <div class="card-neo p-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-lg font-semibold text-white/90">Attendance</h3>
        <p class="text-xs text-white/50">Track your work hours</p>
      </div>
      
      <!-- Timer Display (only when clocked in) -->
      <div v-if="isClockedIn" class="text-right">
        <div class="text-2xl font-mono font-bold text-[var(--primary)]">
          {{ formattedElapsed }}
        </div>
        <div class="text-xs text-white/50">Elapsed time</div>
      </div>
    </div>

    <!-- Clock In/Out Button -->
    <div class="flex flex-col gap-3">
      <button
        v-if="!isClockedIn"
        @click="handleClockIn"
        :disabled="loading"
        class="w-full py-4 rounded-xl font-semibold text-white transition-all duration-200"
        :class="
          loading
            ? 'bg-white/10 cursor-not-allowed'
            : 'bg-green-600 hover:bg-green-700 active:scale-[0.98]'
        "
      >
        <span v-if="loading">Processing...</span>
        <span v-else class="flex items-center justify-center gap-2">
          <svg
            class="w-5 h-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          Clock In
        </span>
      </button>

      <button
        v-else
        @click="handleClockOut"
        :disabled="loading"
        class="w-full py-4 rounded-xl font-semibold text-white transition-all duration-200"
        :class="
          loading
            ? 'bg-white/10 cursor-not-allowed'
            : 'bg-red-600 hover:bg-red-700 active:scale-[0.98]'
        "
      >
        <span v-if="loading">Processing...</span>
        <span v-else class="flex items-center justify-center gap-2">
          <svg
            class="w-5 h-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
          Clock Out
        </span>
      </button>

      <!-- Status indicator -->
      <div class="text-center text-xs text-white/50">
        <span v-if="isClockedIn" class="inline-flex items-center gap-1">
          <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
          Currently clocked in
        </span>
        <span v-else class="inline-flex items-center gap-1">
          <span class="w-2 h-2 rounded-full bg-white/20"></span>
          Not clocked in
        </span>
      </div>
    </div>
  </div>
</template>
