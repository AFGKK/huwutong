<template>
    <div class="password-policy-page">
        <el-tabs v-model="activeTab">
            <!-- 密码策略配置 -->
            <el-tab-pane label="密码策略" name="config">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>密码强度策略配置</span>
                        </div>
                    </template>

                    <el-form :model="form" label-width="180px" v-loading="loading">
                        <el-divider content-position="left">密码长度</el-divider>
                        <el-form-item label="最小长度">
                            <el-input-number v-model="form.min_length" :min="4" :max="256" />
                            <span class="form-help">字符</span>
                        </el-form-item>
                        <el-form-item label="最大长度">
                            <el-input-number v-model="form.max_length" :min="8" :max="256" />
                            <span class="form-help">字符</span>
                        </el-form-item>

                        <el-divider content-position="left">密码复杂度</el-divider>
                        <el-form-item label="需要大写字母">
                            <el-switch v-model="form.require_uppercase" />
                        </el-form-item>
                        <el-form-item label="需要小写字母">
                            <el-switch v-model="form.require_lowercase" />
                        </el-form-item>
                        <el-form-item label="需要数字">
                            <el-switch v-model="form.require_number" />
                        </el-form-item>
                        <el-form-item label="需要特殊字符">
                            <el-switch v-model="form.require_special" />
                        </el-form-item>

                        <el-divider content-position="left">密码历史与过期</el-divider>
                        <el-form-item label="禁止使用最近密码次数">
                            <el-input-number v-model="form.history_count" :min="0" :max="50" />
                            <span class="form-help">次，设为 0 则不检查</span>
                        </el-form-item>
                        <el-form-item label="密码过期天数">
                            <el-input-number v-model="form.expiry_days" :min="0" :max="365" />
                            <span class="form-help">天，设为 0 则永不过期</span>
                        </el-form-item>

                        <el-divider content-position="left">账号锁定</el-divider>
                        <el-form-item label="最大失败尝试次数">
                            <el-input-number v-model="form.lockout_max_attempts" :min="1" :max="100" />
                            <span class="form-help">次</span>
                        </el-form-item>
                        <el-form-item label="锁定持续时间">
                            <el-input-number v-model="form.lockout_duration_minutes" :min="1" :max="1440" />
                            <span class="form-help">分钟</span>
                        </el-form-item>

                        <el-divider content-position="left">生效状态</el-divider>
                        <el-form-item label="策略已启用">
                            <el-switch v-model="form.is_active" />
                        </el-form-item>

                        <el-form-item>
                            <el-button type="primary" @click="handleSave" :loading="saving">
                                保存策略
                            </el-button>
                            <el-button @click="fetchConfig">重置</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <!-- 锁定账号管理 -->
            <el-tab-pane label="锁定账号" name="locked">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>被锁定的账号</span>
                            <el-tag type="danger">共 {{ total }} 个账号被锁定</el-tag>
                        </div>
                    </template>

                    <el-table :data="lockedAccounts" v-loading="loadingLocked" stripe style="width: 100%">
                        <el-table-column prop="id" label="ID" width="80" />
                        <el-table-column prop="name" label="用户名" min-width="150" />
                        <el-table-column prop="email" label="邮箱" min-width="200" />
                        <el-table-column label="锁定原因" width="200">
                            <template #default="{ row }">
                                <el-tag type="danger" size="small">
                                    连续登录失败 {{ row.login_attempts || '多次' }} 次
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="锁定直到" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.locked_until) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="120">
                            <template #default="{ row }">
                                <el-tag type="danger" size="small">
                                    {{ getRemaining(row) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="120" fixed="right">
                            <template #default="{ row }">
                                <el-popconfirm
                                    title="确定解锁此账号？"
                                    @confirm="handleUnlock(row.id)"
                                >
                                    <template #reference>
                                        <el-button type="primary" size="small" :icon="Unlock">
                                            解锁
                                        </el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-empty v-if="!loadingLocked && !lockedAccounts.length" description="暂无被锁定的账号" />

                    <el-pagination
                        v-if="total > 0"
                        v-model:current-page="currentPage"
                        :page-size="perPage"
                        :total="total"
                        layout="prev, pager, next"
                        class="mt-4 justify-center"
                        @current-change="fetchLockedAccounts"
                    />
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Unlock } from '@element-plus/icons-vue'
import {
    getPasswordPolicy,
    updatePasswordPolicy,
    getLockedAccounts,
    unlockAccount,
} from '@/api/password-policy'

const activeTab = ref('config')

// 策略配置
const form = ref({
    min_length: 8,
    max_length: 128,
    require_uppercase: true,
    require_lowercase: true,
    require_number: true,
    require_special: true,
    history_count: 5,
    expiry_days: 90,
    lockout_max_attempts: 5,
    lockout_duration_minutes: 15,
    is_active: true,
})
const loading = ref(false)
const saving = ref(false)

// 锁定账号
const lockedAccounts = ref([])
const loadingLocked = ref(false)
const total = ref(0)
const currentPage = ref(1)
const perPage = ref(20)

async function fetchConfig() {
    loading.value = true
    try {
        const res = await getPasswordPolicy()
        const data = res.data?.data
        if (data) {
            form.value = {
                min_length: data.min_length ?? 8,
                max_length: data.max_length ?? 128,
                require_uppercase: data.require_uppercase ?? true,
                require_lowercase: data.require_lowercase ?? true,
                require_number: data.require_number ?? true,
                require_special: data.require_special ?? true,
                history_count: data.history_count ?? 5,
                expiry_days: data.expiry_days ?? 90,
                lockout_max_attempts: data.lockout_max_attempts ?? 5,
                lockout_duration_minutes: data.lockout_duration_minutes ?? 15,
                is_active: data.is_active ?? true,
            }
        }
    } catch (e) {
        ElMessage.error('获取密码策略失败')
    } finally {
        loading.value = false
    }
}

async function handleSave() {
    saving.value = true
    try {
        await updatePasswordPolicy(form.value)
        ElMessage.success('密码策略已更新')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败')
    } finally {
        saving.value = false
    }
}

async function fetchLockedAccounts() {
    loadingLocked.value = true
    try {
        const res = await getLockedAccounts({
            page: currentPage.value,
            per_page: perPage.value,
        })
        lockedAccounts.value = res.data?.data?.data || []
        total.value = res.data?.data?.total || 0
    } catch (e) {
        ElMessage.error('获取锁定账号列表失败')
    } finally {
        loadingLocked.value = false
    }
}

async function handleUnlock(userId) {
    try {
        await unlockAccount(userId)
        ElMessage.success('账号已解锁')
        fetchLockedAccounts()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '解锁失败')
    }
}

function getRemaining(row) {
    if (!row.locked_until) return '正常'
    const remaining = new Date(row.locked_until) - new Date()
    if (remaining <= 0) return '即将解锁'
    const mins = Math.ceil(remaining / 60000)
    return `${mins} 分钟后解锁`
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    })
}

onMounted(() => {
    fetchConfig()
})
</script>

<style scoped>
.password-policy-page {
    max-width: 900px;
    margin: 0 auto;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.form-help {
    color: #999;
    font-size: 12px;
    margin-left: 8px;
}

.mt-4 {
    margin-top: 16px;
}

.justify-center {
    display: flex;
    justify-content: center;
}
</style>
