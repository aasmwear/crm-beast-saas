<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3' // Only using 'router' for navigation
import Flatpickr from 'vue-flatpickr-component'
import 'flatpickr/dist/flatpickr.css'

// Props passed from Index.vue
const props = defineProps<{
  // Raw YYYY-MM-DD dates passed from the controller (for Flatpickr initialization)
  startDateRaw: string
  endDateRaw: string
  // Formatted dates (e.g., "Jan 01") for the button display
  startDate: string
  endDate: string
}>()

// Flatpickr uses a single string input for date ranges, separated by ' to '
const dateString = ref('')

// Watch the RAW date props to initialize dateString safely.
watch([() => props.startDateRaw, () => props.endDateRaw], ([newStart, newEnd]) => {
    // Only set the date string if we have valid, non-empty dates
    if (newStart && newEnd) {
        dateString.value = `${newStart} to ${newEnd}`
    }
}, { immediate: true })

// --- Display Formatting ---
// Combines the formatted props for display in the button text
const displayDateRange = computed(() => {
    // We rely on the formatted props for display
    return `${props.startDate} - ${props.endDate}`
})

// --- Flatpickr Configuration ---
const config = {
  mode: 'range' as const,
  dateFormat: 'Y-m-d',
  // Use data-toggle to link the picker to the button element
  wrap: true,
  // NOTE: Removed onClose. The logic is now in the watcher below.
}

// --- Instant Apply Fix ---
// Watch for changes in the flatpickr v-model (dateString)
watch(dateString, (newDateString) => {
    const [start, end] = newDateString.split(' to ')

    // When a full range (start and end) is selected, apply the filter immediately.
    if (start && end) {
        // Replaces the query parameters in the URL and forces the controller to rerun
        router.get(
            window.location.pathname,
            {
                start_date: start,
                end_date: end
            },
            {
                // This prevents unnecessary history entries
                replace: true,
                // Do not preserve scroll on dashboard refresh
                preserveScroll: false,
            }
        )
    }
})

</script>

<template>
  <div class="relative">
    
    <i class="bi bi-calendar-range text-base"></i>

    <Flatpickr
      v-model="dateString"
      :config="config"
    >
      <button
        type="button"
        data-toggle
        class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition
               bg-white/5 border border-white/10 hover:bg-white/10 text-white shadow-lg"
      >
        <i class="bi bi-calendar-range text-base"></i>
        <span>
        {{ displayDateRange }}
      </span>
      </button>
    </Flatpickr>
  </div>
</template>

<style>
/* Adjustments for a dark, professional Flatpickr look */
.flatpickr-input{
  width: 210px;
  padding: 5px 9px;
  border: 2px solid #ffffff2b;
  border-radius: 12px;
  background-color: #ffffff0f;
  box-shadow: 0px 0px 6px 0px #a855f736 inset;
}

.flatpickr-calendar {
  background: #14171D; /* Card background (from theme.css) */
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
  border-radius: 12px;
  color: #E8EBF7; /* Foreground color */
}

/* Day and month text */
.flatpickr-months .flatpickr-month,
.flatpickr-day,
.flatpickr-weekday,
.flatpickr-current-month .flatpickr-monthDropdown-months,
.flatpickr-current-month .numInputWrapper {
  color: #E8EBF7 !important;
  background: #14171D;
}

/* Selected/Range background color (using a light purple accent) */
.flatpickr-day.selected,
.flatpickr-day.startRange,
.flatpickr-day.endRange,
.flatpickr-day.selected.inRange,
.flatpickr-day.startRange.inRange,
.flatpickr-day.endRange.inRange,
.flatpickr-day.selected:focus,
.flatpickr-day.startRange:focus,
.flatpickr-day.endRange:focus,
.flatpickr-day.selected:hover,
.flatpickr-day.startRange:hover,
.flatpickr-day.endRange:hover,
.flatpickr-day.selected.prevMonthDay,
.flatpickr-day.startRange.prevMonthDay,
.flatpickr-day.endRange.prevMonthDay,
.flatpickr-day.selected.nextMonthDay,
.flatpickr-day.startRange.nextMonthDay,
.flatpickr-day.endRange.nextMonthDay {
    background-color: #A855F7 !important; /* Tailwind Purple-500 */
    border-color: #A855F7 !important;
    color: #0D0F14 !important; /* Dark text for contrast */
    box-shadow: none;
}
/* Hover effect for unselected days */
.flatpickr-day:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: transparent;
}
.flatpickr-day.inRange, .flatpickr-day.prevMonthDay.inRange, .flatpickr-day.nextMonthDay.inRange, .flatpickr-day.today.inRange, .flatpickr-day.prevMonthDay.today.inRange, .flatpickr-day.nextMonthDay.today.inRange, .flatpickr-day:hover, .flatpickr-day.prevMonthDay:hover, .flatpickr-day.nextMonthDay:hover, .flatpickr-day:focus, .flatpickr-day.prevMonthDay:focus, .flatpickr-day.nextMonthDay:focus{
  background:none;
}
.flatpickr-months .flatpickr-prev-month, .flatpickr-months .flatpickr-next-month{
  color: #ffffffe6;
  fill: #ffffffe6;
}
</style>
