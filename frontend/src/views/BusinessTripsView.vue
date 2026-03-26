<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div class="text-h5 font-weight-bold">Xizmat Safarlar</div>
      <v-btn color="primary" prepend-icon="mdi-plus" :to="'/business-trips/new'">
        Yangi safari
      </v-btn>
    </div>

    <!-- Filters -->
    <v-row class="mb-4">
      <v-col cols="12" md="3">
        <v-select
          v-model="statusFilter"
          :items="statusItems"
          item-title="label"
          item-value="value"
          label="Status"
          variant="outlined"
          density="compact"
          clearable
          hide-details
        />
      </v-col>
      <v-col cols="12" md="3">
        <v-select
          v-model="orgFilter"
          :items="organizations"
          item-title="name"
          item-value="id"
          label="Tashkilot"
          variant="outlined"
          density="compact"
          clearable
          hide-details
        />
      </v-col>
    </v-row>

    <v-card rounded="xl" elevation="0" border>
      <v-data-table
        :headers="headers"
        :items="trips"
        :loading="loading"
        density="comfortable"
        hover
      >
        <template #item.employee="{ item }">
          <div class="text-body-2 font-weight-medium">
            {{ item.employee?.last_name }} {{ item.employee?.first_name }}
          </div>
          <div class="text-caption text-medium-emphasis">{{ item.organization?.name }}</div>
        </template>

        <template #item.destination="{ item }">
          <div class="text-body-2">{{ item.destination }}</div>
          <div v-if="item.destinations?.length" class="text-caption text-medium-emphasis">
            <span v-for="(d, i) in item.destinations" :key="d.id">
              <span v-if="i > 0"> · </span>
              <span v-if="d.arrival_date">{{ formatDateTime(d.arrival_date) }}</span>
              <span v-if="d.arrival_date && d.departure_date"> — </span>
              <span v-if="d.departure_date">{{ formatDateTime(d.departure_date) }}</span>
            </span>
          </div>
        </template>

        <template #item.dates="{ item }">
          <div class="text-caption">
            {{ formatDate(item.start_date) }} — {{ formatDate(item.end_date) }}
          </div>
          <div class="text-caption text-medium-emphasis">{{ item.days_count }} kun</div>
        </template>

        <template #item.certificate_serial="{ item }">
          <span class="text-caption font-weight-medium">{{ item.certificate_serial ?? '—' }}</span>
        </template>

        <template #item.status="{ item }">
          <div>
            <v-chip :color="tripStatusColor(item.status)" size="small" variant="tonal">
              {{ tripStatusLabel(item.status) }}
            </v-chip>
            <v-chip
              v-if="item.device_push_status && item.device_push_status !== 'success'"
              :color="item.device_push_status === 'partial' ? 'warning' : 'error'"
              size="x-small"
              variant="tonal"
              class="ml-1"
            >
              {{ item.device_push_status === 'partial' ? 'Qisman' : 'Qurilma xato' }}
            </v-chip>
          </div>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <v-btn
              size="small"
              variant="text"
              icon="mdi-eye"
              :to="`/business-trips/${item.id}`"
            />
            <v-btn
              size="small"
              color="primary"
              variant="text"
              icon="mdi-file-pdf-box"
              @click="downloadPdf(item.id)"
            />
            <template v-if="item.status === 'pending'">
              <v-btn
                size="small"
                color="success"
                variant="text"
                icon="mdi-check"
                @click="approveTrip(item)"
              />
              <v-btn
                size="small"
                color="error"
                variant="text"
                icon="mdi-close"
                @click="rejectDialog(item)"
              />
            </template>
          </div>
        </template>
      </v-data-table>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import api from '@/api/axios'
import dayjs from 'dayjs'

const trips = ref<any[]>([])
const organizations = ref<any[]>([])
const loading = ref(true)
const statusFilter = ref<string | null>(null)
const orgFilter = ref<number | null>(null)

const headers = [
  { title: '№', key: 'certificate_serial', sortable: false, width: '90px' },
  { title: 'Xodim', key: 'employee', sortable: false },
  { title: 'Yo\'nalish / Maqsad', key: 'destination', sortable: false },
  { title: 'Sana', key: 'dates', sortable: false },
  { title: 'Status', key: 'status' },
  { title: '', key: 'actions', sortable: false, width: '130px' },
]

const statusItems = [
  { label: 'Kutilmoqda', value: 'pending' },
  { label: 'Tasdiqlandi', value: 'approved' },
  { label: 'Rad etildi', value: 'rejected' },
  { label: 'Yakunlandi', value: 'completed' },
]

function tripStatusColor(s: string): string {
  return { pending: 'warning', approved: 'success', rejected: 'error', completed: 'info' }[s] || 'grey'
}
function tripStatusLabel(s: string): string {
  return { pending: 'Kutilmoqda', approved: 'Tasdiqlandi', rejected: 'Rad etildi', completed: 'Yakunlandi' }[s] || s
}
function formatDate(d: string): string {
  return dayjs(d).format('DD.MM.YYYY')
}
function formatDateTime(d: string): string {
  const dt = dayjs(d)
  const timeStr = dt.format('HH:mm')
  return timeStr === '00:00' ? dt.format('DD.MM.YYYY') : dt.format('DD.MM HH:mm')
}

async function downloadPdf(id: number) {
  try {
    const res = await api.get(`/business-trips/${id}/pdf`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `safari_${id}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    // silent
  }
}

async function fetchTrips() {
  loading.value = true
  const res = await api.get('/business-trips', {
    params: {
      status: statusFilter.value,
      organization_id: orgFilter.value,
    },
  })
  trips.value = res.data.data || res.data
  loading.value = false
}

async function approveTrip(trip: any) {
  await api.post(`/business-trips/${trip.id}/approve`)
  trip.status = 'approved'
}

function rejectDialog(trip: any) {
  const reason = prompt('Rad etish sababi:')
  if (reason) {
    api.post(`/business-trips/${trip.id}/reject`, { reject_reason: reason })
      .then(() => { trip.status = 'rejected' })
  }
}

watch([statusFilter, orgFilter], fetchTrips)

onMounted(async () => {
  const orgsRes = await api.get('/organizations')
  organizations.value = orgsRes.data
  await fetchTrips()
})
</script>
