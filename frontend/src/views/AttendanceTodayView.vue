<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <div class="text-h5 font-weight-bold">Bugungi Holat</div>
        <div class="text-caption text-medium-emphasis">{{ today }}</div>
      </div>
      <v-btn
        variant="outlined"
        prepend-icon="mdi-refresh"
        :loading="loading"
        @click="fetchData"
      >
        Yangilash
      </v-btn>
    </div>

    <!-- Org filter: faqat super_admin / hr_manager uchun -->
    <v-select
      v-if="!authStore.isOrgAdmin"
      v-model="selectedOrg"
      :items="[{ name: 'Barcha tashkilotlar', id: null }, ...organizations]"
      item-title="name"
      item-value="id"
      variant="outlined"
      density="compact"
      hide-details
      class="mb-6"
      style="max-width: 300px"
      @update:model-value="fetchData"
    />

    <!-- Summary chips -->
    <v-row class="mb-6">
      <v-col v-for="s in summary" :key="s.label" cols="6" sm="3">
        <v-card rounded="xl" elevation="0" border :color="s.bg" variant="tonal">
          <v-card-text class="pa-4 text-center">
            <div class="text-h4 font-weight-bold" :style="{ color: s.color }">{{ s.value }}</div>
            <div class="text-caption mt-1">{{ s.label }}</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Employees list -->
    <v-card rounded="xl" elevation="0" border>
      <v-data-table
        :headers="headers"
        :items="records"
        :loading="loading"
        density="comfortable"
        item-value="id"
        hover
      >
        <template #item.employee="{ item }">
          <div class="d-flex align-center py-2">
            <div class="mr-3 d-flex gap-1">
              <!-- Profil rasmi -->
              <v-avatar size="48" color="primary">
                <v-img v-if="item.employee?.photo_url" :src="item.employee.photo_url" cover />
                <span v-else class="text-body-2 text-white font-weight-bold">
                  {{ (item.employee?.last_name?.[0] || '') + (item.employee?.first_name?.[0] || '') }}
                </span>
              </v-avatar>
              <!-- Qurilmadan kelgan yuz rasmi -->
              <v-avatar v-if="item.face_log_id" size="48" rounded="lg">
                <v-img :src="`/api/v1/attendance/logs/${item.face_log_id}/picture`" cover>
                  <template #error>
                    <v-icon size="24" color="grey">mdi-face-recognition</v-icon>
                  </template>
                </v-img>
              </v-avatar>
            </div>
            <div>
              <div class="text-body-2">{{ item.employee?.last_name }} {{ item.employee?.first_name }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.employee?.position }}</div>
            </div>
          </div>
        </template>

        <template #item.department="{ item }">
          <span class="text-caption text-medium-emphasis">{{ item.employee?.department || '—' }}</span>
        </template>

        <template #item.status="{ item }">
          <v-chip :color="statusColor(item.status)" size="small" variant="tonal">
            {{ statusLabel(item.status) }}
          </v-chip>
        </template>

        <template #item.first_entry="{ item }">
          <span v-if="item.first_entry" class="text-body-2 font-weight-medium text-success">
            {{ item.first_entry }}
          </span>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </template>

        <template #item.last_exit="{ item }">
          <span v-if="item.last_exit" class="text-body-2">{{ item.last_exit }}</span>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </template>

        <template #item.work_minutes="{ item }">
          <span v-if="item.work_minutes > 0">{{ Math.round(item.work_minutes / 60) }} soat</span>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </template>
      </v-data-table>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/api/axios'
import { useAuthStore } from '@/stores/auth'
import dayjs from 'dayjs'

const authStore = useAuthStore()
const organizations = ref<any[]>([])
const selectedOrg = ref<number | null>(null)
const records = ref<any[]>([])
const stats = ref<any>({})
const loading = ref(true)
const today = dayjs().format('DD MMMM YYYY, dddd')

const headers = [
  { title: 'Xodim', key: 'employee', sortable: false },
  { title: 'Bo\'lim', key: 'department', sortable: false },
  { title: 'Holat', key: 'status' },
  { title: 'Kirim', key: 'first_entry' },
  { title: 'Chiqim', key: 'last_exit' },
  { title: 'Ishladi', key: 'work_minutes' },
]

const summary = computed(() => [
  { label: 'Keldi', value: stats.value.present || 0, color: '#2E7D32', bg: 'success' },
  { label: 'Kech keldi', value: stats.value.late || 0, color: '#F57F17', bg: 'warning' },
  { label: 'Kelmadi', value: stats.value.absent || 0, color: '#C62828', bg: 'error' },
  { label: 'Xizmat safari', value: stats.value.business_trip || 0, color: '#0277BD', bg: 'info' },
])

const statusColors: Record<string, string> = {
  present: 'success', late: 'warning', absent: 'error',
  business_trip: 'info', half_day: 'orange', leave: 'grey', holiday: 'purple',
}
const statusLabels: Record<string, string> = {
  present: 'Keldi', late: 'Kech keldi', absent: 'Kelmadi',
  business_trip: 'Safari', half_day: 'Yarim kun', leave: "Ta'til", holiday: 'Bayram',
}
function statusColor(s: string): string { return statusColors[s] || 'grey' }
function statusLabel(s: string): string { return statusLabels[s] || s }

async function fetchData() {
  loading.value = true
  const res = await api.get('/attendance/daily', {
    params: {
      date: dayjs().format('YYYY-MM-DD'),
      organization_id: selectedOrg.value,
    },
  })
  records.value = res.data.records
  stats.value = res.data.stats
  loading.value = false
}

onMounted(async () => {
  if (!authStore.user) await authStore.fetchMe()
  if (authStore.isOrgAdmin) {
    selectedOrg.value = authStore.user?.organization_id ?? null
  } else {
    const orgsRes = await api.get('/organizations')
    organizations.value = orgsRes.data
  }
  await fetchData()
})
</script>
