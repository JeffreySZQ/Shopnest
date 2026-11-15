<<<<<<< HEAD
<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    product: {
        type: Object,
        required: true
    },
    large: Boolean
});

const imageFailed = ref(false);

const imageSrc = computed(() => {
    if (!props.product.imagePath) return '';
    const path = String(props.product.imagePath).replace(/^\/+/, '');
    return `${import.meta.env.BASE_URL}${path}`;
});

watch(() => props.product.imagePath, () => {
    imageFailed.value = false;
});
=======
<script>
export default {
    name: 'ProductArt',
    props: {
        product: { type: Object, required: true },
        large: { type: Boolean, default: false }
    },
    data() {
        return { imageFailed: false };
    },
    computed: {
        imageSrc() {
            if (!this.product || !this.product.imagePath) return '';
            const path = String(this.product.imagePath).replace(/^\/+/, '');
            return `${import.meta.env.BASE_URL}${path}`;
        }
    },
    watch: {
        'product.imagePath'() {
            this.imageFailed = false;
        }
    }
};
>>>>>>> origin/main
</script>

<template>
    <div
        class="product-art"
        :class="['tone-' + (product.tone || 'blue'), { large }]"
        role="img"
        :aria-label="product.imageAlt || product.name"
    >
        <img
            v-if="imageSrc && !imageFailed"
            :src="imageSrc"
            :alt="product.imageAlt || product.name"
            class="product-database-image"
            @error="imageFailed = true"
        />

        <template v-else>
            <div class="art-glow"></div>
            <div class="art-object">
                <span>{{ product.emoji || '📦' }}</span>
                <div class="art-mini-label">SHOPNEST COLLECTION</div>
            </div>
            <span class="art-corner">SN / {{ String(product.id).padStart(2, '0') }}</span>
        </template>
    </div>
</template>

<style scoped>
.product-database-image {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>
