<template>
    <div class="bot-manager-page">
        <div class="page-header">
            <h2>{{ t('bot_manager_page.title') }}</h2>
            <el-button type="primary" @click="showRegisterDialog = true">
                <el-icon><Plus /></el-icon> {{ t('bot_manager_page.register') }}
            </el-button>
        </div>

        <el-tabs v-model="activeTab" type="border-card">
            <el-tab-pane :label="t('bot_manager_page.tabs.my')" name="my">
                <div class="tab-content">
                    <el-table :data="myBots" v-loading="loadingMy" stripe size="small" style="width:100%">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column prop="name" :label="t('bot_manager_page.cols.name')" min-width="120" />
                        <el-table-column prop="description" :label="t('bot_manager_page.cols.desc')" min-width="200" show-overflow-tooltip />
                        <el-table-column :label="t('bot_manager_page.cols.status')" width="90">
                            <template #default="{row}">
                                <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                                    {{ row.is_active ? t('bot_manager_page.active') : t('bot_manager_page.inactive') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('bot_manager_page.cols.public')" width="70">
                            <template #default="{row}">
                                <el-tag :type="row.is_public ? 'success' : 'info'" size="small">{{ row.is_public ? t('bot_manager_page.yes') : t('bot_manager_page.no') }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="Token" min-width="200">
                            <template #default="{row}">
                                <div class="token-display">
                                    <code>{{ maskToken(row.token) }}</code>
                                    <el-button text size="small" @click="copyToken(row.token)" :title="t('actions.copy')">
                                        <el-icon><CopyDocument /></el-icon>
                                    </el-button>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('bot_manager_page.cols.created')" width="150">
                            <template #default="{row}">{{ row.created_at }}</template>
                        </el-table-column>
                        <el-table-column :label="t('bot_manager_page.cols.actions')" width="180" fixed="right">
                            <template #default="{row}">
                                <el-button size="small" text @click="refreshBotToken(row)">{{ t('bot_manager_page.refresh_token') }}</el-button>
                                <el-button size="small" text @click="showCommands(row)">{{ t('bot_manager_page.commands') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div v-if="!myBots.length && !loadingMy" class="empty-state">
                        <el-empty :description="t('bot_manager_page.empty_my')" :image-size="60" />
                    </div>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="t('bot_manager_page.tabs.market')" name="market">
                <div class="tab-content">
                    <div class="toolbar">
                        <el-input v-model="marketQuery" :placeholder="t('bot_manager_page.search_ph')" size="small" clearable
                            style="width:300px" @keydown.enter="loadMarketplace" />
                        <el-button size="small" type="primary" @click="loadMarketplace">{{ t('actions.search') }}</el-button>
                    </div>
                    <el-row :gutter="16">
                        <el-col v-for="bot in marketBots" :key="bot.id" :span="8" style="margin-bottom:16px">
                            <el-card shadow="hover" class="market-card">
                                <div class="market-card-header">
                                    <div class="market-avatar">{{ bot.name.charAt(0) }}</div>
                                    <div class="market-info">
                                        <div class="market-name">{{ bot.name }}</div>
                                        <div class="market-author">{{ t('bot_manager_page.by_author', { name: bot.user?.name || t('bot_manager_page.anonymous') }) }}</div>
                                    </div>
                                </div>
                                <div class="market-desc">{{ bot.description || t('bot_manager_page.no_desc') }}</div>
                                <div v-if="bot.commands?.length" class="market-commands">
                                    <el-tag v-for="cmd in bot.commands.slice(0, 3)" :key="cmd.command" size="small" style="margin:2px">
                                        /{{ cmd.command }}
                                    </el-tag>
                                    <span v-if="bot.commands.length > 3" class="text-muted">+{{ bot.commands.length - 3 }}</span>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>
                    <div v-if="!marketBots.length && !loadingMarket" class="empty-state">
                        <el-empty :description="t('bot_manager_page.empty_market')" :image-size="60" />
                    </div>
                </div>
            </el-tab-pane>
        </el-tabs>

        <el-dialog v-model="showRegisterDialog" :title="t('bot_manager_page.register_title')" width="520px">
            <el-form :model="form" label-width="100px" :rules="rules" ref="formRef">
                <el-form-item :label="t('bot_manager_page.cols.name')" prop="name">
                    <el-input v-model="form.name" :placeholder="t('bot_manager_page.name_ph')" maxlength="100" />
                </el-form-item>
                <el-form-item :label="t('bot_manager_page.cols.desc')" prop="description">
                    <el-input v-model="form.description" type="textarea" :rows="2" :placeholder="t('bot_manager_page.desc_ph')" maxlength="500" />
                </el-form-item>
                <el-form-item :label="t('bot_manager_page.webhook')" prop="webhook_url">
                    <el-input v-model="form.webhook_url" placeholder="https://example.com/bot-webhook" />
                    <div class="form-hint">{{ t('bot_manager_page.webhook_hint') }}</div>
                </el-form-item>
                <el-form-item :label="t('bot_manager_page.commands')">
                    <div v-for="(cmd, i) in form.commands" :key="i" class="cmd-row">
                        <el-input v-model="cmd.command" :placeholder="t('bot_manager_page.cmd_name')" size="small" style="width:140px" />
                        <el-input v-model="cmd.description" :placeholder="t('bot_manager_page.cols.desc')" size="small" style="width:200px" />
                        <el-button text size="small" type="danger" @click="form.commands.splice(i, 1)">
                            <el-icon><Delete /></el-icon>
                        </el-button>
                    </div>
                    <el-button size="small" text @click="form.commands.push({ command: '', description: '' })">
                        <el-icon><Plus /></el-icon> {{ t('bot_manager_page.add_command') }}
                    </el-button>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showRegisterDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="registerBot">{{ t('bot_manager_page.register_btn') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="showCommandsDialog" :title="t('bot_manager_page.commands_title')" width="420px">
            <div v-if="selectedBot?.commands?.length">
                <div v-for="cmd in selectedBot.commands" :key="cmd.command" class="cmd-display-row">
                    <el-tag type="primary">/{{ cmd.command }}</el-tag>
                    <span>{{ cmd.description }}</span>
                </div>
            </div>
            <div v-else style="text-align:center;padding:20px;color:#909399">{{ t('bot_manager_page.no_commands') }}</div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, CopyDocument, Delete } from '@element-plus/icons-vue'
import apiClient from '@/utils/request'

const { t } = useI18n()

const activeTab = ref('my')
const loadingMy = ref(false)
const loadingMarket = ref(false)
const myBots = ref([])
const marketBots = ref([])
const marketQuery = ref('')
const showRegisterDialog = ref(false)
const saving = ref(false)
const formRef = ref(null)
const showCommandsDialog = ref(false)
const selectedBot = ref(null)

const form = reactive({
    name: '',
    description: '',
    webhook_url: '',
    commands: [],
})

const rules = computed(() => ({
    name: [{ required: true, message: t('bot_manager_page.validation.name'), trigger: 'blur' }],
}))

async function loadMyBots() {
    loadingMy.value = true
    try {
        const res = await apiClient.get('/bots/my')
        myBots.value = res.data?.data || []
    } catch { myBots.value = [] }
    finally { loadingMy.value = false }
}

async function loadMarketplace() {
    loadingMarket.value = true
    try {
        const params = {}
        if (marketQuery.value) params.q = marketQuery.value
        const res = await apiClient.get('/bots/marketplace', { params })
        marketBots.value = res.data?.data?.data || res.data?.data || []
    } catch { marketBots.value = [] }
    finally { loadingMarket.value = false }
}

async function registerBot() {
    if (!formRef.value) return
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return

    saving.value = true
    try {
        const payload = {
            name: form.name,
            description: form.description,
            webhook_url: form.webhook_url,
            commands: form.commands.filter(c => c.command.trim()),
        }
        const res = await apiClient.post('/bots/register', payload)
        const bot = res.data?.data
        if (bot) {
            ElMessage.success(t('bot_manager_page.messages.registered', { name: bot.name, token: bot.token }))
            myBots.value.unshift(bot)
            showRegisterDialog.value = false
            resetForm()
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('bot_manager_page.messages.register_failed'))
    } finally { saving.value = false }
}

async function refreshBotToken(bot) {
    try {
        await ElMessageBox.confirm(
            t('bot_manager_page.refresh_confirm', { name: bot.name }),
            t('bot_manager_page.refresh_title')
        )
        const res = await apiClient.post(`/bots/${bot.id}/refresh-token`)
        bot.token = res.data?.data?.token || bot.token
        ElMessage.success(t('bot_manager_page.messages.token_refreshed'))
    } catch { /* cancelled */ }
}

function copyToken(token) {
    navigator.clipboard.writeText(token)
        .then(() => ElMessage.success(t('bot_manager_page.messages.copied')))
        .catch(() => ElMessage.warning(t('bot_manager_page.messages.copy_failed')))
}

function maskToken(token) {
    if (!token || token.length < 12) return token || ''
    return token.substring(0, 8) + '••••' + token.substring(token.length - 4)
}

function showCommands(bot) {
    selectedBot.value = bot
    showCommandsDialog.value = true
}

function resetForm() {
    form.name = ''
    form.description = ''
    form.webhook_url = ''
    form.commands = []
}

onMounted(() => {
    loadMyBots()
    loadMarketplace()
})
</script>

<style scoped>
.bot-manager-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 22px; }
.tab-content { padding: 16px 0; min-height: 300px; }
.toolbar { display: flex; gap: 8px; margin-bottom: 16px; }
.empty-state { padding: 40px 0; }
.token-display { display: flex; align-items: center; gap: 4px; }
.token-display code { font-size: 12px; background: #f5f7fa; padding: 2px 6px; border-radius: 3px; }
.form-hint { font-size: 11px; color: #909399; margin-top: 4px; }
.cmd-row { display: flex; gap: 6px; margin-bottom: 6px; align-items: center; }
.cmd-display-row { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
.cmd-display-row:last-child { border-bottom: none; }
.market-card { cursor: default; }
.market-card-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.market-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #0f172a, #66b1ff); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700; flex-shrink: 0; }
.market-info { flex: 1; min-width: 0; }
.market-name { font-size: 15px; font-weight: 600; }
.market-author { font-size: 12px; color: #909399; }
.market-desc { font-size: 13px; color: #606266; margin-bottom: 8px; line-height: 1.5; }
.market-commands { display: flex; flex-wrap: wrap; gap: 2px; align-items: center; }
.text-muted { font-size: 12px; color: #909399; margin-left: 4px; }
</style>
