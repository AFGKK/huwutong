<template>
    <div class="merkle-page">
        <div class="page-header">
            <h2>审计日志 Merkle 链</h2>
            <el-tag type="danger" effect="dark" size="small">防篡改验证</el-tag>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">审计日志总数</div>
                        <div class="stat-value">{{ stats.total_logs }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">已哈希保护</div>
                        <div class="stat-value text-success">{{ stats.hashed_logs }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ backgroundColor: stats.unhashed_logs > 0 ? '#fef0f0' : '' }">
                    <div class="stat-item">
                        <div class="stat-label">未哈希（待回填）</div>
                        <div class="stat-value" :class="stats.unhashed_logs > 0 ? 'text-danger' : 'text-success'">
                            {{ stats.unhashed_logs }}
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">哈希链覆盖率</div>
                        <div class="stat-value" :class="coverageClass">{{ stats.chain_coverage }}%</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">锚定次数</div>
                        <div class="stat-value">{{ stats.anchor_count }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="18">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">最新根哈希</div>
                        <div class="stat-hash">
                            <code>{{ latestRootHash }}</code>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 操作按钮组 -->
        <el-card shadow="never" class="mb-4">
            <template #header>
                <span>操作</span>
            </template>
            <el-space>
                <el-button type="primary" :loading="verifying" @click="handleVerify">
                    <el-icon><DataBoard /></el-icon> 验证完整性
                </el-button>
                <el-button type="warning" :loading="anchoring" @click="handleAnchor">
                    <el-icon><Link /></el-icon> 锚定根哈希
                </el-button>
                <el-button type="warning" plain :loading="anchoringForce" @click="handleAnchorForce">
                    <el-icon><WarnTriangleFilled /></el-icon> 强制锚定
                </el-button>
                <el-button :disabled="stats.unhashed_logs === 0" :loading="backfilling" @click="handleBackfill">
                    <el-icon><Refresh /></el-icon> 回填旧日志 ({{ stats.unhashed_logs }})
                </el-button>
            </el-space>
        </el-card>

        <!-- 验证结果 -->
        <el-card v-if="verifyResult" shadow="never" class="mb-4"
            :class="{ 'verify-pass': verifyResult.valid, 'verify-fail': !verifyResult.valid }"
        >
            <template #header>
                <div class="card-header">
                    <span>验证结果</span>
                    <el-tag :type="verifyResult.valid ? 'success' : 'danger'" size="small">
                        {{ verifyResult.valid ? '完整性验证通过 ✓' : '发现篡改痕迹 ✗' }}
                    </el-tag>
                </div>
            </template>
            <div v-if="verifyResult.checked === 0" class="verify-empty">
                <el-empty description="暂无数据可验证" />
            </div>
            <template v-else>
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item label="检查日志数">{{ verifyResult.checked }}</el-descriptions-item>
                    <el-descriptions-item label="锚定匹配">
                        <el-tag v-if="verifyResult.anchor_match === true" type="success" size="small">匹配</el-tag>
                        <el-tag v-else-if="verifyResult.anchor_match === false" type="danger" size="small">不匹配</el-tag>
                        <span v-else>未检查</span>
                    </el-descriptions-item>
                </el-descriptions>

                <div class="verify-details">
                    <h4>验证详情</h4>
                    <el-timeline>
                        <el-timeline-item
                            v-for="(detail, i) in verifyResult.details"
                            :key="i"
                            :type="detail.includes('✓') ? 'success' : 'danger'"
                        >
                            {{ detail }}
                        </el-timeline-item>
                    </el-timeline>
                </div>

                <div v-if="verifyResult.errors.length" class="verify-errors">
                    <h4>错误详情</h4>
                    <el-table :data="verifyResult.errors" stripe size="small" border>
                        <el-table-column prop="log_id" label="日志 ID" width="80" />
                        <el-table-column prop="action" label="操作" min-width="120" />
                        <el-table-column prop="expected_hash" label="期望哈希" min-width="260">
                            <template #default="{ row }">
                                <code class="hash-text">{{ row.expected_hash }}</code>
                            </template>
                        </el-table-column>
                        <el-table-column prop="actual_hash" label="实际哈希" min-width="260">
                            <template #default="{ row }">
                                <code class="hash-text text-danger">{{ row.actual_hash }}</code>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </template>
        </el-card>

        <!-- 锚定历史 -->
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>锚定历史</span>
                    <el-tag size="small">{{ anchors.length }} 条记录</el-tag>
                </div>
            </template>
            <el-table :data="anchors" v-loading="loadingAnchors" stripe>
                <el-table-column label="锚定时间" width="180">
                    <template #default="{ row }">
                        {{ formatDate(row.anchored_at) }}
                    </template>
                </el-table-column>
                <el-table-column label="类型" width="120">
                    <template #default="{ row }">
                        <el-tag size="small" effect="plain">{{ row.anchor_type }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="覆盖日志范围" width="180">
                    <template #default="{ row }">
                        日志 #{{ row.from_log_id }} ~ #{{ row.to_log_id }}
                    </template>
                </el-table-column>
                <el-table-column label="日志数量" width="100" prop="log_count" align="center" />
                <el-table-column label="根哈希" min-width="260">
                    <template #default="{ row }">
                        <code class="hash-text">{{ row.root_hash }}</code>
                    </template>
                </el-table-column>
                <el-table-column label="引用" min-width="150" prop="anchor_ref" />
            </el-table>
            <el-empty v-if="!loadingAnchors && !anchors.length" description="暂无锚定记录" />
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { DataBoard, Link, WarnTriangleFilled, Refresh } from '@element-plus/icons-vue'
import {
    getMerkleStats,
    verifyMerkleChain,
    triggerMerkleAnchor,
    getMerkleAnchors,
    backfillMerkleHashes,
} from '@/api/merkle-chain'

const stats = ref({})
const anchors = ref([])
const loadingAnchors = ref(false)
const verifying = ref(false)
const anchoring = ref(false)
const anchoringForce = ref(false)
const backfilling = ref(false)
const verifyResult = ref(null)

const coverageClass = computed(() => {
    const v = stats.value.chain_coverage || 0
    if (v >= 99) return 'text-success'
    if (v >= 50) return 'text-warning'
    return 'text-danger'
})

const latestRootHash = computed(() => {
    return stats.value.latest_anchor?.root_hash || '—'
})

async function fetchStats() {
    try {
        const res = await getMerkleStats()
        stats.value = res.data?.data || {}
    } catch {
        stats.value = {}
    }
}

async function fetchAnchors() {
    loadingAnchors.value = true
    try {
        const res = await getMerkleAnchors()
        anchors.value = res.data?.data || []
    } catch {
        anchors.value = []
    } finally {
        loadingAnchors.value = false
    }
}

async function handleVerify() {
    verifying.value = true
    try {
        const res = await verifyMerkleChain()
        verifyResult.value = res.data?.data
        if (verifyResult.value?.valid) {
            ElMessage.success('审计日志完整性验证通过，哈希链未被篡改')
        } else if (verifyResult.value?.checked > 0) {
            ElMessage.error('发现审计日志哈希链断裂，可能有数据被篡改！')
        }
    } catch (e) {
        ElMessage.error('验证失败')
    } finally {
        verifying.value = false
    }
}

async function handleAnchor() {
    anchoring.value = true
    try {
        await triggerMerkleAnchor(false, true)
        ElMessage.success('根哈希已锚定')
        fetchStats()
        fetchAnchors()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '锚定失败')
    } finally {
        anchoring.value = false
    }
}

async function handleAnchorForce() {
    anchoringForce.value = true
    try {
        await triggerMerkleAnchor(true, true)
        ElMessage.success('根哈希已强制锚定')
        fetchStats()
        fetchAnchors()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '强制锚定失败')
    } finally {
        anchoringForce.value = false
    }
}

async function handleBackfill() {
    backfilling.value = true
    try {
        const res = await backfillMerkleHashes()
        ElMessage.success(res.data?.data?.message || '回填完成')
        fetchStats()
    } catch (e) {
        ElMessage.error('回填失败')
    } finally {
        backfilling.value = false
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
    })
}

onMounted(() => {
    fetchStats()
    fetchAnchors()
})
</script>

<style scoped>
.merkle-page {
    max-width: 1200px;
    margin: 0 auto;
}

.page-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.page-header h2 {
    margin: 0;
    font-size: 20px;
}

.mb-4 {
    margin-bottom: 16px;
}

.stat-item {
    text-align: center;
    padding: 8px 0;
}

.stat-label {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-bottom: 8px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--el-text-color-primary);
}

.stat-hash {
    word-break: break-all;
    font-size: 12px;
    background: var(--el-fill-color-light);
    padding: 8px;
    border-radius: 4px;
}

.stat-hash code {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
}

.text-success {
    color: var(--el-color-success);
}

.text-warning {
    color: var(--el-color-warning);
}

.text-danger {
    color: var(--el-color-danger);
}

.verify-empty {
    padding: 20px 0;
}

.verify-details {
    margin-top: 16px;
}

.verify-details h4,
.verify-errors h4 {
    margin: 0 0 8px;
    font-size: 14px;
}

.verify-errors {
    margin-top: 16px;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.hash-text {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 11px;
    word-break: break-all;
}

:deep(.verify-pass) {
    border-left: 4px solid var(--el-color-success);
}

:deep(.verify-fail) {
    border-left: 4px solid var(--el-color-danger);
}

:deep(.el-card__body) {
    padding: 16px;
}
</style>
