<template>
  <div v-if="employee">
    <div class="d-flex align-center mb-6">
      <v-btn icon="mdi-arrow-left" variant="text" class="mr-2" @click="$router.back()" />
      <div class="text-h5 font-weight-bold">Xodim profili</div>
    </div>

    <v-row>
      <v-col cols="12" md="4">
        <v-card rounded="xl" elevation="0" border class="pa-6 text-center">
          <v-avatar size="96" color="primary" class="mb-4">
            <v-img v-if="employee.photo_url" :src="employee.photo_url" cover />
            <span v-else class="text-h5 text-white">
              {{ (employee.last_name?.[0] || '') + (employee.first_name?.[0] || '') }}
            </span>
          </v-avatar>
          <div class="text-h6 font-weight-bold">{{ employee.last_name }} {{ employee.first_name }}</div>
          <div class="text-body-2 text-medium-emphasis mb-1">{{ employee.middle_name }}</div>
          <v-chip color="primary" size="small" class="mb-4">{{ employee.position }}</v-chip>

          <v-divider class="mb-4" />

          <div class="text-left">
            <div v-for="field in fields" :key="field.label" class="d-flex justify-space-between mb-2">
              <span class="text-caption text-medium-emphasis">{{ field.label }}</span>
              <span class="text-caption font-weight-medium">{{ field.value }}</span>
            </div>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" md="8">
        <v-tabs v-model="activeTab" class="mb-3">
          <v-tab value="attendance">Keldi-ketdi (30 kun)</v-tab>
          <v-tab value="trips">Xizmat safarlar</v-tab>
        </v-tabs>

        <v-window v-model="activeTab">
          <!-- Attendance tab -->
          <v-window-item value="attendance">
            <v-card rounded="xl" elevation="0" border>
              <v-card-text class="pa-5">
                <div v-if="loadingAttendance" class="text-center py-8">
                  <v-progress-circular indeterminate color="primary" />
                </div>
                <v-timeline v-else density="compact" side="end">
                  <v-timeline-item
                    v-for="rec in attendance"
                    :key="rec.work_date"
                    :dot-color="statusColor(rec.status)"
                    size="small"
                  >
                    <div class="d-flex align-center gap-3">
                      <span class="text-body-2 font-weight-medium" style="min-width: 90px">
                        {{ formatDate(rec.work_date) }}
                      </span>
                      <v-chip :color="statusColor(rec.status)" size="x-small" variant="tonal">
                        {{ statusLabel(rec.status) }}
                      </v-chip>
                      <span v-if="rec.first_entry" class="text-caption text-medium-emphasis">
                        {{ rec.first_entry }} — {{ rec.last_exit || '?' }}
                        ({{ Math.round(rec.work_minutes / 60) }} soat)
                      </span>
                    </div>
                  </v-timeline-item>
                  <div v-if="!attendance.length" class="text-caption text-medium-emphasis text-center py-4">
                    Ma'lumot yo'q
                  </div>
                </v-timeline>
              </v-card-text>
            </v-card>
          </v-window-item>

          <!-- Trips tab -->
          <v-window-item value="trips">
            <v-card rounded="xl" elevation="0" border>
              <v-card-text class="pa-5">
                <div v-if="loadingTrips" class="text-center py-8">
                  <v-progress-circular indeterminate color="primary" />
                </div>
                <div v-else-if="!trips.length" class="text-caption text-medium-emphasis text-center py-4">
                  Xizmat safari yo'q
                </div>
                <v-list v-else density="compact" class="pa-0">
                  <v-list-item
                    v-for="trip in trips"
                    :key="trip.id"
                    :to="`/business-trips/${trip.id}`"
                    rounded="lg"
                    class="mb-2"
                  >
                    <template #prepend>
                      <v-icon :color="tripStatusColor(trip.status)" size="20" class="mr-2">
                        mdi-airplane
                      </v-icon>
                    </template>
                    <v-list-item-title class="text-body-2 font-weight-medium">
                      {{ trip.destination }}
                      <v-chip class="ml-2" size="x-small" :color="tripStatusColor(trip.status)" variant="tonal">
                        {{ tripStatusLabel(trip.status) }}
                      </v-chip>
                    </v-list-item-title>
                    <v-list-item-subtitle class="text-caption">
                      {{ formatDate(trip.start_date) }} — {{ formatDate(trip.end_date) }}
                      · {{ trip.days_count }} kun
                      <span v-if="trip.certificate_serial" class="ml-2 text-medium-emphasis">№{{ trip.certificate_serial }}</span>
                    </v-list-item-subtitle>
                  </v-list-item>
                </v-list>
              </v-card-text>
            </v-card>
          </v-window-item>
        </v-window>
      </v-col>
    </v-row>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/api/axios'
import dayjs from 'dayjs'

const route = useRoute()
const employee = ref<any>(null)
const attendance = ref<any[]>([])
const trips = ref<any[]>([])
const loadingAttendance = ref(true)
const loadingTrips = ref(false)
const activeTab = ref('attendance')

const fields = computed(() => [
  { label: 'Tabel №', value: employee.value?.employee_id },
  { label: 'Bo\'lim', value: employee.value?.department },
  { label: 'Tashkilot', value: employee.value?.organization?.name },
  { label: 'Telefon', value: employee.value?.phone },
  { label: 'Ishga kirgan', value: employee.value?.hired_at ? dayjs(employee.value.hired_at).format('DD.MM.YYYY') : '—' },
])

function tripStatusColor(s: string): string {
  return { pending: 'warning', approved: 'success', rejected: 'error', completed: 'info' }[s] ?? 'grey'
}
function tripStatusLabel(s: string): string {
  return { pending: 'Kutilmoqda', approved: 'Tasdiqlandi', rejected: 'Rad etildi', completed: 'Yakunlandi' }[s] ?? s
}

function statusColor(s: string): string {
  return { present: 'success', late: 'warning', absent: 'error', business_trip: 'info' }[s] || 'grey'
}
function statusLabel(s: string): string {
  return { present: 'Keldi', late: 'Kech', absent: 'Kelmadi', business_trip: 'Safari' }[s] || s
}
function formatDate(d: string): string {
  return dayjs(d).format('DD.MM.YYYY')
}

onMounted(async () => {
  const id = route.params.id
  const [empRes, attRes] = await Promise.all([
    api.get(`/employees/${id}`),
    api.get(`/employees/${id}/attendance`, {
      params: {
        from: dayjs().subtract(30, 'day').format('YYYY-MM-DD'),
        to: dayjs().format('YYYY-MM-DD'),
      },
    }),
  ])
  employee.value = empRes.data
  attendance.value = attRes.data.reverse()
  loadingAttendance.value = false

  // Load trips
  loadingTrips.value = true
  try {
    const tripsRes = await api.get('/business-trips', { params: { employee_id: id, per_page: 50 } })
    trips.value = tripsRes.data.data ?? tripsRes.data
  } finally {
    loadingTrips.value = false
  }
})
</script>
