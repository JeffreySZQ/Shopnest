<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Heart, Plus, Check } from 'lucide-vue-next';
import { useStore } from '@/stores/shop.js';
import ProductArt from './ProductArt.vue';
import { useCurrencyStore } from '@/stores/currency.js';
import { api, refreshCart, refreshLikes } from '@/services/shopApi.js';

defineProps({
    product: {
        type: Object,
        required: true
    }
});

const s = useStore();
const currency = useCurrencyStore();
const router = useRouter();
const route = useRoute();
const added = ref(false);
const error = ref('');
const busy = ref(false);

async function add(p) {
    error.value = '';
    if (!s.user) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }
    busy.value = true;
    try {
        await api.post('/cart/items', { productId: p.id, quantity: 1 });
        await refreshCart(s);
        added.value = true;
        setTimeout(() => { added.value = false; }, 1400);
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}

async function like(p) {
    error.value = '';
    if (!s.user) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }
    try {
        const result = await api.post(`/likes/${p.id}/toggle`, {});
        p.likes = Number(result.likes);
        await refreshLikes(s);
    } catch (e) {
        error.value = e.message;
    }
}
</script>

<template>
    <article class="product-card">
        <RouterLink
            :to="'/products/' + product.id"
            class="product-art-link"
        >
            <ProductArt :product="product" />
        </RouterLink>

        <div class="product-card-body">
            <div class="product-meta">
                {{ product.type === 'digital' ? 'DIGITAL DOWNLOAD' : 'PHYSICAL PRODUCT' }}
                <span>· {{ product.category }}</span>
            </div>

            <RouterLink
                class="product-name"
                :to="'/products/' + product.id"
            >
                {{ product.name }}
            </RouterLink>

            <p class="product-short">
                {{ product.short }}
            </p>

            <div class="product-card-bottom">
                <strong class="product-price">
                    {{ currency.formatPrice(product.price) }}
                </strong>

                <button
                    class="like-btn"
                    :class="{ liked: s.isLiked(product.id) }"
                    :aria-label="
                        (s.isLiked(product.id) ? 'Unlike ' : 'Like ') +
                        product.name
                    "
                    @click="like(product)"
                >
                    <Heart
                        :size="17"
                        :fill="s.isLiked(product.id) ? 'currentColor' : 'none'"
                    />

                    <span>{{ product.likes }}</span>
                </button>
            </div>

            <button
                class="btn btn-primary w-100 mt-3"
                :disabled="busy"
                @click="add(product)"
            >
                <Check v-if="added" :size="17" />
                <Plus v-else :size="17" />

                {{ added ? 'Added to cart' : 'Add to cart' }}
            </button>

            <p
                v-if="error"
                class="field-error"
                role="alert"
            >
                {{ error }}
            </p>
        </div>
    </article>
</template>