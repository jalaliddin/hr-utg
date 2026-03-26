<template>
  <div>
    <div class="d-flex align-center gap-3 mb-6">
      <v-btn icon="mdi-arrow-left" variant="text" @click="$router.back()" />
      <div class="text-h5 font-weight-bold">
        {{ isEdit ? 'Safarni tahrirlash' : 'Yangi xizmat safari' }}
      </div>
      <v-chip v-if="certSerial" color="info" size="small">№ {{ certSerial }}</v-chip>
    </div>

    <v-card rounded="xl" elevation="0" border>
      <v-card-text class="pa-6">
        <v-form ref="formRef" @submit.prevent="save">
          <v-row>
            <!-- Organization (readonly — from auth) -->
            <v-col cols="12" md="6">
              <v-text-field
                :model-value="orgName"
                label="Tashkilot"
                variant="outlined"
                density="compact"
                readonly
                prepend-inner-icon="mdi-office-building"
              />
            </v-col>

            <!-- Employee — only org's employees -->
            <v-col cols="12" md="6">
              <v-autocomplete
                v-model="form.employee_id"
                :items="employeeItems"
                item-title="label"
                item-value="id"
                label="Xodim *"
                variant="outlined"
                density="compact"
                :rules="[v => !!v || 'Majburiy maydon']"
              >
                <template #item="{ props, item }">
                  <v-list-item v-bind="props" :subtitle="item.raw?.sub" />
                </template>
                <template #selection="{ item }">
                  <span>{{ item.raw?.label }}</span>
                  <span v-if="item.raw?.sub" class="text-caption text-medium-emphasis ml-1">— {{ item.raw.sub }}</span>
                </template>
              </v-autocomplete>
            </v-col>

            <!-- Dates -->
            <v-col cols="12" sm="4">
              <v-text-field
                v-model="form.start_date"
                label="Boshlanish sanasi *"
                variant="outlined"
                density="compact"
                type="date"
                :rules="[v => !!v || 'Majburiy maydon']"
                @update:model-value="calcDays"
              />
            </v-col>
            <v-col cols="12" sm="4">
              <v-text-field
                v-model="form.end_date"
                label="Tugash sanasi *"
                variant="outlined"
                density="compact"
                type="date"
                :rules="[v => !!v || 'Majburiy maydon']"
                @update:model-value="calcDays"
              />
            </v-col>
            <v-col cols="12" sm="4">
              <v-text-field
                :model-value="form.days_count > 0 ? form.days_count + ' kun' : ''"
                label="Davomiyligi"
                variant="outlined"
                density="compact"
                readonly
                prepend-inner-icon="mdi-calendar-range"
              />
            </v-col>

            <!-- Transport -->
            <v-col cols="12" md="4">
              <v-select
                v-model="form.transport"
                :items="transportItems"
                item-title="label"
                item-value="value"
                label="Transport"
                variant="outlined"
                density="compact"
                clearable
              />
            </v-col>

            <!-- Order info -->
            <v-col cols="12" sm="4">
              <v-text-field
                v-model="form.order_number"
                label="Buyruq raqami"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="12" sm="4">
              <v-text-field
                v-model="form.order_date"
                label="Buyruq sanasi"
                variant="outlined"
                density="compact"
                type="date"
              />
            </v-col>

            <!-- Description -->
            <v-col cols="12">
              <v-textarea
                v-model="form.description"
                label="Izoh"
                variant="outlined"
                density="compact"
                rows="2"
                auto-grow
              />
            </v-col>

            <!-- Destinations — list of organizations visited -->
            <v-col cols="12">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-subtitle-2 font-weight-bold">Boradigan tashkilotlar</span>
                <v-btn size="small" variant="tonal" color="primary" prepend-icon="mdi-plus" @click="addDestination">
                  Qo'shish
                </v-btn>
              </div>
              <div v-if="form.destinations.length === 0" class="text-caption text-medium-emphasis py-2">
                Hali qo'shilmagan
              </div>
              <v-card
                v-for="(dest, idx) in form.destinations"
                :key="idx"
                variant="outlined"
                rounded="lg"
                class="mb-3"
              >
                <v-card-text class="pa-4">
                  <div class="d-flex align-center justify-space-between mb-3">
                    <span class="text-body-2 font-weight-medium">{{ idx + 1 }}. Tashkilot</span>
                    <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="removeDestination(idx)" />
                  </div>
                  <v-row dense>
                    <v-col cols="12">
                      <v-autocomplete
                        v-model="dest.organization_id"
                        :items="organizations"
                        item-title="name"
                        item-value="id"
                        label="Tashkilot *"
                        variant="outlined"
                        density="compact"
                        :rules="[v => !!v || 'Majburiy']"
                      />
                    </v-col>
                    <v-col cols="12">
                      <div class="text-caption text-medium-emphasis">
                        <v-icon size="14" class="mr-1">mdi-information-outline</v-icon>
                        Kelgan va ketgan sana/vaqt qurilmadan avtomatik yoziladi
                      </div>
                    </v-col>
                  </v-row>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-6">
        <v-btn variant="outlined" @click="$router.back()">Bekor qilish</v-btn>
        <v-spacer />
        <v-btn color="primary" size="large" :loading="saving" prepend-icon="mdi-content-save" @click="save">
          Saqlash
        </v-btn>
      </v-card-actions>
    </v-card>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000">
      {{ snackbar.text }}
    </v-snackbar>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/api/axios'
import dayjs from 'dayjs'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const isEdit = computed(() => !!route.params.id)
const tripId = computed(() => route.params.id as string | undefined)

const saving = ref(false)
const employeeItems = ref<any[]>([])
const organizations = ref<any[]>([])
const formRef = ref<any>(null)
const certSerial = ref('')

const orgName = computed(() => {
  if (!form.value.organization_id) return ''
  return organizations.value.find(o => o.id === form.value.organization_id)?.name ?? ''
})

async function loadEmployees(orgId: number) {
  const res = await api.get('/employees', {
    params: { organization_id: orgId, per_page: 500, is_active: 1 },
  })
  const emps = res.data.data ?? res.data
  employeeItems.value = emps.map((e: any) => ({
    id: e.id,
    label: `${e.last_name} ${e.first_name}${e.middle_name ? ' ' + e.middle_name : ''}`,
    sub: [e.department, e.position].filter(Boolean).join(' · '),
    organization_id: e.organization_id,
  }))
}

const transportItems = [
  { label: 'Avtomobil', value: 'car' },
  { label: 'Poezd', value: 'train' },
  { label: 'Samolyot', value: 'plane' },
  { label: 'Avtobus', value: 'bus' },
  { label: 'Boshqa', value: 'other' },
]

interface Destination {
  id?: number
  organization_id: number | null
  arrival_date?: string
  departure_date?: string
  arrival_signed_by?: string
  departure_signed_by?: string
}

const defaultForm = () => ({
  employee_id: null as number | null,
  organization_id: null as number | null,
  destination: '',
  purpose: '',
  start_date: '',
  end_date: '',
  days_count: 0,
  transport: null as string | null,
  order_number: '',
  order_date: '',
  description: '',
  destinations: [] as Destination[],
})
const form = ref(defaultForm())

const snackbar = ref({ show: false, text: '', color: 'success' })
function showSnack(text: string, color = 'success') {
  snackbar.value = { show: true, text, color }
}

function calcDays() {
  if (form.value.start_date && form.value.end_date) {
    const diff = dayjs(form.value.end_date).diff(dayjs(form.value.start_date), 'day') + 1
    form.value.days_count = diff > 0 ? diff : 0
  }
}

function addDestination() {
  form.value.destinations.push({ organization_id: null })
}
function removeDestination(idx: number) {
  form.value.destinations.splice(idx, 1)
}

async function save() {
  const { valid } = await formRef.value?.validate()
  if (!valid) return
  saving.value = true
  try {
    // destination = joined org names for display; purpose = optional
    const destOrgNames = form.value.destinations
      .map(d => organizations.value.find(o => o.id === d.organization_id)?.name)
      .filter(Boolean)
      .join(', ')

    const payload: any = {
      ...form.value,
      destination: destOrgNames || '—',
      purpose: form.value.description || '—',
    }
    if (!payload.order_number) payload.order_number = null
    if (!payload.order_date) payload.order_date = null
    if (!payload.transport) payload.transport = null

    if (isEdit.value) {
      await api.put(`/business-trips/${tripId.value}`, payload)
      showSnack('Xizmat safari yangilandi')
    } else {
      await api.post('/business-trips', payload)
      showSnack('Xizmat safari yaratildi')
    }
    setTimeout(() => router.push('/business-trips'), 1000)
  } catch (e: any) {
    const errors = e?.response?.data?.errors
    const msg = errors
      ? Object.values(errors).flat().join(', ')
      : (e?.response?.data?.message ?? 'Xatolik yuz berdi')
    showSnack(msg, 'error')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  // Ensure user is loaded
  if (!authStore.user) {
    await authStore.fetchMe()
  }

  const orgsRes = await api.get('/organizations')
  organizations.value = orgsRes.data

  if (isEdit.value) {
    const res = await api.get(`/business-trips/${tripId.value}`)
    const trip = res.data
    certSerial.value = trip.certificate_serial ?? ''
    form.value = {
      employee_id: trip.employee_id,
      organization_id: trip.organization_id,
      destination: trip.destination ?? '',
      purpose: trip.purpose ?? '',
      start_date: trip.start_date ?? '',
      end_date: trip.end_date ?? '',
      days_count: trip.days_count ?? 0,
      transport: trip.transport ?? null,
      order_number: trip.order_number ?? '',
      order_date: trip.order_date ?? '',
      description: trip.description ?? '',
      destinations: (trip.destinations ?? []).map((d: any) => ({
        id: d.id,
        organization_id: d.organization_id,
        arrival_date: d.arrival_date ?? '',
        departure_date: d.departure_date ?? '',
        arrival_signed_by: d.arrival_signed_by ?? '',
        departure_signed_by: d.departure_signed_by ?? '',
      })),
    }
    await loadEmployees(trip.organization_id)
  } else {
    const orgId = authStore.user?.organization_id
    if (orgId) {
      form.value.organization_id = orgId
      await loadEmployees(orgId)
    }
  }
})

// Watch in case user loads after mount (edge case)
watch(
  () => authStore.user?.organization_id,
  async (orgId) => {
    if (!isEdit.value && orgId && !form.value.organization_id) {
      form.value.organization_id = orgId
      await loadEmployees(orgId)
    }
  },
)
</script>
