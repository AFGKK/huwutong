<template>
  <div class="api-version-management">
    <el-tabs v-model="activeTab">
      <el-tab-pane label="版本管理" name="versions">
        <div class="section-header">
          <h3>API 版本管理</h3>
          <el-button type="primary" @click="showCreateDialog = true">
            <el-icon><Plus /></el-icon> 新建版本
          </el-button>
        </div>

        <el-table :data="versions" stripe style="width: 100%">
          <el-table-column prop="version" label="版本号" width="100" />
          <el-table-column prop="base_path" label="基础路径" width="160" />
          <el-table-column prop="name" label="名称" min-width="140" />
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="statusType(row.status)" size="small">
                {{ statusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="默认" width="70" align="center">
            <template #default="{ row }">
              <el-tag v-if="row.is_default" type="success" size="small">默认</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="deprecated_at" label="废弃时间" width="120">
            <template #default="{ row }">{{ row.deprecated_at ? formatDate(row.deprecated_at) : '-' }}</template>
          </el-table-column>
          <el-table-column prop="sunset_at" label="计划停用" width="120">
            <template #default="{ row }">{{ row.sunset_at ? formatDate(row.sunset_at) : '-' }}</template>
          </el-table-column>
          <el-table-column prop="created_at" label="创建时间" width="120">
            <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
          </el-table-column>
          <el-table-column label="操作" width="280" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="viewVersion(row)">详情</el-button>
              <el-dropdown v-if="row.status !== 'retired'" trigger="click" @command="(cmd) => handleLifecycle(cmd, row)">
                <el-button size="small">
                  生命周期 <el-icon><ArrowDown /></el-icon>
                </el-button>
                <template #dropdown>
                  <el-dropdown-menu>
                    <el-dropdown-item v-if="row.status === 'active'" command="deprecate" :disabled="row.is_default">
                      标记废弃
                    </el-dropdown-item>
                    <el-dropdown-item v-if="row.status === 'deprecated'" command="sunset">
                      停用 (Sunset)
                    </el-dropdown-item>
                    <el-dropdown-item v-if="row.status === 'sunset' || row.status === 'deprecated'" command="retire">
                      退役 (Retire)
                    </el-dropdown-item>
                    <el-dropdown-item divided command="delete">
                      <span class="text-danger">删除</span>
                    </el-dropdown-item>
                  </el-dropdown-menu>
                </template>
              </el-dropdown>
              <el-button v-if="!row.is_default && row.status === 'active'" size="small" type="primary" plain @click="setDefault(row)">
                设为默认
              </el-button>
            </template>
          </el-table-column>
        </el-table>

        <el-empty v-if="!loading && versions.length === 0" description="暂无 API 版本" />
      </el-tab-pane>

      <el-tab-pane label="版本详情" name="detail" :disabled="!selectedVersion">
        <template #label>
          <span v-if="selectedVersion">{{ selectedVersion.version }} 详情</span>
          <span v-else>版本详情</span>
        </template>

        <div v-if="selectedVersion" class="version-detail">
          <el-descriptions :column="2" border>
            <el-descriptions-item label="版本号">{{ selectedVersion.version }}</el-descriptions-item>
            <el-descriptions-item label="基础路径">
              <code>{{ selectedVersion.base_path }}</code>
            </el-descriptions-item>
            <el-descriptions-item label="名称">{{ selectedVersion.name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag :type="statusType(selectedVersion.status)" size="small">
                {{ statusLabel(selectedVersion.status) }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="默认版本">
              <el-tag v-if="selectedVersion.is_default" type="success" size="small">是</el-tag>
              <span v-else>否</span>
            </el-descriptions-item>
            <el-descriptions-item label="废弃时间">
              {{ selectedVersion.deprecated_at ? formatDate(selectedVersion.deprecated_at) : '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="计划停用时间">
              {{ selectedVersion.sunset_at ? formatDate(selectedVersion.sunset_at) : '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="退役时间">
              {{ selectedVersion.retired_at ? formatDate(selectedVersion.retired_at) : '-' }}
            </el-descriptions-item>
            <el-descriptions-item :span="2" label="废弃通知">
              {{ selectedVersion.deprecation_notice || '-' }}
            </el-descriptions-item>
            <el-descriptions-item :span="2" label="迁移指南">
              <template #default>
                <div v-if="selectedVersion.migration_guide" class="migration-guide-content">
                  <pre>{{ selectedVersion.migration_guide }}</pre>
                </div>
                <span v-else>-</span>
              </template>
            </el-descriptions-item>
            <el-descriptions-item :span="2" label="变更说明">
              <template #default>
                <div v-if="selectedVersion.changelog" class="changelog-content">
                  <pre>{{ selectedVersion.changelog }}</pre>
                </div>
                <span v-else>-</span>
              </template>
            </el-descriptions-item>
          </el-descriptions>

          <el-divider />

          <h4>注册的路由</h4>
          <el-table :data="versionRoutes" stripe style="width: 100%">
            <el-table-column prop="method" label="方法" width="80">
              <template #default="{ row }">
                <el-tag :type="methodType(row.method)" size="small">{{ row.method }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="path" label="路径" min-width="250">
              <template #default="{ row }">
                <code>{{ selectedVersion.base_path }}{{ row.path }}</code>
              </template>
            </el-table-column>
            <el-table-column prop="route_name" label="路由名称" width="150" />
            <el-table-column prop="controller" label="控制器" width="200" />
            <el-table-column prop="action" label="方法" width="120" />
            <el-table-column prop="is_deprecated" label="已废弃" width="80" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.is_deprecated" type="warning" size="small">是</el-tag>
                <span v-else>否</span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="80" fixed="right">
              <template #default="{ row }">
                <el-popconfirm title="确定删除此路由?" @confirm="deleteRoute(row)">
                  <template #reference>
                    <el-button size="small" type="danger" link>删除</el-button>
                  </template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </el-tab-pane>

      <el-tab-pane label="调用统计" name="stats">
        <div class="section-header">
          <h3>API 版本调用统计</h3>
          <div class="filter-bar">
            <el-select v-model="statsVersion" placeholder="选择版本" clearable style="width: 150px">
              <el-option v-for="v in versions" :key="v.id" :label="v.version" :value="v.version" />
            </el-select>
            <el-date-picker
              v-model="statsDateRange"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
            />
            <el-button type="primary" @click="loadStats">查询</el-button>
          </div>
        </div>

        <el-table v-if="statsData.length > 0" :data="statsData" stripe style="width: 100%">
          <el-table-column prop="call_date" label="日期" width="120" />
          <el-table-column prop="method" label="方法" width="80">
            <template #default="{ row }">
              <el-tag :type="methodType(row.method)" size="small">{{ row.method }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="path" label="路径" min-width="250" />
          <el-table-column prop="total_calls" label="调用次数" width="120" align="right">
            <template #default="{ row }">
              <strong>{{ formatNumber(row.total_calls) }}</strong>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-else-if="statsLoaded" description="暂无统计数据" />
      </el-tab-pane>

      <el-tab-pane label="影响分析" name="impact">
        <div class="section-header">
          <h3>版本影响分析</h3>
          <div class="filter-bar">
            <el-select v-model="impactVersion" placeholder="选择版本" style="width: 200px">
              <el-option v-for="v in versions" :key="v.id" :label="v.version" :value="v.version" />
            </el-select>
            <el-button type="primary" @click="loadImpactAnalysis">分析</el-button>
          </div>
        </div>

        <template v-if="impactData">
          <el-alert
            :title="`版本 ${impactVersion} 影响 ${impactData.affected_tenants_count} 个客户`"
            :type="impactData.affected_tenants_count > 0 ? 'warning' : 'success'"
            show-icon
            style="margin-bottom: 16px"
          />
          <el-table v-if="impactData.tenants.length > 0" :data="impactData.tenants" stripe style="width: 100%">
            <el-table-column prop="tenant_name" label="客户名称" min-width="200" />
            <el-table-column prop="total_calls" label="总调用次数" width="120" align="right">
              <template #default="{ row }">
                <strong>{{ formatNumber(row.total_calls) }}</strong>
              </template>
            </el-table-column>
            <el-table-column prop="last_call_date" label="最后调用时间" width="140">
              <template #default="{ row }">{{ row.last_call_date }}</template>
            </el-table-column>
          </el-table>
        </template>
      </el-tab-pane>

      <el-tab-pane label="使用趋势" name="trend">
        <div class="section-header">
          <h3>版本使用趋势</h3>
          <div class="filter-bar">
            <el-date-picker
              v-model="trendDateRange"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
            />
            <el-button type="primary" @click="loadTrend">查询</el-button>
          </div>
        </div>

        <el-table v-if="trendData.length > 0" :data="trendData" stripe style="width: 100%">
          <el-table-column prop="date" label="日期" width="120" />
          <el-table-column prop="version" label="版本" width="100" />
          <el-table-column prop="calls" label="调用次数" width="120" align="right">
            <template #default="{ row }">
              <strong>{{ formatNumber(row.calls) }}</strong>
            </template>
          </el-table-column>
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-else-if="trendLoaded && trendData.length === 0" description="暂无趋势数据" />
      </el-tab-pane>
    </el-tabs>

    <!-- 新建版本对话框 -->
    <el-dialog v-model="showCreateDialog" title="新建 API 版本" width="600px">
      <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="100px">
        <el-form-item label="版本号" prop="version">
          <el-input v-model="createForm.version" placeholder="如 v2" />
        </el-form-item>
        <el-form-item label="名称" prop="name">
          <el-input v-model="createForm.name" placeholder="如 当前稳定版" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="createForm.status" default-first-option>
            <el-option label="活跃 (active)" value="active" />
            <el-option label="废弃 (deprecated)" value="deprecated" />
          </el-select>
        </el-form-item>
        <el-form-item label="设为默认">
          <el-switch v-model="createForm.is_default" />
        </el-form-item>
        <el-form-item label="变更说明" prop="changelog">
          <el-input v-model="createForm.changelog" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="迁移指南" prop="migration_guide">
          <el-input v-model="createForm.migration_guide" type="textarea" :rows="3" placeholder="URL 或文字说明" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" :loading="creating" @click="createVersion">确定</el-button>
      </template>
    </el-dialog>

    <!-- 标记废弃对话框 -->
    <el-dialog v-model="showDeprecateDialog" title="标记版本废弃" width="500px">
      <el-alert
        title="版本废弃后将开始 6 个月倒计时，到期自动停用。请确保提供清晰的迁移指南。"
        type="warning"
        show-icon
        :closable="false"
        style="margin-bottom: 16px"
      />
      <el-form ref="deprecateFormRef" :model="deprecateForm" label-width="120px">
        <el-form-item label="迁移指南">
          <el-input v-model="deprecateForm.migration_guide" type="textarea" :rows="3" placeholder="URL 或文字说明" />
        </el-form-item>
        <el-form-item label="废弃通知">
          <el-input v-model="deprecateForm.deprecation_notice" type="textarea" :rows="3" placeholder="返回给客户端的通知文本" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDeprecateDialog = false">取消</el-button>
        <el-button type="warning" :loading="deprecating" @click="confirmDeprecate">确认废弃</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, ArrowDown } from '@element-plus/icons-vue'
import api from '../../api/apiVersion'

const activeTab = ref('versions')
const loading = ref(false)
const versions = ref([])
const selectedVersion = ref(null)
const versionRoutes = ref([])

// 统计
const statsVersion = ref('')
const statsDateRange = ref([])
const statsData = ref([])
const statsLoaded = ref(false)

// 影响分析
const impactVersion = ref('')
const impactData = ref(null)

// 趋势
const trendDateRange = ref([])
const trendData = ref([])
const trendLoaded = ref(false)

// 创建
const showCreateDialog = ref(false)
const creating = ref(false)
const createFormRef = ref(null)
const createForm = reactive({
    version: '',
    name: '',
    status: 'active',
    is_default: false,
    changelog: '',
    migration_guide: '',
})
const createRules = {
    version: [{ required: true, message: '请输入版本号', trigger: 'blur' }],
}

// 废弃对话框
const showDeprecateDialog = ref(false)
const deprecatingVersion = ref(null)
const deprecateFormRef = ref(null)
const deprecating = ref(false)
const deprecateForm = reactive({
    migration_guide: '',
    deprecation_notice: '',
})

onMounted(() => {
    loadVersions()
})

async function loadVersions() {
    loading.value = true
    try {
        const { data } = await api.index()
        versions.value = data.data || []
    } catch (e) {
        ElMessage.error('获取版本列表失败')
    } finally {
        loading.value = false
    }
}

function statusType(status) {
    const map = { active: 'success', deprecated: 'warning', sunset: 'danger', retired: 'info' }
    return map[status] || 'info'
}

function statusLabel(status) {
    const map = { active: '活跃', deprecated: '废弃', sunset: '停用中', retired: '已退役' }
    return map[status] || status
}

function methodType(method) {
    const map = { GET: 'success', POST: 'primary', PUT: 'warning', PATCH: 'warning', DELETE: 'danger' }
    return map[method] || 'info'
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    const d = new Date(dateStr)
    return d.toLocaleDateString('zh-CN') + ' ' + d.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' })
}

function formatNumber(n) {
    return Number(n || 0).toLocaleString()
}

async function viewVersion(version) {
    selectedVersion.value = version
    activeTab.value = 'detail'
    try {
        const { data } = await api.show(version.version)
        selectedVersion.value = data.data?.version || version
        versionRoutes.value = data.data?.routes || []
    } catch (e) {
        ElMessage.error('获取版本详情失败')
    }
}

async function createVersion() {
    if (!createFormRef.value) return
    const valid = await createFormRef.value.validate().catch(() => false)
    if (!valid) return

    creating.value = true
    try {
        await api.store(createForm)
        ElMessage.success('版本创建成功')
        showCreateDialog.value = false
        createForm.version = ''
        createForm.name = ''
        createForm.status = 'active'
        createForm.is_default = false
        createForm.changelog = ''
        createForm.migration_guide = ''
        await loadVersions()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '创建失败')
    } finally {
        creating.value = false
    }
}

function handleLifecycle(cmd, row) {
    if (cmd === 'delete') {
        ElMessageBox.confirm(`确定删除版本 ${row.version}？此操作不可撤销！`, '警告', {
            confirmButtonText: '删除',
            cancelButtonText: '取消',
            type: 'error',
        }).then(async () => {
            try {
                await api.destroy(row.version)
                ElMessage.success('版本已删除')
                await loadVersions()
                if (selectedVersion.value?.id === row.id) {
                    selectedVersion.value = null
                    versionRoutes.value = []
                }
            } catch (e) {
                ElMessage.error('删除失败')
            }
        }).catch(() => {})
        return
    }

    if (cmd === 'deprecate') {
        deprecatingVersion.value = row
        deprecateForm.migration_guide = row.migration_guide || ''
        deprecateForm.deprecation_notice = row.deprecation_notice || ''
        showDeprecateDialog.value = true
        return
    }

    const actionMap = {
        sunset: { method: 'sunset', msg: '版本已停用' },
        retire: { method: 'retire', msg: '版本已退役' },
    }

    const action = actionMap[cmd]
    if (!action) return

    ElMessageBox.confirm(`确定${cmd === 'sunset' ? '停用' : '退役'}版本 ${row.version}？`, '确认', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
    }).then(async () => {
        try {
            await api[action.method](row.version)
            ElMessage.success(action.msg)
            await loadVersions()
            if (selectedVersion.value?.id === row.id) {
                selectedVersion.value = null
                versionRoutes.value = []
            }
        } catch (e) {
            ElMessage.error(e.response?.data?.message || '操作失败')
        }
    }).catch(() => {})
}

async function confirmDeprecate() {
    deprecating.value = true
    try {
        const { data } = await api.deprecate(deprecatingVersion.value.version, deprecateForm)
        ElMessage.success(`版本已废弃，${data.data?.notice || ''}`)
        showDeprecateDialog.value = false
        deprecatingVersion.value = null
        await loadVersions()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    } finally {
        deprecating.value = false
    }
}

async function setDefault(row) {
    try {
        await api.update(row.version, { is_default: true })
        ElMessage.success('已设为默认版本')
        await loadVersions()
    } catch (e) {
        ElMessage.error('设置失败')
    }
}

async function deleteRoute(route) {
    if (!selectedVersion.value) return
    try {
        await api.deleteRoute(selectedVersion.value.version, route.id)
        ElMessage.success('路由已删除')
        await viewVersion(selectedVersion.value)
    } catch (e) {
        ElMessage.error('删除失败')
    }
}

async function loadStats() {
    if (!statsVersion.value) {
        ElMessage.warning('请选择版本')
        return
    }
    statsLoaded.value = false
    try {
        const params = {}
        if (statsDateRange.value) {
            params.start_date = statsDateRange.value[0]
            params.end_date = statsDateRange.value[1]
        }
        const { data } = await api.callStats(statsVersion.value, params)
        statsData.value = data.data?.stats || []
    } catch (e) {
        ElMessage.error('获取统计数据失败')
    } finally {
        statsLoaded.value = true
    }
}

async function loadImpactAnalysis() {
    if (!impactVersion.value) {
        ElMessage.warning('请选择版本')
        return
    }
    try {
        const { data } = await api.impactAnalysis(impactVersion.value)
        impactData.value = data.data
    } catch (e) {
        ElMessage.error('获取影响分析失败')
    }
}

async function loadTrend() {
    trendLoaded.value = false
    try {
        const params = {}
        if (trendDateRange.value) {
            params.start_date = trendDateRange.value[0]
            params.end_date = trendDateRange.value[1]
        }
        const { data } = await api.usageTrend(params)
        trendData.value = data.data || []
    } catch (e) {
        ElMessage.error('获取趋势数据失败')
    } finally {
        trendLoaded.value = true
    }
}
</script>

<style scoped>
.api-version-management {
    padding: 20px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.section-header h3 {
    margin: 0;
}

.filter-bar {
    display: flex;
    gap: 12px;
    align-items: center;
}

.version-detail {
    max-width: 1000px;
}

.migration-guide-content pre,
.changelog-content pre {
    background: #f5f7fa;
    padding: 12px;
    border-radius: 4px;
    white-space: pre-wrap;
    font-size: 13px;
    max-height: 200px;
    overflow-y: auto;
}

.text-danger {
    color: #f56c6c;
}

code {
    background: #f5f7fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 13px;
}
</style>
