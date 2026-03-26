<template>
  <div v-if="trip">
    <div class="d-flex align-center gap-3 mb-6">
      <v-btn icon="mdi-arrow-left" variant="text" @click="$router.back()" />
      <div>
        <div class="text-h5 font-weight-bold">Xizmat safari</div>
        <div class="text-caption text-medium-emphasis">№ {{ trip.certificate_serial }}</div>
      </div>
      <v-spacer />
      <v-btn
        variant="tonal"
        color="primary"
        prepend-icon="mdi-file-pdf-box"
        size="small"
        :loading="downloadingPdf"
        @click="downloadPdf"
      >
        PDF
      </v-btn>
      <v-btn
        v-if="trip.status === 'pending'"
        variant="tonal"
        color="secondary"
        prepend-icon="mdi-pencil"
        size="small"
        :to="`/business-trips/${trip.id}/edit`"
      >
        Tahrirlash
      </v-btn>
    </div>

    <v-row>
      <!-- Main info -->
      <v-col cols="12" md="8">
        <v-card rounded="xl" elevation="0" border class="mb-4">
          <v-card-title class="pa-5 pb-2 text-subtitle-1 font-weight-bold">Asosiy ma'lumotlar</v-card-title>
          <v-card-text class="pa-5 pt-2">
            <v-row dense>
              <v-col cols="6" sm="3">
                <div class="text-caption text-medium-emphasis">Xodim</div>
                <div class="text-body-2 font-weight-medium">
                  {{ trip.employee?.last_name }} {{ trip.employee?.first_name }}
                </div>
                <div class="text-caption text-medium-emphasis">{{ trip.employee?.position }}</div>
              </v-col>
              <v-col cols="6" sm="3">
                <div class="text-caption text-medium-emphasis">Tashkilot</div>
                <div class="text-body-2 font-weight-medium">{{ trip.organization?.name }}</div>
              </v-col>
              <v-col cols="6" sm="3">
                <div class="text-caption text-medium-emphasis">Boshlanish</div>
                <div class="text-body-2 font-weight-medium">{{ formatDate(trip.start_date) }}</div>
              </v-col>
              <v-col cols="6" sm="3">
                <div class="text-caption text-medium-emphasis">Tugash</div>
                <div class="text-body-2 font-weight-medium">{{ formatDate(trip.extended_end_date ?? trip.end_date) }}</div>
                <div v-if="trip.extended_end_date" class="text-caption text-medium-emphasis">(uzaytirilgan)</div>
              </v-col>
            </v-row>
            <v-divider class="my-3" />
            <v-row dense>
              <v-col cols="12" sm="6">
                <div class="text-caption text-medium-emphasis">Yo'nalish</div>
                <div class="text-body-2">{{ trip.destination }}</div>
              </v-col>
              <v-col cols="12" sm="6">
                <div class="text-caption text-medium-emphasis">Maqsad</div>
                <div class="text-body-2">{{ trip.purpose }}</div>
              </v-col>
              <v-col v-if="trip.order_number" cols="6">
                <div class="text-caption text-medium-emphasis">Buyruq</div>
                <div class="text-body-2">{{ trip.order_number }} {{ trip.order_date ? '/ ' + formatDate(trip.order_date) : '' }}</div>
              </v-col>
              <v-col v-if="trip.transport" cols="6">
                <div class="text-caption text-medium-emphasis">Transport</div>
                <div class="text-body-2">{{ transportLabel(trip.transport) }}</div>
              </v-col>
              <v-col v-if="trip.passport_series" cols="6">
                <div class="text-caption text-medium-emphasis">Pasport</div>
                <div class="text-body-2">{{ trip.passport_series }}</div>
              </v-col>
              <v-col v-if="trip.service_id_number" cols="6">
                <div class="text-caption text-medium-emphasis">Xizmat guvohnomasi</div>
                <div class="text-body-2">{{ trip.service_id_number }}</div>
              </v-col>
            </v-row>

            <!-- Extension info -->
            <template v-if="trip.extension_days">
              <v-divider class="my-3" />
              <div class="d-flex align-center gap-2">
                <v-icon color="info" size="18">mdi-calendar-plus</v-icon>
                <span class="text-body-2">
                  <strong>+{{ trip.extension_days }} kun uzaytirilgan</strong>
                  <template v-if="trip.extension_order_number"> — Buyruq №{{ trip.extension_order_number }}</template>
                </span>
              </div>
              <div v-if="trip.extension_reason" class="text-caption text-medium-emphasis mt-1 ml-7">{{ trip.extension_reason }}</div>
            </template>

            <!-- Financials -->
            <template v-if="trip.daily_allowance">
              <v-divider class="my-3" />
              <v-row dense>
                <v-col cols="6">
                  <div class="text-caption text-medium-emphasis">Kunlik yo'l puli</div>
                  <div class="text-body-2">{{ Number(trip.daily_allowance).toLocaleString() }} so'm</div>
                </v-col>
                <v-col cols="6">
                  <div class="text-caption text-medium-emphasis">Jami</div>
                  <div class="text-body-2 font-weight-bold">{{ Number(trip.total_amount).toLocaleString() }} so'm</div>
                </v-col>
              </v-row>
            </template>
          </v-card-text>
        </v-card>

        <!-- Destinations -->
        <v-card v-if="trip.destinations?.length" rounded="xl" elevation="0" border class="mb-4">
          <v-card-title class="pa-5 pb-2 text-subtitle-1 font-weight-bold">Borgan joylari</v-card-title>
          <v-card-text class="pa-5 pt-2">
            <v-table density="compact">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Tashkilot</th>
                  <th>Kelgan</th>
                  <th>Ketgan</th>
                  <th>Qurilma holati</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(dest, i) in trip.destinations" :key="dest.id">
                  <td>{{ (i as number) + 1 }}</td>
                  <td>{{ dest.organization?.name ?? '—' }}</td>
                  <td>{{ dest.arrival_date ? formatDate(dest.arrival_date) : '—' }}</td>
                  <td>{{ dest.departure_date ? formatDate(dest.departure_date) : '—' }}</td>
                  <td>
                    <v-chip
                      :color="pushStatusColor(dest.push_status)"
                      size="x-small"
                      variant="tonal"
                    >
                      {{ pushStatusLabel(dest.push_status) }}
                    </v-chip>
                  </td>
                </tr>
              </tbody>
            </v-table>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Right: status & actions -->
      <v-col cols="12" md="4">
        <v-card rounded="xl" elevation="0" border class="mb-4">
          <v-card-text class="pa-5">
            <div class="text-caption text-medium-emphasis mb-1">Holat</div>
            <v-chip :color="tripStatusColor(trip.status)" variant="tonal" class="mb-4">
              {{ tripStatusLabel(trip.status) }}
            </v-chip>

            <div v-if="trip.approved_by" class="mb-3">
              <div class="text-caption text-medium-emphasis">Tasdiqlagan</div>
              <div class="text-body-2">{{ trip.approved_by?.name }}</div>
            </div>
            <div v-if="trip.reject_reason" class="mb-3">
              <div class="text-caption text-medium-emphasis">Rad sababi</div>
              <div class="text-body-2 text-error">{{ trip.reject_reason }}</div>
            </div>
            <div v-if="trip.returned_at" class="mb-3">
              <div class="text-caption text-medium-emphasis">Qaytgan sana</div>
              <div class="text-body-2">{{ formatDate(trip.returned_at) }}</div>
            </div>

            <v-divider v-if="trip.device_push_status" class="my-3" />
            <div v-if="trip.device_push_status">
              <div class="text-caption text-medium-emphasis mb-1">Qurilma push holati</div>
              <v-chip :color="pushStatusColor(trip.device_push_status)" size="small" variant="tonal" class="mb-2">
                {{ pushStatusLabel(trip.device_push_status) }}
              </v-chip>
              <div v-if="trip.device_pushed_at" class="text-caption text-medium-emphasis">
                {{ formatDate(trip.device_pushed_at) }}
              </div>
            </div>
          </v-card-text>

          <v-card-actions class="pa-4 pt-0 flex-column gap-2">
            <template v-if="trip.status === 'pending'">
              <v-btn block color="success" variant="tonal" :loading="approving" @click="approve">
                Tasdiqlash
              </v-btn>
              <v-btn block color="error" variant="tonal" @click="rejectDialogOpen = true">
                Rad etish
              </v-btn>
            </template>
            <template v-if="trip.status === 'approved'">
              <v-btn block color="info" variant="tonal" :loading="completing" @click="complete">
                Yakunlash
              </v-btn>
              <v-btn block color="warning" variant="tonal" @click="extendDialogOpen = true">
                Muddatni uzaytirish
              </v-btn>
              <v-btn
                v-if="trip.device_push_status && trip.device_push_status !== 'success'"
                block
                color="secondary"
                variant="tonal"
                :loading="retryPushing"
                @click="retryPush"
              >
                Qurilmaga qayta yuklash
              </v-btn>
            </template>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>

    <!-- Reject dialog -->
    <v-dialog v-model="rejectDialogOpen" max-width="400">
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">Rad etish</v-card-title>
        <v-card-text class="pa-6 pt-2">
          <v-textarea v-model="rejectReason" label="Sababi *" variant="outlined" density="compact" rows="3" auto-grow />
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="rejectDialogOpen = false">Bekor qilish</v-btn>
          <v-btn color="error" :loading="rejecting" :disabled="!rejectReason" @click="reject">Rad etish</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Extend dialog -->
    <v-dialog v-model="extendDialogOpen" max-width="440">
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">Muddatni uzaytirish</v-card-title>
        <v-card-text class="pa-6 pt-2">
          <v-row dense>
            <v-col cols="12">
              <v-text-field
                v-model.number="extendForm.extension_days"
                label="Qo'shimcha kunlar *"
                type="number"
                min="1"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="6">
              <v-text-field
                v-model="extendForm.extension_order_number"
                label="Buyruq №"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="6">
              <v-text-field
                v-model="extendForm.extension_order_date"
                label="Buyruq sanasi"
                type="date"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="12">
              <v-text-field
                v-model="extendForm.extension_reason"
                label="Sababi"
                variant="outlined"
                density="compact"
              />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="extendDialogOpen = false">Bekor qilish</v-btn>
          <v-btn color="primary" :loading="extending" :disabled="!extendForm.extension_days" @click="extendTrip">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snack.show" :color="snack.color" location="bottom right" :timeout="3000">
      {{ snack.text }}
    </v-snackbar>
  </div>

  <div v-else class="text-center py-12">
    <v-progress-circular indeterminate color="primary" />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/api/axios'
import dayjs from 'dayjs'

const route = useRoute()
const router = useRouter()
const trip = ref<any>(null)
const snack = ref({ show: false, text: '', color: 'success' })

const downloadingPdf = ref(false)
const approving = ref(false)
const rejecting = ref(false)
const completing = ref(false)
const extending = ref(false)
const retryPushing = ref(false)
const rejectDialogOpen = ref(false)
const extendDialogOpen = ref(false)
const rejectReason = ref('')
const extendForm = ref({ extension_days: 1, extension_order_number: '', extension_order_date: '', extension_reason: '' })

function formatDate(d: string) {
  return d ? dayjs(d).format('DD.MM.YYYY') : '—'
}

function transportLabel(t: string) {
  return { car: 'Avtomobil', train: 'Poyezd', plane: 'Samolyot', bus: 'Avtobus', other: 'Boshqa' }[t] ?? t
}

function tripStatusColor(s: string) {
  return { pending: 'warning', approved: 'success', rejected: 'error', completed: 'info' }[s] ?? 'grey'
}

function tripStatusLabel(s: string) {
  return { pending: 'Kutilmoqda', approved: 'Tasdiqlandi', rejected: 'Rad etildi', completed: 'Yakunlandi' }[s] ?? s
}

function pushStatusColor(s: string) {
  return { success: 'success', failed: 'error', partial: 'warning', pending: 'grey' }[s] ?? 'grey'
}

function pushStatusLabel(s: string) {
  return { success: 'Yuklandi', failed: 'Xato', partial: 'Qisman', pending: 'Kutmoqda' }[s] ?? (s ?? '—')
}

function showSnack(text: string, color = 'success') {
  snack.value = { show: true, text, color }
}

async function downloadPdf() {
  downloadingPdf.value = true
  try {
    const res = await api.get(`/business-trips/${trip.value.id}/pdf`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `safari_${trip.value.certificate_serial ?? trip.value.id}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    showSnack('PDF yuklab bo\'lmadi', 'error')
  } finally {
    downloadingPdf.value = false
  }
}

async function loadTrip() {
  const res = await api.get(`/business-trips/${route.params.id}`)
  trip.value = res.data
}

async function approve() {
  approving.value = true
  try {
    await api.post(`/business-trips/${trip.value.id}/approve`)
    await loadTrip()
    showSnack('Tasdiqlandi')
  } catch (e: any) {
    showSnack(e?.response?.data?.message ?? 'Xatolik', 'error')
  } finally {
    approving.value = false
  }
}

async function reject() {
  rejecting.value = true
  try {
    await api.post(`/business-trips/${trip.value.id}/reject`, { reject_reason: rejectReason.value })
    await loadTrip()
    rejectDialogOpen.value = false
    showSnack('Rad etildi')
  } catch (e: any) {
    showSnack(e?.response?.data?.message ?? 'Xatolik', 'error')
  } finally {
    rejecting.value = false
  }
}

async function complete() {
  completing.value = true
  try {
    await api.post(`/business-trips/${trip.value.id}/complete`)
    await loadTrip()
    showSnack('Yakunlandi')
  } catch (e: any) {
    showSnack(e?.response?.data?.message ?? 'Xatolik', 'error')
  } finally {
    completing.value = false
  }
}

async function extendTrip() {
  extending.value = true
  try {
    await api.post(`/business-trips/${trip.value.id}/extend`, extendForm.value)
    await loadTrip()
    extendDialogOpen.value = false
    showSnack('Muddat uzaytirildi')
  } catch (e: any) {
    showSnack(e?.response?.data?.message ?? 'Xatolik', 'error')
  } finally {
    extending.value = false
  }
}

async function retryPush() {
  retryPushing.value = true
  try {
    await api.post(`/business-trips/${trip.value.id}/retry-push`)
    await loadTrip()
    showSnack('Qayta yuklash boshlandi')
  } catch (e: any) {
    showSnack(e?.response?.data?.message ?? 'Xatolik', 'error')
  } finally {
    retryPushing.value = false
  }
}

onMounted(loadTrip)
</script>
