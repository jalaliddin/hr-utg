<template>
  <div>
    <div class="text-h5 font-weight-bold mb-6">Hisobotlar</div>

    <v-tabs v-model="activeTab" color="primary" class="mb-6">
      <v-tab value="monthly">Oylik tabel</v-tab>
      <v-tab value="trips">Xizmat safarlar</v-tab>
      <v-tab value="summary">Umumiy</v-tab>
    </v-tabs>

    <v-window v-model="activeTab">
      <!-- TAB 1: Monthly tabel -->
      <v-window-item value="monthly">
        <div class="d-flex align-center justify-space-between mb-4 flex-wrap gap-3">
          <div class="d-flex align-center gap-3 flex-wrap">
            <v-select
              v-if="!authStore.isOrgAdmin"
              v-model="tabelOrg"
              :items="[{ name: 'Barcha tashkilotlar', id: null }, ...organizations]"
              item-title="name"
              item-value="id"
              label="Tashkilot"
              variant="outlined"
              density="compact"
              clearable
              hide-details
              style="min-width: 200px"
              @update:model-value="onTabelOrgChange"
            />
            <v-select
              v-model="tabelDept"
              :items="[{ name: 'Barcha bo\'limlar', value: null }, ...tabelDepts]"
              item-title="name"
              item-value="value"
              label="Bo'lim"
              variant="outlined"
              density="compact"
              clearable
              hide-details
              style="min-width: 180px"
            />
            <v-select
              v-model="tabelMonth"
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
              v-model="tabelYear"
              :items="yearItems"
              label="Yil"
              variant="outlined"
              density="compact"
              hide-details
              style="min-width: 100px"
            />
            <v-btn color="primary" variant="outlined" prepend-icon="mdi-refresh" @click="fetchTabel">
              Yangilash
            </v-btn>
          </div>
          <v-btn
            color="success"
            prepend-icon="mdi-microsoft-excel"
            :loading="exporting"
            @click="exportExcel"
          >
            Excel
          </v-btn>
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

        <v-card rounded="xl" elevation="0" border>
          <div v-if="tabelLoading" class="text-center py-12">
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
                          {{ getStatusCode(row.days[d]?.status ?? 'absent') }}
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
      </v-window-item>

      <!-- TAB 2: Business Trips -->
      <v-window-item value="trips">
        <div class="d-flex align-center gap-3 mb-4 flex-wrap">
          <v-select
            v-if="!authStore.isOrgAdmin"
            v-model="tripsOrg"
            :items="[{ name: 'Barcha tashkilotlar', id: null }, ...organizations]"
            item-title="name"
            item-value="id"
            label="Tashkilot"
            variant="outlined"
            density="compact"
            clearable
            hide-details
            style="min-width: 200px"
          />
          <v-text-field
            v-model="tripsDateFrom"
            label="Dan"
            type="date"
            variant="outlined"
            density="compact"
            hide-details
            style="width: 160px"
          />
          <v-text-field
            v-model="tripsDateTo"
            label="Gacha"
            type="date"
            variant="outlined"
            density="compact"
            hide-details
            style="width: 160px"
          />
          <v-btn color="primary" variant="outlined" prepend-icon="mdi-magnify" @click="fetchTrips">
            Qidirish
          </v-btn>
        </div>

        <v-card rounded="xl" elevation="0" border>
          <v-data-table
            :headers="tripsHeaders"
            :items="trips"
            :loading="tripsLoading"
            density="comfortable"
            hover
          >
            <template #item.employee="{ item }">
              <div class="py-1">
                <div class="text-body-2 font-weight-medium">
                  {{ item.employee?.last_name }} {{ item.employee?.first_name }}
                </div>
                <div class="text-caption text-medium-emphasis">{{ item.organization?.name }}</div>
              </div>
            </template>
            <template #item.destination="{ item }">
              <div>
                <div class="text-body-2">{{ item.destination }}</div>
                <div class="text-caption text-medium-emphasis">{{ item.purpose }}</div>
              </div>
            </template>
            <template #item.dates="{ item }">
              <div class="text-caption">
                {{ formatDate(item.start_date) }} — {{ formatDate(item.end_date) }}
              </div>
              <div class="text-caption text-medium-emphasis">{{ item.days_count }} kun</div>
            </template>
            <template #item.total_amount="{ item }">
              <span class="font-weight-medium">{{ formatMoney(item.total_amount) }}</span>
            </template>
            <template #item.status="{ item }">
              <v-chip :color="tripStatusColor(item.status)" size="small" variant="tonal">
                {{ tripStatusLabel(item.status) }}
              </v-chip>
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>

      <!-- TAB 3: Summary -->
      <v-window-item value="summary">
        <div class="d-flex align-center gap-3 mb-4 flex-wrap">
          <v-select
            v-model="summaryMonth"
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
            v-model="summaryYear"
            :items="yearItems"
            label="Yil"
            variant="outlined"
            density="compact"
            hide-details
            style="min-width: 100px"
          />
          <v-btn color="primary" variant="outlined" prepend-icon="mdi-refresh" @click="fetchSummary">
            Yangilash
          </v-btn>
        </div>

        <v-card rounded="xl" elevation="0" border>
          <v-data-table
            :headers="summaryHeaders"
            :items="summary"
            :loading="summaryLoading"
            density="comfortable"
          >
            <template #item.organization="{ item }">
              <div>
                <div class="text-body-2 font-weight-medium">{{ item.organization?.name ?? item.name }}</div>
                <v-chip size="x-small" variant="tonal" :color="item.organization?.type === 'head' ? 'primary' : 'secondary'">
                  {{ item.organization?.code ?? item.code }}
                </v-chip>
              </div>
            </template>
            <template #item.present_pct="{ item }">
              <v-progress-linear
                :model-value="item.present_pct ?? 0"
                color="success"
                rounded
                height="8"
                class="mt-1"
              />
              <span class="text-caption">{{ item.present_pct ?? 0 }}%</span>
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>
    </v-window>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000">
      {{ snackbar.text }}
    </v-snackbar>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/api/axios'
import { useAuthStore } from '@/stores/auth'
import dayjs from 'dayjs'

const authStore = useAuthStore()

const activeTab = ref('monthly')
const organizations = ref<any[]>([])
const snackbar = ref({ show: false, text: '', color: 'success' })

function showSnack(text: string, color = 'success') {
  snackbar.value = { show: true, text, color }
}

// --- Tabel ---
const tabelOrg = ref<number | null>(null)
const tabelDept = ref<string | null>(null)
const tabelDepts = ref<{ name: string; value: string }[]>([])
const tabelYear = ref(dayjs().year())
const tabelMonth = ref(dayjs().month() + 1)
const tableData = ref<any[]>([])
const daysInMonth = ref(30)
const tabelLoading = ref(false)
const exporting = ref(false)

async function loadTabelDepts(orgId?: number | null) {
  tabelDept.value = null
  if (orgId === undefined || orgId === null) {
    tabelDepts.value = []
    return
  }
  try {
    const res = await api.get('/departments', { params: { organization_id: orgId } })
    const depts = res.data.data ?? res.data
    tabelDepts.value = depts.map((d: any) => ({ name: d.name, value: d.name }))
  } catch {
    tabelDepts.value = []
  }
}

function onTabelOrgChange(orgId: number | null) {
  loadTabelDepts(orgId)
}

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
  { code: 'T', label: "Ta'til", color: 'grey' },
  { code: 'YK', label: 'Yarim kun', color: 'orange' },
]

const statusCodeMap: Record<string, string> = {
  present: 'K', absent: 'B', late: 'KK', business_trip: 'X', leave: 'T', half_day: 'YK', holiday: 'D',
}
function getStatusCode(status: string): string {
  return statusCodeMap[status] ?? '?'
}
function isWeekend(day: number): boolean {
  const date = dayjs(`${tabelYear.value}-${tabelMonth.value}-${day}`)
  return date.day() === 0 || date.day() === 6
}
function getDayName(day: number): string {
  const days = ['Ya', 'Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh']
  const date = dayjs(`${tabelYear.value}-${tabelMonth.value}-${day}`)
  return days[date.day()] ?? 'Ya'
}
function getDayTooltip(dayData: any): string {
  if (!dayData) return ''
  const parts: string[] = []
  if (dayData.first_entry) parts.push(`Kirim: ${dayData.first_entry}`)
  if (dayData.last_exit) parts.push(`Chiqim: ${dayData.last_exit}`)
  if (dayData.work_minutes) parts.push(`${Math.round(dayData.work_minutes / 60)} soat`)
  return parts.join(' · ')
}

async function fetchTabel() {
  tabelLoading.value = true
  try {
    const res = await api.get('/reports/monthly-table', {
      params: {
        year: tabelYear.value,
        month: tabelMonth.value,
        organization_id: tabelOrg.value ?? undefined,
        department: tabelDept.value ?? undefined,
      },
    })
    tableData.value = res.data.table
    daysInMonth.value = res.data.days_in_month
  } catch {
    showSnack("Ma'lumotlarni yuklashda xatolik", 'error')
  } finally {
    tabelLoading.value = false
  }
}

async function exportExcel() {
  exporting.value = true
  try {
    const res = await api.get('/attendance/export', {
      params: { year: tabelYear.value, month: tabelMonth.value, organization_id: tabelOrg.value ?? undefined },
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `tabel_${tabelYear.value}_${tabelMonth.value}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch {
    showSnack('Export xatosi', 'error')
  } finally {
    exporting.value = false
  }
}

watch([tabelOrg, tabelYear, tabelMonth, tabelDept], fetchTabel)

// --- Trips ---
const tripsOrg = ref<number | null>(null)
const tripsDateFrom = ref(dayjs().startOf('month').format('YYYY-MM-DD'))
const tripsDateTo = ref(dayjs().format('YYYY-MM-DD'))
const trips = ref<any[]>([])
const tripsLoading = ref(false)

const tripsHeaders = [
  { title: 'Xodim', key: 'employee', sortable: false },
  { title: "Yo'nalish / Maqsad", key: 'destination', sortable: false },
  { title: 'Sana', key: 'dates', sortable: false },
  { title: 'Summa', key: 'total_amount', sortable: false },
  { title: 'Status', key: 'status' },
]

function formatDate(d?: string): string {
  if (!d) return '—'
  return dayjs(d).format('DD.MM.YYYY')
}
function formatMoney(val?: number): string {
  if (!val) return '—'
  return new Intl.NumberFormat('uz-UZ').format(val) + " so'm"
}
function tripStatusColor(s: string): string {
  const map: Record<string, string> = { pending: 'warning', approved: 'success', rejected: 'error', completed: 'info' }
  return map[s] ?? 'grey'
}
function tripStatusLabel(s: string): string {
  const map: Record<string, string> = { pending: 'Kutilmoqda', approved: 'Tasdiqlandi', rejected: 'Rad etildi', completed: 'Yakunlandi' }
  return map[s] ?? s
}

async function fetchTrips() {
  tripsLoading.value = true
  try {
    const res = await api.get('/reports/business-trips', {
      params: {
        organization_id: tripsOrg.value ?? undefined,
        date_from: tripsDateFrom.value,
        date_to: tripsDateTo.value,
      },
    })
    trips.value = res.data.data ?? res.data
  } catch {
    showSnack("Ma'lumotlarni yuklashda xatolik", 'error')
  } finally {
    tripsLoading.value = false
  }
}

// --- Summary ---
const summaryMonth = ref(dayjs().month() + 1)
const summaryYear = ref(dayjs().year())
const summary = ref<any[]>([])
const summaryLoading = ref(false)

const summaryHeaders = [
  { title: 'Tashkilot', key: 'organization', sortable: false },
  { title: 'Jami xodim', key: 'total_employees' },
  { title: 'Keldi', key: 'present_count' },
  { title: 'Kelmadi', key: 'absent_count' },
  { title: 'Xizmat safari', key: 'business_trip_count' },
  { title: 'Davomat %', key: 'present_pct', sortable: false },
]

async function fetchSummary() {
  summaryLoading.value = true
  try {
    const res = await api.get('/reports/summary', {
      params: { year: summaryYear.value, month: summaryMonth.value },
    })
    summary.value = res.data.data ?? res.data
  } catch {
    showSnack("Ma'lumotlarni yuklashda xatolik", 'error')
  } finally {
    summaryLoading.value = false
  }
}

watch([summaryYear, summaryMonth], fetchSummary)

onMounted(async () => {
  if (!authStore.user) await authStore.fetchMe()
  if (authStore.isOrgAdmin) {
    tabelOrg.value = authStore.user?.organization_id ?? null
    tripsOrg.value = authStore.user?.organization_id ?? null
    await loadTabelDepts(tabelOrg.value)
  } else {
    const orgsRes = await api.get('/organizations')
    organizations.value = orgsRes.data
  }
  await fetchTabel()
})

watch(activeTab, (tab) => {
  if (tab === 'trips' && trips.value.length === 0) fetchTrips()
  if (tab === 'summary' && summary.value.length === 0) fetchSummary()
})
</script>

<style scoped>
.tabel-wrapper {
  overflow-x: auto;
  max-height: 65vh;
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
.status-present { background: #E8F5E9 !important; color: #1B5E20; }
.status-late { background: #FFF8E1 !important; color: #F57F17; }
.status-absent { background: #FFEBEE !important; color: #B71C1C; }
.status-business_trip { background: #E3F2FD !important; color: #0D47A1; }
.status-half_day { background: #FFF3E0 !important; color: #E65100; }
</style>
