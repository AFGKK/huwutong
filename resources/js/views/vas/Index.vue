<template>
  <div class="vas-admin">
    <h2 class="mb-4">增值服务管理</h2>

    <!-- 概览统计 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ stats.total_services }}</div>
            <div class="stat-label">服务总数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-success">{{ stats.active_subscriptions }}</div>
            <div class="stat-label">活跃订阅</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ stats.total_subscriptions }}</div>
            <div class="stat-label">总开通次数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-warning">¥{{ stats.monthly_revenue }}</div>
            <div class="stat-label">月度收入</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 热门服务 -->
    <el-row :gutter="20" class="mb-4" v-if="stats.top_services?.length">
      <el-col :span="24">
        <el-card shadow="hover">
          <template #header>热门增值服务 Top 5</template>
          <el-table :data="stats.top_services" stripe size="small">
            <el-table-column label="服务名称" prop="name" min-width="160" />
            <el-table-column label="编码" prop="code" width="120" />
            <el-table-column label="分类" prop="category" width="100">
              <template #default="{ row }">
                <el-tag :type="categoryTag(row.category)" size="small">{{ categoryLabel(row.category) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="月价" prop="price_monthly" width="100">
              <template #default="{ row }">¥{{ row.price_monthly }}</template>
            </el-table-column>
            <el-table-column label="年价" prop="price_yearly" width="100">
              <template #default="{ row }">¥{{ row.price_yearly }}</template>
            </el-table-column>
            <el-table-column label="活跃订阅" prop="active_subscriptions_count" width="120">
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
      <template #header>分类分布</template>
      <el-row :gutter="16">
        <el-col :span="4" v-for="cat in stats.by_category" :key="cat.category">
          <el-card shadow="never" class="category-card">
            <div class="category-name">{{ categoryLabel(cat.category) }}</div>
            <div class="category-count">{{ cat.count }} 服务 / {{ cat.subscriptions }} 订阅</div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <!-- 标签页：服务管理 / 开通记录 -->
    <el-tabs v-model="activeTab" type="border-card">
      <el-tab-pane label="服务目录" name="services">
        <!-- 操作栏 -->
        <div class="flex justify-between items-center mb-3">
          <el-button type="primary" @click="showServiceDialog(null)">
            <el-icon><Plus /></el-icon> 新建增值服务
          </el-button>
        </div>
        <el-table :data="services" stripe v-loading="loading.services" size="small">
          <el-table-column label="编码" prop="code" width="100" />
          <el-table-column label="名称" prop="name" min-width="160" />
          <el-table-column label="分类" width="90">
            <template #default="{ row }">
              <el-tag :type="categoryTag(row.category)" size="small">{{ categoryLabel(row.category) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="月价" prop="price_monthly" width="90">
            <template #default="{ row }">¥{{ row.price_monthly }}</template>
          </el-table-column>
          <el-table-column label="年价" prop="price_yearly" width="90">
            <template #default="{ row }">¥{{ row.price_yearly }}</template>
          </el-table-column>
          <el-table-column label="计费模式" width="100">
            <template #default="{ row }">{{ billingModeLabel(row.billing_mode) }}</template>
          </el-table-column>
          <el-table-column label="公开" width="70">
            <template #default="{ row }">
              <el-tag :type="row.is_public ? 'success' : 'info'" size="small">{{ row.is_public ? '是' : '否' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="70">
            <template #default="{ row }">
              <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="排序" prop="sort_order" width="60" />
          <el-table-column label="操作" width="180" fixed="right">
            <template #default="{ row }">
              <el-button link size="small" type="primary" @click="showServiceDialog(row)">编辑</el-button>
              <el-button link size="small" type="primary" @click="showServiceDetail(row)">详情</el-button>
              <el-popconfirm title="确认删除该服务？" @confirm="deleteService(row.id)">
                <template #reference>
                  <el-button link size="small" type="danger">删除</el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="开通记录" name="subscriptions">
        <el-table :data="subscriptions.data" stripe v-loading="loading.subscriptions" size="small">
          <el-table-column label="服务" prop="vas_service?.name" min-width="160" />
          <el-table-column label="编码" prop="vas_service?.code" width="100" />
          <el-table-column label="分类" width="90">
            <template #default="{ row }">
              <el-tag :type="categoryTag(row.vas_service?.category)" size="small">{{ categoryLabel(row.vas_service?.category) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag :type="subStatusTag(row.status)" size="small">{{ subStatusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="价格" prop="price" width="90">
            <template #default="{ row }">¥{{ row.price }}</template>
          </el-table-column>
          <el-table-column label="周期" prop="billing_period" width="90">
            <template #default="{ row }">{{ row.billing_period === 'yearly' ? '年付' : '月付' }}</template>
          </el-table-column>
          <el-table-column label="开通日期" prop="start_date" width="110" />
          <el-table-column label="操作" width="130" fixed="right">
            <template #default="{ row }">
              <el-button v-if="row.status === 'active'" link size="small" type="danger" @click="cancelSub(row)">取消</el-button>
              <el-tag v-else type="info" size="small">{{ row.status }}</el-tag>
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
    <el-dialog v-model="dialog.visible" :title="dialog.isEdit ? '编辑增值服务' : '新建增值服务'" width="680">
      <el-form :model="dialog.form" label-width="120" size="small">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="编码" prop="code">
              <el-input v-model="dialog.form.code" placeholder="唯一编码，如 sso_audit" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="名称" prop="name" required>
              <el-input v-model="dialog.form.name" placeholder="服务名称" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="分类" prop="category">
              <el-select v-model="dialog.form.category" style="width:100%">
                <el-option v-for="(label, key) in categories" :key="key" :label="label" :value="key" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="计费模式" prop="billing_mode">
              <el-select v-model="dialog.form.billing_mode" style="width:100%">
                <el-option v-for="(label, key) in billingModes" :key="key" :label="label" :value="key" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="月价">
          <el-input-number v-model="dialog.form.price_monthly" :min="0" :precision="2" />
        </el-form-item>
        <el-form-item label="年价">
          <el-input-number v-model="dialog.form.price_yearly" :min="0" :precision="2" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="dialog.form.description" type="textarea" :rows="3" placeholder="服务描述" />
        </el-form-item>
        <el-form-item label="功能清单">
          <el-input v-model="featuresText" type="textarea" :rows="3" placeholder="每行一个功能，如：\n- 实时同步\n- 7x24监控" />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="公开显示">
              <el-switch v-model="dialog.form.is_public" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="启用">
              <el-switch v-model="dialog.form.is_active" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="排序">
              <el-input-number v-model="dialog.form.sort_order" :min="0" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="dialog.saving" @click="saveService">保存</el-button>
      </template>
    </el-dialog>

    <!-- 服务详情对话框 -->
    <el-dialog v-model="detailDialog.visible" title="服务详情" width="600">
      <template v-if="detailDialog.service">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="编码">{{ detailDialog.service.code }}</el-descriptions-item>
          <el-descriptions-item label="名称">{{ detailDialog.service.name }}</el-descriptions-item>
          <el-descriptions-item label="分类">{{ categoryLabel(detailDialog.service.category) }}</el-descriptions-item>
          <el-descriptions-item label="计费模式">{{ billingModeLabel(detailDialog.service.billing_mode) }}</el-descriptions-item>
          <el-descriptions-item label="月价">¥{{ detailDialog.service.price_monthly }}</el-descriptions-item>
          <el-descriptions-item label="年价">¥{{ detailDialog.service.price_yearly }}</el-descriptions-item>
          <el-descriptions-item label="活跃订阅">{{ detailDialog.service.active_subscriptions_count }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="detailDialog.service.is_active ? 'success' : 'danger'" size="small">
              {{ detailDialog.service.is_active ? '启用' : '停用' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="公开市场">
            <el-tag :type="detailDialog.service.is_public ? 'success' : 'info'" size="small">
              {{ detailDialog.service.is_public ? '公开' : '隐藏' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="描述" :span="2">{{ detailDialog.service.description || '-' }}</el-descriptions-item>
          <el-descriptions-item label="功能清单" :span="2">
            <ul class="feature-list" v-if="detailDialog.service.features?.length">
              <li v-for="(f, i) in detailDialog.service.features" :key="i">{{ f }}</li>
            </ul>
            <span v-else>-</span>
          </el-descriptions-item>
        </el-descriptions>
      </template>
    </el-dialog>

    <!-- 取消订阅对话框 -->
    <el-dialog v-model="cancelDialog.visible" title="取消开通" width="420">
      <p class="mb-3">确认取消增值服务「{{ cancelDialog.name }}」？</p>
      <el-input v-model="cancelDialog.reason" type="textarea" :rows="3" placeholder="取消原因（可选）" />
      <template #footer>
        <el-button @click="cancelDialog.visible = false">返回</el-button>
        <el-button type="danger" :loading="cancelDialog.loading" @click="doCancel">确认取消</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getVasServices, getVasService, createVasService, updateVasService, deleteVasService,
  getVasSubscriptions, cancelVasSubscription,
  getVasStats, getVasCategories, getVasBillingModes,
} from '../../api/vas'

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

function categoryTag(cat) {
  const map = { feature: 'success', support: 'primary', storage: 'warning', api: '', ai: 'danger' }
  return map[cat] || ''
}
function categoryLabel(cat) { return categories.value[cat] || cat }
function billingModeLabel(m) { return billingModes.value[m] || m }
function subStatusTag(s) {
  const map = { active: 'success', suspended: 'warning', cancelled: 'info', expired: 'danger' }
  return map[s] || ''
}
function subStatusLabel(s) {
  const map = { active: '使用中', suspended: '已暂停', cancelled: '已取消', expired: '已过期' }
  return map[s] || s
}

async function loadServices() {
  loading.services = true
  try {
    const { data } = await getVasServices()
    services.value = data || []
  } catch (e) { ElMessage.error('加载服务列表失败') }
  finally { loading.services = false }
}

async function loadSubscriptions(page = 1) {
  loading.subscriptions = true
  try {
    const { data } = await getVasSubscriptions({ page })
    Object.assign(subscriptions, data)
  } catch (e) { ElMessage.error('加载开通记录失败') }
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
      ElMessage.success('已更新')
    } else {
      await createVasService(dialog.form)
      ElMessage.success('已创建')
    }
    dialog.visible = false
    loadServices()
    loadStats()
  } catch (e) { ElMessage.error('保存失败') }
  finally { dialog.saving = false }
}

async function deleteService(id) {
  try {
    await deleteVasService(id)
    ElMessage.success('已删除')
    loadServices()
  } catch (e) { ElMessage.error('删除失败') }
}

async function showServiceDetail(service) {
  try {
    const { data } = await getVasService(service.id)
    detailDialog.service = data
    detailDialog.visible = true
  } catch (e) { ElMessage.error('加载详情失败') }
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
    ElMessage.success('已取消')
    cancelDialog.visible = false
    loadSubscriptions()
  } catch (e) { ElMessage.error('取消失败') }
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
