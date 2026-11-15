
<<<<<<< HEAD
<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
=======
<script>
import { mapStores } from 'pinia';
>>>>>>> origin/main
import { useStore } from '@/stores/shop.js';
import { Search, Download } from 'lucide-vue-next';
import { downloadReceipt } from '@/utils/receipt.js';
import PageHeading from '@/components/PageHeading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { refreshOrders } from '@/services/shopApi.js';

<<<<<<< HEAD
const route = useRoute();
const s = useStore();
const search = ref('');
const status = ref(route.query.type === 'digital' ? 'digital delivered' : '');
const page = ref(1);
const size = 5;
const error = ref('');
const onlyDigital = computed(() => route.query.type === 'digital');

onMounted(async () => {
    try {
        await refreshOrders(s);
    } catch (e) {
        error.value = e.message;
    }
});

watch(onlyDigital, isDigital => {
    status.value = isDigital ? 'digital delivered' : '';
    page.value = 1;
});

const results = computed(() =>
    s.myOrders.filter(o =>
        (!onlyDigital.value || o.items.some(i => i.type === 'digital')) &&
        (!search.value || o.id.toLowerCase().includes(search.value.toLowerCase())) &&
        (!status.value || o.fulfillmentStatus === status.value)
    )
);

const pages = computed(() => Math.max(1, Math.ceil(results.value.length / size)));
const shown = computed(() => results.value.slice((page.value - 1) * size, page.value * size));

function getReceipt(order) {
    try {
        error.value = '';
        downloadReceipt(order);
    } catch (e) {
        error.value = e.message;
    }
}
=======
export default {
    name: 'HistoryView',
    components: { Search, Download, PageHeading, StatusBadge },
    data() {
        return {
            search: '',
            status: this.$route.query.type === 'digital' ? 'digital delivered' : '',
            page: 1,
            size: 5,
            error: ''
        };
    },
    computed: {
        ...mapStores(useStore),
        s() { return this.shopStore; },
        onlyDigital() { return this.$route.query.type === 'digital'; },
        results() {
            return this.s.myOrders.filter(order =>
                (!this.onlyDigital || order.items.some(item => item.type === 'digital')) &&
                (!this.search || String(order.id).toLowerCase().includes(this.search.toLowerCase())) &&
                (!this.status || order.fulfillmentStatus === this.status)
            );
        },
        pages() { return Math.max(1, Math.ceil(this.results.length / this.size)); },
        shown() { return this.results.slice((this.page - 1) * this.size, this.page * this.size); }
    },
    watch: {
        onlyDigital(isDigital) {
            this.status = isDigital ? 'digital delivered' : '';
            this.page = 1;
        },
        search() { this.page = 1; },
        status() { this.page = 1; }
    },
    async mounted() {
        try { await refreshOrders(this.s); }
        catch (error) { this.error = error.message; }
    },
    methods: {
        getReceipt(order) {
            try { this.error = ''; downloadReceipt(order); }
            catch (error) { this.error = error.message; }
        }
    }
};
>>>>>>> origin/main
</script>

<template>
  <div class="container section">

    <PageHeading
      eyebrow="YOUR PURCHASES"
      title="Purchase history"
      :description="
        onlyDigital
          ? 'Your previous orders containing digital products. Open the purchased product or view the order details.'
          : 'Every ShopNest order, in one convenient place.'
      "
    />

    <div class="panel">

      <!-- Digital purchases heading -->
      <div
        v-if="onlyDigital"
        class="mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2"
      >
        <strong>Digital purchases</strong>

        <RouterLink
          class="btn btn-outline-primary btn-sm"
          to="/orders"
        >
          Show all orders
        </RouterLink>
      </div>

      <!-- Search and status filter -->
<<<<<<< HEAD
      <div class="toolbar">

        <label class="search-box">
          <Search :size="18" aria-hidden="true" />

          <span class="visually-hidden">
            Search order number
          </span>

          <input
            v-model="search"
            @input="page = 1"
            placeholder="Search order number"
          />
        </label>

        <label>
          <span class="visually-hidden">
            Filter order status
          </span>

          <select
            v-model="status"
            class="form-select"
            @change="page = 1"
          >
            <option value="">All statuses</option>
            <option value="processing">Processing</option>
            <option value="digital delivered">Digital delivered</option>
            <option value="shipped">Shipped</option>
            <option value="delivered">Delivered</option>
          </select>
        </label>

=======
      <div class="row g-3 align-items-center mb-4">
        <div class="col-12 col-md-6">
          <v-text-field
            v-model="search"
            label="Search order number"
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            density="comfortable"
            clearable
            hide-details
          />
        </div>
        <div class="col-12 col-md-6">
          <v-select
            v-model="status"
            :items="[
              { title: 'All statuses', value: '' },
              { title: 'Processing', value: 'processing' },
              { title: 'Digital delivered', value: 'digital delivered' },
              { title: 'Shipped', value: 'shipped' },
              { title: 'Delivered', value: 'delivered' }
            ]"
            item-title="title"
            item-value="value"
            label="Order status"
            variant="outlined"
            density="comfortable"
            hide-details
          />
        </div>
>>>>>>> origin/main
      </div>

      <!-- Error message -->
      <p v-if="error" role="alert" class="text-danger">
        {{ error }}
      </p>

      <!-- Order list -->
      <div v-if="shown.length">

        <div
          v-for="o in shown"
          :key="o.id"
          class="order-row"
        >

          <div>
            <RouterLink
              class="fw-bold"
              :to="'/orders/' + o.id"
            >
              {{ o.id }}
            </RouterLink>

            <div class="small muted">
              {{ new Date(o.createdAt).toLocaleString('en-MY') }}
              · {{ o.items.length }} product(s)
            </div>

            <!-- Purchased digital products -->
            <div
              v-if="onlyDigital"
              class="d-flex flex-wrap gap-2 mt-2"
            >
              <template
                v-for="(item, index) in o.items.filter(i => i.type === 'digital')"
                :key="index"
              >
                <RouterLink
                  v-if="s.products.some(p => p.id === item.productId)"
                  class="btn btn-outline-primary btn-sm"
                  :to="'/products/' + item.productId"
                >
                  View {{ item.name }} →
                </RouterLink>

                <span v-else class="small muted">
                  {{ item.name }} (listing unavailable)
                </span>
              </template>
            </div>
          </div>

          <!-- Order total -->
          <strong>
            RM {{ o.total.toFixed(2) }}
          </strong>

          <!-- Order status -->
          <StatusBadge :value="o.fulfillmentStatus" />

          <!-- Order actions -->
          <div class="d-flex flex-wrap gap-2">

<<<<<<< HEAD
            <button
              v-if="o.paymentStatus === 'paid'"
              class="btn btn-outline-primary btn-sm"
              @click="getReceipt(o)"
            >
              <Download :size="15" aria-hidden="true" />
              Receipt
            </button>
=======
            <v-btn
              v-if="o.paymentStatus === 'paid'"
              variant="outlined"
              color="primary"
              size="small"
              @click="getReceipt(o)"
            >
              <Download :size="15" aria-hidden="true" /> Receipt
            </v-btn>
>>>>>>> origin/main

            <RouterLink
              class="btn btn-outline-primary btn-sm"
              :to="'/orders/' + o.id"
            >
              Details
            </RouterLink>

          </div>

        </div>
      </div>

      <!-- No matching orders -->
      <div v-else class="empty-inline">
        No matching orders.
        <RouterLink to="/catalog">
          Explore products →
        </RouterLink>
      </div>

      <!-- Pagination -->
<<<<<<< HEAD
      <div v-if="pages > 1" class="page-controls">

        <button
          class="btn btn-outline-primary"
          :disabled="page === 1"
          @click="page--"
        >
          Previous
        </button>

        <span>
          Page {{ page }} of {{ pages }}
        </span>

        <button
          class="btn btn-outline-primary"
          :disabled="page === pages"
          @click="page++"
        >
          Next
        </button>

=======
      <div v-if="pages > 1" class="d-flex justify-content-center mt-4">
        <v-pagination
          v-model="page"
          :length="pages"
          :total-visible="5"
          density="comfortable"
          rounded="circle"
        />
>>>>>>> origin/main
      </div>

    </div>
  </div>
</template>
