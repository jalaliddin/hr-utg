<template>
  <div>
    <div class="text-h5 font-weight-bold mb-6">Sozlamalar</div>

    <v-tabs v-model="activeTab" class="mb-4">
      <v-tab v-if="authStore.isSuperAdmin || authStore.isHrManager" value="users">Foydalanuvchilar</v-tab>
      <v-tab value="departments">Bo'lim va Lavozimlar</v-tab>
      <v-tab value="schedules">Ish jadvali</v-tab>
      <v-tab value="directors">Rahbarlar</v-tab>
    </v-tabs>

    <v-window v-model="activeTab">
      <!-- ============================================================ -->
      <!-- TAB: Users                                                    -->
      <!-- ============================================================ -->
      <v-window-item v-if="authStore.isSuperAdmin || authStore.isHrManager" value="users">
        <v-card rounded="xl" elevation="0" border>
          <v-card-title class="pa-6 pb-0 d-flex align-center justify-space-between">
            <span>Foydalanuvchilar</span>
            <v-btn color="primary" prepend-icon="mdi-plus" size="small" @click="openAddDialog">
              Foydalanuvchi qo'shish
            </v-btn>
          </v-card-title>

          <v-data-table
            :headers="headers"
            :items="users"
            :loading="loading"
            density="comfortable"
            hover
          >
            <template #item.name="{ item }">
              <div class="d-flex align-center gap-3 py-1">
                <v-avatar size="32" color="primary">
                  <span class="text-caption text-white font-weight-bold">{{ initials(item.name) }}</span>
                </v-avatar>
                <div>
                  <div class="text-body-2 font-weight-medium">{{ item.name }}</div>
                  <div class="text-caption text-medium-emphasis">{{ item.email }}</div>
                </div>
              </div>
            </template>

            <template #item.role="{ item }">
              <v-chip :color="roleColor(item.role)" size="small" variant="tonal">
                {{ roleLabel(item.role) }}
              </v-chip>
            </template>

            <template #item.organization="{ item }">
              <span class="text-body-2">{{ item.organization?.name ?? '—' }}</span>
            </template>

            <template #item.created_at="{ item }">
              <span class="text-caption">{{ formatDate(item.created_at) }}</span>
            </template>

            <template #item.actions="{ item }">
              <div class="d-flex gap-1">
                <v-btn icon="mdi-pencil" variant="text" size="small" color="secondary" @click="openEditDialog(item)" />
                <v-btn icon="mdi-delete" variant="text" size="small" color="error" @click="confirmDelete(item)" />
              </div>
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>

      <!-- ============================================================ -->
      <!-- TAB: Departments & Positions                                  -->
      <!-- ============================================================ -->
      <v-window-item value="departments">
        <v-row>
          <!-- Left: Department list -->
          <v-col cols="12" md="5">
            <v-card rounded="xl" elevation="0" border>
              <v-card-title class="pa-4 pb-2 d-flex align-center justify-space-between">
                <span class="text-body-1 font-weight-semibold">Bo'limlar</span>
                <v-btn
                  color="primary"
                  size="small"
                  prepend-icon="mdi-plus"
                  @click="openDeptDialog()"
                >
                  Bo'lim qo'shish
                </v-btn>
              </v-card-title>

              <!-- Org filter for departments (super_admin / hr_manager only) -->
              <v-card-text v-if="!authStore.isOrgAdmin" class="pa-4 pb-2">
                <v-select
                  v-model="deptOrgFilter"
                  :items="[{ name: 'Barcha tashkilotlar', id: null }, ...organizations]"
                  item-title="name"
                  item-value="id"
                  label="Tashkilot bo'yicha filtrlash"
                  variant="outlined"
                  density="compact"
                  hide-details
                  clearable
                  @update:model-value="onDeptOrgFilterChange"
                />
              </v-card-text>

              <v-divider />

              <v-list v-if="deptList.length > 0" lines="one" density="compact">
                <v-list-item
                  v-for="dept in deptList"
                  :key="dept.id"
                  :active="selectedDept?.id === dept.id"
                  active-color="primary"
                  rounded="lg"
                  class="mx-2 my-1"
                  @click="selectDept(dept)"
                >
                  <template #title>
                    <span class="text-body-2">{{ dept.name }}</span>
                  </template>
                  <template #subtitle>
                    <span class="text-caption text-medium-emphasis">{{ dept.organization?.name ?? '' }}</span>
                  </template>
                  <template #append>
                    <div class="d-flex gap-1" @click.stop>
                      <v-btn
                        icon="mdi-pencil"
                        variant="text"
                        size="x-small"
                        color="secondary"
                        @click="openDeptDialog(dept)"
                      />
                      <v-btn
                        icon="mdi-delete"
                        variant="text"
                        size="x-small"
                        color="error"
                        @click="confirmDeleteDept(dept)"
                      />
                    </div>
                  </template>
                </v-list-item>
              </v-list>

              <v-card-text v-else-if="loadingDeptList" class="text-center pa-6">
                <v-progress-circular indeterminate color="primary" size="32" />
              </v-card-text>

              <v-card-text v-else class="text-center text-medium-emphasis pa-6">
                Bo'limlar topilmadi
              </v-card-text>
            </v-card>
          </v-col>

          <!-- Right: Positions panel -->
          <v-col cols="12" md="7">
            <v-card rounded="xl" elevation="0" border>
              <template v-if="selectedDept">
                <v-card-title class="pa-4 pb-2 d-flex align-center justify-space-between">
                  <span class="text-body-1 font-weight-semibold">
                    {{ selectedDept.name }} — Lavozimlar
                  </span>
                  <v-btn
                    color="primary"
                    size="small"
                    prepend-icon="mdi-plus"
                    @click="openPosDialog()"
                  >
                    Lavozim qo'shish
                  </v-btn>
                </v-card-title>

                <v-divider />

                <v-list v-if="positionList.length > 0" lines="one" density="compact">
                  <v-list-item
                    v-for="pos in positionList"
                    :key="pos.id"
                    rounded="lg"
                    class="mx-2 my-1"
                  >
                    <template #title>
                      <span class="text-body-2">{{ pos.name }}</span>
                    </template>
                    <template #append>
                      <div class="d-flex gap-1">
                        <v-btn
                          icon="mdi-pencil"
                          variant="text"
                          size="x-small"
                          color="secondary"
                          @click="openPosDialog(pos)"
                        />
                        <v-btn
                          icon="mdi-delete"
                          variant="text"
                          size="x-small"
                          color="error"
                          @click="confirmDeletePos(pos)"
                        />
                      </div>
                    </template>
                  </v-list-item>
                </v-list>

                <v-card-text v-else-if="loadingPositions" class="text-center pa-6">
                  <v-progress-circular indeterminate color="primary" size="32" />
                </v-card-text>

                <v-card-text v-else class="text-center text-medium-emphasis pa-6">
                  Bu bo'limda lavozimlar yo'q
                </v-card-text>
              </template>

              <template v-else>
                <v-card-text class="text-center text-medium-emphasis pa-10">
                  <v-icon size="48" color="grey-lighten-1" class="mb-3">mdi-office-building-outline</v-icon>
                  <div>Lavozimlarni ko'rish uchun bo'limni tanlang</div>
                </v-card-text>
              </template>
            </v-card>
          </v-col>
        </v-row>
      </v-window-item>

      <!-- ============================================================ -->
      <!-- TAB: Ish jadvali                                             -->
      <!-- ============================================================ -->
      <v-window-item value="schedules">
        <!-- Davomat belgilari -->
        <v-card rounded="xl" elevation="0" border class="mb-4">
          <v-card-title class="pa-6 pb-3">Davomat belgilari</v-card-title>
          <v-card-text class="pt-0 pb-5">
            <v-row dense>
              <v-col
                v-for="s in attendanceStatuses"
                :key="s.code"
                cols="6" sm="4" md="2"
              >
                <div class="d-flex align-center gap-3 pa-3 rounded-lg" style="border:1px solid rgba(0,0,0,.08)">
                  <v-chip :color="s.color" variant="tonal" size="small" class="font-weight-bold">
                    {{ s.code }}
                  </v-chip>
                  <span class="text-body-2">{{ s.label }}</span>
                </div>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <v-card rounded="xl" elevation="0" border>
          <v-card-title class="pa-6 pb-0 d-flex align-center justify-space-between">
            <span>Ish jadvallari</span>
            <v-btn color="primary" prepend-icon="mdi-plus" size="small" @click="openScheduleDialog()">
              Jadval qo'shish
            </v-btn>
          </v-card-title>

          <v-data-table
            :headers="scheduleHeaders"
            :items="schedules"
            :loading="loadingSchedules"
            density="comfortable"
            hover
          >
            <template #item.organization="{ item }">
              <span class="text-body-2">{{ item.organization?.name ?? '—' }}</span>
            </template>

            <template #item.work_time="{ item }">
              <span class="text-body-2 font-weight-medium">
                {{ fmtTime(item.work_start) }} – {{ fmtTime(item.work_end) }}
              </span>
            </template>

            <template #item.late_rule="{ item }">
              <div>
                <span class="text-caption text-medium-emphasis">kechikish: {{ item.late_tolerance_minutes ?? 15 }} daq →</span>
                <v-chip size="x-small" color="warning" variant="tonal" class="ml-1">
                  {{ lateThreshold(item) }} dan keyin
                </v-chip>
              </div>
            </template>

            <template #item.work_days="{ item }">
              <div class="d-flex gap-1 flex-wrap">
                <v-chip
                  v-for="d in (item.work_days ?? [1,2,3,4,5,6])"
                  :key="d"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >
                  {{ dayName(d) }}
                </v-chip>
              </div>
            </template>

            <template #item.is_default="{ item }">
              <v-chip v-if="item.is_default" size="x-small" color="success" variant="tonal">
                Asosiy
              </v-chip>
            </template>

            <template #item.actions="{ item }">
              <div class="d-flex gap-1">
                <v-btn icon="mdi-pencil" variant="text" size="small" color="secondary" @click="openScheduleDialog(item)" />
                <v-btn icon="mdi-delete" variant="text" size="small" color="error" @click="confirmDeleteSchedule(item)" />
              </div>
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>

      <!-- ============================================================ -->
      <!-- TAB: Directors                                                -->
      <!-- ============================================================ -->
      <v-window-item value="directors">
        <v-row class="mb-4">
          <v-col v-if="!authStore.isOrgAdmin" cols="12" md="4">
            <v-select
              v-model="directorOrgFilter"
              :items="organizations"
              item-title="name"
              item-value="id"
              label="Tashkilot"
              variant="outlined"
              density="compact"
              clearable
              hide-details
              @update:model-value="fetchDirectors"
            />
          </v-col>
          <v-col cols="12" :md="authStore.isOrgAdmin ? 12 : 8" class="d-flex align-center justify-end">
            <v-btn
              color="primary"
              prepend-icon="mdi-plus"
              size="small"
              :disabled="!directorOrgFilter"
              @click="openDirectorDialog(null)"
            >
              Rahbar qo'shish
            </v-btn>
          </v-col>
        </v-row>

        <v-card rounded="xl" elevation="0" border>
          <v-data-table
            :headers="directorHeaders"
            :items="directors"
            :loading="loadingDirectors"
            density="comfortable"
            hover
          >
            <template #item.full_name="{ item }">
              <div class="text-body-2 font-weight-medium">{{ item.full_name }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.short_name }}</div>
            </template>
            <template #item.is_active="{ item }">
              <v-chip :color="item.is_active ? 'success' : 'grey'" size="small" variant="tonal">
                {{ item.is_active ? 'Aktiv' : 'Eski' }}
              </v-chip>
            </template>
            <template #item.appointed_at="{ item }">
              <span class="text-caption">{{ item.appointed_at ? formatDateStr(item.appointed_at) : '—' }}</span>
            </template>
            <template #item.actions="{ item }">
              <div class="d-flex gap-1">
                <v-btn icon="mdi-pencil" variant="text" size="small" color="secondary" @click="openDirectorDialog(item)" />
                <v-btn icon="mdi-delete" variant="text" size="small" color="error" @click="confirmDeleteDirector(item)" />
              </div>
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>
    </v-window>

    <!-- ============================================================ -->
    <!-- Director Add/Edit Dialog                                      -->
    <!-- ============================================================ -->
    <v-dialog v-model="directorDialog" max-width="480" persistent>
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">
          {{ editingDirector ? 'Rahbarni tahrirlash' : "Rahbar qo'shish" }}
        </v-card-title>
        <v-card-text class="pa-6 pt-2">
          <v-form ref="directorFormRef" @submit.prevent="saveDirector">
            <v-row dense>
              <v-col cols="12">
                <v-text-field
                  v-model="directorForm.full_name"
                  label="To'liq ismi *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="directorForm.position"
                  label="Lavozimi *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="directorForm.short_name"
                  label="Qisqa ismi (imzo uchun)"
                  variant="outlined"
                  density="compact"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="directorForm.appointed_at"
                  label="Tayinlangan sana"
                  type="date"
                  variant="outlined"
                  density="compact"
                />
              </v-col>
              <v-col cols="12" md="6" class="d-flex align-center">
                <v-switch
                  v-model="directorForm.is_active"
                  label="Aktiv rahbar"
                  color="success"
                  density="compact"
                  hide-details
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="directorDialog = false">Bekor qilish</v-btn>
          <v-btn color="primary" :loading="savingDirector" @click="saveDirector">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Director Delete Dialog -->
    <v-dialog v-model="deleteDirectorDialog" max-width="400">
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">Rahbarni o'chirish</v-card-title>
        <v-card-text class="pa-6 pt-0">
          <strong>{{ deletingDirector?.full_name }}</strong> ni o'chirishni tasdiqlaysizmi?
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="deleteDirectorDialog = false">Bekor qilish</v-btn>
          <v-btn color="error" :loading="deletingDirectorLoading" @click="deleteDirector">O'chirish</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- ============================================================ -->
    <!-- Schedule Add/Edit Dialog                                      -->
    <!-- ============================================================ -->
    <v-dialog v-model="scheduleDialog" max-width="520" persistent>
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">
          {{ editingSchedule ? 'Jadvalni tahrirlash' : "Jadval qo'shish" }}
        </v-card-title>
        <v-card-text class="pa-6 pt-2">
          <v-form ref="scheduleFormRef" @submit.prevent="saveSchedule">
            <v-row dense>
              <v-col v-if="!authStore.isOrgAdmin" cols="12">
                <v-select
                  v-model="scheduleForm.organization_id"
                  :items="organizations"
                  item-title="name"
                  item-value="id"
                  label="Tashkilot *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Majburiy maydon']"
                  :disabled="!!editingSchedule"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="scheduleForm.name"
                  label="Jadval nomi *"
                  variant="outlined"
                  density="compact"
                  placeholder="Asosiy jadval"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model="scheduleForm.work_start"
                  label="Ish boshlanishi *"
                  variant="outlined"
                  density="compact"
                  type="time"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model="scheduleForm.work_end"
                  label="Ish tugashi *"
                  variant="outlined"
                  density="compact"
                  type="time"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model.number="scheduleForm.lunch_minutes"
                  label="Tushlik (daqiqa)"
                  variant="outlined"
                  density="compact"
                  type="number"
                  min="0"
                  max="120"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model.number="scheduleForm.late_tolerance_minutes"
                  label="Kechikish chegarasi (daqiqa)"
                  variant="outlined"
                  density="compact"
                  type="number"
                  min="0"
                  max="120"
                  :hint="lateTresholdHint"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12">
                <div class="text-body-2 text-medium-emphasis mb-2">Ish kunlari</div>
                <div class="d-flex gap-2 flex-wrap">
                  <v-chip
                    v-for="d in weekDays"
                    :key="d.value"
                    :color="scheduleForm.work_days.includes(d.value) ? 'primary' : 'default'"
                    :variant="scheduleForm.work_days.includes(d.value) ? 'tonal' : 'outlined'"
                    size="small"
                    style="cursor:pointer"
                    @click="toggleDay(d.value)"
                  >
                    {{ d.label }}
                  </v-chip>
                </div>
              </v-col>
              <v-col cols="12" class="mt-2">
                <v-switch
                  v-model="scheduleForm.is_default"
                  label="Asosiy jadval (attendance hisoblashda ishlatiladi)"
                  color="primary"
                  density="compact"
                  hide-details
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="scheduleDialog = false">Bekor qilish</v-btn>
          <v-btn color="primary" :loading="savingSchedule" @click="saveSchedule">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Schedule Delete Dialog -->
    <v-dialog v-model="deleteScheduleDialog" max-width="400">
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">Jadvalni o'chirish</v-card-title>
        <v-card-text class="pa-6 pt-0">
          <strong>{{ deletingSchedule?.name }}</strong> jadvalni o'chirishni tasdiqlaysizmi?
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="deleteScheduleDialog = false">Bekor qilish</v-btn>
          <v-btn color="error" :loading="deletingScheduleLoading" @click="deleteSchedule">O'chirish</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- ============================================================ -->
    <!-- User Add/Edit Dialog                                          -->
    <!-- ============================================================ -->
    <v-dialog v-model="dialog" max-width="500" persistent>
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">
          {{ editingUser ? 'Foydalanuvchini tahrirlash' : "Foydalanuvchi qo'shish" }}
        </v-card-title>
        <v-card-text class="pa-6 pt-2">
          <v-form ref="formRef" @submit.prevent="saveUser">
            <v-row dense>
              <v-col cols="12">
                <v-text-field
                  v-model="form.name"
                  label="Ism *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="form.email"
                  label="Email *"
                  variant="outlined"
                  density="compact"
                  type="email"
                  :rules="[
                    v => !!v || 'Majburiy maydon',
                    v => /.+@.+\..+/.test(v) || 'Email noto\'g\'ri formatda',
                  ]"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="form.password"
                  :label="editingUser ? 'Yangi parol (ixtiyoriy)' : 'Parol *'"
                  variant="outlined"
                  density="compact"
                  :type="showPass ? 'text' : 'password'"
                  :append-inner-icon="showPass ? 'mdi-eye-off' : 'mdi-eye'"
                  :rules="editingUser ? [] : [v => !!v || 'Majburiy maydon']"
                  @click:append-inner="showPass = !showPass"
                />
              </v-col>
              <v-col cols="12">
                <v-select
                  v-model="form.role"
                  :items="roleItems"
                  item-title="label"
                  item-value="value"
                  label="Rol *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="12">
                <v-select
                  v-model="form.organization_id"
                  :items="[{ name: 'Hamma tashkilotlar', id: null }, ...organizations]"
                  item-title="name"
                  item-value="id"
                  label="Tashkilot"
                  variant="outlined"
                  density="compact"
                  clearable
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Bekor qilish</v-btn>
          <v-btn color="primary" :loading="saving" @click="saveUser">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- User Delete Dialog -->
    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">Foydalanuvchini o'chirish</v-card-title>
        <v-card-text class="pa-6 pt-0">
          <strong>{{ deletingUser?.name }}</strong> foydalanuvchisini o'chirishni tasdiqlaysizmi?
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="deleteDialog = false">Bekor qilish</v-btn>
          <v-btn color="error" :loading="deleting" @click="deleteUser">O'chirish</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- ============================================================ -->
    <!-- Department Add/Edit Dialog                                    -->
    <!-- ============================================================ -->
    <v-dialog v-model="deptDialog" max-width="420" persistent>
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">
          {{ editingDept ? "Bo'limni tahrirlash" : "Bo'lim qo'shish" }}
        </v-card-title>
        <v-card-text class="pa-6 pt-2">
          <v-form ref="deptFormRef" @submit.prevent="saveDept">
            <v-row dense>
              <v-col v-if="!authStore.isOrgAdmin" cols="12">
                <v-select
                  v-model="deptForm.organization_id"
                  :items="organizations"
                  item-title="name"
                  item-value="id"
                  label="Tashkilot *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Majburiy maydon']"
                  :disabled="!!editingDept"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="deptForm.name"
                  label="Bo'lim nomi *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="deptDialog = false">Bekor qilish</v-btn>
          <v-btn color="primary" :loading="savingDept" @click="saveDept">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Department Delete Dialog -->
    <v-dialog v-model="deleteDeptDialog" max-width="400">
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">Bo'limni o'chirish</v-card-title>
        <v-card-text class="pa-6 pt-0">
          <strong>{{ deletingDept?.name }}</strong> bo'limini o'chirishni tasdiqlaysizmi?
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="deleteDeptDialog = false">Bekor qilish</v-btn>
          <v-btn color="error" :loading="deletingDeptLoading" @click="deleteDept">O'chirish</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- ============================================================ -->
    <!-- Position Add/Edit Dialog                                      -->
    <!-- ============================================================ -->
    <v-dialog v-model="posDialog" max-width="420" persistent>
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">
          {{ editingPos ? 'Lavozimni tahrirlash' : "Lavozim qo'shish" }}
        </v-card-title>
        <v-card-text class="pa-6 pt-2">
          <v-form ref="posFormRef" @submit.prevent="savePos">
            <v-text-field
              v-model="posForm.name"
              label="Lavozim nomi *"
              variant="outlined"
              density="compact"
              :rules="[v => !!v || 'Majburiy maydon']"
            />
          </v-form>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="posDialog = false">Bekor qilish</v-btn>
          <v-btn color="primary" :loading="savingPos" @click="savePos">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Position Delete Dialog -->
    <v-dialog v-model="deletePosDialog" max-width="400">
      <v-card rounded="xl">
        <v-card-title class="pa-6 pb-2">Lavozimni o'chirish</v-card-title>
        <v-card-text class="pa-6 pt-0">
          <strong>{{ deletingPos?.name }}</strong> lavozimini o'chirishni tasdiqlaysizmi?
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="deletePosDialog = false">Bekor qilish</v-btn>
          <v-btn color="error" :loading="deletingPosLoading" @click="deletePos">O'chirish</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000">
      {{ snackbar.text }}
    </v-snackbar>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/api/axios'
import dayjs from 'dayjs'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

// ============================================================
// Types
// ============================================================
interface UserItem {
  id: number
  name: string
  email: string
  role: string
  organization_id?: number | null
  organization?: { id: number; name: string } | null
  created_at?: string
}

interface DeptItem {
  id: number
  name: string
  organization_id: number
  organization?: { id: number; name: string } | null
}

interface PosItem {
  id: number
  name: string
  department_id: number
}

// ============================================================
// Shared
// ============================================================
const activeTab = ref(
  authStore.isSuperAdmin || authStore.isHrManager ? 'users' : 'departments'
)
const organizations = ref<any[]>([])
const snackbar = ref({ show: false, text: '', color: 'success' })

const attendanceStatuses = [
  { code: 'K',  label: 'Keldi',        color: 'success' },
  { code: 'B',  label: 'Kelmadi',      color: 'error'   },
  { code: 'KK', label: 'Kech keldi',   color: 'warning' },
  { code: 'X',  label: 'Xizmat safari',color: 'info'    },
  { code: 'T',  label: "Ta'til",       color: 'purple'  },
  { code: 'YK', label: 'Yarim kun',    color: 'orange'  },
]

function showSnack(text: string, color = 'success') {
  snackbar.value = { show: true, text, color }
}

// ============================================================
// Users tab
// ============================================================
const users = ref<UserItem[]>([])
const loading = ref(true)
const saving = ref(false)
const deleting = ref(false)
const showPass = ref(false)

const dialog = ref(false)
const deleteDialog = ref(false)
const editingUser = ref<UserItem | null>(null)
const deletingUser = ref<UserItem | null>(null)
const formRef = ref<any>(null)

const roleItems = [
  { label: 'Super Admin', value: 'super_admin' },
  { label: 'Tashkilot Admin', value: 'org_admin' },
  { label: 'HR Menejer', value: 'hr_manager' },
  { label: 'Kuzatuvchi', value: 'viewer' },
]

const headers = [
  { title: 'Foydalanuvchi', key: 'name', sortable: false },
  { title: 'Rol', key: 'role', sortable: false },
  { title: 'Tashkilot', key: 'organization', sortable: false },
  { title: "Ro'yxatdan o'tgan", key: 'created_at' },
  { title: '', key: 'actions', sortable: false, width: '80px' },
]

const defaultForm = () => ({
  name: '',
  email: '',
  password: '',
  role: 'viewer' as string,
  organization_id: null as number | null,
})
const form = ref(defaultForm())

function initials(name: string): string {
  return name.split(' ').slice(0, 2).map(w => w[0] ?? '').join('').toUpperCase()
}

function roleLabel(role: string): string {
  const map: Record<string, string> = {
    super_admin: 'Super Admin',
    org_admin: 'Tashkilot Admin',
    hr_manager: 'HR Menejer',
    viewer: 'Kuzatuvchi',
  }
  return map[role] ?? role
}

function roleColor(role: string): string {
  const map: Record<string, string> = {
    super_admin: 'error',
    org_admin: 'primary',
    hr_manager: 'success',
    viewer: 'secondary',
  }
  return map[role] ?? 'grey'
}

function formatDate(date?: string): string {
  if (!date) return '—'
  return dayjs(date).format('DD.MM.YYYY')
}

function openAddDialog() {
  editingUser.value = null
  form.value = defaultForm()
  showPass.value = false
  dialog.value = true
}

function openEditDialog(user: UserItem) {
  editingUser.value = user
  form.value = {
    name: user.name,
    email: user.email,
    password: '',
    role: user.role,
    organization_id: user.organization_id ?? null,
  }
  showPass.value = false
  dialog.value = true
}

function confirmDelete(user: UserItem) {
  deletingUser.value = user
  deleteDialog.value = true
}

async function saveUser() {
  const { valid } = await formRef.value?.validate()
  if (!valid) return
  saving.value = true
  try {
    const payload: any = { ...form.value }
    if (editingUser.value && !payload.password) delete payload.password

    if (editingUser.value) {
      const res = await api.put(`/users/${editingUser.value.id}`, payload)
      const idx = users.value.findIndex(u => u.id === editingUser.value!.id)
      if (idx !== -1) users.value[idx] = res.data
      showSnack('Foydalanuvchi yangilandi')
    } else {
      const res = await api.post('/users', payload)
      users.value.unshift(res.data)
      showSnack("Foydalanuvchi qo'shildi")
    }
    dialog.value = false
  } catch (e: any) {
    showSnack(e?.response?.data?.message ?? 'Xatolik yuz berdi', 'error')
  } finally {
    saving.value = false
  }
}

async function deleteUser() {
  if (!deletingUser.value) return
  deleting.value = true
  try {
    await api.delete(`/users/${deletingUser.value.id}`)
    users.value = users.value.filter(u => u.id !== deletingUser.value!.id)
    showSnack("Foydalanuvchi o'chirildi")
    deleteDialog.value = false
  } catch {
    showSnack("O'chirishda xatolik", 'error')
  } finally {
    deleting.value = false
  }
}

// ============================================================
// Departments & Positions tab
// ============================================================
const deptList = ref<DeptItem[]>([])
const positionList = ref<PosItem[]>([])
const selectedDept = ref<DeptItem | null>(null)
const deptOrgFilter = ref<number | null>(null)
const loadingDeptList = ref(false)
const loadingPositions = ref(false)

// Department dialog
const deptDialog = ref(false)
const editingDept = ref<DeptItem | null>(null)
const savingDept = ref(false)
const deleteDeptDialog = ref(false)
const deletingDept = ref<DeptItem | null>(null)
const deletingDeptLoading = ref(false)
const deptFormRef = ref<any>(null)
const deptForm = ref({ organization_id: null as number | null, name: '' })

// Position dialog
const posDialog = ref(false)
const editingPos = ref<PosItem | null>(null)
const savingPos = ref(false)
const deletePosDialog = ref(false)
const deletingPos = ref<PosItem | null>(null)
const deletingPosLoading = ref(false)
const posFormRef = ref<any>(null)
const posForm = ref({ name: '' })

async function fetchDepartments() {
  loadingDeptList.value = true
  deptList.value = []
  selectedDept.value = null
  positionList.value = []
  try {
    const params: Record<string, any> = {}
    if (deptOrgFilter.value) params.organization_id = deptOrgFilter.value
    const res = await api.get('/departments', { params })
    deptList.value = res.data.data ?? res.data
  } catch {
    showSnack("Bo'limlarni yuklashda xatolik", 'error')
  } finally {
    loadingDeptList.value = false
  }
}

function onDeptOrgFilterChange() {
  fetchDepartments()
}

async function selectDept(dept: DeptItem) {
  selectedDept.value = dept
  loadingPositions.value = true
  positionList.value = []
  try {
    const res = await api.get(`/departments/${dept.id}/positions`)
    positionList.value = res.data.data ?? res.data
  } catch {
    showSnack("Lavozimlarni yuklashda xatolik", 'error')
  } finally {
    loadingPositions.value = false
  }
}

function openDeptDialog(dept?: DeptItem) {
  editingDept.value = dept ?? null
  const defaultOrgId = dept
    ? dept.organization_id
    : (deptOrgFilter.value ?? (authStore.isOrgAdmin ? (authStore.user?.organization_id ?? null) : null))
  deptForm.value = {
    organization_id: defaultOrgId,
    name: dept ? dept.name : '',
  }
  deptDialog.value = true
}

async function saveDept() {
  const { valid } = await deptFormRef.value?.validate()
  if (!valid) return
  savingDept.value = true
  try {
    if (editingDept.value) {
      const res = await api.put(`/departments/${editingDept.value.id}`, { name: deptForm.value.name })
      const idx = deptList.value.findIndex(d => d.id === editingDept.value!.id)
      if (idx !== -1) deptList.value[idx] = { ...deptList.value[idx], ...res.data }
      if (selectedDept.value?.id === editingDept.value.id) {
        selectedDept.value = { ...selectedDept.value, name: res.data.name ?? deptForm.value.name }
      }
      showSnack("Bo'lim yangilandi")
    } else {
      const res = await api.post('/departments', {
        organization_id: deptForm.value.organization_id,
        name: deptForm.value.name,
      })
      deptList.value.push(res.data)
      showSnack("Bo'lim qo'shildi")
    }
    deptDialog.value = false
  } catch (e: any) {
    showSnack(e?.response?.data?.message ?? 'Xatolik yuz berdi', 'error')
  } finally {
    savingDept.value = false
  }
}

function confirmDeleteDept(dept: DeptItem) {
  deletingDept.value = dept
  deleteDeptDialog.value = true
}

async function deleteDept() {
  if (!deletingDept.value) return
  deletingDeptLoading.value = true
  try {
    await api.delete(`/departments/${deletingDept.value.id}`)
    deptList.value = deptList.value.filter(d => d.id !== deletingDept.value!.id)
    if (selectedDept.value?.id === deletingDept.value.id) {
      selectedDept.value = null
      positionList.value = []
    }
    showSnack("Bo'lim o'chirildi")
    deleteDeptDialog.value = false
  } catch {
    showSnack("O'chirishda xatolik", 'error')
  } finally {
    deletingDeptLoading.value = false
  }
}

function openPosDialog(pos?: PosItem) {
  editingPos.value = pos ?? null
  posForm.value = { name: pos ? pos.name : '' }
  posDialog.value = true
}

async function savePos() {
  const { valid } = await posFormRef.value?.validate()
  if (!valid) return
  if (!selectedDept.value) return
  savingPos.value = true
  try {
    if (editingPos.value) {
      const res = await api.put(
        `/departments/${selectedDept.value.id}/positions/${editingPos.value.id}`,
        { name: posForm.value.name }
      )
      const idx = positionList.value.findIndex(p => p.id === editingPos.value!.id)
      if (idx !== -1) positionList.value[idx] = { ...positionList.value[idx], ...res.data }
      showSnack('Lavozim yangilandi')
    } else {
      const res = await api.post(`/departments/${selectedDept.value.id}/positions`, {
        name: posForm.value.name,
      })
      positionList.value.push(res.data)
      showSnack("Lavozim qo'shildi")
    }
    posDialog.value = false
  } catch (e: any) {
    showSnack(e?.response?.data?.message ?? 'Xatolik yuz berdi', 'error')
  } finally {
    savingPos.value = false
  }
}

function confirmDeletePos(pos: PosItem) {
  deletingPos.value = pos
  deletePosDialog.value = true
}

async function deletePos() {
  if (!deletingPos.value || !selectedDept.value) return
  deletingPosLoading.value = true
  try {
    await api.delete(`/departments/${selectedDept.value.id}/positions/${deletingPos.value.id}`)
    positionList.value = positionList.value.filter(p => p.id !== deletingPos.value!.id)
    showSnack("Lavozim o'chirildi")
    deletePosDialog.value = false
  } catch {
    showSnack("O'chirishda xatolik", 'error')
  } finally {
    deletingPosLoading.value = false
  }
}

// ============================================================
// Work Schedules tab
// ============================================================
interface ScheduleItem {
  id: number
  organization_id: number
  organization?: { id: number; name: string } | null
  name: string
  work_start: string
  work_end: string
  lunch_minutes: number
  work_days: number[]
  late_tolerance_minutes: number
  is_default: boolean
}

const schedules = ref<ScheduleItem[]>([])
const loadingSchedules = ref(false)
const scheduleDialog = ref(false)
const editingSchedule = ref<ScheduleItem | null>(null)
const savingSchedule = ref(false)
const deleteScheduleDialog = ref(false)
const deletingSchedule = ref<ScheduleItem | null>(null)
const deletingScheduleLoading = ref(false)
const scheduleFormRef = ref<any>(null)

const scheduleHeaders = [
  { title: 'Tashkilot', key: 'organization', sortable: false },
  { title: 'Jadval nomi', key: 'name', sortable: false },
  { title: 'Ish vaqti', key: 'work_time', sortable: false },
  { title: 'Kech keldi qoidasi', key: 'late_rule', sortable: false },
  { title: 'Ish kunlari', key: 'work_days', sortable: false },
  { title: '', key: 'is_default', sortable: false, width: '80px' },
  { title: '', key: 'actions', sortable: false, width: '80px' },
]

const weekDays = [
  { label: 'Du', value: 1 },
  { label: 'Se', value: 2 },
  { label: 'Cho', value: 3 },
  { label: 'Pay', value: 4 },
  { label: 'Ju', value: 5 },
  { label: 'Sha', value: 6 },
  { label: 'Yak', value: 7 },
]

const defaultScheduleForm = () => ({
  organization_id: null as number | null,
  name: '',
  work_start: '08:00',
  work_end: '17:00',
  lunch_minutes: 60,
  work_days: [1, 2, 3, 4, 5, 6] as number[],
  late_tolerance_minutes: 15,
  is_default: true,
})
const scheduleForm = ref(defaultScheduleForm())

function fmtTime(t: string): string {
  if (!t) return '—'
  return t.slice(0, 5)
}

function dayName(d: number): string {
  return weekDays.find(w => w.value === d)?.label ?? String(d)
}

function lateThreshold(s: ScheduleItem): string {
  if (!s.work_start) return '—'
  const parts = s.work_start.split(':').map(Number)
  const h = parts[0] ?? 0
  const m = parts[1] ?? 0
  const total = h * 60 + m + (s.late_tolerance_minutes ?? 15)
  const th = Math.floor(total / 60)
  const tm = total % 60
  return `${String(th).padStart(2, '0')}:${String(tm).padStart(2, '0')}`
}

const lateTresholdHint = computed(() => {
  const parts = (scheduleForm.value.work_start || '08:00').split(':').map(Number)
  const h = parts[0] ?? 0
  const m = parts[1] ?? 0
  const total = h * 60 + m + (scheduleForm.value.late_tolerance_minutes ?? 15)
  const th = Math.floor(total / 60)
  const tm = total % 60
  return `Kech keldi: ${String(th).padStart(2, '0')}:${String(tm).padStart(2, '0')} dan keyin`
})

function toggleDay(d: number) {
  const idx = scheduleForm.value.work_days.indexOf(d)
  if (idx === -1) scheduleForm.value.work_days.push(d)
  else scheduleForm.value.work_days.splice(idx, 1)
}

async function fetchSchedules() {
  loadingSchedules.value = true
  try {
    const res = await api.get('/work-schedules')
    schedules.value = res.data
  } catch {
    showSnack("Jadvallarni yuklashda xatolik", 'error')
  } finally {
    loadingSchedules.value = false
  }
}

function openScheduleDialog(s?: ScheduleItem) {
  editingSchedule.value = s ?? null
  if (s) {
    scheduleForm.value = {
      organization_id: s.organization_id,
      name: s.name,
      work_start: fmtTime(s.work_start),
      work_end: fmtTime(s.work_end),
      lunch_minutes: s.lunch_minutes ?? 60,
      work_days: [...(s.work_days ?? [1, 2, 3, 4, 5, 6])],
      late_tolerance_minutes: s.late_tolerance_minutes ?? 15,
      is_default: s.is_default,
    }
  } else {
    const form = defaultScheduleForm()
    if (authStore.isOrgAdmin) {
      form.organization_id = authStore.user?.organization_id ?? null
    }
    scheduleForm.value = form
  }
  scheduleDialog.value = true
}

async function saveSchedule() {
  const { valid } = await scheduleFormRef.value?.validate()
  if (!valid) return
  savingSchedule.value = true
  try {
    if (editingSchedule.value) {
      const res = await api.put(`/work-schedules/${editingSchedule.value.id}`, scheduleForm.value)
      const idx = schedules.value.findIndex(s => s.id === editingSchedule.value!.id)
      if (idx !== -1) schedules.value[idx] = res.data
      if (scheduleForm.value.is_default) {
        schedules.value.forEach(s => { if (s.id !== editingSchedule.value!.id && s.organization_id === editingSchedule.value!.organization_id) s.is_default = false })
      }
      showSnack('Jadval yangilandi')
    } else {
      const res = await api.post('/work-schedules', scheduleForm.value)
      schedules.value.push(res.data)
      if (scheduleForm.value.is_default) {
        schedules.value.forEach(s => { if (s.id !== res.data.id && s.organization_id === res.data.organization_id) s.is_default = false })
      }
      showSnack("Jadval qo'shildi")
    }
    scheduleDialog.value = false
  } catch (e: any) {
    showSnack(e?.response?.data?.message ?? 'Xatolik yuz berdi', 'error')
  } finally {
    savingSchedule.value = false
  }
}

function confirmDeleteSchedule(s: ScheduleItem) {
  deletingSchedule.value = s
  deleteScheduleDialog.value = true
}

async function deleteSchedule() {
  if (!deletingSchedule.value) return
  deletingScheduleLoading.value = true
  try {
    await api.delete(`/work-schedules/${deletingSchedule.value.id}`)
    schedules.value = schedules.value.filter(s => s.id !== deletingSchedule.value!.id)
    showSnack("Jadval o'chirildi")
    deleteScheduleDialog.value = false
  } catch {
    showSnack("O'chirishda xatolik", 'error')
  } finally {
    deletingScheduleLoading.value = false
  }
}

// ============================================================
// Directors
// ============================================================
const directorOrgFilter = ref<number | null>(null)
const directors = ref<any[]>([])
const loadingDirectors = ref(false)
const directorDialog = ref(false)
const deleteDirectorDialog = ref(false)
const deletingDirector = ref<any>(null)
const deletingDirectorLoading = ref(false)
const savingDirector = ref(false)
const editingDirector = ref<any>(null)
const directorFormRef = ref<any>(null)
const directorForm = ref({ full_name: '', position: '', short_name: '', is_active: true, appointed_at: '' })

const directorHeaders = [
  { title: 'F.I.Sh.', key: 'full_name', sortable: false },
  { title: 'Lavozimi', key: 'position', sortable: false },
  { title: 'Tayinlangan', key: 'appointed_at', sortable: false },
  { title: 'Holat', key: 'is_active' },
  { title: '', key: 'actions', sortable: false },
]

function formatDateStr(d: string) {
  if (!d) return ''
  const datePart = d.split('T')[0] ?? d
  return datePart.split('-').reverse().join('.')
}

async function fetchDirectors() {
  if (!directorOrgFilter.value) { directors.value = []; return }
  loadingDirectors.value = true
  try {
    const res = await api.get(`/organizations/${directorOrgFilter.value}/directors`)
    directors.value = res.data
  } finally {
    loadingDirectors.value = false
  }
}

function openDirectorDialog(item: any) {
  editingDirector.value = item
  if (item) {
    directorForm.value = { full_name: item.full_name, position: item.position, short_name: item.short_name ?? '', is_active: item.is_active, appointed_at: item.appointed_at ?? '' }
  } else {
    directorForm.value = { full_name: '', position: '', short_name: '', is_active: true, appointed_at: '' }
  }
  directorDialog.value = true
}

async function saveDirector() {
  const { valid } = await directorFormRef.value?.validate()
  if (!valid) return
  savingDirector.value = true
  try {
    const payload = { ...directorForm.value, organization_id: directorOrgFilter.value }
    if (editingDirector.value) {
      const res = await api.put(`/organizations/${directorOrgFilter.value}/directors/${editingDirector.value.id}`, payload)
      const idx = directors.value.findIndex(d => d.id === editingDirector.value!.id)
      if (idx !== -1) directors.value[idx] = res.data
      if (directorForm.value.is_active) directors.value.forEach(d => { if (d.id !== editingDirector.value!.id) d.is_active = false })
    } else {
      const res = await api.post(`/organizations/${directorOrgFilter.value}/directors`, payload)
      directors.value.unshift(res.data)
      if (directorForm.value.is_active) directors.value.forEach(d => { if (d.id !== res.data.id) d.is_active = false })
    }
    directorDialog.value = false
    showSnack("Rahbar saqlandi")
  } catch (e: any) {
    showSnack(e?.response?.data?.message ?? 'Xatolik', 'error')
  } finally {
    savingDirector.value = false
  }
}

function confirmDeleteDirector(item: any) {
  deletingDirector.value = item
  deleteDirectorDialog.value = true
}

async function deleteDirector() {
  if (!deletingDirector.value) return
  deletingDirectorLoading.value = true
  try {
    await api.delete(`/organizations/${directorOrgFilter.value}/directors/${deletingDirector.value.id}`)
    directors.value = directors.value.filter(d => d.id !== deletingDirector.value!.id)
    deleteDirectorDialog.value = false
    showSnack("Rahbar o'chirildi")
  } catch {
    showSnack("O'chirishda xatolik", 'error')
  } finally {
    deletingDirectorLoading.value = false
  }
}

// ============================================================
// Mount
// ============================================================
onMounted(async () => {
  if (!authStore.user) await authStore.fetchMe()

  try {
    const promises: Promise<any>[] = []
    if (authStore.isSuperAdmin || authStore.isHrManager) {
      promises.push(api.get('/users').then(r => { users.value = r.data.data ?? r.data }))
    }
    promises.push(api.get('/organizations').then(r => { organizations.value = r.data }))
    await Promise.all(promises)
  } finally {
    loading.value = false
  }

  // For org_admin: auto-set org filter on departments, schedules, directors
  if (authStore.isOrgAdmin && authStore.user?.organization_id) {
    const orgId = authStore.user.organization_id
    deptOrgFilter.value = orgId
    directorOrgFilter.value = orgId
    scheduleForm.value.organization_id = orgId
    deptForm.value.organization_id = orgId
  }

  // Pre-load departments and schedules
  await fetchDepartments()
  await fetchSchedules()

  // Pre-load directors if org_admin
  if (authStore.isOrgAdmin && directorOrgFilter.value) {
    await fetchDirectors()
  }
})
</script>
