<template>
    <div class="merkle-page">
        <div class="page-header">
            <h2>{{ t(`${P}.title`) }}</h2>
            <el-tag type="danger" effect="dark" size="small">{{ t(`${P}.badge`) }}</el-tag>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t(`${P}.stats.total`) }}</div>
                        <div class="stat-value">{{ stats.total_logs }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t(`${P}.stats.hashed`) }}</div>
                        <div class="stat-value text-success">{{ stats.hashed_logs }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ backgroundColor: stats.unhashed_logs > 0 ? '#fef0f0' : '' }">
                    <div class="stat-item">
                        <div class="stat-label">{{ t(`${P}.stats.unhashed`) }}</div>
                        <div class="stat-value" :class="stats.unhashed_logs > 0 ? 'text-danger' : 'text-success'">
                            {{ stats.unhashed_logs }}
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t(`${P}.stats.coverage`) }}</div>
                        <div class="stat-value" :class="coverageClass">{{ stats.chain_coverage }}%</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t(`${P}.stats.anchors`) }}</div>
                        <div class="stat-value">{{ stats.anchor_count }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="18">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t(`${P}.stats.latest_root`) }}</div>
                        <div class="stat-hash">
                            <code>{{ latestRootHash }}</code>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never" class="mb-4">
            <template #header>
                <span>{{ t(`${P}.actions_title`) }}</span>
            </template>
            <el-space>
                <el-button type="primary" :loading="verifying" @click="handleVerify">
                    <el-icon><DataBoard /></el-icon> {{ t(`${P}.verify`) }}
                </el-button>
                <el-button type="warning" :loading="anchoring" @click="handleAnchor">
                    <el-icon><Link /></el-icon> {{ t(`${P}.anchor`) }}
                </el-button>
                <el-button type="warning" plain :loading="anchoringForce" @click="handleAnchorForce">
                    <el-icon><WarnTriangleFilled /></el-icon> {{ t(`${P}.anchor_force`) }}
                </el-button>
                <el-button :disabled="stats.unhashed_logs === 0" :loading="backfilling" @click="handleBackfill">
                    <el-icon><Refresh /></el-icon> {{ t(`${P}.backfill`, { n: stats.unhashed_logs }) }}
                </el-button>
            </el-space>
        </el-card>

        <el-card v-if="verifyResult" shadow="never" class="mb-4"
            :class="{ 'verify-pass': verifyResult.valid, 'verify-fail': !verifyResult.valid }"
        >
            <template #header>
                <div class="card-header">
                    <span>{{ t(`${P}.verify_result`) }}</span>
                    <el-tag :type="verifyResult.valid ? 'success' : 'danger'" size="small">
                        {{ verifyResult.valid ? t(`${P}.valid`) : t(`${P}.invalid`) }}
                    </el-tag>
                </div>
            </template>
            <div v-if="verifyResult.checked === 0" class="verify-empty">
                <el-empty :description="t(`${P}.nothing_to_verify`)" />
            </div>
            <template v-else>
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item :label="t(`${P}.checked`)">{{ verifyResult.checked }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.anchor_match`)">
                        <el-tag v-if="verifyResult.anchor_match === true" type="success" size="small">{{ t(`${P}.match`) }}</el-tag>
                        <el-tag v-else-if="verifyResult.anchor_match === false" type="danger" size="small">{{ t(`${P}.mismatch`) }}</el-tag>
                        <span v-else>{{ t(`${P}.not_checked`) }}</span>
                    </el-descriptions-item>
                </el-descriptions>

                <div class="verify-details">
                    <h4>{{ t(`${P}.verify_details`) }}</h4>
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
                    <h4>{{ t(`${P}.error_details`) }}</h4>
                    <el-table :data="verifyResult.errors" stripe size="small" border>
                        <el-table-column prop="log_id" :label="t(`${P}.cols.log_id`)" width="80" />
                        <el-table-column prop="action" :label="t(`${P}.cols.action`)" min-width="120" />
                        <el-table-column prop="expected_hash" :label="t(`${P}.cols.expected`)" min-width="260">
                            <template #default="{ row }">
                                <code class="hash-text">{{ row.expected_hash }}</code>
                            </template>
                        </el-table-column>
                        <el-table-column prop="actual_hash" :label="t(`${P}.cols.actual`)" min-width="260">
                            <template #default="{ row }">
                                <code class="hash-text text-danger">{{ row.actual_hash }}</code>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </template>
        </el-card>

        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>{{ t(`${P}.anchor_history`) }}</span>
                    <el-tag size="small">{{ t(`${P}.records_n`, { n: anchors.length }) }}</el-tag>
                </div>
            </template>
            <el-table :data="anchors" v-loading="loadingAnchors" stripe>
                <el-table-column :label="t(`${P}.cols.anchored_at`)" width="180">
                    <template #default="{ row }">
                        {{ formatDate(row.anchored_at) }}
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.type`)" width="120">
                    <template #default="{ row }">
                        <el-tag size="small" effect="plain">{{ row.anchor_type }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.range`)" width="180">
                    <template #default="{ row }">
                        {{ t(`${P}.log_range`, { from: row.from_log_id, to: row.to_log_id }) }}
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.log_count`)" width="100" prop="log_count" align="center" />
                <el-table-column :label="t(`${P}.cols.root_hash`)" min-width="260">
                    <template #default="{ row }">
                        <code class="hash-text">{{ row.root_hash }}</code>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.ref`)" min-width="150" prop="anchor_ref" />
            </el-table>
            <el-empty v-if="!loadingAnchors && !anchors.length" :description="t(`${P}.empty_anchors`)" />
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { DataBoard, Link, WarnTriangleFilled, Refresh } from '@element-plus/icons-vue'
import {
    getMerkleStats,
    verifyMerkleChain,
    triggerMerkleAnchor,
    getMerkleAnchors,
    backfillMerkleHashes,
} from '@/api/merkle-chain'

const { t, locale } = useI18n()
const P = 'merkle_chain_page'
const dateLocale = computed(() => (locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'))

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
            ElMessage.success(t(`${P}.messages.valid`))
        } else if (verifyResult.value?.checked > 0) {
            ElMessage.error(t(`${P}.messages.tampered`))
        }
    } catch (e) {
        ElMessage.error(t(`${P}.messages.verify_failed`))
    } finally {
        verifying.value = false
    }
}

async function handleAnchor() {
    anchoring.value = true
    try {
        await triggerMerkleAnchor(false, true)
        ElMessage.success(t(`${P}.messages.anchored`))
        fetchStats()
        fetchAnchors()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${P}.messages.anchor_failed`))
    } finally {
        anchoring.value = false
    }
}

async function handleAnchorForce() {
    anchoringForce.value = true
    try {
        await triggerMerkleAnchor(true, true)
        ElMessage.success(t(`${P}.messages.force_anchored`))
        fetchStats()
        fetchAnchors()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${P}.messages.force_failed`))
    } finally {
        anchoringForce.value = false
    }
}

async function handleBackfill() {
    backfilling.value = true
    try {
        const res = await backfillMerkleHashes()
        ElMessage.success(res.data?.data?.message || t(`${P}.messages.backfill_done`))
        fetchStats()
    } catch (e) {
        ElMessage.error(t(`${P}.messages.backfill_failed`))
    } finally {
        backfilling.value = false
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleString(dateLocale.value, {
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
