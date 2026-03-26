<template>
  <v-app>
    <!-- Sidebar -->
    <v-navigation-drawer
      v-model="drawer"
      :rail="rail"
      permanent
      :color="sidebarBg"
      width="260"
    >
      <!-- Logo -->
      <div class="d-flex align-center px-4 py-3" style="min-height: 64px;">
        <img src="/logo.svg" :style="rail ? 'height:32px;width:auto;' : 'height:40px;width:auto;'" />
        <div v-if="!rail" class="ml-3">
          <div class="text-white font-weight-bold text-body-1" style="line-height:1.2;">Urganchtransgaz</div>
          <div class="text-caption" style="color:#B8D4E8;">HR Tizimi</div>
        </div>
      </div>

      <v-divider :color="sidebarDivider" />

      <v-list density="compact" nav class="px-2 mt-2">
        <!-- Dashboard -->
        <v-list-item
          v-for="item in navItems"
          :key="item.title"
          :prepend-icon="item.icon"
          :title="item.title"
          :to="item.to"
          :value="item.to"
          active-class="sidebar-active-item"
          :nav="!rail"
          rounded="lg"
          class="mb-1 sidebar-nav-item"
        >
          <template v-if="(item as any).badge" #append>
            <v-badge :content="(item as any).badge" color="error" />
          </template>
        </v-list-item>

        <!-- Xizmat Safarlar group -->
        <v-list-group value="trips" :fluid="false">
          <template #activator="{ props }">
            <v-list-item
              v-bind="props"
              prepend-icon="mdi-airplane"
              title="Xizmat Safarlar"
              class="sidebar-nav-item"
            />
          </template>
          <v-list-item
            v-for="child in tripItems"
            :key="child.title"
            :prepend-icon="child.icon"
            :title="child.title"
            :to="child.to"
            :value="child.to"
            active-class="sidebar-active-item"
            class="sidebar-nav-item pl-8"
            rounded="lg"
          />
        </v-list-group>

        <!-- Keldi-Ketdi group -->
        <v-list-group value="attendance">
          <template #activator="{ props }">
            <v-list-item
              v-bind="props"
              prepend-icon="mdi-clock-check-outline"
              title="Keldi-Ketdi"
              class="sidebar-nav-item"
            />
          </template>
          <v-list-item
            v-for="child in attendanceItems"
            :key="child.title"
            :prepend-icon="child.icon"
            :title="child.title"
            :to="child.to"
            :value="child.to"
            active-class="sidebar-active-item"
            class="sidebar-nav-item pl-8"
            rounded="lg"
          />
        </v-list-group>
      </v-list>

      <template #append>
        <v-divider :color="sidebarDivider" class="mb-2" />
        <v-list density="compact" nav class="px-2 pb-2">
          <v-list-item
            prepend-icon="mdi-cog-outline"
            title="Sozlamalar"
            to="/settings"
            active-class="sidebar-active-item"
            class="sidebar-nav-item"
            rounded="lg"
          />
          <v-list-item
            prepend-icon="mdi-logout"
            title="Chiqish"
            class="sidebar-nav-item"
            rounded="lg"
            @click="handleLogout"
          />
        </v-list>
      </template>
    </v-navigation-drawer>

    <!-- Top Bar -->
    <v-app-bar elevation="0" border="b" color="white" height="64">
      <v-app-bar-nav-icon @click="toggleDrawer" />

      <v-breadcrumbs :items="breadcrumbs" class="text-caption" />

      <v-spacer />

      <!-- Last sync info -->
      <span class="text-caption text-medium-emphasis mr-4">
        <v-icon size="14" class="mr-1">mdi-sync</v-icon>
        Oxirgi yangilanish: {{ lastSyncTime }}
      </span>

      <!-- User menu -->
      <v-menu>
        <template #activator="{ props }">
          <v-btn v-bind="props" variant="text" class="mr-2">
            <v-avatar size="32" color="primary" class="mr-2">
              <span class="text-caption text-white font-weight-bold">
                {{ userInitials }}
              </span>
            </v-avatar>
            <span class="text-body-2">{{ authStore.user?.name }}</span>
            <v-icon size="18" class="ml-1">mdi-chevron-down</v-icon>
          </v-btn>
        </template>
        <v-list density="compact" min-width="180">
          <v-list-item prepend-icon="mdi-account" title="Profil" />
          <v-divider />
          <v-list-item prepend-icon="mdi-logout" title="Chiqish" @click="handleLogout" />
        </v-list>
      </v-menu>
    </v-app-bar>

    <!-- Main Content -->
    <v-main :style="{ background: '#F5F7FA' }">
      <v-container fluid class="pa-6">
        <router-view />
      </v-container>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import dayjs from 'dayjs'
import 'dayjs/locale/uz'

dayjs.locale('uz')

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

const drawer = ref(true)
const rail = ref(false)
const lastSyncTime = ref('hali yo\'q')

const sidebarBg = '#0D1B2A'
const sidebarDivider = '#1A3A5C'

const navItems = computed(() => {
  const items = [
    { title: 'Dashboard', icon: 'mdi-view-dashboard-outline', to: '/dashboard' },
    { title: 'Xodimlar', icon: 'mdi-account-group-outline', to: '/employees' },
  ]
  // Tashkilotlar faqat super_admin va hr_manager uchun
  if (authStore.isSuperAdmin || authStore.isHrManager) {
    items.push({ title: 'Tashkilotlar', icon: 'mdi-office-building-outline', to: '/organizations' })
  }
  items.push(
    { title: 'Qurilmalar', icon: 'mdi-cellphone-lock', to: '/devices' },
    { title: 'Hisobotlar', icon: 'mdi-chart-bar', to: '/reports' },
  )
  return items
})

const attendanceItems = [
  { title: 'Bugungi holat', icon: 'mdi-calendar-today', to: '/attendance/today' },
  { title: 'Kunlik hisobot', icon: 'mdi-calendar-check', to: '/attendance/daily' },
  { title: 'Oylik hisobot', icon: 'mdi-table-large', to: '/attendance/monthly' },
  { title: 'Oylik tabel', icon: 'mdi-table-edit', to: '/attendance/tabel' },
]

const tripItems = [
  { title: 'Barcha safarlar', icon: 'mdi-format-list-bulleted', to: '/business-trips' },
  { title: 'Yangi safari', icon: 'mdi-plus-circle-outline', to: '/business-trips/new' },
]

const userInitials = computed(() => {
  const name = authStore.user?.name || ''
  return name.split(' ').map((n: string) => n[0]).join('').slice(0, 2).toUpperCase()
})

const breadcrumbs = computed(() => {
  return [
    { title: 'Bosh sahifa', to: '/dashboard' },
    { title: route.meta.title as string || route.name as string || '' },
  ]
})

function toggleDrawer() {
  if (window.innerWidth < 960) {
    drawer.value = !drawer.value
  } else {
    rail.value = !rail.value
  }
}

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}

onMounted(() => {
  authStore.fetchMe()
  lastSyncTime.value = dayjs().format('HH:mm')
})
</script>

<style>
.sidebar-nav-item {
  color: #B8D4E8 !important;
}
.sidebar-nav-item:hover {
  background-color: #152F4E !important;
  color: white !important;
}
.sidebar-active-item {
  background-color: #1A3A5C !important;
  color: white !important;
  border-left: 3px solid #2196F3;
}
</style>
