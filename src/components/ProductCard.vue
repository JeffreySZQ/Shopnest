<<<<<<< HEAD
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
=======
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
>>>>>>> origin/main
</script>

<template>
    <article class="product-card">
<<<<<<< HEAD
        <RouterLink
            :to="'/products/' + product.id"
            class="product-art-link"
        >
=======
        <RouterLink :to="'/products/' + product.id" class="product-art-link">
>>>>>>> origin/main
            <ProductArt :product="product" />
        </RouterLink>

        <div class="product-card-body">
            <div class="product-meta">
                {{ product.type === 'digital' ? 'DIGITAL DOWNLOAD' : 'PHYSICAL PRODUCT' }}
                <span>· {{ product.category }}</span>
            </div>

<<<<<<< HEAD
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
=======
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
>>>>>>> origin/main
                @click="add(product)"
            >
                <Check v-if="added" :size="17" />
                <Plus v-else :size="17" />
<<<<<<< HEAD

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
=======
                {{ added ? 'Added to cart' : 'Add to cart' }}
            </v-btn>

            <v-alert v-if="error" type="error" density="compact" variant="tonal" class="mt-2">
                {{ error }}
            </v-alert>
        </div>
    </article>
</template>
>>>>>>> origin/main
