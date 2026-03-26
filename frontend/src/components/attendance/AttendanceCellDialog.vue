<template>
  <v-dialog :model-value="modelValue" max-width="520" persistent @update:model-value="emit('update:modelValue', $event)">
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-2 d-flex align-center justify-space-between">
        <div>
          <div class="text-subtitle-1 font-weight-bold">Tabel yozuvi</div>
          <div class="text-caption text-medium-emphasis mt-1">
            {{ employee?.full_name }} — {{ formattedDate }}
          </div>
        </div>
        <v-btn icon="mdi-close" variant="text" size="small" @click="close" />
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-5">
        <!-- Device data hint -->
        <v-alert
          v-if="cell?.device"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          <div class="text-caption">
            Qurilma: {{ cell.device.first_entry ?? '—' }} → {{ cell.device.last_exit ?? '—' }}
            ({{ cell.device.hours }} soat)
          </div>
        </v-alert>

        <!-- Auto entry info (К, Я, О) -->
        <v-alert
          v-if="isAutoEntry"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
          prepend-icon="mdi-lock-outline"
        >
          <div class="text-caption font-weight-medium">
            Bu yozuv avtomatik yaratilgan: <strong>{{ selectedCode }} — {{ CODE_LABELS[selectedCode!] }}</strong>
          </div>
          <div class="text-caption mt-1 text-medium-emphasis">
            {{ autoEntrySource }}
          </div>
        </v-alert>

        <!-- Code selection (only when NOT auto) -->
        <template v-if="!isAutoEntry">
          <div class="text-caption text-medium-emphasis font-weight-bold mb-2">ISHDA YO'Q</div>
          <div class="d-flex flex-wrap gap-2 mb-4">
            <v-chip
              v-for="code in MANUAL_ABSENT_CODES"
              :key="code"
              :style="selectedCode === code
                ? `background:${CODE_COLORS[code].bg};color:${CODE_COLORS[code].text};border:2px solid ${CODE_COLORS[code].border};`
                : 'border:1px solid #e0e0e0'"
              size="small"
              class="font-weight-bold"
              style="cursor:pointer;min-width:48px;justify-content:center"
              @click="selectCode(code)"
            >
              {{ code }}
              <v-tooltip activator="parent" location="top">{{ CODE_LABELS[code] }}</v-tooltip>
            </v-chip>
          </div>

          <div class="text-caption text-medium-emphasis font-weight-bold mb-2">ISHLANGAN</div>
          <div class="d-flex flex-wrap gap-2 mb-5">
            <v-chip
              v-for="code in MANUAL_WORKED_CODES"
              :key="code"
              :style="selectedCode === code
                ? `background:${CODE_COLORS[code].bg};color:${CODE_COLORS[code].text};border:2px solid ${CODE_COLORS[code].border};`
                : 'border:1px solid #e0e0e0'"
              size="small"
              class="font-weight-bold"
              style="cursor:pointer;min-width:48px;justify-content:center"
              @click="selectCode(code)"
            >
              {{ code }}
              <v-tooltip activator="parent" location="top">{{ CODE_LABELS[code] }}</v-tooltip>
            </v-chip>
          </div>
        </template>

        <!-- Selected code details (only for manual) -->
        <template v-if="selectedCode && !isAutoEntry">
          <v-divider class="mb-4" />
          <div class="text-body-2 font-weight-medium mb-3">
            {{ selectedCode }} — {{ CODE_LABELS[selectedCode] }}
          </div>

          <v-row dense>
            <v-col v-if="isAbsent" cols="6">
              <v-text-field
                v-model.number="form.days"
                label="Kunlar"
                variant="outlined"
                density="compact"
                type="number"
                min="0.5"
                max="1"
                step="0.5"
                hide-details
              />
            </v-col>
            <v-col :cols="isAbsent ? 6 : 6">
              <v-text-field
                v-model.number="form.hours"
                label="Soat"
                variant="outlined"
                density="compact"
                type="number"
                min="0"
                max="24"
                step="0.5"
                hide-details
              />
            </v-col>
            <v-col cols="6">
              <v-select
                v-model="form.document_type"
                :items="documentTypes"
                item-title="label"
                item-value="value"
                label="Hujjat turi"
                variant="outlined"
                density="compact"
                clearable
                hide-details
              />
            </v-col>
            <v-col cols="6">
              <v-text-field
                v-model="form.document_number"
                label="Hujjat raqami"
                variant="outlined"
                density="compact"
                hide-details
              />
            </v-col>
            <v-col cols="6">
              <v-text-field
                v-model="form.document_date"
                label="Hujjat sanasi"
                variant="outlined"
                density="compact"
                type="date"
                hide-details
              />
            </v-col>
            <v-col cols="12">
              <v-textarea
                v-model="form.note"
                label="Izoh"
                variant="outlined"
                density="compact"
                rows="2"
                auto-grow
                hide-details
              />
            </v-col>
          </v-row>
        </template>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-5">
        <v-btn
          v-if="cell?.entry"
          color="error"
          variant="text"
          prepend-icon="mdi-delete"
          :loading="deleting"
          @click="deleteEntry"
        >
          O'chirish
        </v-btn>
        <v-spacer />
        <v-btn variant="text" @click="close">Bekor qilish</v-btn>
        <v-btn
          v-if="!isAutoEntry"
          color="primary"
          :loading="saving"
          :disabled="!selectedCode"
          prepend-icon="mdi-content-save"
          @click="save"
        >
          Saqlash
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import dayjs from 'dayjs'
import { useTabelStore, CODE_LABELS, CODE_COLORS, MANUAL_ABSENT_CODES, MANUAL_WORKED_CODES, AUTO_CODES, ABSENT_CODES, type TabelCell } from '@/stores/tabel'

interface Employee {
  id: number
  full_name: string
}

const props = defineProps<{
  modelValue: boolean
  employee: Employee | null
  date: string    // YYYY-MM-DD
  day: number
  cell: TabelCell | null
}>()

const emit = defineEmits<{
  'update:modelValue': [val: boolean]
  saved: []
}>()

const store = useTabelStore()
const saving = ref(false)
const deleting = ref(false)

const selectedCode = ref<string | null>(null)
const form = ref({
  hours: 8 as number | null,
  days: 1 as number | null,
  note: null as string | null,
  document_number: null as string | null,
  document_date: null as string | null,
  document_type: null as string | null,
})

const isAbsent = computed(() => ABSENT_CODES.includes(selectedCode.value ?? ''))

const isAutoEntry = computed(() => {
  const entry = props.cell?.entry
  if (!entry) return false
  return AUTO_CODES.includes(entry.code) && entry.source !== 'manual'
})

const autoEntrySource = computed(() => {
  const src = props.cell?.entry?.source
  if (src === 'device') return 'Manba: Hikvision qurilmasi (avtomatik pull)'
  if (src === 'auto_trip') return 'Manba: Xizmat safari (tasdiqlangan)'
  if (src === 'auto_leave') return 'Manba: Ta\'til (avtomatik)'
  if (src === 'auto_holiday') return 'Manba: Bayram kuni (avtomatik)'
  return 'Manba: Tizim'
})

const formattedDate = computed(() => {
  if (!props.date) return ''
  return dayjs(props.date).format('D MMMM YYYY, dddd')
})

const documentTypes = [
  { label: 'Varaqqa', value: 'varaqqa' },
  { label: 'Buyruq', value: 'buyruq' },
  { label: 'Ariza', value: 'ariza' },
  { label: 'Sertifikat', value: 'sertifikat' },
  { label: 'Boshqa', value: 'boshqa' },
]

watch(() => props.modelValue, (val) => {
  if (val) {
    const entry = props.cell?.entry
    if (entry) {
      selectedCode.value = entry.code
      form.value = {
        hours: entry.hours ?? 8,
        days: entry.days ?? 1,
        note: entry.note,
        document_number: entry.document_number,
        document_date: null,
        document_type: entry.document_type,
      }
    } else {
      selectedCode.value = null
      form.value = { hours: 8, days: 1, note: null, document_number: null, document_date: null, document_type: null }
    }
  }
})

function selectCode(code: string) {
  selectedCode.value = selectedCode.value === code ? null : code
  if (WORKED_CODES.includes(code)) {
    form.value.days = null
  }
}

async function save() {
  if (!selectedCode.value || !props.employee) return
  saving.value = true
  try {
    const entry = await store.saveEntry({
      employee_id: props.employee.id,
      work_date: props.date,
      code: selectedCode.value,
      hours: form.value.hours,
      days: isAbsent.value ? form.value.days : null,
      note: form.value.note,
      document_number: form.value.document_number,
      document_date: form.value.document_date,
      document_type: form.value.document_type,
    })
    store.updateCellLocally(props.employee.id, props.day, entry)
    emit('saved')
    close()
  } catch {
    // snackbar handled by parent
  } finally {
    saving.value = false
  }
}

async function deleteEntry() {
  const entryId = props.cell?.entry?.id
  if (!entryId || !props.employee) return
  deleting.value = true
  try {
    await store.deleteEntry(entryId)
    store.updateCellLocally(props.employee.id, props.day, null)
    emit('saved')
    close()
  } finally {
    deleting.value = false
  }
}

function close() {
  emit('update:modelValue', false)
}
</script>
