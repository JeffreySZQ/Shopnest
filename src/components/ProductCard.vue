<script setup>
import { ref } from 'vue';
import { Heart, Plus, Check } from 'lucide-vue-next';
import { useStore } from '@/stores/shop.js';
import ProductArt from './ProductArt.vue';

defineProps({
    product: {
        type: Object,
        required: true
    }
});

const s = useStore(),
    added = ref(false),
    error = ref('');

function add(p) {
    try {
        s.addCart(p.id);
        added.value = true;
        error.value = '';

        setTimeout(() => added.value = false, 1400);
    } catch (e) {
        error.value = e.message;
    }
}

function like(p) {
    try {
        s.toggleLike(p.id);
        error.value = '';
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
                    RM {{ product.price.toFixed(2) }}
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