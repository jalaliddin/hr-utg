<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div class="text-h5 font-weight-bold">Tashkilotlar ({{ organizations.length }} ta)</div>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openAddDialog">
        Tashkilot qo'shish
      </v-btn>
    </div>

    <v-row v-if="loading">
      <v-col v-for="i in 8" :key="i" cols="12" sm="6" md="4" lg="3">
        <v-skeleton-loader type="card" rounded="xl" />
      </v-col>
    </v-row>

    <v-row v-else>
      <v-col
        v-for="org in organizations"
        :key="org.id"
        cols="12" sm="6" md="4" lg="3"
      >
        <v-card rounded="xl" elevation="0" border hover class="pa-1">
          <v-card-text class="pa-4">
            <div class="d-flex align-center justify-space-between mb-3">
              <v-chip :color="org.type === 'head' ? 'primary' : 'secondary'" size="small" variant="tonal">
                {{ org.code }}
              </v-chip>
              <div class="d-flex align-center gap-1">
                <v-icon :color="org.is_active ? 'success' : 'error'" size="16">
                  {{ org.is_active ? 'mdi-check-circle' : 'mdi-close-circle' }}
                </v-icon>
                <v-btn size="x-small" variant="text" icon="mdi-pencil" color="secondary" @click="openEditDialog(org)" />
                <v-btn size="x-small" variant="text" icon="mdi-delete" color="error" @click="confirmDelete(org)" />
              </div>
            </div>

            <div class="text-subtitle-2 font-weight-bold mb-1">{{ org.name }}</div>
            <div class="text-caption text-medium-emphasis mb-3" style="min-height: 32px">{{ org.address }}</div>

            <v-divider class="mb-3" />

            <div class="d-flex justify-space-between text-caption mb-2">
              <span class="text-medium-emphasis">Xodimlar:</span>
              <span class="font-weight-bold">{{ org.employees_count ?? 0 }}</span>
            </div>

            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <v-icon
                  :color="getDeviceStatus(org) === 'online' ? 'success' : getDeviceStatus(org) === 'offline' ? 'error' : 'warning'"
                  size="16"
                  class="mr-1"
                >
                  {{ getDeviceStatus(org) === 'online' ? 'mdi-wifi' : 'mdi-wifi-off' }}
                </v-icon>
                <span class="text-caption">Qurilma</span>
              </div>
              <span class="text-caption text-medium-emphasis">{{ org.phone }}</span>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col v-if="organizations.length === 0" cols="12">
        <v-card rounded="xl" elevation="0" border>
          <v-card-text class="pa-12 text-center text-medium-emphasis">
            <v-icon size="64" class="mb-4">mdi-office-building</v-icon>
            <div>Tashkilotlar topilmadi</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Add/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="500" persistent>
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">
          {{ editingOrg ? 'Tashkilotni tahrirlash' : "Tashkilot qo'shish" }}
        </v-card-title>
        <v-card-text class="pa-6 pt-2">
          <v-form ref="formRef" @submit.prevent="saveOrg">
            <v-row dense>
              <v-col cols="12">
                <v-text-field
                  v-model="form.name"
                  label="Tashkilot nomi *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.code"
                  label="Kod *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-select
                  v-model="form.type"
                  :items="[{ title: 'Bosh tashkilot', value: 'head' }, { title: 'Filial', value: 'branch' }]"
                  item-title="title"
                  item-value="value"
                  label="Turi *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="form.address"
                  label="Manzil"
                  variant="outlined"
                  density="compact"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.phone"
                  label="Telefon"
                  variant="outlined"
                  density="compact"
                  placeholder="+998901234567"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model.number="form.hikvision_group_no"
                  label="Hikvision Group № (1-30)"
                  variant="outlined"
                  density="compact"
                  type="number"
                  :rules="[v => !v || (v >= 1 && v <= 30) || '1-30 oralig\'ida bo\'lishi kerak']"
                  hint="Qurilmada 01→1, 02→2 ... 10→10 ko'rinishida"
                  persistent-hint
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Bekor qilish</v-btn>
          <v-btn color="primary" :loading="saving" @click="saveOrg">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Dialog -->
    <v-dialog v-model="deleteDialog" max-width="420">
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">Tashkilotni o'chirish</v-card-title>
        <v-card-text class="pa-6 pt-0">
          <strong>{{ deletingOrg?.name }}</strong> tashkilotini o'chirishni tasdiqlaysizmi?
          <div v-if="(deletingOrg?.employees_count ?? 0) > 0" class="mt-2">
            <v-alert type="warning" variant="tonal" density="compact">
              Bu tashkilotda {{ deletingOrg?.employees_count }} xodim mavjud. O'chirish mumkin emas.
            </v-alert>
          </div>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="deleteDialog = false">Bekor qilish</v-btn>
          <v-btn
            color="error"
            :loading="deleting"
            :disabled="(deletingOrg?.employees_count ?? 0) > 0"
            @click="deleteOrg"
          >
            O'chirish
          </v-btn>
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

interface Organization {
  id: number
  name: string
  code: string
  type: string
  address?: string
  phone?: string
  is_active: boolean
  employees_count?: number
  devices?: any[]
  hikvision_group_no?: number | null
}

const organizations = ref<Organization[]>([])
const loading = ref(true)
const saving = ref(false)
const deleting = ref(false)

const dialog = ref(false)
const deleteDialog = ref(false)
const editingOrg = ref<Organization | null>(null)
const deletingOrg = ref<Organization | null>(null)
const formRef = ref<any>(null)

const snackbar = ref({ show: false, text: '', color: 'success' })

function showSnack(text: string, color = 'success') {
  snackbar.value = { show: true, text, color }
}

const defaultForm = () => ({
  name: '',
  code: '',
  type: 'branch' as string,
  address: '',
  phone: '',
  hikvision_group_no: null as number | null,
})
const form = ref(defaultForm())

function getDeviceStatus(org: Organization): string {
  const device = org.devices?.[0]
  return device?.status ?? 'unknown'
}

function openAddDialog() {
  editingOrg.value = null
  form.value = defaultForm()
  dialog.value = true
}

function openEditDialog(org: Organization) {
  editingOrg.value = org
  form.value = {
    name: org.name,
    code: org.code,
    type: org.type,
    address: org.address ?? '',
    phone: org.phone ?? '',
    hikvision_group_no: org.hikvision_group_no ?? null,
  }
  dialog.value = true
}

function confirmDelete(org: Organization) {
  deletingOrg.value = org
  deleteDialog.value = true
}

async function saveOrg() {
  const { valid } = await formRef.value?.validate()
  if (!valid) return
  saving.value = true
  try {
    const payload: any = { ...form.value }
    if (!payload.address) payload.address = null
    if (!payload.phone) payload.phone = null
    if (!payload.hikvision_group_no) payload.hikvision_group_no = null

    if (editingOrg.value) {
      const res = await api.put(`/organizations/${editingOrg.value.id}`, payload)
      const idx = organizations.value.findIndex(o => o.id === editingOrg.value!.id)
      if (idx !== -1) organizations.value[idx] = { ...organizations.value[idx], ...res.data }
      showSnack('Tashkilot yangilandi')
    } else {
      const res = await api.post('/organizations', payload)
      organizations.value.push(res.data)
      showSnack("Tashkilot qo'shildi")
    }
    dialog.value = false
  } catch (e: any) {
    showSnack(e?.response?.data?.message ?? 'Xatolik yuz berdi', 'error')
  } finally {
    saving.value = false
  }
}

async function deleteOrg() {
  if (!deletingOrg.value) return
  deleting.value = true
  try {
    await api.delete(`/organizations/${deletingOrg.value.id}`)
    organizations.value = organizations.value.filter(o => o.id !== deletingOrg.value!.id)
    showSnack("Tashkilot o'chirildi")
    deleteDialog.value = false
  } catch (e: any) {
    showSnack(e?.response?.data?.message ?? "O'chirishda xatolik", 'error')
  } finally {
    deleting.value = false
  }
}

onMounted(async () => {
  try {
    const res = await api.get('/organizations')
    organizations.value = res.data
  } finally {
    loading.value = false
  }
})
</script>
