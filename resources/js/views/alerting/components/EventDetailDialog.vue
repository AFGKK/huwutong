<template>
  <el-dialog v-model="visible" title="告警详情" width="700px" :close-on-click-modal="false">
    <div v-loading="loading" v-if="event">
      <el-descriptions :column="2" border size="small" class="mb-4">
        <el-descriptions-item label="标题" :span="2">{{ event.title }}</el-descriptions-item>
        <el-descriptions-item label="规则">{{ event.rule?.name }}</el-descriptions-item>
        <el-descriptions-item label="类型">{{ event.event_type }}</el-descriptions-item>
        <el-descriptions-item label="严重程度">
          <el-tag :type="severityTag(event.severity)" size="small">{{ event.severity }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="statusTag(event.status)" size="small">{{ event.status }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="触发时间">{{ event.fired_at }}</el-descriptions-item>
        <el-descriptions-item label="确认时间">{{ event.acknowledged_at || '-' }}</el-descriptions-item>
        <el-descriptions-item label="解决时间">{{ event.resolved_at || '-' }}</el-descriptions-item>
      </el-descriptions>

      <el-card shadow="never" class="mb-4">
        <template #header>消息内容</template>
        <p class="text-sm">{{ event.message }}</p>
      </el-card>

      <el-card shadow="never" class="mb-4" v-if="event.context">
        <template #header>上下文数据</template>
        <pre class="text-xs bg-gray-50 p-3 rounded overflow-auto max-h-48">{{ JSON.stringify(event.context, null, 2) }}</pre>
      </el-card>

      <el-card shadow="never" v-if="event.notification_logs?.length">
        <template #header>通知记录</template>
        <el-table :data="event.notification_logs" size="small">
          <el-table-column label="渠道类型" prop="channel_type" width="100" />
          <el-table-column label="状态" prop="status" width="80">
            <template #default="{ row }"><el-tag :type="row.status === 'sent' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag></template>
          </el-table-column>
          <el-table-column label="发送时间" prop="sent_at" width="160" />
          <el-table-column label="响应" prop="response" min-width="200" show-overflow-tooltip />
        </el-table>
      </el-card>
    </div>
    <el-empty v-else-if="!loading" description="未找到事件" />
    <template #footer>
      <el-button @click="visible = false">关闭</el-button>
      <el-button v-if="event?.status === 'firing'" type="warning" @click="$emit('acknowledge', event.id)">确认</el-button>
      <el-button v-if="['firing','acknowledged'].includes(event?.status)" type="success" @click="$emit('resolve', event.id)">解决</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { getEvent } from '../../../api/alerting'

const props = defineProps({
  visible: { type: Boolean, default: false },
  eventId: { type: Number, default: null },
})
const emit = defineEmits(['update:visible'])

const visible = computed({ get: () => props.visible, set: v => emit('update:visible', v) })

const event = ref(null)
const loading = ref(false)

function severityTag(s) { return { info: 'info', warning: 'warning', critical: 'danger' }[s] || 'info' }
function statusTag(s) { return { firing: 'danger', acknowledged: 'warning', resolved: 'success' }[s] || 'info' }

async function load() {
  if (!props.eventId) return
  loading.value = true
  try {
    const { data } = await getEvent(props.eventId)
    event.value = data
  } catch { event.value = null } finally { loading.value = false }
}

watch(() => props.eventId, (v) => { if (v) load() })
watch(() => props.visible, (v) => { if (v && props.eventId) load() })
</script>
