<template>
  <div>
    <div class="text-h5 font-weight-bold mb-6">Dashboard</div>

    <!-- Stats Cards -->
    <v-row class="mb-6">
      <v-col v-for="stat in statsCards" :key="stat.title" cols="12" sm="6" lg="3">
        <v-card rounded="xl" elevation="0" border>
          <v-card-text class="pa-5">
            <div class="d-flex align-center justify-space-between mb-3">
              <v-icon :color="stat.color" size="36" :icon="stat.icon" />
              <v-chip :color="stat.color" variant="tonal" size="small">
                {{ stat.badge }}
              </v-chip>
            </div>
            <div class="text-h4 font-weight-bold">{{ stat.value }}</div>
            <div class="text-body-2 text-medium-emphasis mt-1">{{ stat.title }}</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row>
      <!-- Organizations attendance today -->
      <v-col cols="12" lg="8">
        <v-card rounded="xl" elevation="0" border>
          <v-card-title class="pa-5 pb-0">
            <div class="text-subtitle-1 font-weight-bold">Bugungi holat — tashkilotlar bo'yicha</div>
          </v-card-title>
          <v-card-text class="pa-5">
            <div v-if="loadingToday" class="text-center py-8">
              <v-progress-circular indeterminate color="primary" />
            </div>
            <div v-else>
              <div
                v-for="org in todayData"
                :key="org.id"
                class="mb-4"
              >
                <div class="d-flex justify-space-between align-center mb-1">
                  <span class="text-body-2 font-weight-medium">{{ org.name }}</span>
                  <span class="text-caption text-medium-emphasis">
                    {{ org.present }}/{{ org.total_employees }}
                    ({{ org.attendance_rate }}%)
                  </span>
                </div>
                <v-progress-linear
                  :model-value="org.attendance_rate"
                  :color="org.attendance_rate >= 90 ? 'success' : org.attendance_rate >= 70 ? 'warning' : 'error'"
                  rounded
                  height="8"
                  bg-color="grey-lighten-3"
                />
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Last syncs -->
      <v-col cols="12" lg="4">
        <v-card rounded="xl" elevation="0" border>
          <v-card-title class="pa-5 pb-0">
            <div class="text-subtitle-1 font-weight-bold">Oxirgi sinxronizatsiyalar</div>
          </v-card-title>
          <v-card-text class="pa-5">
            <div v-if="loadingStats" class="text-center py-8">
              <v-progress-circular indeterminate color="primary" />
            </div>
            <div v-else>
              <v-list density="compact" class="pa-0">
                <v-list-item
                  v-for="sync in stats?.last_syncs"
                  :key="sync.id"
                  class="px-0"
                >
                  <template #prepend>
                    <v-icon
                      :color="sync.status === 'success' ? 'success' : 'error'"
                      size="20"
                    >
                      {{ sync.status === 'success' ? 'mdi-check-circle' : 'mdi-close-circle' }}
                    </v-icon>
                  </template>
                  <v-list-item-title class="text-body-2">
                    {{ sync.device?.name }}
                  </v-list-item-title>
                  <v-list-item-subtitle class="text-caption">
                    {{ formatTime(sync.sync_started_at) }}
                    · {{ sync.records_new }} yangi
                  </v-list-item-subtitle>
                </v-list-item>
                <div v-if="!stats?.last_syncs?.length" class="text-caption text-medium-emphasis text-center py-4">
                  Hali sinxronizatsiya bo'lmagan
                </div>
              </v-list>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/api/axios'
import dayjs from 'dayjs'

const stats = ref<any>(null)
const todayData = ref<any[]>([])
const loadingStats = ref(true)
const loadingToday = ref(true)

const statsCards = computed(() => {
  if (!stats.value) return []
  return [
    {
      title: 'Jami xodimlar',
      value: stats.value.total_employees,
      icon: 'mdi-account-group',
      color: 'primary',
      badge: 'Barcha',
    },
    {
      title: 'Bugun keldi',
      value: stats.value.today.present,
      icon: 'mdi-account-check',
      color: 'success',
      badge: stats.value.today.attendance_rate + '%',
    },
    {
      title: 'Xizmat safarida',
      value: stats.value.today.business_trip,
      icon: 'mdi-airplane',
      color: 'info',
      badge: 'Aktiv',
    },
    {
      title: 'Online qurilmalar',
      value: stats.value.devices.online,
      icon: 'mdi-cellphone-lock',
      color: stats.value.devices.offline > 0 ? 'warning' : 'success',
      badge: `${stats.value.devices.offline} offline`,
    },
  ]
})

function formatTime(time: string) {
  return dayjs(time).format('DD.MM HH:mm')
}

onMounted(async () => {
  const [statsRes, todayRes] = await Promise.all([
    api.get('/dashboard/stats').finally(() => (loadingStats.value = false)),
    api.get('/dashboard/today').finally(() => (loadingToday.value = false)),
  ])
  stats.value = statsRes.data
  todayData.value = todayRes.data
})
</script>
