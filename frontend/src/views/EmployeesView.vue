<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div class="text-h5 font-weight-bold">Xodimlar</div>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openAddDialog">
        Yangi xodim
      </v-btn>
    </div>

    <!-- Filters -->
    <v-card rounded="xl" elevation="0" border class="mb-4">
      <v-card-text class="pa-4">
        <v-row dense>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="search"
              placeholder="Qidirish (ism, lavozim, tabel №)"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
              hide-details
              @update:model-value="fetchEmployees"
            />
          </v-col>
          <v-col v-if="!authStore.isOrgAdmin" cols="12" md="3">
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
          <v-col cols="12" md="2">
            <v-select
              v-model="activeFilter"
              :items="[{ title: 'Barcha', value: null }, { title: 'Aktiv', value: true }, { title: 'Ishdan ketgan', value: false }]"
              item-title="title"
              item-value="value"
              label="Holat"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Table -->
    <v-card rounded="xl" elevation="0" border>
      <v-data-table
        :headers="headers"
        :items="employees"
        :loading="loading"
        item-value="id"
        hover
        density="comfortable"
        @click:row="(_: any, { item }: any) => $router.push(`/employees/${item.id}`)"
      >
        <template #item.full_name="{ item }">
          <div class="d-flex align-center py-2">
            <v-avatar size="36" :color="avatarColor(item)" class="mr-3">
              <v-img v-if="item.photo_url" :src="item.photo_url" cover />
              <span v-else class="text-caption text-white font-weight-bold">
                {{ initials(item) }}
              </span>
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-medium">
                {{ item.last_name }} {{ item.first_name }}
                <span v-if="item.middle_name" class="text-medium-emphasis">{{ item.middle_name }}</span>
              </div>
              <div class="text-caption text-medium-emphasis">
                Tabel: {{ item.employee_id }}
              </div>
            </div>
          </div>
        </template>

        <template #item.department="{ item }">
          {{ item.departmentRel?.name || item.department || '—' }}
        </template>

        <template #item.position="{ item }">
          {{ item.positionRel?.name || item.position || '—' }}
        </template>

        <template #item.organization="{ item }">
          <v-chip size="small" variant="tonal" color="primary">
            {{ item.organization?.code }}
          </v-chip>
        </template>

        <template #item.is_active="{ item }">
          <div class="d-flex align-center gap-1">
            <v-chip
              :color="item.is_active ? 'success' : 'error'"
              size="small"
              variant="tonal"
            >
              {{ item.is_active ? 'Aktiv' : 'Ishdan ketgan' }}
            </v-chip>
            <v-chip
              v-if="item.is_active && !item.is_device_synced"
              color="warning"
              size="small"
              variant="tonal"
              prepend-icon="mdi-sync-alert"
            >
              Qurilmaga yuklanmagan
            </v-chip>
          </div>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1" @click.stop>
            <v-btn
              icon="mdi-pencil"
              variant="text"
              size="small"
              color="secondary"
              @click="openEditDialog(item)"
            />
            <v-btn
              icon="mdi-delete"
              variant="text"
              size="small"
              color="error"
              @click="confirmDelete(item)"
            />
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Add/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="680" persistent>
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">
          {{ editingEmployee ? 'Xodimni tahrirlash' : 'Yangi xodim' }}
        </v-card-title>
        <v-card-text class="pa-6 pt-2">
          <v-form ref="formRef" @submit.prevent="saveEmployee">
            <v-row dense>
              <!-- Photo upload -->
              <v-col cols="12" class="d-flex justify-center mb-2">
                <div class="photo-upload-area" @click="triggerPhotoInput">
                  <v-avatar size="100" color="grey-lighten-2">
                    <v-img v-if="photoPreview" :src="photoPreview" cover />
                    <v-icon v-else size="40" color="grey">mdi-camera</v-icon>
                  </v-avatar>
                  <div class="text-caption text-medium-emphasis mt-2 text-center">
                    Rasm yuklash uchun bosing
                  </div>
                  <input
                    ref="photoInputRef"
                    type="file"
                    accept="image/*"
                    style="display:none"
                    @change="onPhotoSelected"
                  />
                </div>
              </v-col>
              <!-- Organization -->
              <v-col cols="12" sm="6">
                <v-select
                  v-model="form.organization_id"
                  :items="organizations"
                  item-title="name"
                  item-value="id"
                  label="Tashkilot *"
                  variant="outlined"
                  density="compact"
                  :readonly="authStore.isOrgAdmin"
                  :rules="[v => !!v || 'Majburiy maydon']"
                  @update:model-value="onOrgChange"
                />
              </v-col>
              <!-- Tabel № -->
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.employee_id"
                  label="Tabel № *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <!-- Name fields -->
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="form.last_name"
                  label="Familiya *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="form.first_name"
                  label="Ism *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="form.middle_name"
                  label="Otasining ismi"
                  variant="outlined"
                  density="compact"
                />
              </v-col>
              <!-- Department cascading select -->
              <v-col cols="12" sm="6">
                <v-select
                  v-model="form.department_id"
                  :items="departments"
                  item-title="name"
                  item-value="id"
                  label="Bo'lim *"
                  variant="outlined"
                  density="compact"
                  :loading="loadingDepts"
                  :disabled="!form.organization_id"
                  :rules="[v => !!v || 'Majburiy maydon']"
                  :hint="!form.organization_id ? 'Avval tashkilotni tanlang' : ''"
                  persistent-hint
                  @update:model-value="onDeptChange"
                />
              </v-col>
              <!-- Position cascading select -->
              <v-col cols="12" sm="6">
                <v-select
                  v-model="form.position_id"
                  :items="positions"
                  item-title="name"
                  item-value="id"
                  label="Lavozim *"
                  variant="outlined"
                  density="compact"
                  :loading="loadingPositions"
                  :disabled="!form.department_id"
                  :rules="[v => !!v || 'Majburiy maydon']"
                  :hint="!form.department_id ? 'Avval bo\'limni tanlang' : ''"
                  persistent-hint
                />
              </v-col>
              <!-- Phone -->
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.phone"
                  label="Telefon"
                  variant="outlined"
                  density="compact"
                  placeholder="+998901234567"
                />
              </v-col>
              <!-- Hired at -->
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.hired_at"
                  label="Ishga kirgan sana"
                  variant="outlined"
                  density="compact"
                  type="date"
                />
              </v-col>
              <!-- Hikvision fields -->
              <v-col cols="12">
                <v-expansion-panels variant="accordion" elevation="0">
                  <v-expansion-panel>
                    <v-expansion-panel-title class="text-body-2 text-medium-emphasis pa-2">
                      Hikvision ma'lumotlari (ixtiyoriy)
                    </v-expansion-panel-title>
                    <v-expansion-panel-text>
                      <v-row dense class="mt-1">
                        <v-col cols="12" sm="6">
                          <v-text-field
                            v-model="form.hikvision_card_no"
                            label="Hikvision karta №"
                            variant="outlined"
                            density="compact"
                          />
                        </v-col>
                        <v-col cols="12" sm="6">
                          <v-text-field
                            v-model.number="form.hikvision_person_id"
                            label="Hikvision shaxs ID"
                            variant="outlined"
                            density="compact"
                            type="number"
                          />
                        </v-col>
                      </v-row>
                    </v-expansion-panel-text>
                  </v-expansion-panel>
                </v-expansion-panels>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Bekor qilish</v-btn>
          <v-btn color="primary" :loading="saving" @click="saveEmployee">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Dialog -->
    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">Xodimni o'chirish</v-card-title>
        <v-card-text class="pa-6 pt-0">
          <strong>{{ deletingEmployee?.last_name }} {{ deletingEmployee?.first_name }}</strong> xodimini o'chirishni tasdiqlaysizmi?
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="deleteDialog = false">Bekor qilish</v-btn>
          <v-btn color="error" :loading="deleting" @click="deleteEmployee">O'chirish</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000">
      {{ snackbar.text }}
    </v-snackbar>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import api from '@/api/axios'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

interface Employee {
  id: number
  employee_id: string
  first_name: string
  last_name: string
  middle_name?: string
  position?: string
  position_id?: number | null
  department?: string
  department_id?: number | null
  departmentRel?: { id: number; name: string } | null
  positionRel?: { id: number; name: string } | null
  phone?: string
  hired_at?: string
  is_active: boolean
  is_device_synced: boolean
  hikvision_card_no?: string
  hikvision_person_id?: number | null
  photo_path?: string
  photo_url?: string | null
  organization?: { id: number; name: string; code: string }
}

const employees = ref<Employee[]>([])
const organizations = ref<any[]>([])
const departments = ref<any[]>([])
const positions = ref<any[]>([])
const loading = ref(true)
const loadingDepts = ref(false)
const loadingPositions = ref(false)
const search = ref('')
const orgFilter = ref<number | null>(null)
const activeFilter = ref<boolean | null>(null)
const saving = ref(false)
const deleting = ref(false)
const photoFile = ref<File | null>(null)
const photoPreview = ref<string | null>(null)
const photoInputRef = ref<HTMLInputElement | null>(null)

const dialog = ref(false)
const deleteDialog = ref(false)
const editingEmployee = ref<Employee | null>(null)
const deletingEmployee = ref<Employee | null>(null)
const formRef = ref<any>(null)

const snackbar = ref({ show: false, text: '', color: 'success' })

function showSnack(text: string, color = 'success') {
  snackbar.value = { show: true, text, color }
}

const defaultForm = () => ({
  organization_id: null as number | null,
  employee_id: '',
  last_name: '',
  first_name: '',
  middle_name: '',
  department_id: null as number | null,
  position_id: null as number | null,
  phone: '',
  hired_at: '',
  hikvision_card_no: '',
  hikvision_person_id: null as number | null,
})
const form = ref(defaultForm())

const headers = [
  { title: 'Xodim', key: 'full_name', sortable: false },
  { title: 'Lavozim', key: 'position', sortable: false },
  { title: "Bo'lim", key: 'department', sortable: false },
  { title: 'Tashkilot', key: 'organization', sortable: false },
  { title: 'Holat', key: 'is_active', sortable: false, minWidth: '220px' },
  { title: '', key: 'actions', sortable: false, width: '80px' },
]

const colors = ['primary', 'secondary', 'success', 'warning', 'error', 'info']
function avatarColor(item: Employee): string {
  return colors[item.id % colors.length] ?? 'primary'
}
function initials(item: Employee): string {
  return `${item.last_name?.[0] ?? ''}${item.first_name?.[0] ?? ''}`.toUpperCase()
}

async function fetchEmployees() {
  loading.value = true
  try {
    const params: Record<string, any> = { per_page: 200 }
    if (orgFilter.value) params.organization_id = orgFilter.value
    if (activeFilter.value !== null) params.is_active = activeFilter.value
    if (search.value) params.search = search.value
    const res = await api.get('/employees', { params })
    employees.value = res.data.data ?? res.data
  } finally {
    loading.value = false
  }
}

async function loadDepartments(orgId: number) {
  loadingDepts.value = true
  departments.value = []
  try {
    const res = await api.get('/departments', { params: { organization_id: orgId } })
    departments.value = res.data.data ?? res.data
  } catch {
    showSnack("Bo'limlarni yuklashda xatolik", 'error')
  } finally {
    loadingDepts.value = false
  }
}

async function loadPositions(deptId: number) {
  loadingPositions.value = true
  positions.value = []
  try {
    const res = await api.get(`/departments/${deptId}/positions`)
    positions.value = res.data.data ?? res.data
  } catch {
    showSnack("Lavozimlarni yuklashda xatolik", 'error')
  } finally {
    loadingPositions.value = false
  }
}

function onOrgChange(orgId: number | null) {
  form.value.department_id = null
  form.value.position_id = null
  departments.value = []
  positions.value = []
  if (orgId) loadDepartments(orgId)
}

function onDeptChange(deptId: number | null) {
  form.value.position_id = null
  positions.value = []
  if (deptId) loadPositions(deptId)
}

function triggerPhotoInput() {
  photoInputRef.value?.click()
}

function onPhotoSelected(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  photoFile.value = file
  photoPreview.value = URL.createObjectURL(file)
}

function openAddDialog() {
  editingEmployee.value = null
  form.value = defaultForm()
  // Org admin uchun tashkilotni avtomatik to'ldirish
  if (authStore.isOrgAdmin && authStore.user?.organization_id) {
    form.value.organization_id = authStore.user.organization_id
    loadDepartments(authStore.user.organization_id)
  } else {
    departments.value = []
  }
  positions.value = []
  photoFile.value = null
  photoPreview.value = null
  dialog.value = true
}

async function openEditDialog(emp: Employee) {
  editingEmployee.value = emp
  departments.value = []
  positions.value = []
  photoFile.value = null
  photoPreview.value = emp.photo_url ?? null

  form.value = {
    organization_id: emp.organization?.id ?? null,
    employee_id: emp.employee_id,
    last_name: emp.last_name,
    first_name: emp.first_name,
    middle_name: emp.middle_name ?? '',
    department_id: emp.department_id ?? null,
    position_id: emp.position_id ?? null,
    phone: emp.phone ?? '',
    hired_at: emp.hired_at ?? '',
    hikvision_card_no: emp.hikvision_card_no ?? '',
    hikvision_person_id: emp.hikvision_person_id ?? null,
  }

  if (emp.organization?.id) await loadDepartments(emp.organization.id)
  if (emp.department_id) await loadPositions(emp.department_id)

  dialog.value = true
}

function confirmDelete(emp: Employee) {
  deletingEmployee.value = emp
  deleteDialog.value = true
}

async function saveEmployee() {
  const { valid } = await formRef.value?.validate()
  if (!valid) return
  saving.value = true
  try {
    let res: any
    const f = form.value as any

    if (photoFile.value) {
      const fd = new FormData()
      const fields: Record<string, any> = {
        organization_id: f.organization_id,
        employee_id: f.employee_id,
        last_name: f.last_name,
        first_name: f.first_name,
        middle_name: f.middle_name || '',
        department_id: f.department_id ?? '',
        position_id: f.position_id ?? '',
        phone: f.phone || '',
        hired_at: f.hired_at || '',
        hikvision_card_no: f.hikvision_card_no || '',
        hikvision_person_id: f.hikvision_person_id ?? '',
      }
      for (const [k, v] of Object.entries(fields)) {
        fd.append(k, String(v ?? ''))
      }
      fd.append('photo', photoFile.value)
      if (editingEmployee.value) {
        fd.append('_method', 'PUT')
        res = await api.post(`/employees/${editingEmployee.value.id}`, fd, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
      } else {
        res = await api.post('/employees', fd, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
      }
    } else {
      const payload: any = { ...f }
      if (!payload.middle_name) payload.middle_name = null
      if (!payload.phone) payload.phone = null
      if (!payload.hired_at) payload.hired_at = null
      if (!payload.hikvision_card_no) payload.hikvision_card_no = null
      if (!payload.hikvision_person_id) payload.hikvision_person_id = null
      if (editingEmployee.value) {
        res = await api.put(`/employees/${editingEmployee.value.id}`, payload)
      } else {
        res = await api.post('/employees', payload)
      }
    }

    if (editingEmployee.value) {
      const idx = employees.value.findIndex(e => e.id === editingEmployee.value!.id)
      if (idx !== -1) employees.value[idx] = res.data
      showSnack('Xodim yangilandi')
    } else {
      employees.value.unshift(res.data)
      showSnack("Xodim qo'shildi")
    }
    dialog.value = false
  } catch (e: any) {
    showSnack(e?.response?.data?.message ?? 'Xatolik yuz berdi', 'error')
  } finally {
    saving.value = false
  }
}

async function deleteEmployee() {
  if (!deletingEmployee.value) return
  deleting.value = true
  try {
    await api.delete(`/employees/${deletingEmployee.value.id}`)
    employees.value = employees.value.filter(e => e.id !== deletingEmployee.value!.id)
    showSnack("Xodim o'chirildi")
    deleteDialog.value = false
  } catch {
    showSnack("O'chirishda xatolik", 'error')
  } finally {
    deleting.value = false
  }
}

watch([orgFilter, activeFilter], fetchEmployees)

onMounted(async () => {
  if (!authStore.user) await authStore.fetchMe()

  const promises: Promise<any>[] = [
    api.get('/employees', { params: { per_page: 200 } }),
    api.get('/organizations'),
  ]
  const [empsRes, orgsRes] = await Promise.all(promises)
  employees.value = empsRes.data.data ?? empsRes.data
  organizations.value = orgsRes.data
  loading.value = false
})
</script>

<style scoped>
.photo-upload-area {
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
}
</style>
