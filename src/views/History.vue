<script>
import { mapStores } from 'pinia';
import { useStore } from '@/stores/shop.js';
import { Search, Download } from 'lucide-vue-next';
import { downloadReceipt } from '@/utils/receipt.js';
import PageHeading from '@/components/PageHeading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { refreshOrders } from '@/services/shopApi.js';

<script>

// CREATE THE PURCHASE HISTORY COMPONENT
export default {
    name: 'HistoryView',
    components: { Search, Download, PageHeading, StatusBadge },


    // STORE SEARCH, FILTER AND PAGINATION VALUES
    data() {
        return {
            search: '',
            status: this.$route.query.type === 'digital' ? 'digital delivered' : '',
            page: 1,
            size: 5,
            error: ''
        };
    },


    // CONNECT THE PINIA STORE
    computed: {
        ...mapStores(useStore),

        s() {
            return this.shopStore;
        },


        // CHECK WHETHER DIGITAL PRODUCTS ARE SELECTED
        onlyDigital() {
            return this.$route.query.type === 'digital';
        },


        // SEARCH AND FILTER CUSTOMER ORDERS
        results() {
            return this.s.myOrders.filter(order =>

                // Only show orders containing digital products if the digital filter is active.
                (!this.onlyDigital || order.items.some(item => item.type === 'digital')) &&

                // Filter orders using the order ID search keyword.
                (!this.search || String(order.id).toLowerCase().includes(this.search.toLowerCase())) &&

                // Filter orders according to their fulfilment status.
                (!this.status || order.fulfillmentStatus === this.status)
            );
        },


        // CALCULATE THE TOTAL NUMBER OF PAGES
        pages() {
            return Math.max(1, Math.ceil(this.results.length / this.size));
        },


        // DISPLAY ORDERS FOR THE SELECTED PAGE
        shown() {
            return this.results.slice((this.page - 1) * this.size, this.page * this.size);
        }
    },


    // WATCH FOR SEARCH AND FILTER CHANGES
    watch: {

        onlyDigital(isDigital) {
            this.status = isDigital ? 'digital delivered' : '';
            this.page = 1;
        },

        // Reset pagination when the search keyword changes.
        search() {
            this.page = 1;
        },

        // Reset pagination when the order status changes.
        status() {
            this.page = 1;
        }
    },


    // LOAD CUSTOMER PURCHASE HISTORY
    async mounted() {
        try {
            await refreshOrders(this.s);
        }
        catch (error) {
            this.error = error.message;
        }
    },


    // DOWNLOAD PDF RECEIPT FROM PURCHASE HISTORY
    methods: {
        getReceipt(order) {
            try {
                this.error = '';
                downloadReceipt(order);
            }
            catch (error) {
                this.error = error.message;
            }
        }
    }
};

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

            <v-btn
              v-if="o.paymentStatus === 'paid'"
              variant="outlined"
              color="primary"
              size="small"
              @click="getReceipt(o)"
            >
              <Download :size="15" aria-hidden="true" /> Receipt
            </v-btn>

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
      <div v-if="pages > 1" class="d-flex justify-content-center mt-4">
        <v-pagination
          v-model="page"
          :length="pages"
          :total-visible="5"
          density="comfortable"
          rounded="circle"
        />
      </div>

    </div>
  </div>
</template>
