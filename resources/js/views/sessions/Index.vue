<template>
    <div class="sessions-page">
        <div class="page-header">
            <div class="header-left">
                <h2>活跃会话管理</h2>
                <span class="header-subtitle">查看和管理当前账号的所有活跃登录会话</span>
            </div>
            <div class="header-right">
                <el-button type="danger" plain @click="handleRevokeAll">
                    <el-icon><Remove /></el-icon>
                    强制下线所有会话
                </el-button>
                <el-button @click="loadSessions">
                    <el-icon><Refresh /></el-icon>
                    刷新
                </el-button>
            </div>
        </div>

        <el-alert
            title="安全提示"
            type="info"
            :closable="false"
            show-icon
            class="mb-4"
            description="如发现可疑的登录会话，请立即强制下线。当前会话下线后需要重新登录。"
        />

        <!-- 会话统计 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">总会话数</div>
                        <div class="stat-value">{{ sessions.length }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">当前设备</div>
                        <div class="stat-value text-primary">{{ currentSessionCount }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">其他设备</div>
                        <div class="stat-value text-warning">{{ otherSessionCount }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 会话列表 -->
        <el-card shadow="never">
            <div v-loading="loading" class="session-list">
                <div v-if="sessions.length === 0 && !loading" class="empty-state">
                    <el-empty :image-size="80" description="暂无活跃会话" />
                </div>

                <div
                    v-for="session in sessions"
                    :key="session.id"
                    class="session-card"
                    :class="{ 'is-current': session.is_current }"
                >
                    <div class="session-icon">
                        <el-icon :size="32" :color="session.is_current ? '#409eff' : '#909399'">
                            <Monitor />
                        </el-icon>
                    </div>
                    <div class="session-body">
                        <div class="session-header">
                            <span class="session-name">
                                {{ session.name || '未命名设备' }}
                            </span>
                            <el-tag
                                v-if="session.is_current"
                                type="primary"
                                size="small"
                                effect="dark"
                            >
                                当前设备
                            </el-tag>
                            <span class="session-time">
                                最后活跃：{{ formatDate(session.last_used_at) }}
                            </span>
                        </div>
                        <div class="session-meta">
                            <span v-if="session.ip_address" class="meta-item">
                                <el-icon><Location /></el-icon>
                                {{ session.ip_address }}
                            </span>
                            <span class="meta-item">
                                <el-icon><Clock /></el-icon>
                                创建于 {{ formatDate(session.created_at) }}
                            </span>
                            <span v-if="session.expires_at" class="meta-item">
                                <el-icon><Timer /></el-icon>
                                过期于 {{ formatDate(session.expires_at) }}
                            </span>
                        </div>
                        <div v-if="session.user_agent" class="session-ua">
                            {{ session.user_agent }}
                        </div>
                    </div>
                    <div class="session-actions">
                        <el-button
                            v-if="!session.is_current"
                            type="danger"
                            plain
                            size="small"
                            @click="handleRevoke(session)"
                        >
                            强制下线
                        </el-button>
                        <el-tag v-else type="success" effect="plain" size="small">在线</el-tag>
                    </div>
                </div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Remove, Monitor, Location, Clock, Timer } from '@element-plus/icons-vue';
import apiClient from '@/api/client';

const loading = ref(false);
const sessions = ref([]);

const currentSessionCount = computed(() => sessions.value.filter(s => s.is_current).length);
const otherSessionCount = computed(() => sessions.value.filter(s => !s.is_current).length);

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

async function loadSessions() {
    loading.value = true;
    try {
        const { data: res } = await apiClient.get('/sessions');
        sessions.value = res.data || [];
    } catch {
        sessions.value = [];
    } finally {
        loading.value = false;
    }
}

async function handleRevoke(session) {
    try {
        await ElMessageBox.confirm(
            `确定要强制下线 "${session.name || '未命名设备'}" 的登录会话吗？`,
            '强制下线',
            {
                confirmButtonText: '确定下线',
                cancelButtonText: '取消',
                type: 'warning',
            }
        );
        await apiClient.delete(`/sessions/${session.id}`);
        ElMessage.success('已强制下线该会话');
        loadSessions();
    } catch {
        // cancelled
    }
}

async function handleRevokeAll() {
    const otherSessions = sessions.value.filter(s => !s.is_current);
    if (otherSessions.length === 0) {
        ElMessage.info('没有其他活跃会话需要下线');
        return;
    }
    try {
        await ElMessageBox.confirm(
            `确定要强制下线所有 ${otherSessions.length} 个其他设备的登录会话吗？此操作不可撤销。`,
            '下线所有会话',
            {
                confirmButtonText: '全部下线',
                cancelButtonText: '取消',
                type: 'warning',
                confirmButtonClass: 'el-button--danger',
            }
        );
        // 逐个下线
        for (const s of otherSessions) {
            try {
                await apiClient.delete(`/sessions/${s.id}`);
            } catch { /* ignore individual failures */ }
        }
        ElMessage.success(`已强制下线 ${otherSessions.length} 个会话`);
        loadSessions();
    } catch {
        // cancelled
    }
}

onMounted(() => {
    loadSessions();
});
</script>

<style scoped>
.sessions-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.mb-4 { margin-bottom: 16px; }

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
.text-primary { color: var(--el-color-primary); }
.text-warning { color: var(--el-color-warning); }

.empty-state { padding: 40px 0; }

.session-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.session-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    border: 1px solid var(--el-border-color-light);
    border-radius: 8px;
    transition: all 0.2s;
}
.session-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}
.session-card.is-current {
    border-color: var(--el-color-primary-light-5);
    background: var(--el-color-primary-light-9);
}

.session-icon {
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--el-color-info-light-9);
    border-radius: 12px;
}

.session-body {
    flex: 1;
    min-width: 0;
}

.session-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
    flex-wrap: wrap;
}
.session-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--el-text-color-primary);
}
.session-time {
    font-size: 12px;
    color: var(--el-text-color-placeholder);
    margin-left: auto;
}

.session-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 4px;
}
.meta-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: var(--el-text-color-secondary);
}

.session-ua {
    font-size: 11px;
    color: var(--el-text-color-placeholder);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 500px;
}

.session-actions {
    flex-shrink: 0;
}

:deep(.el-card__body) { padding: 16px; }
</style>
