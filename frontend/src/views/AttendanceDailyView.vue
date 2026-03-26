<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6 flex-wrap gap-3">
      <div class="text-h5 font-weight-bold">Kunlik Davomat</div>
      <div class="d-flex align-center gap-3 flex-wrap">
        <v-text-field
          v-model="selectedDate"
          type="date"
          variant="outlined"
          density="compact"
          hide-details
          style="width: 180px"
          @update:model-value="fetchData"
        />
        <v-select
          v-if="!authStore.isOrgAdmin"
          v-model="selectedOrg"
          :items="[{ name: 'Barcha tashkilotlar', id: null }, ...organizations]"
          item-title="name"
          item-value="id"
          label="Tashkilot"
          variant="outlined"
          density="compact"
          clearable
          hide-details
          style="min-width: 200px"
          @update:model-value="fetchData"
        />
        <v-btn color="primary" variant="outlined" prepend-icon="mdi-refresh" @click="fetchData">
          Yangilash
        </v-btn>
      </div>
    </div>

    <!-- Stats chips -->
    <v-row class="mb-4" dense>
      <v-col cols="6" sm="3">
        <v-card rounded="xl" elevation="0" border>
          <v-card-text class="pa-4 text-center">
            <div class="text-h4 font-weight-bold text-success">{{ stats.present ?? 0 }}</div>
            <div class="text-caption text-medium-emphasis mt-1">Keldi</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card rounded="xl" elevation="0" border>
          <v-card-text class="pa-4 text-center">
            <div class="text-h4 font-weight-bold text-warning">{{ stats.late ?? 0 }}</div>
            <div class="text-caption text-medium-emphasis mt-1">Kech keldi</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card rounded="xl" elevation="0" border>
          <v-card-text class="pa-4 text-center">
            <div class="text-h4 font-weight-bold text-error">{{ stats.absent ?? 0 }}</div>
            <div class="text-caption text-medium-emphasis mt-1">Kelmadi</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card rounded="xl" elevation="0" border>
          <v-card-text class="pa-4 text-center">
            <div class="text-h4 font-weight-bold text-info">{{ stats.business_trip ?? 0 }}</div>
            <div class="text-caption text-medium-emphasis mt-1">Xizmat safari</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Data Table -->
    <v-card rounded="xl" elevation="0" border>
      <v-data-table
        :headers="headers"
        :items="records"
        :loading="loading"
        density="comfortable"
        hover
      >
        <template #item.employee="{ item }">
          <div class="py-1">
            <div class="text-body-2 font-weight-medium">
              {{ item.employee?.last_name }} {{ item.employee?.first_name }}
            </div>
            <div class="text-caption text-medium-emphasis">{{ item.employee?.position }}</div>
          </div>
        </template>

        <template #item.organization="{ item }">
          <v-chip size="small" variant="tonal" color="primary">
            {{ item.organization?.code ?? item.employee?.organization?.code ?? '—' }}
          </v-chip>
        </template>

        <template #item.status="{ item }">
          <v-chip :color="statusColor(item.status)" size="small" variant="tonal">
            {{ statusLabel(item.status) }}
          </v-chip>
        </template>

        <template #item.first_entry="{ item }">
          <span class="text-body-2">{{ item.first_entry ?? '—' }}</span>
        </template>

        <template #item.last_exit="{ item }">
          <span class="text-body-2">{{ item.last_exit ?? '—' }}</span>
        </template>

        <template #item.work_minutes="{ item }">
          <span class="text-body-2">{{ item.work_minutes ? formatHours(item.work_minutes) : '—' }}</span>
        </template>

        <template #item.actions="{ item }">
          <v-btn icon="mdi-pencil" variant="text" size="small" color="secondary" @click="openEditDialog(item)" />
        </template>
      </v-data-table>
    </v-card>

    <!-- Edit Dialog -->
    <v-dialog v-model="editDialog" max-width="420" persistent>
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">Davomatni tahrirlash</v-card-title>
        <v-card-text class="pa-6 pt-2">
          <div class="text-body-2 font-weight-medium mb-4">
            {{ editingRecord?.employee?.last_name }} {{ editingRecord?.employee?.first_name }}
          </div>
          <v-row dense>
            <v-col cols="12">
              <v-select
                v-model="editForm.status"
                :items="statusItems"
                item-title="label"
                item-value="value"
                label="Holat"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="12">
              <v-text-field
                v-model="editForm.note"
                label="Izoh"
                variant="outlined"
                density="compact"
              />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="editDialog = false">Bekor qilish</v-btn>
          <v-btn color="primary" :loading="saving" @click="updateRecord">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000">
      {{ snackbar.text }}
    </v-snackbar>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/api/axios'
import { useAuthStore } from '@/stores/auth'
import dayjs from 'dayjs'

const authStore = useAuthStore()

const selectedDate = ref(dayjs().format('YYYY-MM-DD'))
const selectedOrg = ref<number | null>(null)
const organizations = ref<any[]>([])
const records = ref<any[]>([])
const loading = ref(false)
const saving = ref(false)

const stats = ref({ present: 0, late: 0, absent: 0, business_trip: 0 })

const editDialog = ref(false)
const editingRecord = ref<any>(null)
const editForm = ref({ status: '', note: '' })

const snackbar = ref({ show: false, text: '', color: 'success' })

const statusItems = [
  { label: 'Keldi', value: 'present' },
  { label: 'Kech keldi', value: 'late' },
  { label: 'Kelmadi', value: 'absent' },
  { label: 'Xizmat safari', value: 'business_trip' },
  { label: "Ta'til", value: 'leave' },
  { label: 'Yarim kun', value: 'half_day' },
]

const headers = [
  { title: 'Xodim', key: 'employee', sortable: false },
  { title: 'Tashkilot', key: 'organization', sortable: false },
  { title: 'Holat', key: 'status' },
  { title: 'Kirim', key: 'first_entry', sortable: false },
  { title: 'Chiqim', key: 'last_exit', sortable: false },
  { title: 'Ish soati', key: 'work_minutes', sortable: false },
  { title: 'Izoh', key: 'note', sortable: false },
  { title: '', key: 'actions', sortable: false, width: '60px' },
]

function statusColor(status: string): string {
  const map: Record<string, string> = {
    present: 'success',
    late: 'warning',
    absent: 'error',
    business_trip: 'info',
    leave: 'secondary',
    half_day: 'orange',
  }
  return map[status] ?? 'grey'
}

function statusLabel(status: string): string {
  const map: Record<string, string> = {
    present: 'Keldi',
    late: 'Kech keldi',
    absent: 'Kelmadi',
    business_trip: 'Xizmat safari',
    leave: "Ta'til",
    half_day: 'Yarim kun',
  }
  return map[status] ?? status
}

function formatHours(minutes: number): string {
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return `${h}:${String(m).padStart(2, '0')}`
}

function showSnack(text: string, color = 'success') {
  snackbar.value = { show: true, text, color }
}

async function fetchData() {
  loading.value = true
  try {
    const res = await api.get('/attendance/daily', {
      params: {
        date: selectedDate.value,
        organization_id: selectedOrg.value ?? undefined,
      },
    })
    records.value = res.data.records ?? res.data
    const s = res.data.stats
    if (s) {
      stats.value = {
        present: (s.present ?? 0) + (s.late ?? 0),
        late: s.late ?? 0,
        absent: s.absent ?? 0,
        business_trip: s.business_trip ?? 0,
      }
    } else {
      const arr = records.value
      stats.value = {
        present: arr.filter((r: any) => r.status === 'present').length,
        late: arr.filter((r: any) => r.status === 'late').length,
        absent: arr.filter((r: any) => r.status === 'absent').length,
        business_trip: arr.filter((r: any) => r.status === 'business_trip').length,
      }
    }
  } catch {
    showSnack('Ma\'lumotlarni yuklashda xatolik', 'error')
  } finally {
    loading.value = false
  }
}

function openEditDialog(record: any) {
  editingRecord.value = record
  editForm.value = {
    status: record.status ?? '',
    note: record.note ?? '',
  }
  editDialog.value = true
}

async function updateRecord() {
  if (!editingRecord.value) return
  saving.value = true
  try {
    const res = await api.put(`/attendance/${editingRecord.value.id}`, editForm.value)
    const idx = records.value.findIndex((r: any) => r.id === editingRecord.value.id)
    if (idx !== -1) records.value[idx] = { ...records.value[idx], ...res.data }
    showSnack('Davomat yangilandi')
    editDialog.value = false
    await fetchData()
  } catch {
    showSnack('Yangilashda xatolik', 'error')
  } finally {
    saving.value = false
  }
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
