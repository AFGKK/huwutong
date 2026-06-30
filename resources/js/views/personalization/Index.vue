<template>
  <div class="personalization-admin">
    <h2 class="mb-4">AI 个性化管理</h2>

    <!-- 概览统计 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ adminStats.total_events }}</div>
            <div class="stat-label">总行为事件</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-success">{{ adminStats.today_events }}</div>
            <div class="stat-label">今日事件</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-warning">{{ adminStats.active_recommendations }}</div>
            <div class="stat-label">活跃推荐</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ adminStats.clicked_recommendations }}</div>
            <div class="stat-label">已点击推荐</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 操作栏 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="24">
        <el-card shadow="hover">
          <template #header>操作</template>
          <el-button type="primary" :loading="refreshing" @click="refreshAll">
            刷新所有客户推荐
          </el-button>
          <el-button type="success" @click="loadData">刷新统计</el-button>
          <span class="text-muted ml-3" v-if="adminStats.customer_count">
            共 {{ adminStats.customer_count }} 个客户
          </span>
        </el-card>
      </el-col>
    </el-row>

    <el-tabs v-model="activeTab" type="border-card">
      <!-- 行为分析 -->
      <el-tab-pane label="行为分析" name="behaviors">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-card shadow="hover" class="mb-3">
              <template #header>事件类型分布</template>
              <el-table :data="behaviorStats.by_type" stripe size="small">
                <el-table-column label="事件类型" prop="event_type" min-width="160" />
                <el-table-column label="次数" prop="cnt" width="100">
                  <template #default="{ row }"><el-tag>{{ row.cnt }}</el-tag></template>
                </el-table-column>
              </el-table>
            </el-card>
          </el-col>
          <el-col :span="12">
            <el-card shadow="hover" class="mb-3">
              <template #header>近7天趋势</template>
              <el-table :data="behaviorStats.daily_trend" stripe size="small">
                <el-table-column label="日期" prop="date" width="140" />
                <el-table-column label="事件数" prop="cnt" width="100">
                  <template #default="{ row }"><el-tag :type="row.cnt > 50 ? 'success' : 'info'">{{ row.cnt }}</el-tag></template>
                </el-table-column>
              </el-table>
            </el-card>
          </el-col>
        </el-row>

        <el-card shadow="hover">
          <template #header>活跃客户 Top 10</template>
          <el-table :data="behaviorStats.top_customers" stripe size="small">
            <el-table-column label="客户" prop="customer?.name" min-width="200" />
            <el-table-column label="事件数" prop="cnt" width="120">
              <template #default="{ row }"><el-tag type="warning">{{ row.cnt }}</el-tag></template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>

      <!-- 个性化推荐 -->
      <el-tab-pane label="个性化推荐" name="recommendations">
        <el-card shadow="hover">
          <template #header>
            <div class="flex justify-between items-center">
              <span>活跃推荐列表</span>
              <div>
                <el-button size="small" type="primary" @click="generateForCustomer">为指定客户生成</el-button>
              </div>
            </div>
          </template>
          <el-table :data="recommendations" stripe v-loading="loading.recs" size="small" empty-text="暂无活跃推荐">
            <el-table-column label="类型" prop="recommendation_type" width="100">
              <template #default="{ row }">
                <el-tag :type="recTypeTag(row.recommendation_type)" size="small">{{ recTypeLabel(row.recommendation_type) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="推荐理由" prop="reason" min-width="240" show-overflow-tooltip />
            <el-table-column label="分数" prop="score" width="80">
              <template #default="{ row }">
                <el-tag :type="row.score > 0.8 ? 'success' : row.score > 0.5 ? 'warning' : 'info'" size="small">{{ row.score }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="来源" prop="source" width="80">
              <template #default="{ row }">
                <el-tag size="small">{{ sourceLabel(row.source) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="创建时间" prop="created_at" width="160" />
          </el-table>
        </el-card>
      </el-tab-pane>

      <!-- 偏好管理 -->
      <el-tab-pane label="偏好管理" name="preferences">
        <el-card shadow="hover">
          <template #header>用户偏好设置</template>
          <el-table :data="preferenceKeys" stripe size="small">
            <el-table-column label="偏好键" prop="key" width="180" />
            <el-table-column label="说明" prop="label" min-width="200" />
            <el-table-column label="操作" width="120">
              <template #default="{ row }">
                <el-button link size="small" type="primary" @click="editPreference(row)">设置</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>
    </el-tabs>

    <!-- 生成推荐对话框 -->
    <el-dialog v-model="genDialog.visible" title="为客户生成推荐" width="420">
      <el-form :model="genDialog">
        <el-form-item label="客户ID">
          <el-input-number v-model="genDialog.customer_id" :min="1" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="genDialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="genDialog.loading" @click="doGenerateForCustomer">生成</el-button>
      </template>
    </el-dialog>

    <!-- 设置偏好对话框 -->
    <el-dialog v-model="prefDialog.visible" title="设置偏好" width="420">
      <el-form :model="prefDialog">
        <el-form-item label="键">
          <el-input v-model="prefDialog.key" disabled />
        </el-form-item>
        <el-form-item label="值">
          <el-input v-model="prefDialog.value" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="prefDialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="prefDialog.saving" @click="doSetPreference">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import {
  getPersonalizationAdminDashboard, getBehaviorStats, getRecommendations,
  generateRecommendations, refreshAllRecommendations, setPreference,
} from '../../api/personalization'

const activeTab = ref('behaviors')
const refreshing = ref(false)
const loading = reactive({ recs: false })
const adminStats = ref({})
const behaviorStats = ref({ by_type: [], daily_trend: [], top_customers: [] })
const recommendations = ref([])

const genDialog = reactive({ visible: false, customer_id: null, loading: false })
const prefDialog = reactive({ visible: false, key: '', value: '', saving: false })

const preferenceKeys = [
  { key: 'preferred_layout', label: '偏好布局' },
  { key: 'content_focus', label: '内容焦点' },
  { key: 'notification_freq', label: '通知频率' },
  { key: 'dashboard_widgets', label: '仪表盘组件' },
  { key: 'theme', label: '主题偏好' },
]

function recTypeTag(t) {
  const map = { license: 'success', feature: 'primary', addon: 'warning', article: 'info', product: 'danger' }
  return map[t] || ''
}
function recTypeLabel(t) {
  const map = { license: '套餐', feature: '功能', addon: '增值', article: '文章', product: '产品' }
  return map[t] || t
}
function sourceLabel(s) {
  const map = { rule: '规则', rfm: 'RFM', behavior: '行为', llm: 'LLM' }
  return map[s] || s
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
  } catch (e) { ElMessage.error('加载数据失败') }
}

async function refreshAll() {
  refreshing.value = true
  try {
    const { data } = await refreshAllRecommendations()
    ElMessage.success(`已刷新 ${data.refreshed}/${data.total} 个客户的推荐`)
    loadData()
  } catch (e) { ElMessage.error('刷新失败') }
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
    ElMessage.success('推荐已生成')
    genDialog.visible = false
  } catch (e) { ElMessage.error('生成失败') }
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
    ElMessage.success('偏好已保存')
    prefDialog.visible = false
  } catch (e) { ElMessage.error('保存失败') }
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
