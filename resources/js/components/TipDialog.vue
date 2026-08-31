<template>
  <el-dialog v-model="visible" :title="t('tip.title')" width="420px" :close-on-click-modal="false" top="20vh">
    <div v-if="loading" style="text-align:center;padding:20px">
      <el-icon class="is-loading" :size="24"><Loading /></el-icon>
      <p style="color:#999;font-size:13px;margin-top:8px">{{ t('tip.loading') }}</p>
    </div>
    <div v-else>
      <div class="tip-balance-bar">
        <span>{{ t('tip.my_points') }}<strong>{{ balance }}</strong></span>
        <el-button text size="small" type="primary" @click="$emit('viewTransactions')" style="font-size:12px">{{ t('tip.transactions') }}</el-button>
      </div>

      <div class="tip-amount-grid">
        <div v-for="amt in presetAmounts" :key="amt" class="tip-amount-btn"
          :class="{ selected: selectedAmount === amt }"
          @click="selectedAmount = amt">
          <PointsIcon :size="16" /> {{ amt }}
        </div>
        <div class="tip-amount-btn tip-custom-btn"
          :class="{ selected: !presetAmounts.includes(selectedAmount) && selectedAmount > 0 }"
          @click="isCustom = true">
          {{ t('tip.custom') }}
        </div>
      </div>

      <div v-if="isCustom" style="margin-top:10px">
        <el-input v-model.number="customAmount" type="number" :min="1" :max="Math.min(99999, balance)" :placeholder="t('tip.amount_ph')" size="small" @input="selectedAmount = customAmount" />
      </div>

      <div style="margin-top:12px">
        <el-input v-model="tipMessage" type="textarea" :rows="2" :placeholder="t('tip.message_ph')" maxlength="200" size="small" />
      </div>

      <div v-if="balance < 1" style="margin-top:10px;text-align:center;color:#e6a23c;font-size:13px">
        {{ t('tip.insufficient') }}
      </div>

      <div class="tip-footer">
        <el-button @click="visible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="warning" :loading="submitting" :disabled="!selectedAmount || selectedAmount < 1 || balance < 1" @click="submitTip">
          <PointsIcon :size="16" /> {{ t('tip.tip_btn', { n: selectedAmount }) }}
        </el-button>
      </div>
    </div>
  </el-dialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Loading } from '@element-plus/icons-vue'
import apiClient from '@/api/client'
import PointsIcon from '@/components/PointsIcon.vue'

const { t } = useI18n()

const props = defineProps({
  modelValue: Boolean,
  contentId: { type: Number, required: true },
  contentType: { type: String, required: true }, // oa_article | forum_post | blog_post
  receiverId: { type: Number, default: 0 },
})

const emit = defineEmits(['update:modelValue', 'tipped', 'viewTransactions'])

const visible = ref(props.modelValue)
const loading = ref(false)
const balance = ref(0)
const selectedAmount = ref(0)
const customAmount = ref(0)
const isCustom = ref(false)
const tipMessage = ref('')
const submitting = ref(false)
const presetAmounts = [1, 5, 10, 20, 50, 100]

watch(() => props.modelValue, (v) => {
  visible.value = v
  if (v) loadBalance()
})

watch(visible, (v) => emit('update:modelValue', v))

async function loadBalance() {
  loading.value = true
  try {
    const r = await apiClient.get('/points/balance')
    const data = r.data?.data || {}
    balance.value = data.balance || 0
  } catch { balance.value = 0 }
  finally { loading.value = false }
}

async function submitTip() {
  const amount = selectedAmount.value
  if (!amount || amount < 1) { ElMessage.warning(t('tip.select_amount')); return }
  if (balance.value < amount) { ElMessage.warning(t('tip.not_enough')); return }

  submitting.value = true
  try {
    const r = await apiClient.post('/points/tip', {
      content_type: props.contentType,
      content_id: props.contentId,
      points: amount,
      message: tipMessage.value.trim() || null,
    })
    const data = r.data?.data || {}
    balance.value = data.balance || 0
    ElMessage.success(t('tip.success', { n: amount }))
    selectedAmount.value = 0
    customAmount.value = 0
    tipMessage.value = ''
    isCustom.value = false
    emit('tipped', { points: amount, balance: data.balance })
    visible.value = false
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('tip.fail'))
  } finally { submitting.value = false }
}
</script>

<style scoped>
.tip-balance-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff9e6;
  border-radius: 8px;
  padding: 10px 14px;
  margin-bottom: 14px;
  font-size: 14px;
}
.tip-balance-bar strong { color: #e6a23c; font-size: 18px; margin-left: 4px; }
.tip-amount-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}
.tip-amount-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 10px 0;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  transition: all 0.15s;
  user-select: none;
}
.tip-amount-btn:hover { border-color: #e6a23c; color: #e6a23c; }
.tip-amount-btn.selected {
  border-color: #e6a23c;
  background: #fff9e6;
  color: #e6a23c;
}
.tip-custom-btn { font-size: 12px; font-weight: 500; color: #9ca3af; }
.tip-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 18px;
}
</style>
