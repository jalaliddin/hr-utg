<template>
  <v-app>
    <v-main style="background: linear-gradient(135deg, #0D1B2A 0%, #1565C0 100%);">
      <v-container class="fill-height" fluid>
        <v-row align="center" justify="center">
          <v-col cols="12" sm="8" md="5" lg="4">
            <v-card elevation="8" rounded="xl" class="pa-6">
              <!-- Logo -->
              <div class="text-center mb-6">
                <img src="/logo.svg" style="height: 72px; width: auto;" />
                <div class="text-body-2 text-medium-emphasis mt-3">HR Boshqaruv Tizimi</div>
              </div>

              <v-form @submit.prevent="handleLogin">
                <v-text-field
                  v-model="form.email"
                  label="Email manzil"
                  prepend-inner-icon="mdi-email-outline"
                  type="email"
                  variant="outlined"
                  density="comfortable"
                  class="mb-3"
                  :error-messages="errors.email"
                  autocomplete="email"
                />

                <v-text-field
                  v-model="form.password"
                  label="Parol"
                  prepend-inner-icon="mdi-lock-outline"
                  :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                  :type="showPassword ? 'text' : 'password'"
                  variant="outlined"
                  density="comfortable"
                  class="mb-4"
                  :error-messages="errors.password"
                  autocomplete="current-password"
                  @click:append-inner="showPassword = !showPassword"
                />

                <v-alert
                  v-if="errorMessage"
                  type="error"
                  variant="tonal"
                  density="compact"
                  class="mb-4"
                  closable
                  @click:close="errorMessage = ''"
                >
                  {{ errorMessage }}
                </v-alert>

                <v-btn
                  type="submit"
                  color="primary"
                  size="large"
                  block
                  :loading="loading"
                  elevation="0"
                >
                  Kirish
                </v-btn>
              </v-form>

            </v-card>
          </v-col>
        </v-row>
      </v-container>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()

const form = ref({ email: '', password: '' })
const errors = ref<Record<string, string[]>>({})
const errorMessage = ref('')
const loading = ref(false)
const showPassword = ref(false)

async function handleLogin() {
  loading.value = true
  errors.value = {}
  errorMessage.value = ''

  try {
    await authStore.login(form.value.email, form.value.password)
    router.push('/dashboard')
  } catch (err: any) {
    if (err.response?.data?.errors) {
      errors.value = err.response.data.errors
    } else {
      errorMessage.value = err.response?.data?.message || 'Kirish mumkin bo\'lmadi'
    }
  } finally {
    loading.value = false
  }
}
</script>
