<template>
    <div class="text-to-sql-page">
        <div class="page-header">
            <div>
                <h2>Text-to-SQL 安全护栏</h2>
                <p class="text-muted">自然语言→SQL · 只读强制 · 危险关键词拦截 · 敏感字段脱敏 · 租户隔离 · 行数限制</p>
            </div>
            <div class="header-actions">
                <el-button @click="loadConfig" :loading="loading" :icon="Refresh">刷新配置</el-button>
            </div>
        </div>

        <el-row :gutter="16">
            <!-- 配置面板 -->
            <el-col :span="8">
                <el-card shadow="hover" class="mb-4">
                    <template #header><span><el-icon><Setting /></el-icon> 安全配置</span></template>
                    <div v-if="cfg">
                        <div class="cfg-item"><span class="cfg-label">只读模式</span><el-tag type="success" size="small">仅 SELECT / WITH</el-tag></div>
                        <div class="cfg-item"><span class="cfg-label">最大行数</span><el-tag size="small">{{ cfg.max_rows }}</el-tag></div>
                        <div class="cfg-item"><span class="cfg-label">查询超时</span><el-tag size="small">{{ cfg.query_timeout }}s</el-tag></div>
                        <div class="cfg-item"><span class="cfg-label">禁止关键词</span></div>
                        <div class="cfg-tags">
                            <el-tag v-for="kw in forbiddenKeywords" :key="kw" size="small" type="danger" style="margin:2px">{{ kw }}</el-tag>
                        </div>
                        <div class="cfg-item" style="margin-top:8px"><span class="cfg-label">敏感字段</span></div>
                        <div class="cfg-tags">
                            <el-tag v-for="col in sensitiveColumns" :key="col" size="small" type="warning" style="margin:2px">{{ col }}</el-tag>
                        </div>
                        <div class="cfg-item" style="margin-top:8px"><span class="cfg-label">允许的表</span></div>
                        <div class="cfg-tags">
                            <el-tag v-if="cfg.allowed_tables?.length" v-for="t in cfg.allowed_tables" :key="t" size="small" type="info" style="margin:2px">{{ t }}</el-tag>
                            <span v-else class="text-muted">全部表允许</span>
                        </div>
                    </div>
                    <el-skeleton v-else :rows="6" animated />
                </el-card>

                <!-- 安全统计 -->
                <el-card shadow="hover">
                    <template #header><span><el-icon><DataBoard /></el-icon> 安全层</span></template>
                    <el-steps direction="vertical" :active="8" space="36px">
                        <el-step title="只读检查" description="仅允许 SELECT / WITH" />
                        <el-step title="危险关键词" description="31 个关键词拦截" />
                        <el-step title="敏感字段" description="20+ 字段自动脱敏" />
                        <el-step title="表白名单" description="仅允许配置的表" />
                        <el-step title="行数限制" description="自动追加 LIMIT" />
                        <el-step title="租户隔离" description="自动注入 tenant_id" />
                        <el-step title="最终格式化" description="去多余空格加分号" />
                        <el-step title="结果脱敏" description="敏感列值替换为 ***" />
                    </el-steps>
                </el-card>
            </el-col>

            <!-- SQL 查询区 -->
            <el-col :span="16">
                <el-card shadow="hover" class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><EditPen /></el-icon> SQL 安全测试</span>
                            <div>
                                <el-button @click="tab = 'sql'" :type="tab === 'sql' ? 'primary' : ''" size="small">SQL 模式</el-button>
                                <el-button @click="tab = 'nl'" :type="tab === 'nl' ? 'primary' : ''" size="small">自然语言</el-button>
                            </div>
                        </div>
                    </template>

                    <!-- SQL 模式 -->
                    <div v-if="tab === 'sql'">
                        <el-input v-model="sqlInput" type="textarea" :rows="5" placeholder="输入 SQL 语句…" />
                        <div style="margin-top:8px;display:flex;gap:8px">
                            <el-button type="primary" @click="runValidate" :loading="valLoading">验证</el-button>
                            <el-button type="success" @click="runExecute" :loading="execLoading">验证并执行</el-button>
                            <el-button @click="sqlInput = exampleSql">加载示例</el-button>
                        </div>
                    </div>

                    <!-- 自然语言模式 -->
                    <div v-if="tab === 'nl'">
                        <el-input v-model="nlInput" type="textarea" :rows="5" placeholder="例如: 显示最近10个激活的License" />
                        <div style="margin-top:8px">
                            <el-button type="primary" @click="runQuery" :loading="qLoading">生成 SQL 并执行</el-button>
                            <el-button @click="nlInput = exampleNl">加载示例</el-button>
                        </div>
                    </div>
                </el-card>

                <!-- 验证结果 -->
                <el-card v-if="validateResult" shadow="hover" class="mb-4">
                    <template #header>
                        <span><el-icon><CircleCheck /></el-icon> 验证结果</span>
                    </template>
                    <el-alert :title="validateResult.valid ? '✅ 验证通过' : '❌ 验证失败'" :type="validateResult.valid ? 'success' : 'error'" show-icon :closable="false" />
                    <div v-if="validateResult.errors?.length" style="margin-top:8px">
                        <el-tag v-for="e in validateResult.errors" :key="e" type="danger" size="small" style="margin:2px">{{ e }}</el-tag>
                    </div>
                    <div v-if="validateResult.modified_sql" style="margin-top:8px">
                        <div class="label-sm">处理后 SQL</div>
                        <pre class="sql-block"><code>{{ validateResult.modified_sql }}</code></pre>
                    </div>
                </el-card>

                <!-- 查询结果 -->
                <el-card v-if="queryResult" shadow="hover">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><DataBoard /></el-icon> 查询结果</span>
                            <el-tag size="small">{{ queryTime }}</el-tag>
                        </div>
                    </template>
                    <el-alert v-if="queryResult.error" :title="queryResult.error" type="error" show-icon :closable="false" />
                    <el-table v-if="queryResult.columns?.length" :data="queryResult.rows" stripe size="small" max-height="400" border>
                        <el-table-column v-for="col in queryResult.columns" :key="col" :prop="col" :label="col" min-width="120" show-overflow-tooltip />
                    </el-table>
                    <div v-if="queryResult.rows?.length" class="result-info">
                        共 {{ queryResult.rows.length }} 行 · {{ queryResult.columns?.length }} 列
                    </div>
                    <el-empty v-if="queryResult.rows && !queryResult.rows.length && !queryResult.error" description="查询结果为空" />
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, Setting, DataBoard, EditPen, CircleCheck } from '@element-plus/icons-vue';
import textToSqlApi from '@/api/textToSql';

const loading = ref(false);
const valLoading = ref(false);
const execLoading = ref(false);
const qLoading = ref(false);
const tab = ref('sql');
const sqlInput = ref('');
const nlInput = ref('');
const validateResult = ref(null);
const queryResult = ref(null);
const queryTime = ref('');
const cfg = ref(null);

const exampleSql = 'SELECT l.license_key, l.status, c.name AS customer_name\nFROM licenses l\nJOIN customers c ON c.id = l.customer_id\nLIMIT 10';
const exampleNl = '显示最近10个激活的License，包含客户名和激活时间';

const forbiddenKeywords = ['DROP', 'TRUNCATE', 'ALTER', 'INSERT', 'UPDATE', 'DELETE', 'EXEC', 'GRANT', 'REVOKE', 'BENCHMARK', 'PG_SLEEP', 'INTO OUTFILE', 'INTO DUMPFILE', 'LOAD_FILE', 'CREATE', 'RENAME', 'REPLACE', 'KILL', 'SHUTDOWN', 'SET', 'CREATE USER', 'DROP USER', 'ALTER USER', 'FLUSH', 'LOCK', 'UNLOCK', 'INSTALL', 'UNINSTALL', 'DO', 'HANDLER'];
const sensitiveColumns = ['password', 'password_hash', 'api_key', 'api_secret', 'token', 'refresh_token', 'secret', 'credit_card', 'cvv', 'card_number', 'private_key', 'pem_private', 'seed_encrypted', 'totp_secret', 'recovery_codes', 'ssh_key'];

onMounted(loadConfig);

async function loadConfig() {
    loading.value = true;
    try { const r = await textToSqlApi.config(); cfg.value = r.data?.data; } catch { ElMessage.error('加载配置失败'); }
    finally { loading.value = false; }
}

async function runValidate() {
    if (!sqlInput.value.trim()) return;
    valLoading.value = true;
    try {
        const r = await textToSqlApi.validate({ sql: sqlInput.value });
        validateResult.value = r.data?.data;
    } catch (e) { validateResult.value = { valid: false, errors: [e.response?.data?.message || '验证请求失败'] }; }
    finally { valLoading.value = false; }
}

async function runExecute() {
    if (!sqlInput.value.trim()) return;
    execLoading.value = true;
    try {
        const r = await textToSqlApi.execute({ sql: sqlInput.value });
        queryResult.value = r.data?.data;
        queryTime.value = r.data?.data?.duration_ms ? `${r.data.data.duration_ms}ms` : '';
    } catch (e) { queryResult.value = { error: e.response?.data?.message || '执行失败' }; }
    finally { execLoading.value = false; }
}

async function runQuery() {
    if (!nlInput.value.trim()) return;
    qLoading.value = true;
    try {
        const r = await textToSqlApi.query({ query: nlInput.value });
        const d = r.data?.data;
        queryResult.value = d?.result || d;
        sqlInput.value = d?.sql || '';
        validateResult.value = d?.validation || null;
        queryTime.value = d?.duration_ms ? `${d.duration_ms}ms` : '';
    } catch (e) { queryResult.value = { error: e.response?.data?.message || '查询失败' }; }
    finally { qLoading.value = false; }
}
</script>

<style scoped>
.text-to-sql-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.text-muted { color: #909399; }
.card-header { display: flex; justify-content: space-between; align-items: center; }

.cfg-item { margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
.cfg-label { font-size: 13px; color: #909399; min-width: 80px; }
.cfg-tags { display: flex; flex-wrap: wrap; gap: 2px; }

.label-sm { font-size: 12px; color: #909399; margin-bottom: 4px; }
.sql-block { background: #1e1e1e; color: #d4d4d4; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 12px; margin-top: 4px; }
.result-info { margin-top: 8px; font-size: 12px; color: #909399; }
</style>
