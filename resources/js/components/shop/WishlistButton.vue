<template>
  <el-tooltip :content="wishlisted ? t('wishlist.remove') : t('wishlist.add')" placement="top">
    <button
      class="wishlist-btn"
      :class="{ active: wishlisted }"
      @click.stop="handleToggle"
      :disabled="toggling"
    >
      <el-icon :size="18">
        <StarFilled v-if="wishlisted" />
        <Star v-else />
      </el-icon>
    </button>
  </el-tooltip>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Star, StarFilled } from '@element-plus/icons-vue';
import shopApi from '@/api/shop';

const { t } = useI18n();

const props = defineProps({
  productId: { type: [Number, String], required: true },
  modelValue: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);

const wishlisted = ref(props.modelValue);
const toggling = ref(false);

onMounted(async () => {
  if (!props.modelValue) {
    try {
      const res = await shopApi.isWishlisted(props.productId);
      wishlisted.value = res.data?.data?.wishlisted || false;
    } catch { /* ignore */ }
  }
});

async function handleToggle() {
  toggling.value = true;
  try {
    await shopApi.toggleWishlist(props.productId);
    wishlisted.value = !wishlisted.value;
    emit('update:modelValue', wishlisted.value);
    emit('change', wishlisted.value);
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || t('wishlist.fail'));
  } finally {
    toggling.value = false;
  }
}
</script>

<style scoped>
.wishlist-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 50%;
  background: rgba(255,255,255,0.9);
  cursor: pointer;
  transition: all 0.2s;
  color: #c0c4cc;
  backdrop-filter: blur(4px);
}
.wishlist-btn:hover {
  transform: scale(1.15);
  color: #f56c6c;
  background: rgba(255,255,255,1);
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.wishlist-btn.active {
  color: #f56c6c;
}
.wishlist-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
