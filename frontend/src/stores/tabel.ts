import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/api/axios'
import dayjs from 'dayjs'

export interface TabelEntry {
  id: number
  employee_id: number
  work_date: string
  code: string
  hours: number | null
  days: number | null
  note: string | null
  document_number: string | null
  document_type: string | null
  is_approved: boolean
  source: string
}

export interface TabelCell {
  entry: TabelEntry | null
  device: { status: string; first_entry: string | null; last_exit: string | null; hours: number } | null
  is_holiday: boolean
  is_weekend: boolean
}

export interface TabelRow {
  employee: { id: number; full_name: string; department: string | null; position: string | null }
  cells: Record<number, TabelCell>
  summary: Record<string, number>
}

export const CODE_LABELS: Record<string, string> = {
  'Б': 'Mehnatga layoqatsizlik',
  'К': 'Xizmat safari',
  'О': 'Asosiy mehnat ta\'tili',
  'Р': 'Homiladorlik ta\'tili',
  'ЧБ': 'Bola parvarishlash',
  'У': 'O\'quv ta\'tili',
  'А': 'Ma\'muriyat ruxsati',
  'Я': 'Haqiqatda ishlangan',
  'С': 'Ortiqcha ish vaqti',
  'В': 'Kechki ish (18-22)',
  'Н': 'Tungi ish (22-06)',
  'П': 'Bayram/dam olish kuni',
}

export const CODE_COLORS: Record<string, { bg: string; text: string; border: string }> = {
  'Б':  { bg: '#FFEBEE', text: '#C62828', border: '#EF9A9A' },
  'К':  { bg: '#E3F2FD', text: '#1565C0', border: '#90CAF9' },
  'О':  { bg: '#E8F5E9', text: '#2E7D32', border: '#A5D6A7' },
  'Р':  { bg: '#FCE4EC', text: '#880E4F', border: '#F48FB1' },
  'ЧБ': { bg: '#F3E5F5', text: '#6A1B9A', border: '#CE93D8' },
  'У':  { bg: '#E0F7FA', text: '#00695C', border: '#80DEEA' },
  'А':  { bg: '#FFF3E0', text: '#E65100', border: '#FFCC80' },
  'Я':  { bg: '#E1F5FE', text: '#01579B', border: '#81D4FA' },
  'С':  { bg: '#FBE9E7', text: '#BF360C', border: '#FFAB91' },
  'В':  { bg: '#EFEBE9', text: '#4E342E', border: '#BCAAA4' },
  'Н':  { bg: '#E8EAF6', text: '#283593', border: '#9FA8DA' },
  'П':  { bg: '#F9FBE7', text: '#558B2F', border: '#DCE775' },
}

export const ABSENT_CODES = ['Б', 'К', 'О', 'Р', 'ЧБ', 'У', 'А']
export const WORKED_CODES = ['Я', 'С', 'В', 'Н', 'П']
export const ALL_CODES = [...ABSENT_CODES, ...WORKED_CODES]

/** Qo'lda kiritiladigan kodlar (К va Я avtomatik) */
export const MANUAL_ABSENT_CODES = ['Б', 'Р', 'ЧБ', 'У', 'А']
export const MANUAL_WORKED_CODES = ['С', 'В', 'Н', 'П']
export const MANUAL_CODES = [...MANUAL_ABSENT_CODES, ...MANUAL_WORKED_CODES]

/** Avtomatik to'ldiriladigan kodlar */
export const AUTO_CODES = ['К', 'Я', 'О']

export const useTabelStore = defineStore('tabel', () => {
  const selectedOrg = ref<number | null>(null)
  const selectedYear = ref(dayjs().year())
  const selectedMonth = ref(dayjs().month() + 1)
  const rows = ref<TabelRow[]>([])
  const daysInMonth = ref(30)
  const holidays = ref<number[]>([])
  const loading = ref(false)
  const saving = ref(false)

  const isEmpty = computed(() => rows.value.length === 0)

  async function loadTabel(orgId: number, year: number, month: number, department?: string | null) {
    selectedOrg.value = orgId
    selectedYear.value = year
    selectedMonth.value = month
    loading.value = true
    try {
      const res = await api.get('/attendance/tabel', {
        params: { organization_id: orgId, year, month, department: department ?? undefined },
      })
      rows.value = res.data.rows
      daysInMonth.value = res.data.days_in_month
      holidays.value = res.data.holidays
    } finally {
      loading.value = false
    }
  }

  async function saveEntry(payload: {
    employee_id: number
    work_date: string
    code: string
    hours?: number | null
    days?: number | null
    note?: string | null
    document_number?: string | null
    document_date?: string | null
    document_type?: string | null
  }): Promise<TabelEntry> {
    saving.value = true
    try {
      const res = await api.post('/attendance/entries', payload)
      return res.data
    } finally {
      saving.value = false
    }
  }

  async function updateEntry(id: number, payload: Partial<TabelEntry>): Promise<TabelEntry> {
    saving.value = true
    try {
      const res = await api.put(`/attendance/entries/${id}`, payload)
      return res.data
    } finally {
      saving.value = false
    }
  }

  async function deleteEntry(id: number): Promise<void> {
    await api.delete(`/attendance/entries/${id}`)
  }

  async function bulkSave(entries: any[]): Promise<{ saved: number }> {
    saving.value = true
    try {
      const res = await api.post('/attendance/entries/bulk', { entries })
      return res.data
    } finally {
      saving.value = false
    }
  }

  /** Lokalda row ni yangilash (server qaytarishidan keyin) */
  function updateCellLocally(employeeId: number, day: number, entry: TabelEntry | null) {
    const row = rows.value.find(r => r.employee.id === employeeId)
    if (!row) return
    if (!row.cells[day]) {
      row.cells[day] = { entry: null, device: null, is_holiday: false, is_weekend: false }
    }
    row.cells[day].entry = entry
  }

  return {
    selectedOrg, selectedYear, selectedMonth,
    rows, daysInMonth, holidays, loading, saving, isEmpty,
    loadTabel, saveEntry, updateEntry, deleteEntry, bulkSave, updateCellLocally,
  }
})
