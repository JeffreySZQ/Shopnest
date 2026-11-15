<<<<<<< HEAD
<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCurrencyStore } from '@/stores/currency.js';
import {
    Heart,
    ShoppingCart,
    ArrowLeft,
    Truck,
    Download,
    CheckCircle2,
    Minus,
    Plus
} from 'lucide-vue-next';
import { useStore } from '@/stores/shop.js';
=======
<script>
import { mapStores } from 'pinia';
import { useStore } from '@/stores/shop.js';
import { useCurrencyStore } from '@/stores/currency.js';
import { Heart, ShoppingCart, ArrowLeft, Truck, Download, CheckCircle2, Minus, Plus } from 'lucide-vue-next';
>>>>>>> origin/main
import ProductArt from '@/components/ProductArt.vue';
import ProductCard from '@/components/ProductCard.vue';
import { api, refreshCart, refreshLikes } from '@/services/shopApi.js';

<<<<<<< HEAD
const s = useStore();
const currency = useCurrencyStore();
const route = useRoute();
const router = useRouter();
const qty = ref(1);
const notice = ref('');
const busy = ref(false);

const p = computed(() =>
    s.products.find(product => Number(product.id) === Number(route.params.id) && product.status === 'published')
);

const related = computed(() =>
    s.published.filter(product => product.category === p.value?.category && product.id !== p.value?.id).slice(0, 3)
);

watch(() => route.params.id, () => {
    qty.value = 1;
    notice.value = '';
});

async function add() {
    if (!p.value) return;
    if (!s.user) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }
    busy.value = true;
    notice.value = '';
    try {
        await api.post('/cart/items', { productId: p.value.id, quantity: qty.value });
        await refreshCart(s);
        notice.value = 'Added to cart!';
    } catch (e) {
        notice.value = e.message;
    } finally {
        busy.value = false;
    }
}

async function like() {
    if (!p.value) return;
    if (!s.user) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }
    try {
        const result = await api.post(`/likes/${p.value.id}/toggle`, {});
        p.value.likes = Number(result.likes);
        await refreshLikes(s);
    } catch (e) {
        notice.value = e.message;
    }
}
=======
export default {
    name: 'ProductView',
    components: { Heart, ShoppingCart, ArrowLeft, Truck, Download, CheckCircle2, Minus, Plus, ProductArt, ProductCard },
    data() {
        return { qty: 1, notice: '', busy: false };
    },
    computed: {
        ...mapStores(useStore, useCurrencyStore),
        s() { return this.shopStore; },
        currency() { return this.currencyStore; },
        p() {
            return this.s.products.find(product =>
                Number(product.id) === Number(this.$route.params.id) &&
                product.status === 'published'
            ) || null;
        },
        related() {
            if (!this.p) return [];
            return this.s.published
                .filter(product => product.category === this.p.category && Number(product.id) !== Number(this.p.id))
                .slice(0, 3);
        }
    },
    watch: {
        '$route.params.id'() {
            this.qty = 1;
            this.notice = '';
        }
    },
    methods: {
        async add() {
            if (!this.p) return;
            this.busy = true;
            this.notice = '';
            try {
                if (!this.s.user) {
                    this.s.addGuestCart(this.p.id, this.qty);
                } else {
                    await api.post('/cart/items', {
                        productId: this.p.id,
                        quantity: this.qty
                    });
                    await refreshCart(this.s);
                }
                this.notice = 'Added to cart!';
            } catch (error) {
                this.notice = error.message;
            } finally {
                this.busy = false;
            }
        },
        async like() {
            if (!this.p) return;
            if (!this.s.user) {
                this.$router.push({ name: 'login', query: { redirect: this.$route.fullPath } });
                return;
            }
            try {
                const result = await api.post(`/likes/${this.p.id}/toggle`, {});
                this.p.likes = Number(result.likes);
                await refreshLikes(this.s);
            } catch (error) {
                this.notice = error.message;
            }
        }
    }
};
>>>>>>> origin/main
</script>

<template>
  <div v-if="p" class="container section">
    <RouterLink to="/catalog" class="back-link">
      <ArrowLeft :size="17" />
      Back to all products
    </RouterLink>

    <div class="product-detail-grid">
      <ProductArt :product="p" large />

      <div class="product-detail-copy">
        <span class="eyebrow">
          {{ p.category.toUpperCase() }} / {{ p.type.toUpperCase() }}
        </span>

        <h1>{{ p.name }}</h1>
        <p class="muted product-detail-short">{{ p.short }}</p>

        <div class="d-flex align-items-center gap-3 mb-4">
          <strong class="detail-price">{{ currency.formatPrice(p.price) }}</strong>
          <span class="status-badge status-good">
            {{ p.type === 'digital' ? 'Digital delivery' : p.stock > 0 ? 'In stock' : 'Out of stock' }}
          </span>
        </div>

        <p>{{ p.description }}</p>

        <div class="detail-benefits">
          <p>
            <component :is="p.type === 'digital' ? Download : Truck" :size="19" />
            {{ p.type === 'digital'
              ? 'Digital item — simulated access after purchase'
              : 'Physical item — demo shipping RM 8 per order' }}
          </p>
          <p>
            <CheckCircle2 :size="19" />
            Created for practical everyday use
          </p>
        </div>

        <div class="detail-actions">
          <div class="qty-picker">
<<<<<<< HEAD
            <button
              :disabled="qty <= 1"
              aria-label="Decrease quantity"
              @click="qty--"
            >
              <Minus :size="17" />
            </button>
            <span aria-live="polite">{{ qty }}</span>
            <button
              :disabled="p.type === 'physical' && qty >= p.stock"
              aria-label="Increase quantity"
              @click="qty++"
            >
              <Plus :size="17" />
            </button>
          </div>

          <button
            class="btn btn-primary btn-lg flex-grow-1"
            :disabled="busy || (p.type === 'physical' && p.stock === 0)"
            @click="add"
          >
            <ShoppingCart :size="18" />
            Add to cart
          </button>

          <button
            class="btn btn-outline-primary heart-action"
            :aria-label="'Like ' + p.name"
            @click="like"
          >
            <Heart :size="20" :fill="s.isLiked(p.id) ? 'currentColor' : 'none'" />
          </button>
=======
            <v-btn icon variant="text" :disabled="qty <= 1" aria-label="Decrease quantity" @click="qty--">
              <Minus :size="17" />
            </v-btn>
            <span aria-live="polite">{{ qty }}</span>
            <v-btn icon variant="text" :disabled="p.type === 'physical' && qty >= p.stock" aria-label="Increase quantity" @click="qty++">
              <Plus :size="17" />
            </v-btn>
          </div>

          <v-btn color="primary" size="large" class="flex-grow-1" :loading="busy" :disabled="p.type === 'physical' && p.stock === 0" @click="add">
            <ShoppingCart :size="18" /> Add to cart
          </v-btn>

          <v-btn icon variant="outlined" color="primary" class="heart-action" :aria-label="'Like ' + p.name" @click="like">
            <Heart :size="20" :fill="s.isLiked(p.id) ? 'currentColor' : 'none'" />
          </v-btn>
>>>>>>> origin/main
        </div>

        <p v-if="notice" role="status" class="form-notice">
          {{ notice }}
          <RouterLink v-if="notice === 'Added to cart!'" to="/cart">
            View cart
          </RouterLink>
        </p>

      </div>
    </div>

    <section v-if="related.length" class="mt-5">
      <div class="section-head">
        <h2>You might also like</h2>
      </div>
      <div class="row g-4">
        <div
          v-for="item in related"
          :key="item.id"
          class="col-12 col-sm-6 col-lg-4"
        >
          <ProductCard :product="item" />
        </div>
      </div>
    </section>
  </div>

  <div v-else class="container section empty-state">
    <h1>Product not available</h1>
    <RouterLink to="/catalog" class="btn btn-primary">
      Browse catalog
    </RouterLink>
  </div>
</template>
