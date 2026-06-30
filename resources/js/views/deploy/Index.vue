<template>
  <div class="deploy-page">
    <div class="page-header">
      <h2><el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>DevOps 部署管道</h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_releases }}</div>
          <div class="stat-label">总发布数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card stat-success">
          <div class="stat-value">{{ stats.deployed_releases }}</div>
          <div class="stat-label">已部署</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.environment_count }}</div>
          <div class="stat-label">环境数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.success_rate }}%</div>
          <div class="stat-label">部署成功率</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 各环境最新部署 -->
    <el-card shadow="hover" class="mb-4" v-if="stats.latest_deployments && stats.latest_deployments.length">
      <template #header><span>环境部署状态</span></template>
      <el-row :gutter="16">
        <el-col :span="8" v-for="dep in stats.latest_deployments" :key="dep.id">
          <el-card shadow="never" class="env-card" :class="`env-${dep.status}`">
            <div class="env-name">{{ dep.environment?.name || '未知环境' }}</div>
            <div class="env-version">v{{ dep.release?.version || '-' }}</div>
            <div class="env-status">
              <el-tag :type="dep.status === 'success' ? 'success' : dep.status === 'failed' ? 'danger' : 'warning'" size="small">
                {{ dep.status }}
              </el-tag>
            </div>
            <div class="env-time">{{ formatTime(dep.created_at) }}</div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <!-- 主内容 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <el-tab-pane label="部署作业" name="jobs">
          <div class="tab-toolbar">
            <el-button size="small" type="primary" @click="showDeployDialog = true">
              <el-icon><Plus /></el-icon> 新建部署
            </el-button>
            <el-select v-model="jobFilter.status" placeholder="状态过滤" clearable style="width:130px;margin-left:8px">
              <el-option label="全部" value="" />
              <el-option label="排队中" value="pending" />
              <el-option label="运行中" value="running" />
              <el-option label="成功" value="success" />
              <el-option label="失败" value="failed" />
              <el-option label="已回滚" value="rolled_back" />
            </el-select>
            <el-select v-model="jobFilter.environment_id" placeholder="环境过滤" clearable style="width:140px;margin-left:8px">
              <el-option label="全部环境" value="" />
              <el-option v-for="env in environments" :key="env.id" :label="env.name" :value="env.id" />
            </el-select>
          </div>
          <el-table :data="jobs" stripe v-loading="jobsLoading" @row-click="showJobDetail">
            <el-table-column prop="id" label="ID" width="80" />
            <el-table-column label="环境" width="120">
              <template #default="{ row }">
                <el-tag size="small">{{ row.environment?.name || '-' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="版本" width="120">
              <template #default="{ row }">
                <span class="mono">v{{ row.release?.version || '-' }}</span>
              </template>
            </el-table-column>
            <el-table-column label="类型" width="120">
              <template #default="{ row }">
                <el-tag size="small" type="info">{{ typeLabel(row.type) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="status" label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="triggered_by" label="触发人" width="120" />
            <el-table-column label="时间" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="120">
              <template #default="{ row }">
                <el-button size="small" text type="primary" @click.stop="showJobDetail(row)">详情</el-button>
                <el-button v-if="row.status === 'failed' || row.status === 'success'" size="small" text type="warning" @click.stop="rollbackJob(row)">回滚</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane label="发布版本" name="releases">
          <div class="tab-toolbar">
            <el-button size="small" type="primary" @click="showReleaseDialog = true">
              <el-icon><Plus /></el-icon> 新建发布
            </el-button>
          </div>
          <el-table :data="releases" stripe v-loading="releasesLoading">
            <el-table-column prop="version" label="版本号" width="120">
              <template #default="{ row }">
                <span class="mono">v{{ row.version }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="code_name" label="代号" width="120" />
            <el-table-column prop="author" label="作者" width="100" />
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="releaseStatusTag(row.status)" size="small">{{ releaseStatusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="Git 分支" width="140">
              <template #default="{ row }">
                <code>{{ row.git_branch || '-' }}</code>
              </template>
            </el-table-column>
            <el-table-column label="提交" width="100">
              <template #default="{ row }">
                <code v-if="row.git_commit_hash">{{ row.git_commit_hash.substring(0, 7) }}</code>
              </template>
            </el-table-column>
            <el-table-column prop="changelog" label="变更说明" min-width="200" show-overflow-tooltip />
            <el-table-column label="时间" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="100">
              <template #default="{ row }">
                <el-button size="small" text type="primary" @click="editRelease(row)">编辑</el-button>
                <el-button size="small" text type="danger" @click="deleteRelease(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane label="环境管理" name="environments">
          <div class="tab-toolbar">
            <el-button size="small" type="primary" @click="showEnvDialog = true">
              <el-icon><Plus /></el-icon> 新建环境
            </el-button>
          </div>
          <el-table :data="environments" stripe v-loading="envLoading">
            <el-table-column prop="name" label="环境名称" width="150" />
            <el-table-column prop="slug" label="标识" width="120">
              <template #default="{ row }"><code>{{ row.slug }}</code></template>
            </el-table-column>
            <el-table-column label="类型" width="120">
              <template #default="{ row }">
                <el-tag size="small" type="info">{{ serverTypeLabel(row.server_type) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="base_url" label="基础地址" min-width="200" show-overflow-tooltip />
            <el-table-column label="保护" width="70">
              <template #default="{ row }">
                <el-tag v-if="row.is_protected" size="small" type="danger">是</el-tag>
                <el-tag v-else size="small" type="success">否</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="部署数" width="80">
              <template #default="{ row }">{{ row.deploy_jobs_count || 0 }}</template>
            </el-table-column>
            <el-table-column label="操作" width="140">
              <template #default="{ row }">
                <el-button size="small" text type="primary" @click="editEnv(row)">编辑</el-button>
                <el-button size="small" text type="danger" @click="deleteEnv(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 新建部署对话框 -->
    <el-dialog v-model="showDeployDialog" title="新建部署" width="500px">
      <el-form :model="deployForm" label-width="120px">
        <el-form-item label="发布版本" required>
          <el-select v-model="deployForm.deploy_release_id" style="width:100%">
            <el-option v-for="r in releases" :key="r.id" :label="`v${r.version} (${r.code_name || r.changelog?.substring(0, 30) || ''})`" :value="r.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="部署环境" required>
          <el-select v-model="deployForm.deploy_environment_id" style="width:100%">
            <el-option v-for="e in environments" :key="e.id" :label="e.name" :value="e.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="部署类型">
          <el-radio-group v-model="deployForm.type">
            <el-radio label="full">全量部署</el-radio>
            <el-radio label="backend_only">仅后端</el-radio>
            <el-radio label="frontend_only">仅前端</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDeployDialog = false">取消</el-button>
        <el-button type="primary" @click="triggerDeploy" :loading="deploying">开始部署</el-button>
      </template>
    </el-dialog>

    <!-- 新建/编辑发布对话框 -->
    <el-dialog v-model="showReleaseDialog" :title="editingRelease ? '编辑发布' : '新建发布'" width="600px">
      <el-form :model="releaseForm" label-width="120px">
        <el-form-item label="版本号" required>
          <el-input v-model="releaseForm.version" placeholder="如 2.5.1" />
        </el-form-item>
        <el-form-item label="代号">
          <el-input v-model="releaseForm.code_name" placeholder="如 '蓝色多瑙河'" />
        </el-form-item>
        <el-form-item label="变更说明">
          <el-input v-model="releaseForm.changelog" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="Git 分支">
          <el-input v-model="releaseForm.git_branch" placeholder="如 main" />
        </el-form-item>
        <el-form-item label="提交哈希">
          <el-input v-model="releaseForm.git_commit_hash" placeholder="40位SHA" maxlength="40" />
        </el-form-item>
        <el-form-item label="作者">
          <el-input v-model="releaseForm.author" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showReleaseDialog = false">取消</el-button>
        <el-button type="primary" @click="saveRelease" :loading="savingRelease">{{ editingRelease ? '保存' : '创建' }}</el-button>
      </template>
    </el-dialog>

    <!-- 新建/编辑环境对话框 -->
    <el-dialog v-model="showEnvDialog" :title="editingEnv ? '编辑环境' : '新建环境'" width="500px">
      <el-form :model="envForm" label-width="110px">
        <el-form-item label="环境名称" required>
          <el-input v-model="envForm.name" placeholder="如 Production" />
        </el-form-item>
        <el-form-item label="标识" required>
          <el-input v-model="envForm.slug" placeholder="如 production" />
        </el-form-item>
        <el-form-item label="服务器类型">
          <el-select v-model="envForm.server_type" style="width:100%">
            <el-option label="自托管" value="self-hosted" />
            <el-option label="云服务器" value="cloud" />
            <el-option label="Kubernetes" value="kubernetes" />
          </el-select>
        </el-form-item>
        <el-form-item label="基础地址">
          <el-input v-model="envForm.base_url" placeholder="如 https://api.example.com" />
        </el-form-item>
        <el-form-item label="是否受保护">
          <el-switch v-model="envForm.is_protected" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showEnvDialog = false">取消</el-button>
        <el-button type="primary" @click="saveEnv" :loading="savingEnv">{{ editingEnv ? '保存' : '创建' }}</el-button>
      </template>
    </el-dialog>

    <!-- 部署详情对话框 -->
    <el-dialog v-model="showJobDetailDialog" title="部署详情" width="700px">
      <div v-if="jobDetail">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="版本">v{{ jobDetail.release?.version }}</el-descriptions-item>
          <el-descriptions-item label="环境">
            <el-tag size="small">{{ jobDetail.environment?.name }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="类型">{{ typeLabel(jobDetail.type) }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="statusTag(jobDetail.status)" size="small">{{ statusLabel(jobDetail.status) }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="触发人">{{ jobDetail.triggered_by }}</el-descriptions-item>
          <el-descriptions-item label="开始时间">{{ formatTime(jobDetail.started_at) }}</el-descriptions-item>
          <el-descriptions-item label="完成时间">{{ formatTime(jobDetail.completed_at) }}</el-descriptions-item>
        </el-descriptions>

        <h4 style="margin:16px 0 8px">部署步骤</h4>
        <el-timeline>
          <el-timeline-item v-for="step in jobDetail.steps" :key="step.name"
            :timestamp="step.duration_ms > 0 ? `${(step.duration_ms / 1000).toFixed(1)}s` : ''"
            :type="step.status === 'success' ? 'primary' : step.status === 'failed' ? 'danger' : step.status === 'running' ? 'warning' : 'info'">
            {{ step.name }}
          </el-timeline-item>
        </el-timeline>

        <h4 style="margin:16px 0 8px">部署日志</h4>
        <pre class="deploy-log">{{ jobDetail.output || '无日志输出' }}</pre>

        <div v-if="jobDetail.error_message" class="mb-4">
          <h4 style="margin:16px 0 8px;color:#f56c6c">错误信息</h4>
          <el-alert :title="jobDetail.error_message" type="error" show-icon />
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Connection, Refresh, Plus } from '@element-plus/icons-vue'
import deployApi from '../../api/deploy'

const loading = ref(false)
const activeTab = ref('jobs')
const stats = ref({})

const environments = ref([])
const envLoading = ref(false)
const showEnvDialog = ref(false)
const editingEnv = ref(null)
const envForm = ref({ name: '', slug: '', server_type: 'self-hosted', base_url: '', is_protected: true })
const savingEnv = ref(false)

const releases = ref([])
const releasesLoading = ref(false)
const showReleaseDialog = ref(false)
const editingRelease = ref(null)
const releaseForm = ref({ version: '', code_name: '', changelog: '', git_branch: '', git_commit_hash: '', author: '' })
const savingRelease = ref(false)

const jobs = ref([])
const jobsLoading = ref(false)
const jobFilter = ref({ status: '', environment_id: '' })
const showDeployDialog = ref(false)
const deployForm = ref({ deploy_release_id: '', deploy_environment_id: '', type: 'full' })
const deploying = ref(false)

const showJobDetailDialog = ref(false)
const jobDetail = ref(null)

function formatTime(t) {
    if (!t) return '-'
    return new Date(t).toLocaleString('zh-CN')
}

function statusTag(s) {
    const map = { success: 'success', failed: 'danger', running: 'warning', rolling_back: 'warning', rolled_back: 'info', pending: 'info' }
    return map[s] || 'info'
}

function statusLabel(s) {
    const map = { pending: '排队中', running: '运行中', success: '成功', failed: '失败', rolling_back: '回滚中', rolled_back: '已回滚' }
    return map[s] || s
}

function typeLabel(t) {
    const map = { full: '全量部署', backend_only: '仅后端', frontend_only: '仅前端', rollback: '回滚' }
    return map[t] || t
}

function releaseStatusTag(s) {
    return s === 'deployed' ? 'success' : s === 'failed' ? 'danger' : s === 'rolled_back' ? 'warning' : 'info'
}

function releaseStatusLabel(s) {
    const map = { pending: '待构建', building: '构建中', built: '构建完成', deployed: '已部署', rolled_back: '已回滚', failed: '失败' }
    return map[s] || s
}

function serverTypeLabel(t) {
    const map = { 'self-hosted': '自托管', cloud: '云服务器', kubernetes: 'Kubernetes' }
    return map[t] || t
}

async function loadDashboard() {
    try {
        const res = await deployApi.getDashboard()
        stats.value = res.data || {}
    } catch (e) {
        console.error('Failed to load deploy dashboard', e)
    }
}

async function loadEnvironments() {
    envLoading.value = true
    try {
        const res = await deployApi.getEnvironments()
        environments.value = res.data || []
    } catch (e) {
        console.error('Failed to load environments', e)
    } finally {
        envLoading.value = false
    }
}

async function loadReleases() {
    releasesLoading.value = true
    try {
        const res = await deployApi.getReleases({ per_page: 100 })
        releases.value = res.data?.data || res.data || []
    } catch (e) {
        console.error('Failed to load releases', e)
    } finally {
        releasesLoading.value = false
    }
}

async function loadJobs() {
    jobsLoading.value = true
    try {
        const params = {}
        if (jobFilter.value.status) params.status = jobFilter.value.status
        if (jobFilter.value.environment_id) params.environment_id = jobFilter.value.environment_id
        const res = await deployApi.getJobs(params)
        jobs.value = res.data?.data || res.data || []
    } catch (e) {
        console.error('Failed to load jobs', e)
    } finally {
        jobsLoading.value = false
    }
}

async function refreshAll() {
    loading.value = true
    await Promise.all([loadDashboard(), loadEnvironments(), loadReleases(), loadJobs()])
    loading.value = false
}

// 环境 CRUD
function editEnv(row) {
    editingEnv.value = row
    envForm.value = {
        name: row.name, slug: row.slug, server_type: row.server_type || 'self-hosted',
        base_url: row.base_url || '', is_protected: row.is_protected,
    }
    showEnvDialog.value = true
}

function saveEnv() {
    savingEnv.value = true
    const call = editingEnv.value
        ? deployApi.updateEnvironment(editingEnv.value.id, envForm.value)
        : deployApi.createEnvironment(envForm.value)
    call.then(() => {
        ElMessage.success(editingEnv.value ? '环境已更新' : '环境已创建')
        showEnvDialog.value = false
        editingEnv.value = null
        loadEnvironments()
        loadDashboard()
    }).catch(e => ElMessage.error('操作失败: ' + (e.response?.data?.message || e.message)))
    .finally(() => savingEnv.value = false)
}

function deleteEnv(row) {
    ElMessageBox.confirm(`确定删除环境"${row.name}"?`, '确认', { type: 'warning' }).then(() => {
        deployApi.deleteEnvironment(row.id).then(() => {
            ElMessage.success('已删除')
            loadEnvironments()
        })
    }).catch(() => {})
}

// 发布 CRUD
function editRelease(row) {
    editingRelease.value = row
    releaseForm.value = {
        version: row.version, code_name: row.code_name || '', changelog: row.changelog || '',
        git_branch: row.git_branch || '', git_commit_hash: row.git_commit_hash || '',
        author: row.author || '',
    }
    showReleaseDialog.value = true
}

function saveRelease() {
    savingRelease.value = true
    const call = editingRelease.value
        ? deployApi.updateRelease(editingRelease.value.id, releaseForm.value)
        : deployApi.createRelease(releaseForm.value)
    call.then(() => {
        ElMessage.success(editingRelease.value ? '发布已更新' : '发布已创建')
        showReleaseDialog.value = false
        editingRelease.value = null
        loadReleases()
        loadDashboard()
    }).catch(e => ElMessage.error('操作失败: ' + (e.response?.data?.message || e.message)))
    .finally(() => savingRelease.value = false)
}

function deleteRelease(row) {
    ElMessageBox.confirm(`确定删除发布 v${row.version}?`, '确认', { type: 'warning' }).then(() => {
        deployApi.deleteRelease(row.id).then(() => {
            ElMessage.success('已删除')
            loadReleases()
        })
    }).catch(() => {})
}

// 部署操作
async function triggerDeploy() {
    if (!deployForm.value.deploy_release_id || !deployForm.value.deploy_environment_id) {
        ElMessage.warning('请选择发布版本和部署环境')
        return
    }
    deploying.value = true
    try {
        await deployApi.triggerDeploy(deployForm.value)
        ElMessage.success('部署已触发')
        showDeployDialog.value = false
        loadJobs()
        loadReleases()
        loadDashboard()
    } catch (e) {
        ElMessage.error('触发部署失败: ' + (e.response?.data?.message || e.message))
    } finally {
        deploying.value = false
    }
}

async function showJobDetail(row) {
    try {
        const res = await deployApi.getJobDetail(row.id)
        jobDetail.value = res.data
        showJobDetailDialog.value = true
    } catch (e) {
        console.error('Failed to load job detail', e)
    }
}

function rollbackJob(row) {
    ElMessageBox.confirm(`确定回滚 #${row.id} 部署?`, '确认回滚', { type: 'warning' }).then(async () => {
        try {
            await deployApi.rollbackDeploy(row.id)
            ElMessage.success('回滚已触发')
            loadJobs()
            loadReleases()
        } catch (e) {
            ElMessage.error('回滚失败: ' + (e.response?.data?.message || e.message))
        }
    }).catch(() => {})
}

// 侦听过滤条件变化
watch(() => jobFilter.value.status, () => loadJobs())
watch(() => jobFilter.value.environment_id, () => loadJobs())

onMounted(() => {
    refreshAll()
})
</script>

<style scoped>
.deploy-page {
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-header h2 {
    margin: 0;
    font-size: 22px;
}

.header-actions { display: flex; align-items: center; }

.mb-4 { margin-bottom: 16px; }

.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-success .stat-value { color: #67c23a; }

.tab-toolbar {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}

.env-card {
    text-align: center;
    padding: 8px;
    border-radius: 8px;
    position: relative;
    min-height: 100px;
}

.env-card .env-name { font-size: 14px; font-weight: 600; margin-bottom: 4px; }
.env-card .env-version { font-size: 20px; font-weight: 700; color: #409eff; margin-bottom: 6px; }
.env-card .env-status { margin-bottom: 4px; }
.env-card .env-time { font-size: 11px; color: #909399; }

.env-success { border-left: 3px solid #67c23a; }
.env-failed { border-left: 3px solid #f56c6c; }
.env-running { border-left: 3px solid #e6a23c; }

.mono { font-family: 'SFMono-Regular', Consolas, monospace; }

.deploy-log {
    background: #f5f7fa;
    border: 1px solid #e4e7ed;
    border-radius: 4px;
    padding: 12px;
    font-size: 12px;
    font-family: 'SFMono-Regular', Consolas, monospace;
    max-height: 300px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
}

:deep(.el-timeline-item__timestamp) {
    font-size: 11px !important;
    color: #909399;
}
</style>
