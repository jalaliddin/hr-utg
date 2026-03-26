import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      component: () => import('@/layouts/DefaultLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: '/dashboard' },
        { path: 'dashboard', name: 'dashboard', component: () => import('@/views/DashboardView.vue') },
        { path: 'organizations', name: 'organizations', component: () => import('@/views/OrganizationsView.vue') },
        { path: 'employees', name: 'employees', component: () => import('@/views/EmployeesView.vue') },
        { path: 'employees/:id', name: 'employee-detail', component: () => import('@/views/EmployeeDetailView.vue') },
        { path: 'attendance/today', name: 'attendance-today', component: () => import('@/views/AttendanceTodayView.vue') },
        { path: 'attendance/daily', name: 'attendance-daily', component: () => import('@/views/AttendanceDailyView.vue') },
        { path: 'attendance/monthly', name: 'attendance-monthly', component: () => import('@/views/AttendanceMonthlyView.vue') },
        { path: 'attendance/tabel', name: 'attendance-tabel', component: () => import('@/views/TabelView.vue'), meta: { title: 'Oylik Tabel' } },
        { path: 'business-trips', name: 'business-trips', component: () => import('@/views/BusinessTripsView.vue') },
        { path: 'business-trips/new', name: 'business-trip-new', component: () => import('@/views/BusinessTripFormView.vue') },
        { path: 'business-trips/:id', name: 'business-trip-detail', component: () => import('@/views/BusinessTripDetailView.vue') },
        { path: 'business-trips/:id/edit', name: 'business-trip-edit', component: () => import('@/views/BusinessTripFormView.vue') },
        { path: 'devices', name: 'devices', component: () => import('@/views/DevicesView.vue') },
        { path: 'reports', name: 'reports', component: () => import('@/views/ReportsView.vue') },
        { path: 'settings', name: 'settings', component: () => import('@/views/SettingsView.vue') },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.guest && authStore.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
