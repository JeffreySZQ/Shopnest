<script>
import { mapStores } from 'pinia';
import { useRoute } from 'vue-router';
import { useStore } from '@/stores/shop.js';
import { useCurrencyStore } from '@/stores/currency.js';
import { Heart, Plus, Check } from 'lucide-vue-next';
import ProductArt from './ProductArt.vue';
import { api, refreshCart, refreshLikes } from '@/services/shopApi.js';

export default {
    name: 'ProductCard',
    components: { Heart, Plus, Check, ProductArt },
    props: {
        product: { type: Object, required: true }
    },
    data() {
        return { added: false, error: '', busy: false };
    },
    computed: {
        ...mapStores(useStore, useCurrencyStore),
        s() { return this.shopStore; },
        currency() { return this.currencyStore; },
        liked() { return this.s.isLiked(this.product.id); }
    },
    methods: {
        async add(product) {
            this.error = '';
            this.busy = true;
            try {
                if (!this.s.user) {
                    this.s.addGuestCart(product.id, 1);
                } else {
                    await api.post('/cart/items', {
                        productId: product.id,
                        quantity: 1
                    });
                    await refreshCart(this.s);
                }

                this.added = true;
                window.setTimeout(() => { this.added = false; }, 1400);
            } catch (error) {
                this.error = error.message;
            } finally {
                this.busy = false;
            }
        },

        async like(product) {
            this.error = '';
            if (!this.s.user) {
                this.$router.push({
                    name: 'login',
                    query: { redirect: this.$route.fullPath }
                });
                return;
            }

            try {
                const result = await api.post(`/likes/${product.id}/toggle`, {});
                product.likes = Number(result.likes);
                await refreshLikes(this.s);
            } catch (error) {
                this.error = error.message;
            }
        }
    }
};
</script>

<template>
    <article class="product-card">
        <RouterLink :to="'/products/' + product.id" class="product-art-link">
            <ProductArt :product="product" />
        </RouterLink>

        <div class="product-card-body">
            <div class="product-meta">
                {{ product.type === 'digital' ? 'DIGITAL DOWNLOAD' : 'PHYSICAL PRODUCT' }}
                <span>· {{ product.category }}</span>
            </div>

            <RouterLink class="product-name" :to="'/products/' + product.id">
                {{ product.name }}
            </RouterLink>

            <p class="product-short">{{ product.short }}</p>

            <div class="product-card-bottom">
                <strong class="product-price">{{ currency.formatPrice(product.price) }}</strong>

                <v-btn
                    class="like-btn"
                    :class="{ liked }"
                    variant="text"
                    size="small"
                    :aria-label="(liked ? 'Unlike ' : 'Like ') + product.name"
                    @click="like(product)"
                >
                    <Heart :size="17" :fill="liked ? 'currentColor' : 'none'" />
                    <span>{{ product.likes }}</span>
                </v-btn>
            </div>

            <v-btn
                color="primary"
                block
                class="mt-3"
                :loading="busy"
                @click="add(product)"
            >
                <Check v-if="added" :size="17" />
                <Plus v-else :size="17" />
                {{ added ? 'Added to cart' : 'Add to cart' }}
            </v-btn>

            <v-alert v-if="error" type="error" density="compact" variant="tonal" class="mt-2">
                {{ error }}
            </v-alert>
        </div>
    </article>
</template>
