<template>
  <v-dialog :model-value="modelValue" max-width="600" persistent @update:model-value="emit('update:modelValue', $event)">
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-2 d-flex align-center justify-space-between">
        <span class="text-subtitle-1 font-weight-bold">Ko'p kunlik kiritish</span>
        <v-btn icon="mdi-close" variant="text" size="small" @click="close" />
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-5">
        <!-- Employees -->
        <div class="text-caption text-medium-emphasis font-weight-bold mb-2">XODIMLAR</div>
        <div v-for="(empId, idx) in form.employeeIds" :key="idx" class="d-flex align-center gap-2 mb-2">
          <v-autocomplete
            :model-value="empId"
            :items="employees"
            item-title="label"
            item-value="id"
            label="Xodim"
            variant="outlined"
            density="compact"
            hide-details
            class="flex-grow-1"
            @update:model-value="val => form.employeeIds[idx] = val"
          />
          <v-btn
            icon="mdi-close"
            variant="text"
            size="small"
            color="error"
            :disabled="form.employeeIds.length === 1"
            @click="removeEmployee(idx)"
          />
        </div>
        <v-btn size="small" variant="tonal" prepend-icon="mdi-plus" class="mb-4" @click="addEmployee">
          Xodim qo'shish
        </v-btn>

        <v-divider class="mb-4" />

        <!-- Date range -->
        <div class="text-caption text-medium-emphasis font-weight-bold mb-2">SANA ORALIG'I</div>
        <v-row dense class="mb-2">
          <v-col cols="6">
            <v-text-field
              v-model="form.dateFrom"
              label="Boshlanish *"
              type="date"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="6">
            <v-text-field
              v-model="form.dateTo"
              label="Tugash *"
              type="date"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>
        </v-row>
        <div class="d-flex gap-4 mb-4">
          <v-checkbox
            v-model="form.skipWeekends"
            label="Dam olish kunlarini o'tkazib yuborish"
            density="compact"
            hide-details
          />
          <v-checkbox
            v-model="form.skipHolidays"
            label="Bayramlarni o'tkazib yuborish"
            density="compact"
            hide-details
          />
        </div>

        <!-- Code -->
        <div class="text-caption text-medium-emphasis font-weight-bold mb-2">TUR</div>
        <div class="d-flex flex-wrap gap-2 mb-4">
          <v-chip
            v-for="code in MANUAL_CODES"
            :key="code"
            :style="form.code === code
              ? `background:${CODE_COLORS[code].bg};color:${CODE_COLORS[code].text};border:2px solid ${CODE_COLORS[code].border};`
              : 'border:1px solid #e0e0e0'"
            size="small"
            class="font-weight-bold"
            style="cursor:pointer;min-width:48px;justify-content:center"
            @click="form.code = code"
          >
            {{ code }}
            <v-tooltip activator="parent" location="top">{{ CODE_LABELS[code] }}</v-tooltip>
          </v-chip>
        </div>

        <v-row dense class="mb-3">
          <v-col cols="6">
            <v-text-field
              v-model.number="form.hours"
              label="Kunlik soat"
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

        <!-- Preview -->
        <v-alert
          v-if="preview.length"
          type="success"
          variant="tonal"
          density="compact"
          class="text-caption"
        >
          Jami: <strong>{{ preview.length }}</strong> ta yozuv kiritiladi
          ({{ form.employeeIds.filter(Boolean).length }} xodim × {{ uniqueDates.length }} kun)
        </v-alert>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-5">
        <v-spacer />
        <v-btn variant="text" @click="close">Bekor qilish</v-btn>
        <v-btn
          color="primary"
          :loading="saving"
          :disabled="!canSave"
          prepend-icon="mdi-content-save"
          @click="save"
        >
          Saqlash ({{ preview.length }} ta)
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import dayjs from 'dayjs'
import { useTabelStore, CODE_LABELS, CODE_COLORS, MANUAL_CODES } from '@/stores/tabel'

interface EmployeeItem {
  id: number
  label: string
}

const props = defineProps<{
  modelValue: boolean
  employees: EmployeeItem[]
  holidays: number[]
  year: number
  month: number
}>()

const emit = defineEmits<{
  'update:modelValue': [val: boolean]
  saved: []
}>()

const store = useTabelStore()
const saving = ref(false)

const form = ref({
  employeeIds: [null as number | null],
  dateFrom: '',
  dateTo: '',
  skipWeekends: true,
  skipHolidays: true,
  code: '',
  hours: 8 as number,
  document_type: null as string | null,
  document_number: null as string | null,
  document_date: null as string | null,
  note: null as string | null,
})

const documentTypes = [
  { label: 'Varaqqa', value: 'varaqqa' },
  { label: 'Buyruq', value: 'buyruq' },
  { label: 'Ariza', value: 'ariza' },
  { label: 'Sertifikat', value: 'sertifikat' },
  { label: 'Boshqa', value: 'boshqa' },
]

const uniqueDates = computed(() => {
  if (!form.value.dateFrom || !form.value.dateTo || !form.value.code) return []
  const dates: string[] = []
  let cur = dayjs(form.value.dateFrom)
  const end = dayjs(form.value.dateTo)
  while (!cur.isAfter(end)) {
    const day = cur.date()
    const isWeekend = cur.day() === 0 || cur.day() === 6
    const isHoliday = props.holidays.includes(day) && cur.month() + 1 === props.month && cur.year() === props.year
    if (!(form.value.skipWeekends && isWeekend) && !(form.value.skipHolidays && isHoliday)) {
      dates.push(cur.format('YYYY-MM-DD'))
    }
    cur = cur.add(1, 'day')
  }
  return dates
})

const preview = computed(() => {
  const entries: any[] = []
  for (const empId of form.value.employeeIds.filter(Boolean)) {
    for (const date of uniqueDates.value) {
      entries.push({ employee_id: empId, work_date: date })
    }
  }
  return entries
})

const canSave = computed(() =>
  form.value.employeeIds.some(Boolean) &&
  form.value.dateFrom &&
  form.value.dateTo &&
  form.value.code &&
  preview.value.length > 0
)

function addEmployee() {
  form.value.employeeIds.push(null)
}

function removeEmployee(idx: number) {
  form.value.employeeIds.splice(idx, 1)
}

async function save() {
  if (!canSave.value) return
  saving.value = true
  try {
    const entries = []
    for (const empId of form.value.employeeIds.filter(Boolean)) {
      for (const date of uniqueDates.value) {
        entries.push({
          employee_id: empId,
          work_date: date,
          code: form.value.code,
          hours: form.value.hours,
          days: 1,
          note: form.value.note,
          document_number: form.value.document_number,
          document_date: form.value.document_date,
          document_type: form.value.document_type,
        })
      }
    }
    await store.bulkSave(entries)
    emit('saved')
    close()
  } finally {
    saving.value = false
  }
}

function close() {
  emit('update:modelValue', false)
}
</script>
