<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6 flex-wrap gap-3">
      <div class="text-h5 font-weight-bold">Hikvision Qurilmalar</div>
      <div class="d-flex gap-3">
        <v-btn
          color="primary"
          variant="outlined"
          prepend-icon="mdi-sync"
          :loading="syncingAll"
          @click="syncAll"
        >
          Barchasini sinxronlash
        </v-btn>
        <v-btn color="primary" prepend-icon="mdi-plus" @click="openAddDialog">
          Qurilma qo'shish
        </v-btn>
      </div>
    </div>

    <v-row v-if="loading">
      <v-col v-for="i in 6" :key="i" cols="12" md="6" lg="4">
        <v-skeleton-loader type="card" rounded="xl" />
      </v-col>
    </v-row>

    <v-row v-else>
      <v-col v-for="device in devices" :key="device.id" cols="12" md="6" lg="4">
        <v-card rounded="xl" elevation="0" border hover>
          <v-card-text class="pa-5">
            <div class="d-flex align-center justify-space-between mb-3">
              <div>
                <div class="text-subtitle-2 font-weight-bold">{{ device.name }}</div>
                <div class="text-caption text-medium-emphasis">{{ device.organization?.name }}</div>
              </div>
              <v-chip
                :color="statusColor(device.status)"
                size="small"
                variant="tonal"
                :prepend-icon="statusIcon(device.status)"
              >
                {{ statusLabel(device.status) }}
              </v-chip>
            </div>

            <v-divider class="mb-3" />

            <div class="d-flex flex-column gap-1 mb-4">
              <div class="d-flex justify-space-between text-caption">
                <span class="text-medium-emphasis">IP manzil:</span>
                <span class="font-weight-medium">{{ device.ip_address }}:{{ device.port }}</span>
              </div>
              <div class="d-flex justify-space-between text-caption">
                <span class="text-medium-emphasis">Oxirgi sinx:</span>
                <span class="font-weight-medium">{{ formatTime(device.last_sync_at) }}</span>
              </div>
              <div class="d-flex justify-space-between text-caption">
                <span class="text-medium-emphasis">Oxirgi ko'rinish:</span>
                <span class="font-weight-medium">{{ formatTime(device.last_seen_at) }}</span>
              </div>
              <div v-if="device.serial_number" class="d-flex justify-space-between text-caption">
                <span class="text-medium-emphasis">Serial:</span>
                <span class="font-weight-medium">{{ device.serial_number }}</span>
              </div>
            </div>

            <div class="d-flex gap-2 flex-wrap align-center">
              <v-btn
                size="small"
                variant="outlined"
                color="primary"
                prepend-icon="mdi-connection"
                :loading="testingDevice === device.id"
                @click="testDevice(device)"
              >
                Test ulanish
              </v-btn>
              <v-btn
                size="small"
                color="primary"
                prepend-icon="mdi-sync"
                :loading="syncDialog && activeSyncDevice?.id === device.id && !syncDone"
                @click="syncDevice(device)"
              >
                Sinxronlash
              </v-btn>
              <v-btn
                size="small"
                variant="outlined"
                color="warning"
                prepend-icon="mdi-account-check"
                :loading="reconcilingDevice === device.id"
                @click="reconcileDevice(device)"
              >
                Tekshirish
              </v-btn>
              <v-spacer />
              <v-btn
                size="small"
                variant="text"
                color="secondary"
                icon="mdi-pencil"
                @click="openEditDialog(device)"
              />
              <v-btn
                size="small"
                variant="text"
                color="error"
                icon="mdi-delete"
                @click="confirmDelete(device)"
              />
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col v-if="devices.length === 0" cols="12">
        <v-card rounded="xl" elevation="0" border>
          <v-card-text class="pa-12 text-center text-medium-emphasis">
            <v-icon size="64" class="mb-4">mdi-devices</v-icon>
            <div>Qurilmalar topilmadi</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Add/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="560" persistent>
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">
          {{ editingDevice ? 'Qurilmani tahrirlash' : "Qurilma qo'shish" }}
        </v-card-title>
        <v-card-text class="pa-6 pt-2">
          <v-form ref="formRef" @submit.prevent="saveDevice">
            <v-row dense>
              <v-col cols="12">
                <v-text-field
                  v-model="form.name"
                  label="Qurilma nomi *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="12">
                <v-select
                  v-model="form.organization_id"
                  :items="organizations"
                  item-title="name"
                  item-value="id"
                  label="Tashkilot *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="12" sm="8">
                <v-text-field
                  v-model="form.ip_address"
                  label="IP manzil *"
                  variant="outlined"
                  density="compact"
                  placeholder="192.168.1.100"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model.number="form.port"
                  label="Port"
                  variant="outlined"
                  density="compact"
                  type="number"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.username"
                  label="Foydalanuvchi nomi"
                  variant="outlined"
                  density="compact"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.password"
                  label="Parol"
                  variant="outlined"
                  density="compact"
                  :type="showPass ? 'text' : 'password'"
                  :append-inner-icon="showPass ? 'mdi-eye-off' : 'mdi-eye'"
                  @click:append-inner="showPass = !showPass"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="form.serial_number"
                  label="Seriya raqami"
                  variant="outlined"
                  density="compact"
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Bekor qilish</v-btn>
          <v-btn color="primary" :loading="saving" @click="saveDevice">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Sync Progress Dialog -->
    <v-dialog v-model="syncDialog" max-width="480" persistent>
      <v-card rounded="xl">
        <v-card-text class="pa-8">
          <div class="text-center mb-6">
            <v-icon size="48" color="primary" class="mb-3">mdi-sync</v-icon>
            <div class="text-h6 font-weight-bold mb-1">Sinxronizatsiya</div>
            <div class="text-caption text-medium-emphasis">{{ activeSyncDevice?.name }} · {{ activeSyncDevice?.ip_address }}</div>
          </div>

          <div v-if="!syncDone">
            <div class="d-flex justify-space-between text-caption mb-2">
              <span>Qurilmadan ma'lumot olinmoqda...</span>
              <span class="font-weight-bold">{{ syncPercent }}%</span>
            </div>
            <v-progress-linear
              :model-value="syncPercent"
              color="primary"
              height="8"
              rounded
              striped
            />
            <div class="text-caption text-medium-emphasis text-center mt-3">
              Iltimos kuting, bu bir necha soniya olishi mumkin
            </div>
          </div>

          <div v-else class="text-center">
            <v-icon size="48" :color="syncError ? 'error' : 'success'" class="mb-3">
              {{ syncError ? 'mdi-alert-circle' : 'mdi-check-circle' }}
            </v-icon>
            <div v-if="!syncError">
              <div class="text-body-1 font-weight-bold mb-2 text-success">Muvaffaqiyatli yakunlandi!</div>
              <div class="d-flex justify-center gap-4 mt-3">
                <div class="text-center">
                  <div class="text-h6 font-weight-bold text-primary">{{ syncResult.records_new }}</div>
                  <div class="text-caption text-medium-emphasis">Yangi</div>
                </div>
                <v-divider vertical />
                <div class="text-center">
                  <div class="text-h6 font-weight-bold text-warning">{{ syncResult.records_duplicate }}</div>
                  <div class="text-caption text-medium-emphasis">Takror</div>
                </div>
              </div>
            </div>
            <div v-else class="text-error text-body-2">{{ syncError }}</div>
            <v-btn class="mt-5" color="primary" variant="tonal" block @click="syncDialog = false">Yopish</v-btn>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- Delete Confirm Dialog -->
    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">Qurilmani o'chirish</v-card-title>
        <v-card-text class="pa-6 pt-0">
          <strong>{{ deletingDevice?.name }}</strong> qurilmasini o'chirishni tasdiqlaysizmi?
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="deleteDialog = false">Bekor qilish</v-btn>
          <v-btn color="error" :loading="deleting" @click="deleteDevice">O'chirish</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Snackbar -->
    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000">
      {{ snackbar.text }}
    </v-snackbar>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/api/axios'
import dayjs from 'dayjs'

interface Device {
  id: number
  name: string
  ip_address: string
  port: number
  username?: string
  status: string
  serial_number?: string
  last_sync_at?: string | null
  last_seen_at?: string | null
  organization?: { id: number; name: string }
}

// Sync dialog state
const syncDialog = ref(false)
const activeSyncDevice = ref<Device | null>(null)
const syncPercent = ref(0)
const syncDone = ref(false)
const syncError = ref('')
const syncResult = ref({ records_new: 0, records_duplicate: 0 })
let syncTimer: ReturnType<typeof setInterval> | null = null

const devices = ref<Device[]>([])
const organizations = ref<any[]>([])
const loading = ref(true)
const syncingAll = ref(false)
const testingDevice = ref<number | null>(null)
const syncingDevice = ref<number | null>(null)
const reconcilingDevice = ref<number | null>(null)
const saving = ref(false)
const deleting = ref(false)
const showPass = ref(false)

const dialog = ref(false)
const deleteDialog = ref(false)
const editingDevice = ref<Device | null>(null)
const deletingDevice = ref<Device | null>(null)
const formRef = ref<any>(null)

const defaultForm = () => ({
  name: '',
  organization_id: null as number | null,
  ip_address: '',
  port: 80,
  username: 'admin',
  password: '',
  serial_number: '',
})
const form = ref(defaultForm())

const snackbar = ref({ show: false, text: '', color: 'success' })

function showSnack(text: string, color = 'success') {
  snackbar.value = { show: true, text, color }
}

function statusColor(status: string): string {
  const map: Record<string, string> = { online: 'success', offline: 'error', unknown: 'warning' }
  return map[status] ?? 'grey'
}
function statusIcon(status: string): string {
  const map: Record<string, string> = { online: 'mdi-check-circle', offline: 'mdi-close-circle', unknown: 'mdi-help-circle' }
  return map[status] ?? 'mdi-help-circle'
}
function statusLabel(status: string): string {
  const map: Record<string, string> = { online: 'Online', offline: 'Offline', unknown: "Noma'lum" }
  return map[status] ?? status
}
function formatTime(time?: string | null): string {
  if (!time) return "Hali yo'q"
  return dayjs(time).format('DD.MM.YYYY HH:mm')
}

function openAddDialog() {
  editingDevice.value = null
  form.value = defaultForm()
  showPass.value = false
  dialog.value = true
}

function openEditDialog(device: Device) {
  editingDevice.value = device
  form.value = {
    name: device.name,
    organization_id: device.organization?.id ?? null,
    ip_address: device.ip_address,
    port: device.port,
    username: device.username ?? 'admin',
    password: '',
    serial_number: device.serial_number ?? '',
  }
  showPass.value = false
  dialog.value = true
}

function confirmDelete(device: Device) {
  deletingDevice.value = device
  deleteDialog.value = true
}

async function saveDevice() {
  const { valid } = await formRef.value?.validate()
  if (!valid) return
  saving.value = true
  try {
    if (editingDevice.value) {
      const payload: any = { ...form.value }
      if (!payload.password) delete payload.password
      const res = await api.put(`/devices/${editingDevice.value.id}`, payload)
      const idx = devices.value.findIndex(d => d.id === editingDevice.value!.id)
      if (idx !== -1) devices.value[idx] = res.data
      showSnack('Qurilma yangilandi')
    } else {
      const res = await api.post('/devices', form.value)
      devices.value.push(res.data)
      showSnack("Qurilma qo'shildi")
    }
    dialog.value = false
  } catch (e: any) {
    showSnack(e?.response?.data?.message ?? 'Xatolik yuz berdi', 'error')
  } finally {
    saving.value = false
  }
}

async function deleteDevice() {
  if (!deletingDevice.value) return
  deleting.value = true
  try {
    await api.delete(`/devices/${deletingDevice.value.id}`)
    devices.value = devices.value.filter(d => d.id !== deletingDevice.value!.id)
    showSnack("Qurilma o'chirildi")
    deleteDialog.value = false
  } catch {
    showSnack("O'chirishda xatolik", 'error')
  } finally {
    deleting.value = false
  }
}

async function testDevice(device: Device) {
  testingDevice.value = device.id
  try {
    const res = await api.post(`/devices/${device.id}/test`)
    const d = devices.value.find(x => x.id === device.id)
    if (d) d.status = res.data.success ? 'online' : 'offline'
    showSnack(res.data.message ?? (res.data.success ? 'Ulanish muvaffaqiyatli' : 'Ulanish amalga oshmadi'), res.data.success ? 'success' : 'error')
  } catch {
    showSnack('Ulanish xatosi', 'error')
  } finally {
    testingDevice.value = null
  }
}

async function syncDevice(device: Device) {
  // Dialog va state ni tayyorlash
  activeSyncDevice.value = device
  syncPercent.value = 0
  syncDone.value = false
  syncError.value = ''
  syncResult.value = { records_new: 0, records_duplicate: 0 }
  syncDialog.value = true

  // Progress animatsiyasi — 0%→85% ga API javob kelguncha, 85%→99% sekinlashadi
  let step = 2
  syncTimer = setInterval(() => {
    if (syncPercent.value < 70) {
      syncPercent.value = Math.min(syncPercent.value + step, 70)
    } else if (syncPercent.value < 85) {
      syncPercent.value = Math.min(syncPercent.value + 0.5, 85)
    }
  }, 300)

  try {
    const res = await api.post(`/devices/${device.id}/sync`)
    syncPercent.value = 100
    syncResult.value = {
      records_new: res.data.records_new ?? 0,
      records_duplicate: res.data.records_duplicate ?? 0,
    }
    // Qurilma kartasini yangilash
    const found = devices.value.find(d => d.id === device.id)
    if (found) found.status = 'online'
  } catch (e: any) {
    syncPercent.value = 100
    syncError.value = e?.response?.data?.message ?? 'Sinxronizatsiya xatosi'
  } finally {
    if (syncTimer) { clearInterval(syncTimer); syncTimer = null }
    syncDone.value = true
  }
}

async function reconcileDevice(device: Device) {
  reconcilingDevice.value = device.id
  try {
    const res = await api.post(`/devices/${device.id}/reconcile`)
    showSnack(res.data.message, 'success')
    const d = devices.value.find(x => x.id === device.id)
    if (d) d.status = 'online'
  } catch (e: any) {
    showSnack(e?.response?.data?.message ?? 'Tekshirish xatosi', 'error')
  } finally {
    reconcilingDevice.value = null
  }
}

async function syncAll() {
  syncingAll.value = true
  try {
    await api.post('/sync/all')
    showSnack("Barcha qurilmalar sinxronizatsiyasi navbatga qo'yildi")
  } catch {
    showSnack('Xatolik yuz berdi', 'error')
  } finally {
    syncingAll.value = false
  }
}

onMounted(async () => {
  try {
    const [devRes, orgRes] = await Promise.all([
      api.get('/devices'),
      api.get('/organizations'),
    ])
    devices.value = devRes.data
    organizations.value = orgRes.data
  } finally {
    loading.value = false
  }
})
</script>
