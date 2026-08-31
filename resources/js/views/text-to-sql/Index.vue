<template>
    <div class="text-to-sql-page">
        <div class="page-header">
            <div>
                <h2>{{ t(`${P}.title`) }}</h2>
                <p class="text-muted">{{ t(`${P}.subtitle`) }}</p>
            </div>
            <div class="header-actions">
                <el-button @click="loadConfig" :loading="loading" :icon="Refresh">{{ t(`${P}.refresh_config`) }}</el-button>
            </div>
        </div>

        <el-row :gutter="16">
            <!-- 配置面板 -->
            <el-col :span="8">
                <el-card shadow="hover" class="mb-4">
                    <template #header><span><el-icon><Setting /></el-icon> {{ t(`${P}.security_config`) }}</span></template>
                    <div v-if="cfg">
                        <div class="cfg-item"><span class="cfg-label">{{ t(`${P}.readonly_mode`) }}</span><el-tag type="success" size="small">{{ t(`${P}.readonly_tag`) }}</el-tag></div>
                        <div class="cfg-item"><span class="cfg-label">{{ t(`${P}.max_rows`) }}</span><el-tag size="small">{{ cfg.max_rows }}</el-tag></div>
                        <div class="cfg-item"><span class="cfg-label">{{ t(`${P}.query_timeout`) }}</span><el-tag size="small">{{ t(`${P}.timeout_seconds`, { n: cfg.query_timeout }) }}</el-tag></div>
                        <div class="cfg-item"><span class="cfg-label">{{ t(`${P}.forbidden_keywords`) }}</span></div>
                        <div class="cfg-tags">
                            <el-tag v-for="kw in forbiddenKeywords" :key="kw" size="small" type="danger" style="margin:2px">{{ kw }}</el-tag>
                        </div>
                        <div class="cfg-item" style="margin-top:8px"><span class="cfg-label">{{ t(`${P}.sensitive_fields`) }}</span></div>
                        <div class="cfg-tags">
                            <el-tag v-for="col in sensitiveColumns" :key="col" size="small" type="warning" style="margin:2px">{{ col }}</el-tag>
                        </div>
                        <div class="cfg-item" style="margin-top:8px"><span class="cfg-label">{{ t(`${P}.allowed_tables`) }}</span></div>
                        <div class="cfg-tags">
                            <el-tag v-if="cfg.allowed_tables?.length" v-for="tbl in cfg.allowed_tables" :key="tbl" size="small" type="info" style="margin:2px">{{ tbl }}</el-tag>
                            <span v-else class="text-muted">{{ t(`${P}.all_tables_allowed`) }}</span>
                        </div>
                    </div>
                    <el-skeleton v-else :rows="6" animated />
                </el-card>

                <!-- 安全统计 -->
                <el-card shadow="hover">
                    <template #header><span><el-icon><DataBoard /></el-icon> {{ t(`${P}.security_layers`) }}</span></template>
                    <el-steps direction="vertical" :active="securityLayers.length" space="36px">
                        <el-step
                            v-for="(layer, idx) in securityLayers"
                            :key="idx"
                            :title="layer.title"
                            :description="layer.description"
                        />
                    </el-steps>
                </el-card>
            </el-col>

            <!-- SQL 查询区 -->
            <el-col :span="16">
                <el-card shadow="hover" class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><EditPen /></el-icon> {{ t(`${P}.sql_test`) }}</span>
                            <div>
                                <el-button @click="tab = 'sql'" :type="tab === 'sql' ? 'primary' : ''" size="small">{{ t(`${P}.tab_sql`) }}</el-button>
                                <el-button @click="tab = 'nl'" :type="tab === 'nl' ? 'primary' : ''" size="small">{{ t(`${P}.tab_nl`) }}</el-button>
                            </div>
                        </div>
                    </template>

                    <!-- SQL 模式 -->
                    <div v-if="tab === 'sql'">
                        <el-input v-model="sqlInput" type="textarea" :rows="5" :placeholder="t(`${P}.sql_placeholder`)" />
                        <div style="margin-top:8px;display:flex;gap:8px">
                            <el-button type="primary" @click="runValidate" :loading="valLoading">{{ t(`${P}.validate`) }}</el-button>
                            <el-button type="success" @click="runExecute" :loading="execLoading">{{ t(`${P}.validate_and_execute`) }}</el-button>
                            <el-button @click="sqlInput = exampleSql">{{ t(`${P}.load_example`) }}</el-button>
                        </div>
                    </div>

                    <!-- 自然语言模式 -->
                    <div v-if="tab === 'nl'">
                        <el-input v-model="nlInput" type="textarea" :rows="5" :placeholder="t(`${P}.nl_placeholder`)" />
                        <div style="margin-top:8px">
                            <el-button type="primary" @click="runQuery" :loading="qLoading">{{ t(`${P}.generate_and_execute`) }}</el-button>
                            <el-button @click="loadExample">{{ t(`${P}.load_example`) }}</el-button>
                        </div>
                    </div>
                </el-card>

                <!-- 验证结果 -->
                <el-card v-if="validateResult" shadow="hover" class="mb-4">
                    <template #header>
                        <span><el-icon><CircleCheck /></el-icon> {{ t(`${P}.validation_result`) }}</span>
                    </template>
                    <el-alert
                        :title="validateResult.valid ? t(`${P}.validation_pass`) : t(`${P}.validation_fail`)"
                        :type="validateResult.valid ? 'success' : 'error'"
                        show-icon
                        :closable="false"
                    />
                    <div v-if="validateResult.errors?.length" style="margin-top:8px">
                        <el-tag v-for="e in validateResult.errors" :key="e" type="danger" size="small" style="margin:2px">{{ e }}</el-tag>
                    </div>
                    <div v-if="validateResult.modified_sql" style="margin-top:8px">
                        <div class="label-sm">{{ t(`${P}.processed_sql`) }}</div>
                        <pre class="sql-block"><code>{{ validateResult.modified_sql }}</code></pre>
                    </div>
                </el-card>

                <!-- 查询结果 -->
                <el-card v-if="queryResult" shadow="hover">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><DataBoard /></el-icon> {{ t(`${P}.query_result`) }}</span>
                            <el-tag size="small">{{ queryTime }}</el-tag>
                        </div>
                    </template>
                    <el-alert v-if="queryResult.error" :title="queryResult.error" type="error" show-icon :closable="false" />
                    <el-table v-if="queryResult.columns?.length" :data="queryResult.rows" stripe size="small" max-height="400" border>
                        <el-table-column v-for="col in queryResult.columns" :key="col" :prop="col" :label="col" min-width="120" show-overflow-tooltip />
                    </el-table>
                    <div v-if="queryResult.rows?.length" class="result-info">
                        {{ t(`${P}.result_summary`, { rows: queryResult.rows.length, cols: queryResult.columns?.length }) }}
                    </div>
                    <el-empty v-if="queryResult.rows && !queryResult.rows.length && !queryResult.error" :description="t(`${P}.empty_result`)" />
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh, Setting, DataBoard, EditPen, CircleCheck } from '@element-plus/icons-vue';
import textToSqlApi from '@/api/textToSql';

const P = 'text_to_sql_page';
const { t } = useI18n();

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
const exampleNl = computed(() => t(`${P}.example_nl`));
function loadExample() {
    nlInput.value = exampleNl.value;
}

const forbiddenKeywords = ['DROP', 'TRUNCATE', 'ALTER', 'INSERT', 'UPDATE', 'DELETE', 'EXEC', 'GRANT', 'REVOKE', 'BENCHMARK', 'PG_SLEEP', 'INTO OUTFILE', 'INTO DUMPFILE', 'LOAD_FILE', 'CREATE', 'RENAME', 'REPLACE', 'KILL', 'SHUTDOWN', 'SET', 'CREATE USER', 'DROP USER', 'ALTER USER', 'FLUSH', 'LOCK', 'UNLOCK', 'INSTALL', 'UNINSTALL', 'DO', 'HANDLER'];
const sensitiveColumns = ['password', 'password_hash', 'api_key', 'api_secret', 'token', 'refresh_token', 'secret', 'credit_card', 'cvv', 'card_number', 'private_key', 'pem_private', 'seed_encrypted', 'totp_secret', 'recovery_codes', 'ssh_key'];

const securityLayers = computed(() => [
    { title: t(`${P}.layers.readonly.title`), description: t(`${P}.layers.readonly.desc`) },
    { title: t(`${P}.layers.keywords.title`), description: t(`${P}.layers.keywords.desc`) },
    { title: t(`${P}.layers.sensitive.title`), description: t(`${P}.layers.sensitive.desc`) },
    { title: t(`${P}.layers.whitelist.title`), description: t(`${P}.layers.whitelist.desc`) },
    { title: t(`${P}.layers.row_limit.title`), description: t(`${P}.layers.row_limit.desc`) },
    { title: t(`${P}.layers.tenant.title`), description: t(`${P}.layers.tenant.desc`) },
    { title: t(`${P}.layers.format.title`), description: t(`${P}.layers.format.desc`) },
    { title: t(`${P}.layers.result_mask.title`), description: t(`${P}.layers.result_mask.desc`) },
]);

onMounted(loadConfig);

async function loadConfig() {
    loading.value = true;
    try { const r = await textToSqlApi.config(); cfg.value = r.data?.data; } catch { ElMessage.error(t('messages.load_failed')); }
    finally { loading.value = false; }
}

async function runValidate() {
    if (!sqlInput.value.trim()) return;
    valLoading.value = true;
    try {
        const r = await textToSqlApi.validate({ sql: sqlInput.value });
        validateResult.value = r.data?.data;
    } catch (e) { validateResult.value = { valid: false, errors: [e.response?.data?.message || t(`${P}.messages.validate_request_failed`)] }; }
    finally { valLoading.value = false; }
}

async function runExecute() {
    if (!sqlInput.value.trim()) return;
    execLoading.value = true;
    try {
        const r = await textToSqlApi.execute({ sql: sqlInput.value });
        queryResult.value = r.data?.data;
        queryTime.value = r.data?.data?.duration_ms ? `${r.data.data.duration_ms}ms` : '';
    } catch (e) { queryResult.value = { error: e.response?.data?.message || t(`${P}.messages.execute_failed`) }; }
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
    } catch (e) { queryResult.value = { error: e.response?.data?.message || t(`${P}.messages.query_failed`) }; }
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
