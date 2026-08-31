<template>
  <div class="vas-admin">
    <h2 class="mb-4">{{ t('vas_page.title') }}</h2>

    <!-- 概览统计 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ stats.total_services }}</div>
            <div class="stat-label">{{ t('vas_page.stats.total_services') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-success">{{ stats.active_subscriptions }}</div>
            <div class="stat-label">{{ t('vas_page.stats.active_subscriptions') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ stats.total_subscriptions }}</div>
            <div class="stat-label">{{ t('vas_page.stats.total_subscriptions') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-warning">¥{{ stats.monthly_revenue }}</div>
            <div class="stat-label">{{ t('vas_page.stats.monthly_revenue') }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 热门服务 -->
    <el-row :gutter="20" class="mb-4" v-if="stats.top_services?.length">
      <el-col :span="24">
        <el-card shadow="hover">
          <template #header>{{ t('vas_page.top_services_title') }}</template>
          <el-table :data="stats.top_services" stripe size="small">
            <el-table-column :label="t('vas_page.cols.service_name')" prop="name" min-width="160" />
            <el-table-column :label="t('vas_page.cols.code')" prop="code" width="120" />
            <el-table-column :label="t('vas_page.cols.category')" prop="category" width="100">
              <template #default="{ row }">
                <el-tag :type="categoryTag(row.category)" size="small">{{ categoryLabel(row.category) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('vas_page.cols.price_monthly')" prop="price_monthly" width="100">
              <template #default="{ row }">¥{{ row.price_monthly }}</template>
            </el-table-column>
            <el-table-column :label="t('vas_page.cols.price_yearly')" prop="price_yearly" width="100">
              <template #default="{ row }">¥{{ row.price_yearly }}</template>
            </el-table-column>
            <el-table-column :label="t('vas_page.cols.active_subscriptions')" prop="active_subscriptions_count" width="120">
              <template #default="{ row }">
                <el-tag type="success">{{ row.active_subscriptions_count }}</el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
    </el-row>

    <!-- 分类分布 -->
    <el-card shadow="hover" class="mb-4" v-if="stats.by_category?.length">
      <template #header>{{ t('vas_page.category_distribution') }}</template>
      <el-row :gutter="16">
        <el-col :span="4" v-for="cat in stats.by_category" :key="cat.category">
          <el-card shadow="never" class="category-card">
            <div class="category-name">{{ categoryLabel(cat.category) }}</div>
            <div class="category-count">{{ t('vas_page.category_count', { count: cat.count, subs: cat.subscriptions }) }}</div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <!-- 标签页：服务管理 / 开通记录 -->
    <el-tabs v-model="activeTab" type="border-card">
      <el-tab-pane :label="t('vas_page.tabs.services')" name="services">
        <!-- 操作栏 -->
        <div class="flex justify-between items-center mb-3">
          <el-button type="primary" @click="showServiceDialog(null)">
            <el-icon><Plus /></el-icon> {{ t('vas_page.btn_create') }}
          </el-button>
        </div>
        <el-table :data="services" stripe v-loading="loading.services" size="small">
          <el-table-column :label="t('vas_page.cols.code')" prop="code" width="100" />
          <el-table-column :label="t('vas_page.cols.name')" prop="name" min-width="160" />
          <el-table-column :label="t('vas_page.cols.category')" width="90">
            <template #default="{ row }">
              <el-tag :type="categoryTag(row.category)" size="small">{{ categoryLabel(row.category) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('vas_page.cols.price_monthly')" prop="price_monthly" width="90">
            <template #default="{ row }">¥{{ row.price_monthly }}</template>
          </el-table-column>
          <el-table-column :label="t('vas_page.cols.price_yearly')" prop="price_yearly" width="90">
            <template #default="{ row }">¥{{ row.price_yearly }}</template>
          </el-table-column>
          <el-table-column :label="t('vas_page.cols.billing_mode')" width="100">
            <template #default="{ row }">{{ billingModeLabel(row.billing_mode) }}</template>
          </el-table-column>
          <el-table-column :label="t('vas_page.cols.public')" width="70">
            <template #default="{ row }">
              <el-tag :type="row.is_public ? 'success' : 'info'" size="small">{{ row.is_public ? t('vas_page.yes') : t('vas_page.no') }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('vas_page.cols.status')" width="70">
            <template #default="{ row }">
              <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? t('vas_page.enabled') : t('vas_page.disabled') }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('vas_page.cols.sort_order')" prop="sort_order" width="60" />
          <el-table-column :label="t('vas_page.cols.ops')" width="180" fixed="right">
            <template #default="{ row }">
              <el-button link size="small" type="primary" @click="showServiceDialog(row)">{{ t('actions.edit') }}</el-button>
              <el-button link size="small" type="primary" @click="showServiceDetail(row)">{{ t('vas_page.detail') }}</el-button>
              <el-popconfirm :title="t('vas_page.confirm.delete_service')" @confirm="deleteService(row.id)">
                <template #reference>
                  <el-button link size="small" type="danger">{{ t('actions.delete') }}</el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane :label="t('vas_page.tabs.subscriptions')" name="subscriptions">
        <el-table :data="subscriptions.data" stripe v-loading="loading.subscriptions" size="small">
          <el-table-column :label="t('vas_page.cols.service')" prop="vas_service?.name" min-width="160" />
          <el-table-column :label="t('vas_page.cols.code')" prop="vas_service?.code" width="100" />
          <el-table-column :label="t('vas_page.cols.category')" width="90">
            <template #default="{ row }">
              <el-tag :type="categoryTag(row.vas_service?.category)" size="small">{{ categoryLabel(row.vas_service?.category) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('vas_page.cols.status')" width="90">
            <template #default="{ row }">
              <el-tag :type="subStatusTag(row.status)" size="small">{{ subStatusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('vas_page.cols.price')" prop="price" width="90">
            <template #default="{ row }">¥{{ row.price }}</template>
          </el-table-column>
          <el-table-column :label="t('vas_page.cols.period')" prop="billing_period" width="90">
            <template #default="{ row }">{{ billingPeriodLabel(row.billing_period) }}</template>
          </el-table-column>
          <el-table-column :label="t('vas_page.cols.start_date')" prop="start_date" width="110" />
          <el-table-column :label="t('vas_page.cols.ops')" width="130" fixed="right">
            <template #default="{ row }">
              <el-button v-if="row.status === 'active'" link size="small" type="danger" @click="cancelSub(row)">{{ t('actions.cancel') }}</el-button>
              <el-tag v-else type="info" size="small">{{ subStatusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
        </el-table>
        <div class="flex justify-center mt-4" v-if="subscriptions.total > subscriptions.per_page">
          <el-pagination background layout="prev, pager, next" :total="subscriptions.total"
            :page-size="subscriptions.per_page" :current-page="subscriptions.current_page"
            @current-change="loadSubscriptions" />
        </div>
      </el-tab-pane>
    </el-tabs>

    <!-- 服务编辑对话框 -->
    <el-dialog v-model="dialog.visible" :title="dialog.isEdit ? t('vas_page.dialog.edit_title') : t('vas_page.dialog.create_title')" width="680">
      <el-form :model="dialog.form" label-width="120" size="small">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item :label="t('vas_page.cols.code')" prop="code">
              <el-input v-model="dialog.form.code" :placeholder="t('vas_page.form.code_ph')" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('vas_page.cols.name')" prop="name" required>
              <el-input v-model="dialog.form.name" :placeholder="t('vas_page.form.name_ph')" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item :label="t('vas_page.cols.category')" prop="category">
              <el-select v-model="dialog.form.category" style="width:100%">
                <el-option v-for="(label, key) in categories" :key="key" :label="label" :value="key" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('vas_page.cols.billing_mode')" prop="billing_mode">
              <el-select v-model="dialog.form.billing_mode" style="width:100%">
                <el-option v-for="(label, key) in billingModes" :key="key" :label="label" :value="key" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item :label="t('vas_page.cols.price_monthly')">
          <el-input-number v-model="dialog.form.price_monthly" :min="0" :precision="2" />
        </el-form-item>
        <el-form-item :label="t('vas_page.cols.price_yearly')">
          <el-input-number v-model="dialog.form.price_yearly" :min="0" :precision="2" />
        </el-form-item>
        <el-form-item :label="t('vas_page.form.description')">
          <el-input v-model="dialog.form.description" type="textarea" :rows="3" :placeholder="t('vas_page.form.description_ph')" />
        </el-form-item>
        <el-form-item :label="t('vas_page.form.features')">
          <el-input v-model="featuresText" type="textarea" :rows="3" :placeholder="t('vas_page.form.features_ph')" />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item :label="t('vas_page.form.public_display')">
              <el-switch v-model="dialog.form.is_public" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item :label="t('actions.enable')">
              <el-switch v-model="dialog.form.is_active" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item :label="t('vas_page.cols.sort_order')">
              <el-input-number v-model="dialog.form.sort_order" :min="0" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="dialog.saving" @click="saveService">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 服务详情对话框 -->
    <el-dialog v-model="detailDialog.visible" :title="t('vas_page.dialog.detail_title')" width="600">
      <template v-if="detailDialog.service">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item :label="t('vas_page.cols.code')">{{ detailDialog.service.code }}</el-descriptions-item>
          <el-descriptions-item :label="t('vas_page.cols.name')">{{ detailDialog.service.name }}</el-descriptions-item>
          <el-descriptions-item :label="t('vas_page.cols.category')">{{ categoryLabel(detailDialog.service.category) }}</el-descriptions-item>
          <el-descriptions-item :label="t('vas_page.cols.billing_mode')">{{ billingModeLabel(detailDialog.service.billing_mode) }}</el-descriptions-item>
          <el-descriptions-item :label="t('vas_page.cols.price_monthly')">¥{{ detailDialog.service.price_monthly }}</el-descriptions-item>
          <el-descriptions-item :label="t('vas_page.cols.price_yearly')">¥{{ detailDialog.service.price_yearly }}</el-descriptions-item>
          <el-descriptions-item :label="t('vas_page.cols.active_subscriptions')">{{ detailDialog.service.active_subscriptions_count }}</el-descriptions-item>
          <el-descriptions-item :label="t('vas_page.cols.status')">
            <el-tag :type="detailDialog.service.is_active ? 'success' : 'danger'" size="small">
              {{ detailDialog.service.is_active ? t('vas_page.enabled') : t('vas_page.disabled') }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('vas_page.form.public_market')">
            <el-tag :type="detailDialog.service.is_public ? 'success' : 'info'" size="small">
              {{ detailDialog.service.is_public ? t('vas_page.public_yes') : t('vas_page.public_no') }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('vas_page.form.description')" :span="2">{{ detailDialog.service.description || '-' }}</el-descriptions-item>
          <el-descriptions-item :label="t('vas_page.form.features')" :span="2">
            <ul class="feature-list" v-if="detailDialog.service.features?.length">
              <li v-for="(f, i) in detailDialog.service.features" :key="i">{{ f }}</li>
            </ul>
            <span v-else>-</span>
          </el-descriptions-item>
        </el-descriptions>
      </template>
    </el-dialog>

    <!-- 取消订阅对话框 -->
    <el-dialog v-model="cancelDialog.visible" :title="t('vas_page.dialog.cancel_sub_title')" width="420">
      <p class="mb-3">{{ t('vas_page.confirm.cancel_sub', { name: cancelDialog.name }) }}</p>
      <el-input v-model="cancelDialog.reason" type="textarea" :rows="3" :placeholder="t('vas_page.form.cancel_reason_ph')" />
      <template #footer>
        <el-button @click="cancelDialog.visible = false">{{ t('actions.back') }}</el-button>
        <el-button type="danger" :loading="cancelDialog.loading" @click="doCancel">{{ t('vas_page.btn_confirm_cancel') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getVasServices, getVasService, createVasService, updateVasService, deleteVasService,
  getVasSubscriptions, cancelVasSubscription,
  getVasStats, getVasCategories, getVasBillingModes,
} from '../../api/vas'

const { t } = useI18n()

const activeTab = ref('services')
const loading = reactive({ services: false, subscriptions: false })
const services = ref([])
const subscriptions = reactive({ data: [], total: 0, per_page: 20, current_page: 1 })
const stats = ref({})
const categories = ref({})
const billingModes = ref({})

const dialog = reactive({
  visible: false, isEdit: false, saving: false,
  form: { code: '', name: '', description: '', category: 'feature', billing_mode: 'flat',
    price_monthly: 0, price_yearly: 0, is_public: true, is_active: true, sort_order: 0, features: [] },
})
const detailDialog = reactive({ visible: false, service: null })
const cancelDialog = reactive({ visible: false, id: null, name: '', reason: '', loading: false })

const featuresText = computed({
  get: () => (dialog.form.features || []).join('\n'),
  set: (val) => { dialog.form.features = val.split('\n').map(s => s.trim().replace(/^-\s*/, '')).filter(Boolean) },
})

const subStatusLabels = computed(() => ({
  active: t('vas_page.sub_status.active'),
  suspended: t('vas_page.sub_status.suspended'),
  cancelled: t('vas_page.sub_status.cancelled'),
  expired: t('vas_page.sub_status.expired'),
}))

const billingPeriodLabels = computed(() => ({
  yearly: t('vas_page.billing_period.yearly'),
  monthly: t('vas_page.billing_period.monthly'),
}))

function categoryTag(cat) {
  const map = { feature: 'success', support: 'primary', storage: 'warning', api: '', ai: 'danger' }
  return map[cat] || ''
}
function categoryLabel(cat) { return categories.value[cat] || cat }
function billingModeLabel(m) { return billingModes.value[m] || m }
function billingPeriodLabel(period) { return billingPeriodLabels.value[period] || period }
function subStatusTag(s) {
  const map = { active: 'success', suspended: 'warning', cancelled: 'info', expired: 'danger' }
  return map[s] || ''
}
function subStatusLabel(s) { return subStatusLabels.value[s] || s }

async function loadServices() {
  loading.services = true
  try {
    const { data } = await getVasServices()
    services.value = data || []
  } catch (e) { ElMessage.error(t('vas_page.messages.load_services_failed')) }
  finally { loading.services = false }
}

async function loadSubscriptions(page = 1) {
  loading.subscriptions = true
  try {
    const { data } = await getVasSubscriptions({ page })
    Object.assign(subscriptions, data)
  } catch (e) { ElMessage.error(t('vas_page.messages.load_subscriptions_failed')) }
  finally { loading.subscriptions = false }
}

async function loadStats() {
  try {
    const { data } = await getVasStats()
    stats.value = data || {}
  } catch (e) { /* ignore */ }
}

function showServiceDialog(service) {
  dialog.isEdit = !!service
  if (service) {
    dialog.form = { ...service }
  } else {
    dialog.form = { code: '', name: '', description: '', category: 'feature', billing_mode: 'flat',
      price_monthly: 0, price_yearly: 0, is_public: true, is_active: true, sort_order: 0, features: [] }
  }
  dialog.visible = true
}

async function saveService() {
  dialog.saving = true
  try {
    if (dialog.isEdit) {
      await updateVasService(dialog.form.id, dialog.form)
      ElMessage.success(t('vas_page.messages.updated'))
    } else {
      await createVasService(dialog.form)
      ElMessage.success(t('vas_page.messages.created'))
    }
    dialog.visible = false
    loadServices()
    loadStats()
  } catch (e) { ElMessage.error(t('vas_page.messages.save_failed')) }
  finally { dialog.saving = false }
}

async function deleteService(id) {
  try {
    await deleteVasService(id)
    ElMessage.success(t('vas_page.messages.deleted'))
    loadServices()
  } catch (e) { ElMessage.error(t('vas_page.messages.delete_failed')) }
}

async function showServiceDetail(service) {
  try {
    const { data } = await getVasService(service.id)
    detailDialog.service = data
    detailDialog.visible = true
  } catch (e) { ElMessage.error(t('vas_page.messages.load_detail_failed')) }
}

function cancelSub(sub) {
  cancelDialog.id = sub.id
  cancelDialog.name = sub.vas_service?.name || ''
  cancelDialog.reason = ''
  cancelDialog.visible = true
}

async function doCancel() {
  cancelDialog.loading = true
  try {
    await cancelVasSubscription(cancelDialog.id, cancelDialog.reason)
    ElMessage.success(t('vas_page.messages.cancelled'))
    cancelDialog.visible = false
    loadSubscriptions()
  } catch (e) { ElMessage.error(t('vas_page.messages.cancel_failed')) }
  finally { cancelDialog.loading = false }
}

onMounted(async () => {
  const [catRes, modeRes] = await Promise.all([
    getVasCategories().catch(() => ({ data: {} })),
    getVasBillingModes().catch(() => ({ data: {} })),
  ])
  categories.value = catRes.data || {}
  billingModes.value = modeRes.data || {}
  loadServices()
  loadSubscriptions()
  loadStats()
})
</script>

<style scoped>
.vas-admin { min-height: 400px; }
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a !important; }
.text-warning { color: #e6a23c !important; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mt-4 { margin-top: 16px; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.category-card { text-align: center; padding: 12px; }
.category-name { font-size: 15px; font-weight: 600; margin-bottom: 4px; }
.category-count { font-size: 12px; color: #909399; }
.feature-list { margin: 0; padding-left: 18px; }
.feature-list li { margin-bottom: 2px; }
</style>
