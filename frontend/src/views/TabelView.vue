<template>
  <div>
    <div class="text-h5 font-weight-bold mb-4">Oylik Tabel</div>

    <!-- Filter bar -->
    <v-card rounded="xl" elevation="0" border class="mb-4">
      <v-card-text class="pa-4">
        <div class="d-flex align-center gap-3 flex-wrap">
          <v-select
            v-if="!authStore.isOrgAdmin"
            v-model="selectedOrg"
            :items="organizations"
            item-title="name"
            item-value="id"
            label="Tashkilot *"
            variant="outlined"
            density="compact"
            hide-details
            style="min-width:200px"
            @update:model-value="onOrgChange"
          />
          <v-select
            v-model="selectedDept"
            :items="[{ name: 'Barcha bo\'limlar', value: null }, ...deptItems]"
            item-title="name"
            item-value="value"
            label="Bo'lim"
            variant="outlined"
            density="compact"
            hide-details
            clearable
            style="min-width:180px"
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
            style="min-width:140px"
          />
          <v-select
            v-model="selectedYear"
            :items="yearItems"
            label="Yil"
            variant="outlined"
            density="compact"
            hide-details
            style="min-width:100px"
          />
          <v-btn color="primary" variant="outlined" prepend-icon="mdi-refresh" @click="load">
            Yangilash
          </v-btn>
          <v-spacer />
          <v-btn
            color="secondary"
            variant="outlined"
            prepend-icon="mdi-calculator"
            :loading="calculating"
            :disabled="!currentOrgId"
            @click="calculate"
          >
            Hisoblash
          </v-btn>
          <v-btn
            color="primary"
            prepend-icon="mdi-plus"
            :disabled="!currentOrgId"
            @click="bulkDialog = true"
          >
            Ko'p kiritish
          </v-btn>
        </div>
      </v-card-text>
    </v-card>

    <!-- Legend -->
    <v-card rounded="xl" elevation="0" border class="mb-4">
      <v-card-text class="pa-3">
        <div class="d-flex flex-wrap gap-2 align-center">
          <span class="text-caption text-medium-emphasis mr-1">Belgilar:</span>
          <span
            v-for="(cfg, code) in CODE_COLORS"
            :key="code"
            class="text-caption font-weight-bold px-2 py-1 rounded"
            :style="`background:${cfg.bg};color:${cfg.text};border:1px solid ${cfg.border}`"
          >{{ code }}</span>
          <span class="text-caption px-2 py-1 rounded" style="background:#E3F2FD;color:#1565C0;border:1px solid #90CAF9">8.0 — qurilma</span>
          <span class="text-caption px-2 py-1 rounded" style="background:#FFF9C4;border:1px solid #F9A825">bayram</span>
          <span class="text-caption px-2 py-1 rounded" style="background:#F5F5F5;border:1px solid #BDBDBD">dam olish</span>
        </div>
      </v-card-text>
    </v-card>

    <!-- Tabel grid -->
    <v-card rounded="xl" elevation="0" border>
      <div v-if="store.loading" class="text-center py-12">
        <v-progress-circular indeterminate color="primary" size="48" />
      </div>
      <div v-else-if="!currentOrgId" class="text-center text-medium-emphasis py-12">
        Tashkilotni tanlang
      </div>
      <div v-else-if="store.isEmpty" class="text-center text-medium-emphasis py-12">
        Ma'lumot topilmadi
      </div>
      <div v-else class="tabel-wrap">
        <table class="tabel-table">
          <thead>
            <tr>
              <th class="th sticky-0 text-center" style="min-width:36px">#</th>
              <th class="th sticky-1" style="min-width:190px">Xodim</th>
              <!-- Day headers -->
              <th
                v-for="d in store.daysInMonth"
                :key="d"
                class="th day-th"
                :class="{ 'holiday-col': isHoliday(d), 'weekend-col': isWeekend(d) }"
              >
                <div>{{ d }}</div>
                <div style="font-size:9px;font-weight:normal">{{ dayName(d) }}</div>
              </th>
              <!-- Summary headers: absent -->
              <th class="th sum-th" colspan="7">ISHDA YO'Q (kun)</th>
              <!-- Summary headers: worked -->
              <th class="th sum-th" colspan="5">ISHLANGAN (soat)</th>
              <th class="th sum-th" style="min-width:50px">Ish kuni</th>
            </tr>
            <tr>
              <th class="th sticky-0" />
              <th class="th sticky-1" />
              <th v-for="d in store.daysInMonth" :key="d" class="th day-th"
                  :class="{ 'holiday-col': isHoliday(d), 'weekend-col': isWeekend(d) }" />
              <th v-for="code in ABSENT_CODES" :key="code" class="th sum-code-th"
                  :style="`color:${CODE_COLORS[code].text}`">{{ code }}</th>
              <th v-for="code in WORKED_CODES" :key="code" class="th sum-code-th"
                  :style="`color:${CODE_COLORS[code].text}`">{{ code }}</th>
              <th class="th" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in filteredRows" :key="row.employee.id" class="tabel-tr">
              <td class="td sticky-0 text-center text-caption">{{ idx + 1 }}</td>
              <td class="td sticky-1">
                <div class="text-body-2 font-weight-medium" style="white-space:nowrap">{{ row.employee.full_name }}</div>
                <div class="text-caption text-medium-emphasis" style="white-space:nowrap">{{ row.employee.position }}</div>
              </td>
              <!-- Day cells -->
              <td
                v-for="d in store.daysInMonth"
                :key="d"
                class="td day-td"
                :class="{
                  'holiday-col': isHoliday(d),
                  'weekend-col': isWeekend(d) && !row.cells[d]?.entry && !row.cells[d]?.device,
                  'empty-workday': !isHoliday(d) && !isWeekend(d) && !row.cells[d]?.entry && !row.cells[d]?.device,
                }"
                :style="row.cells[d]?.entry ? `background:${CODE_COLORS[row.cells[d].entry.code]?.bg};` : ''"
                @click="openCell(row, d)"
              >
                <v-tooltip location="top">
                  <template #activator="{ props: tooltipProps }">
                    <span v-bind="tooltipProps" class="cell-content">
                      <template v-if="row.cells[d]?.entry">
                        <span :style="`color:${CODE_COLORS[row.cells[d].entry.code]?.text};font-weight:700`">
                          {{ row.cells[d].entry.code }}
                        </span>
                        <v-icon
                          v-if="row.cells[d].entry.source !== 'manual'"
                          size="9"
                          style="vertical-align:super;opacity:0.7"
                        >mdi-lock</v-icon>
                      </template>
                      <template v-else-if="row.cells[d]?.device?.hours">
                        <span style="color:#1565C0;font-size:10px">{{ row.cells[d].device.hours }}</span>
                      </template>
                    </span>
                  </template>
                  <div v-if="row.cells[d]?.entry">
                    {{ CODE_LABELS[row.cells[d].entry.code] }}
                    <span v-if="row.cells[d].entry.days"> · {{ row.cells[d].entry.days }} kun</span>
                    <span v-if="row.cells[d].entry.hours"> · {{ row.cells[d].entry.hours }} soat</span>
                    <div v-if="row.cells[d].entry.source !== 'manual'" class="text-caption" style="opacity:0.8">
                      🔒 Avtomatik
                    </div>
                    <div v-if="row.cells[d].entry.note" class="text-caption">{{ row.cells[d].entry.note }}</div>
                  </div>
                  <div v-else-if="row.cells[d]?.device">
                    Qurilma: {{ row.cells[d].device.first_entry ?? '—' }} → {{ row.cells[d].device.last_exit ?? '—' }}
                    ({{ row.cells[d].device.hours }} soat)
                  </div>
                  <span v-else>{{ d }}-kun — bosing, kiritish uchun</span>
                </v-tooltip>
              </td>
              <!-- Absent summary -->
              <td v-for="code in ABSENT_CODES" :key="code" class="td sum-td text-center">
                <span v-if="row.summary[code]" :style="`color:${CODE_COLORS[code].text};font-weight:600`">
                  {{ fmt(row.summary[code]) }}
                </span>
              </td>
              <!-- Worked summary -->
              <td v-for="code in WORKED_CODES" :key="code" class="td sum-td text-center">
                <span v-if="row.summary[code]" :style="`color:${CODE_COLORS[code].text};font-weight:600`">
                  {{ fmt(row.summary[code]) }}
                </span>
              </td>
              <td class="td sum-td text-center font-weight-bold">{{ row.summary['work_days'] || '' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </v-card>

    <!-- Cell dialog -->
    <AttendanceCellDialog
      v-model="cellDialog"
      :employee="activeRow?.employee ?? null"
      :date="activeDate"
      :day="activeDay"
      :cell="activeCell"
      @saved="load"
    />

    <!-- Bulk dialog -->
    <BulkEntryDialog
      v-model="bulkDialog"
      :employees="employeeItems"
      :holidays="store.holidays"
      :year="selectedYear"
      :month="selectedMonth"
      @saved="load"
    />

    <v-snackbar v-model="snack.show" :color="snack.color" timeout="3000">
      {{ snack.text }}
    </v-snackbar>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import dayjs from 'dayjs'
import 'dayjs/locale/uz'
import api from '@/api/axios'
import { useAuthStore } from '@/stores/auth'
import { useTabelStore, CODE_LABELS, CODE_COLORS, ABSENT_CODES, WORKED_CODES, type TabelRow, type TabelCell } from '@/stores/tabel'
import AttendanceCellDialog from '@/components/attendance/AttendanceCellDialog.vue'
import BulkEntryDialog from '@/components/attendance/BulkEntryDialog.vue'

dayjs.locale('uz')

const authStore = useAuthStore()
const store = useTabelStore()

const organizations = ref<any[]>([])
const selectedOrg = ref<number | null>(null)
const selectedDept = ref<string | null>(null)
const deptItems = ref<{ name: string; value: string }[]>([])
const selectedYear = ref(dayjs().year())
const selectedMonth = ref(dayjs().month() + 1)
const calculating = ref(false)

const cellDialog = ref(false)
const bulkDialog = ref(false)
const activeRow = ref<TabelRow | null>(null)
const activeDate = ref('')
const activeDay = ref(0)
const activeCell = ref<TabelCell | null>(null)

const snack = ref({ show: false, text: '', color: 'success' })

const currentOrgId = computed(() =>
  authStore.isOrgAdmin ? (authStore.user?.organization_id ?? null) : selectedOrg.value
)

const filteredRows = computed(() => store.rows)

const employeeItems = computed(() =>
  store.rows.map(r => ({
    id: r.employee.id,
    label: r.employee.full_name,
  }))
)

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

function isWeekend(day: number): boolean {
  const d = dayjs(`${selectedYear.value}-${String(selectedMonth.value).padStart(2, '0')}-${String(day).padStart(2, '0')}`)
  return d.day() === 0 || d.day() === 6
}
function isHoliday(day: number): boolean {
  return store.holidays.includes(day)
}
function dayName(day: number): string {
  const names = ['Ya', 'Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh']
  const d = dayjs(`${selectedYear.value}-${String(selectedMonth.value).padStart(2, '0')}-${String(day).padStart(2, '0')}`)
  return names[d.day()] ?? ''
}
function fmt(val: number): string {
  return val % 1 === 0 ? String(val) : val.toFixed(1)
}

async function onOrgChange(orgId: number | null) {
  selectedDept.value = null
  deptItems.value = []
  if (!orgId) return
  const res = await api.get('/departments', { params: { organization_id: orgId } })
  const depts = res.data.data ?? res.data
  deptItems.value = depts.map((d: any) => ({ name: d.name, value: d.name }))
}

async function load() {
  const orgId = currentOrgId.value
  if (!orgId) return
  await store.loadTabel(orgId, selectedYear.value, selectedMonth.value, selectedDept.value)
}

async function calculate() {
  const orgId = currentOrgId.value
  if (!orgId) return
  calculating.value = true
  try {
    await api.post('/attendance/calculate-monthly', {
      organization_id: orgId,
      year: selectedYear.value,
      month: selectedMonth.value,
    })
    snack.value = { show: true, text: 'Tabel hisoblandi', color: 'success' }
    await load()
  } catch {
    snack.value = { show: true, text: 'Hisoblashda xatolik', color: 'error' }
  } finally {
    calculating.value = false
  }
}

function openCell(row: TabelRow, day: number) {
  const dateStr = `${selectedYear.value}-${String(selectedMonth.value).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  activeRow.value = row
  activeDate.value = dateStr
  activeDay.value = day
  activeCell.value = row.cells[day] ?? null
  cellDialog.value = true
}

onMounted(async () => {
  if (!authStore.user) await authStore.fetchMe()
  if (authStore.isOrgAdmin) {
    const orgId = authStore.user?.organization_id
    if (orgId) {
      await onOrgChange(orgId)
      await load()
    }
  } else {
    const res = await api.get('/organizations')
    organizations.value = res.data
  }
})
</script>

<style scoped>
.tabel-wrap {
  overflow-x: auto;
  overflow-y: auto;
  max-height: 70vh;
}
.tabel-table {
  border-collapse: collapse;
  font-size: 12px;
  min-width: 100%;
}
.th {
  background: #F5F7FA;
  padding: 6px 3px;
  text-align: center;
  border: 1px solid #E0E0E0;
  font-weight: 600;
  position: sticky;
  top: 0;
  z-index: 4;
  white-space: nowrap;
}
.td {
  padding: 2px 3px;
  border: 1px solid #E0E0E0;
  font-size: 11px;
  vertical-align: middle;
}
.tabel-tr:hover .td { background: #F9FAFB; }
.sticky-0 { position: sticky; left: 0; z-index: 3; background: white; min-width: 36px; }
.sticky-1 { position: sticky; left: 36px; z-index: 3; background: white; min-width: 190px; max-width: 190px; }
.th.sticky-0, .th.sticky-1 { z-index: 5; }
.day-th { min-width: 34px; width: 34px; max-width: 34px; }
.day-td { width: 34px; max-width: 34px; height: 30px; cursor: pointer; text-align: center; }
.day-td:hover { filter: brightness(0.93); }
.sum-th { min-width: 42px; background: #EEF2F7; }
.sum-code-th { min-width: 38px; background: #EEF2F7; font-size: 11px; }
.sum-td { min-width: 38px; text-align: center; font-size: 11px; }
.weekend-col { background: #F5F5F5 !important; }
.holiday-col { background: #FFF9C4 !important; }
.empty-workday { background: rgba(255,82,82,0.07) !important; }
.cell-content { display: inline-block; width: 100%; text-align: center; }
</style>
