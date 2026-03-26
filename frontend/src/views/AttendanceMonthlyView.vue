<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6 flex-wrap gap-3">
      <div class="text-h5 font-weight-bold">Oylik Tabel</div>
      <div class="d-flex align-center gap-3">
        <v-select
          v-if="!authStore.isOrgAdmin"
          v-model="selectedOrg"
          :items="organizations"
          item-title="name"
          item-value="id"
          label="Tashkilot"
          variant="outlined"
          density="compact"
          clearable
          hide-details
          style="min-width: 200px"
        />
        <v-select
          v-model="selectedMonth"
          :items="monthItems"
          item-title="title"
          item-value="value"
          label="Oy"
          variant="outlined"
          density="compact"
          hide-details
          style="min-width: 140px"
        />
        <v-select
          v-model="selectedYear"
          :items="yearItems"
          label="Yil"
          variant="outlined"
          density="compact"
          hide-details
          style="min-width: 100px"
        />
        <v-btn color="primary" variant="outlined" prepend-icon="mdi-refresh" @click="fetchData">
          Yangilash
        </v-btn>
      </div>
    </div>

    <!-- Legend -->
    <v-card rounded="xl" elevation="0" border class="mb-4">
      <v-card-text class="pa-3">
        <div class="d-flex flex-wrap gap-2">
          <v-chip v-for="leg in legend" :key="leg.code" :color="leg.color" size="small" variant="tonal">
            <strong>{{ leg.code }}</strong>&nbsp;— {{ leg.label }}
          </v-chip>
        </div>
      </v-card-text>
    </v-card>

    <!-- Table -->
    <v-card rounded="xl" elevation="0" border>
      <div v-if="loading" class="text-center py-12">
        <v-progress-circular indeterminate color="primary" size="48" />
      </div>
      <div v-else class="tabel-wrapper">
        <table class="tabel-table">
          <thead>
            <tr>
              <th class="tabel-header sticky-col" rowspan="2">#</th>
              <th class="tabel-header sticky-col2" rowspan="2">F.I.O.</th>
              <th class="tabel-header sticky-col3" rowspan="2">Lavozim</th>
              <th
                v-for="d in daysInMonth"
                :key="d"
                class="tabel-header day-header"
                :class="{ 'weekend-col': isWeekend(d) }"
              >
                {{ d }}
              </th>
              <th class="tabel-header total-col">Ish kuni</th>
              <th class="tabel-header total-col">Soat</th>
            </tr>
            <tr>
              <th
                v-for="d in daysInMonth"
                :key="d"
                class="tabel-header day-header"
                :class="{ 'weekend-col': isWeekend(d) }"
                style="font-size: 9px; font-weight: normal;"
              >
                {{ getDayName(d) }}
              </th>
              <th class="tabel-header" />
              <th class="tabel-header" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in tableData" :key="row.employee.id" class="tabel-row">
              <td class="tabel-cell sticky-col text-center text-caption">{{ idx + 1 }}</td>
              <td class="tabel-cell sticky-col2">
                <div class="text-body-2 font-weight-medium" style="white-space: nowrap">
                  {{ row.employee.full_name }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ row.employee.organization?.code }}
                </div>
              </td>
              <td class="tabel-cell sticky-col3">
                <div class="text-caption" style="max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                  {{ row.employee.position }}
                </div>
              </td>
              <td
                v-for="d in daysInMonth"
                :key="d"
                class="tabel-cell day-cell text-center"
                :class="{ 'weekend-col': isWeekend(d), [`status-${row.days[d]?.status}`]: !!row.days[d] }"
              >
                <v-tooltip v-if="row.days[d]" :text="getDayTooltip(row.days[d])">
                  <template #activator="{ props }">
                    <span v-bind="props" class="day-badge">
                      {{ getStatusCode(row.days[d]?.status ?? "absent") }}
                    </span>
                  </template>
                </v-tooltip>
              </td>
              <td class="tabel-cell total-col text-center font-weight-bold">
                {{ row.work_days }}
              </td>
              <td class="tabel-cell total-col text-center">
                {{ Math.round(row.total_work_minutes / 60) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/api/axios'
import { useAuthStore } from '@/stores/auth'
import dayjs from 'dayjs'

const authStore = useAuthStore()

const organizations = ref<any[]>([])
const selectedOrg = ref<number | null>(null)
const selectedYear = ref(dayjs().year())
const selectedMonth = ref(dayjs().month() + 1)
const tableData = ref<any[]>([])
const daysInMonth = ref(30)
const loading = ref(true)

const monthItems = [
  { title: 'Yanvar', value: 1 }, { title: 'Fevral', value: 2 }, { title: 'Mart', value: 3 },
  { title: 'Aprel', value: 4 }, { title: 'May', value: 5 }, { title: 'Iyun', value: 6 },
  { title: 'Iyul', value: 7 }, { title: 'Avgust', value: 8 }, { title: 'Sentabr', value: 9 },
  { title: 'Oktabr', value: 10 }, { title: 'Noyabr', value: 11 }, { title: 'Dekabr', value: 12 },
]

const yearItems = computed(() => {
  const y = dayjs().year()
  return [y - 1, y, y + 1]
})

const legend = [
  { code: 'K', label: 'Keldi', color: 'success' },
  { code: 'B', label: 'Kelmadi', color: 'error' },
  { code: 'KK', label: 'Kech keldi', color: 'warning' },
  { code: 'X', label: 'Xizmat safari', color: 'info' },
  { code: 'T', label: 'Ta\'til', color: 'grey' },
  { code: 'YK', label: 'Yarim kun', color: 'orange' },
]

const statusCodeMap: Record<string, string> = {
  present: 'K',
  absent: 'B',
  late: 'KK',
  business_trip: 'X',
  leave: 'T',
  half_day: 'YK',
  holiday: 'D',
}

function getStatusCode(status: string): string {
  return statusCodeMap[status] || '?'
}

function isWeekend(day: number): boolean {
  const date = dayjs(`${selectedYear.value}-${selectedMonth.value}-${day}`)
  return date.day() === 0 || date.day() === 6
}

function getDayName(day: number): string {
  const days = ['Ya', 'Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh']
  const date = dayjs(`${selectedYear.value}-${selectedMonth.value}-${day}`)
  return days[date.day()] ?? "Ya"
}

function getDayTooltip(dayData: any): string {
  if (!dayData) return ''
  const parts = []
  if (dayData.first_entry) parts.push(`Kirim: ${dayData.first_entry}`)
  if (dayData.last_exit) parts.push(`Chiqim: ${dayData.last_exit}`)
  if (dayData.work_minutes) parts.push(`${Math.round(dayData.work_minutes / 60)} soat`)
  return parts.join(' · ')
}

async function fetchData() {
  loading.value = true
  try {
    const res = await api.get('/reports/monthly-table', {
      params: {
        year: selectedYear.value,
        month: selectedMonth.value,
        organization_id: selectedOrg.value,
      },
    })
    tableData.value = res.data.table
    daysInMonth.value = res.data.days_in_month
  } finally {
    loading.value = false
  }
}

watch([selectedOrg, selectedYear, selectedMonth], fetchData)

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

<style scoped>
.tabel-wrapper {
  overflow-x: auto;
  max-height: 70vh;
  overflow-y: auto;
}
.tabel-table {
  border-collapse: collapse;
  min-width: 100%;
  font-size: 12px;
}
.tabel-header {
  background: #F5F7FA;
  padding: 8px 4px;
  text-align: center;
  border: 1px solid #E0E0E0;
  font-weight: 600;
  position: sticky;
  top: 0;
  z-index: 2;
  white-space: nowrap;
}
.tabel-cell {
  padding: 4px 6px;
  border: 1px solid #E0E0E0;
  font-size: 11px;
}
.tabel-row:hover td {
  background: #F5F7FA;
}
.sticky-col { left: 0; position: sticky; z-index: 3; background: white; min-width: 30px; }
.sticky-col2 { left: 30px; position: sticky; z-index: 3; background: white; min-width: 170px; }
.sticky-col3 { left: 200px; position: sticky; z-index: 3; background: white; min-width: 150px; }
.day-header { min-width: 32px; width: 32px; max-width: 32px; }
.day-cell { width: 32px; max-width: 32px; height: 28px; }
.weekend-col { background: #FFF9C4 !important; }
.total-col { min-width: 60px; font-weight: 600; }
.day-badge { display: inline-block; cursor: default; }
/* Status colors */
.status-present td, td.status-present { background: #E8F5E9 !important; color: #1B5E20; }
.status-late td, td.status-late { background: #FFF8E1 !important; color: #F57F17; }
.status-absent td, td.status-absent { background: #FFEBEE !important; color: #B71C1C; }
.status-business_trip td, td.status-business_trip { background: #E3F2FD !important; color: #0D47A1; }
.status-half_day td, td.status-half_day { background: #FFF3E0 !important; color: #E65100; }
</style>
