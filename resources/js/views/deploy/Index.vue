<template>
  <div class="deploy-page">
    <div class="page-header">
      <h2><el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>{{ t('deploy_page.title') }}</h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t('deploy_page.refresh') }}
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_releases }}</div>
          <div class="stat-label">{{ t('deploy_page.stats.total_releases') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card stat-success">
          <div class="stat-value">{{ stats.deployed_releases }}</div>
          <div class="stat-label">{{ t('deploy_page.stats.deployed') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.environment_count }}</div>
          <div class="stat-label">{{ t('deploy_page.stats.environment_count') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.success_rate }}%</div>
          <div class="stat-label">{{ t('deploy_page.stats.success_rate') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 各环境最新部署 -->
    <el-card shadow="hover" class="mb-4" v-if="stats.latest_deployments && stats.latest_deployments.length">
      <template #header><span>{{ t('deploy_page.env_deploy_status') }}</span></template>
      <el-row :gutter="16">
        <el-col :span="8" v-for="dep in stats.latest_deployments" :key="dep.id">
          <el-card shadow="never" class="env-card" :class="`env-${dep.status}`">
            <div class="env-name">{{ dep.environment?.name || t('deploy_page.unknown_env') }}</div>
            <div class="env-version">v{{ dep.release?.version || '-' }}</div>
            <div class="env-status">
              <el-tag :type="dep.status === 'success' ? 'success' : dep.status === 'failed' ? 'danger' : 'warning'" size="small">
                {{ statusLabel(dep.status) }}
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
        <el-tab-pane :label="t('deploy_page.tabs.jobs')" name="jobs">
          <div class="tab-toolbar">
            <el-button size="small" type="primary" @click="showDeployDialog = true">
              <el-icon><Plus /></el-icon> {{ t('deploy_page.new_deploy') }}
            </el-button>
            <el-select v-model="jobFilter.status" :placeholder="t('deploy_page.filter_status_ph')" clearable style="width:130px;margin-left:8px">
              <el-option v-for="opt in jobStatusFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-select v-model="jobFilter.environment_id" :placeholder="t('deploy_page.filter_env_ph')" clearable style="width:140px;margin-left:8px">
              <el-option :label="t('deploy_page.all_envs')" value="" />
              <el-option v-for="env in environments" :key="env.id" :label="env.name" :value="env.id" />
            </el-select>
          </div>
          <el-table :data="jobs" stripe v-loading="jobsLoading" @row-click="showJobDetail">
            <el-table-column prop="id" :label="t('deploy_page.cols.id')" width="80" />
            <el-table-column :label="t('deploy_page.cols.environment')" width="120">
              <template #default="{ row }">
                <el-tag size="small">{{ row.environment?.name || '-' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('deploy_page.cols.version')" width="120">
              <template #default="{ row }">
                <span class="mono">v{{ row.release?.version || '-' }}</span>
              </template>
            </el-table-column>
            <el-table-column :label="t('deploy_page.cols.type')" width="120">
              <template #default="{ row }">
                <el-tag size="small" type="info">{{ typeLabel(row.type) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="status" :label="t('deploy_page.cols.status')" width="100">
              <template #default="{ row }">
                <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="triggered_by" :label="t('deploy_page.cols.triggered_by')" width="120" />
            <el-table-column :label="t('deploy_page.cols.time')" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('deploy_page.cols.actions')" width="120">
              <template #default="{ row }">
                <el-button size="small" text type="primary" @click.stop="showJobDetail(row)">{{ t('deploy_page.row_actions.detail') }}</el-button>
                <el-button v-if="row.status === 'failed' || row.status === 'success'" size="small" text type="warning" @click.stop="rollbackJob(row)">{{ t('deploy_page.row_actions.rollback') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane :label="t('deploy_page.tabs.releases')" name="releases">
          <div class="tab-toolbar">
            <el-button size="small" type="primary" @click="showReleaseDialog = true">
              <el-icon><Plus /></el-icon> {{ t('deploy_page.new_release') }}
            </el-button>
          </div>
          <el-table :data="releases" stripe v-loading="releasesLoading">
            <el-table-column prop="version" :label="t('deploy_page.cols.version_no')" width="120">
              <template #default="{ row }">
                <span class="mono">v{{ row.version }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="code_name" :label="t('deploy_page.cols.code_name')" width="120" />
            <el-table-column prop="author" :label="t('deploy_page.cols.author')" width="100" />
            <el-table-column :label="t('deploy_page.cols.status')" width="100">
              <template #default="{ row }">
                <el-tag :type="releaseStatusTag(row.status)" size="small">{{ releaseStatusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('deploy_page.cols.git_branch')" width="140">
              <template #default="{ row }">
                <code>{{ row.git_branch || '-' }}</code>
              </template>
            </el-table-column>
            <el-table-column :label="t('deploy_page.cols.commit')" width="100">
              <template #default="{ row }">
                <code v-if="row.git_commit_hash">{{ row.git_commit_hash.substring(0, 7) }}</code>
              </template>
            </el-table-column>
            <el-table-column prop="changelog" :label="t('deploy_page.cols.changelog')" min-width="200" show-overflow-tooltip />
            <el-table-column :label="t('deploy_page.cols.time')" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('deploy_page.cols.actions')" width="100">
              <template #default="{ row }">
                <el-button size="small" text type="primary" @click="editRelease(row)">{{ t('actions.edit') }}</el-button>
                <el-button size="small" text type="danger" @click="deleteRelease(row)">{{ t('actions.delete') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane :label="t('deploy_page.tabs.environments')" name="environments">
          <div class="tab-toolbar">
            <el-button size="small" type="primary" @click="showEnvDialog = true">
              <el-icon><Plus /></el-icon> {{ t('deploy_page.new_env') }}
            </el-button>
          </div>
          <el-table :data="environments" stripe v-loading="envLoading">
            <el-table-column prop="name" :label="t('deploy_page.cols.env_name')" width="150" />
            <el-table-column prop="slug" :label="t('deploy_page.cols.slug')" width="120">
              <template #default="{ row }"><code>{{ row.slug }}</code></template>
            </el-table-column>
            <el-table-column :label="t('deploy_page.cols.type')" width="120">
              <template #default="{ row }">
                <el-tag size="small" type="info">{{ serverTypeLabel(row.server_type) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="base_url" :label="t('deploy_page.cols.base_url')" min-width="200" show-overflow-tooltip />
            <el-table-column :label="t('deploy_page.cols.protected')" width="70">
              <template #default="{ row }">
                <el-tag v-if="row.is_protected" size="small" type="danger">{{ t('deploy_page.yes') }}</el-tag>
                <el-tag v-else size="small" type="success">{{ t('deploy_page.no') }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('deploy_page.cols.deploy_count')" width="80">
              <template #default="{ row }">{{ row.deploy_jobs_count || 0 }}</template>
            </el-table-column>
            <el-table-column :label="t('deploy_page.cols.actions')" width="140">
              <template #default="{ row }">
                <el-button size="small" text type="primary" @click="editEnv(row)">{{ t('actions.edit') }}</el-button>
                <el-button size="small" text type="danger" @click="deleteEnv(row)">{{ t('actions.delete') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 新建部署对话框 -->
    <el-dialog v-model="showDeployDialog" :title="t('deploy_page.dialogs.new_deploy')" width="500px">
      <el-form :model="deployForm" label-width="120px">
        <el-form-item :label="t('deploy_page.form.release')" required>
          <el-select v-model="deployForm.deploy_release_id" style="width:100%">
            <el-option v-for="r in releases" :key="r.id" :label="`v${r.version} (${r.code_name || r.changelog?.substring(0, 30) || ''})`" :value="r.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('deploy_page.form.environment')" required>
          <el-select v-model="deployForm.deploy_environment_id" style="width:100%">
            <el-option v-for="e in environments" :key="e.id" :label="e.name" :value="e.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('deploy_page.form.deploy_type')">
          <el-radio-group v-model="deployForm.type">
            <el-radio v-for="opt in deployTypeOptions" :key="opt.value" :label="opt.value">{{ opt.label }}</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDeployDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="triggerDeploy" :loading="deploying">{{ t('deploy_page.start_deploy') }}</el-button>
      </template>
    </el-dialog>

    <!-- 新建/编辑发布对话框 -->
    <el-dialog v-model="showReleaseDialog" :title="editingRelease ? t('deploy_page.dialogs.edit_release') : t('deploy_page.dialogs.new_release')" width="600px">
      <el-form :model="releaseForm" label-width="120px">
        <el-form-item :label="t('deploy_page.form.version')" required>
          <el-input v-model="releaseForm.version" :placeholder="t('deploy_page.ph.version')" />
        </el-form-item>
        <el-form-item :label="t('deploy_page.form.code_name')">
          <el-input v-model="releaseForm.code_name" :placeholder="t('deploy_page.ph.code_name')" />
        </el-form-item>
        <el-form-item :label="t('deploy_page.form.changelog')">
          <el-input v-model="releaseForm.changelog" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item :label="t('deploy_page.form.git_branch')">
          <el-input v-model="releaseForm.git_branch" :placeholder="t('deploy_page.ph.git_branch')" />
        </el-form-item>
        <el-form-item :label="t('deploy_page.form.commit_hash')">
          <el-input v-model="releaseForm.git_commit_hash" :placeholder="t('deploy_page.ph.commit_hash')" maxlength="40" />
        </el-form-item>
        <el-form-item :label="t('deploy_page.form.author')">
          <el-input v-model="releaseForm.author" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showReleaseDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="saveRelease" :loading="savingRelease">{{ editingRelease ? t('actions.save') : t('actions.create') }}</el-button>
      </template>
    </el-dialog>

    <!-- 新建/编辑环境对话框 -->
    <el-dialog v-model="showEnvDialog" :title="editingEnv ? t('deploy_page.dialogs.edit_env') : t('deploy_page.dialogs.new_env')" width="500px">
      <el-form :model="envForm" label-width="110px">
        <el-form-item :label="t('deploy_page.form.env_name')" required>
          <el-input v-model="envForm.name" :placeholder="t('deploy_page.ph.env_name')" />
        </el-form-item>
        <el-form-item :label="t('deploy_page.form.slug')" required>
          <el-input v-model="envForm.slug" :placeholder="t('deploy_page.ph.slug')" />
        </el-form-item>
        <el-form-item :label="t('deploy_page.form.server_type')">
          <el-select v-model="envForm.server_type" style="width:100%">
            <el-option v-for="opt in serverTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('deploy_page.form.base_url')">
          <el-input v-model="envForm.base_url" :placeholder="t('deploy_page.ph.base_url')" />
        </el-form-item>
        <el-form-item :label="t('deploy_page.form.is_protected')">
          <el-switch v-model="envForm.is_protected" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showEnvDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="saveEnv" :loading="savingEnv">{{ editingEnv ? t('actions.save') : t('actions.create') }}</el-button>
      </template>
    </el-dialog>

    <!-- 部署详情对话框 -->
    <el-dialog v-model="showJobDetailDialog" :title="t('deploy_page.dialogs.job_detail')" width="700px">
      <div v-if="jobDetail">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item :label="t('deploy_page.cols.version')">v{{ jobDetail.release?.version }}</el-descriptions-item>
          <el-descriptions-item :label="t('deploy_page.cols.environment')">
            <el-tag size="small">{{ jobDetail.environment?.name }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('deploy_page.cols.type')">{{ typeLabel(jobDetail.type) }}</el-descriptions-item>
          <el-descriptions-item :label="t('deploy_page.cols.status')">
            <el-tag :type="statusTag(jobDetail.status)" size="small">{{ statusLabel(jobDetail.status) }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('deploy_page.cols.triggered_by')">{{ jobDetail.triggered_by }}</el-descriptions-item>
          <el-descriptions-item :label="t('deploy_page.detail.started_at')">{{ formatTime(jobDetail.started_at) }}</el-descriptions-item>
          <el-descriptions-item :label="t('deploy_page.detail.completed_at')">{{ formatTime(jobDetail.completed_at) }}</el-descriptions-item>
        </el-descriptions>

        <h4 style="margin:16px 0 8px">{{ t('deploy_page.detail.deploy_steps') }}</h4>
        <el-timeline>
          <el-timeline-item v-for="step in jobDetail.steps" :key="step.name"
            :timestamp="step.duration_ms > 0 ? `${(step.duration_ms / 1000).toFixed(1)}s` : ''"
            :type="step.status === 'success' ? 'primary' : step.status === 'failed' ? 'danger' : step.status === 'running' ? 'warning' : 'info'">
            {{ step.name }}
          </el-timeline-item>
        </el-timeline>

        <h4 style="margin:16px 0 8px">{{ t('deploy_page.detail.deploy_log') }}</h4>
        <pre class="deploy-log">{{ jobDetail.output || t('deploy_page.detail.no_log') }}</pre>

        <div v-if="jobDetail.error_message" class="mb-4">
          <h4 style="margin:16px 0 8px;color:#f56c6c">{{ t('deploy_page.detail.error_info') }}</h4>
          <el-alert :title="jobDetail.error_message" type="error" show-icon />
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Connection, Refresh, Plus } from '@element-plus/icons-vue'
import deployApi from '../../api/deploy'

const P = 'deploy_page'
const { t, locale } = useI18n()

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

const JOB_STATUS_KEYS = ['pending', 'running', 'success', 'failed', 'rolled_back']
const DEPLOY_TYPE_KEYS = ['full', 'backend_only', 'frontend_only']
const RELEASE_STATUS_KEYS = ['pending', 'building', 'built', 'deployed', 'rolled_back', 'failed']
const SERVER_TYPE_KEYS = ['self-hosted', 'cloud', 'kubernetes']

const dateLocale = computed(() => (locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'))

const jobStatusFilterOptions = computed(() => [
    { label: t(`${P}.all`), value: '' },
    ...JOB_STATUS_KEYS.map((value) => ({ value, label: t(`${P}.job_status.${value}`) })),
])

const deployTypeOptions = computed(() =>
    DEPLOY_TYPE_KEYS.map((value) => ({ value, label: t(`${P}.deploy_type.${value}`) })),
)

const serverTypeOptions = computed(() =>
    SERVER_TYPE_KEYS.map((value) => ({ value, label: t(`${P}.server_type.${value}`) })),
)

const jobStatusLabels = computed(() =>
    Object.fromEntries(JOB_STATUS_KEYS.concat(['rolling_back']).map((key) => [key, t(`${P}.job_status.${key}`)])),
)

const deployTypeLabels = computed(() =>
    Object.fromEntries(DEPLOY_TYPE_KEYS.concat(['rollback']).map((key) => [key, t(`${P}.deploy_type.${key}`)])),
)

const releaseStatusLabels = computed(() =>
    Object.fromEntries(RELEASE_STATUS_KEYS.map((key) => [key, t(`${P}.release_status.${key}`)])),
)

const serverTypeLabels = computed(() =>
    Object.fromEntries(SERVER_TYPE_KEYS.map((key) => [key, t(`${P}.server_type.${key}`)])),
)

function formatTime(val) {
    if (!val) return '-'
    return new Date(val).toLocaleString(dateLocale.value)
}

function statusTag(s) {
    const map = { success: 'success', failed: 'danger', running: 'warning', rolling_back: 'warning', rolled_back: 'info', pending: 'info' }
    return map[s] || 'info'
}

function statusLabel(s) {
    return jobStatusLabels.value[s] || s
}

function typeLabel(val) {
    return deployTypeLabels.value[val] || val
}

function releaseStatusTag(s) {
    return s === 'deployed' ? 'success' : s === 'failed' ? 'danger' : s === 'rolled_back' ? 'warning' : 'info'
}

function releaseStatusLabel(s) {
    return releaseStatusLabels.value[s] || s
}

function serverTypeLabel(val) {
    return serverTypeLabels.value[val] || val
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
        ElMessage.success(editingEnv.value ? t(`${P}.messages.env_updated`) : t(`${P}.messages.env_created`))
        showEnvDialog.value = false
        editingEnv.value = null
        loadEnvironments()
        loadDashboard()
    }).catch(e => ElMessage.error(e.response?.data?.message || t('messages.failed')))
    .finally(() => savingEnv.value = false)
}

function deleteEnv(row) {
    ElMessageBox.confirm(t(`${P}.messages.delete_env_confirm`, { name: row.name }), t('actions.confirm'), { type: 'warning' }).then(() => {
        deployApi.deleteEnvironment(row.id).then(() => {
            ElMessage.success(t(`${P}.messages.deleted`))
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
        ElMessage.success(editingRelease.value ? t(`${P}.messages.release_updated`) : t(`${P}.messages.release_created`))
        showReleaseDialog.value = false
        editingRelease.value = null
        loadReleases()
        loadDashboard()
    }).catch(e => ElMessage.error(e.response?.data?.message || t('messages.failed')))
    .finally(() => savingRelease.value = false)
}

function deleteRelease(row) {
    ElMessageBox.confirm(t(`${P}.messages.delete_release_confirm`, { version: row.version }), t('actions.confirm'), { type: 'warning' }).then(() => {
        deployApi.deleteRelease(row.id).then(() => {
            ElMessage.success(t(`${P}.messages.deleted`))
            loadReleases()
        })
    }).catch(() => {})
}

// 部署操作
async function triggerDeploy() {
    if (!deployForm.value.deploy_release_id || !deployForm.value.deploy_environment_id) {
        ElMessage.warning(t(`${P}.messages.select_release_env`))
        return
    }
    deploying.value = true
    try {
        await deployApi.triggerDeploy(deployForm.value)
        ElMessage.success(t(`${P}.messages.deploy_triggered`))
        showDeployDialog.value = false
        loadJobs()
        loadReleases()
        loadDashboard()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${P}.messages.deploy_trigger_failed`))
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
    ElMessageBox.confirm(t(`${P}.messages.rollback_confirm`, { id: row.id }), t(`${P}.dialogs.confirm_rollback`), { type: 'warning' }).then(async () => {
        try {
            await deployApi.rollbackDeploy(row.id)
            ElMessage.success(t(`${P}.messages.rollback_triggered`))
            loadJobs()
            loadReleases()
        } catch (e) {
            ElMessage.error(e.response?.data?.message || t(`${P}.messages.rollback_failed`))
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
.env-card .env-version { font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
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
