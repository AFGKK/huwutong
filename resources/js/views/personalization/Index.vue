<template>
  <div class="personalization-admin">
    <h2 class="mb-4">{{ t('personalization_page.title') }}</h2>

    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ adminStats.total_events }}</div>
            <div class="stat-label">{{ t('personalization_page.stats.total_events') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-success">{{ adminStats.today_events }}</div>
            <div class="stat-label">{{ t('personalization_page.stats.today_events') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-warning">{{ adminStats.active_recommendations }}</div>
            <div class="stat-label">{{ t('personalization_page.stats.active_recs') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ adminStats.clicked_recommendations }}</div>
            <div class="stat-label">{{ t('personalization_page.stats.clicked_recs') }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" class="mb-4">
      <el-col :span="24">
        <el-card shadow="hover">
          <template #header>{{ t('personalization_page.actions_title') }}</template>
          <el-button type="primary" :loading="refreshing" @click="refreshAll">
            {{ t('personalization_page.refresh_all') }}
          </el-button>
          <el-button type="success" @click="loadData">{{ t('personalization_page.refresh_stats') }}</el-button>
          <span class="text-muted ml-3" v-if="adminStats.customer_count">
            {{ t('personalization_page.customer_count', { n: adminStats.customer_count }) }}
          </span>
        </el-card>
      </el-col>
    </el-row>

    <el-tabs v-model="activeTab" type="border-card">
      <el-tab-pane :label="t('personalization_page.tabs.behaviors')" name="behaviors">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-card shadow="hover" class="mb-3">
              <template #header>{{ t('personalization_page.event_type_dist') }}</template>
              <el-table :data="behaviorStats.by_type" stripe size="small">
                <el-table-column :label="t('personalization_page.cols.event_type')" prop="event_type" min-width="160" />
                <el-table-column :label="t('personalization_page.cols.count')" prop="cnt" width="100">
                  <template #default="{ row }"><el-tag>{{ row.cnt }}</el-tag></template>
                </el-table-column>
              </el-table>
            </el-card>
          </el-col>
          <el-col :span="12">
            <el-card shadow="hover" class="mb-3">
              <template #header>{{ t('personalization_page.daily_trend') }}</template>
              <el-table :data="behaviorStats.daily_trend" stripe size="small">
                <el-table-column :label="t('personalization_page.cols.date')" prop="date" width="140" />
                <el-table-column :label="t('personalization_page.cols.event_count')" prop="cnt" width="100">
                  <template #default="{ row }"><el-tag :type="row.cnt > 50 ? 'success' : 'info'">{{ row.cnt }}</el-tag></template>
                </el-table-column>
              </el-table>
            </el-card>
          </el-col>
        </el-row>

        <el-card shadow="hover">
          <template #header>{{ t('personalization_page.top_customers') }}</template>
          <el-table :data="behaviorStats.top_customers" stripe size="small">
            <el-table-column :label="t('personalization_page.cols.customer')" prop="customer?.name" min-width="200" />
            <el-table-column :label="t('personalization_page.cols.event_count')" prop="cnt" width="120">
              <template #default="{ row }"><el-tag type="warning">{{ row.cnt }}</el-tag></template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>

      <el-tab-pane :label="t('personalization_page.tabs.recommendations')" name="recommendations">
        <el-card shadow="hover">
          <template #header>
            <div class="flex justify-between items-center">
              <span>{{ t('personalization_page.active_recs_list') }}</span>
              <div>
                <el-button size="small" type="primary" @click="generateForCustomer">{{ t('personalization_page.gen_for_customer') }}</el-button>
              </div>
            </div>
          </template>
          <el-table :data="recommendations" stripe v-loading="loading.recs" size="small" :empty-text="t('personalization_page.empty_recs')">
            <el-table-column :label="t('personalization_page.cols.type')" prop="recommendation_type" width="100">
              <template #default="{ row }">
                <el-tag :type="recTypeTag(row.recommendation_type)" size="small">{{ recTypeLabel(row.recommendation_type) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('personalization_page.cols.reason')" prop="reason" min-width="240" show-overflow-tooltip />
            <el-table-column :label="t('personalization_page.cols.score')" prop="score" width="80">
              <template #default="{ row }">
                <el-tag :type="row.score > 0.8 ? 'success' : row.score > 0.5 ? 'warning' : 'info'" size="small">{{ row.score }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('personalization_page.cols.source')" prop="source" width="80">
              <template #default="{ row }">
                <el-tag size="small">{{ sourceLabel(row.source) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('personalization_page.cols.created_at')" prop="created_at" width="160" />
          </el-table>
        </el-card>
      </el-tab-pane>

      <el-tab-pane :label="t('personalization_page.tabs.preferences')" name="preferences">
        <el-card shadow="hover">
          <template #header>{{ t('personalization_page.pref_title') }}</template>
          <el-table :data="preferenceKeys" stripe size="small">
            <el-table-column :label="t('personalization_page.cols.pref_key')" prop="key" width="180" />
            <el-table-column :label="t('personalization_page.cols.pref_label')" prop="label" min-width="200" />
            <el-table-column :label="t('personalization_page.cols.actions')" width="120">
              <template #default="{ row }">
                <el-button link size="small" type="primary" @click="editPreference(row)">{{ t('personalization_page.set') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>
    </el-tabs>

    <el-dialog v-model="genDialog.visible" :title="t('personalization_page.gen_dialog_title')" width="420">
      <el-form :model="genDialog">
        <el-form-item :label="t('personalization_page.customer_id')">
          <el-input-number v-model="genDialog.customer_id" :min="1" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="genDialog.visible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="genDialog.loading" @click="doGenerateForCustomer">{{ t('personalization_page.generate') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="prefDialog.visible" :title="t('personalization_page.pref_dialog_title')" width="420">
      <el-form :model="prefDialog">
        <el-form-item :label="t('personalization_page.pref_key')">
          <el-input v-model="prefDialog.key" disabled />
        </el-form-item>
        <el-form-item :label="t('personalization_page.pref_value')">
          <el-input v-model="prefDialog.value" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="prefDialog.visible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="prefDialog.saving" @click="doSetPreference">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import {
  getPersonalizationAdminDashboard, getBehaviorStats, getRecommendations,
  generateRecommendations, refreshAllRecommendations, setPreference,
} from '../../api/personalization'

const { t } = useI18n()

const activeTab = ref('behaviors')
const refreshing = ref(false)
const loading = reactive({ recs: false })
const adminStats = ref({})
const behaviorStats = ref({ by_type: [], daily_trend: [], top_customers: [] })
const recommendations = ref([])

const genDialog = reactive({ visible: false, customer_id: null, loading: false })
const prefDialog = reactive({ visible: false, key: '', value: '', saving: false })

const preferenceKeys = computed(() => [
  { key: 'preferred_layout', label: t('personalization_page.prefs.preferred_layout') },
  { key: 'content_focus', label: t('personalization_page.prefs.content_focus') },
  { key: 'notification_freq', label: t('personalization_page.prefs.notification_freq') },
  { key: 'dashboard_widgets', label: t('personalization_page.prefs.dashboard_widgets') },
  { key: 'theme', label: t('personalization_page.prefs.theme') },
])

function recTypeTag(type) {
  const map = { license: 'success', feature: 'primary', addon: 'warning', article: 'info', product: 'danger' }
  return map[type] || ''
}
function recTypeLabel(type) {
  const key = { license: 'license', feature: 'feature', addon: 'addon', article: 'article', product: 'product' }[type]
  return key ? t(`personalization_page.rec_types.${key}`) : type
}
function sourceLabel(s) {
  const key = { rule: 'rule', rfm: 'rfm', behavior: 'behavior', llm: 'llm' }[s]
  return key ? t(`personalization_page.sources.${key}`) : s
}

async function loadData() {
  try {
    const [statsRes, behavRes, recsRes] = await Promise.all([
      getPersonalizationAdminDashboard().catch(() => ({ data: {} })),
      getBehaviorStats().catch(() => ({ data: { by_type: [], daily_trend: [], top_customers: [] } })),
      getRecommendations().catch(() => ({ data: [] })),
    ])
    adminStats.value = statsRes.data || {}
    behaviorStats.value = behavRes.data || {}
    recommendations.value = Array.isArray(recsRes.data) ? recsRes.data : []
  } catch (e) { ElMessage.error(t('personalization_page.messages.load_failed')) }
}

async function refreshAll() {
  refreshing.value = true
  try {
    const { data } = await refreshAllRecommendations()
    ElMessage.success(t('personalization_page.messages.refreshed', { refreshed: data.refreshed, total: data.total }))
    loadData()
  } catch (e) { ElMessage.error(t('personalization_page.messages.refresh_failed')) }
  finally { refreshing.value = false }
}

function generateForCustomer() {
  genDialog.customer_id = null
  genDialog.visible = true
}

async function doGenerateForCustomer() {
  genDialog.loading = true
  try {
    await generateRecommendations(genDialog.customer_id)
    ElMessage.success(t('personalization_page.messages.generated'))
    genDialog.visible = false
  } catch (e) { ElMessage.error(t('personalization_page.messages.gen_failed')) }
  finally { genDialog.loading = false }
}

function editPreference(row) {
  prefDialog.key = row.key
  prefDialog.value = ''
  prefDialog.visible = true
}

async function doSetPreference() {
  prefDialog.saving = true
  try {
    await setPreference(prefDialog.key, prefDialog.value)
    ElMessage.success(t('personalization_page.messages.pref_saved'))
    prefDialog.visible = false
  } catch (e) { ElMessage.error(t('personalization_page.messages.save_failed')) }
  finally { prefDialog.saving = false }
}

onMounted(loadData)
</script>

<style scoped>
.personalization-admin { min-height: 400px; }
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a !important; }
.text-warning { color: #e6a23c !important; }
.text-muted { color: #909399; font-size: 13px; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.ml-3 { margin-left: 12px; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
</style>
