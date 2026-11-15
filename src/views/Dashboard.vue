<script>
import { mapStores } from 'pinia';
import { useStore } from '@/stores/shop.js';
import { ShoppingBag, Package, Heart, ArrowRight, UserRound } from 'lucide-vue-next';
import PageHeading from '@/components/PageHeading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { refreshCustomerData } from '@/services/shopApi.js';

export default {
    name: 'DashboardView',
    components: { ShoppingBag, Package, Heart, ArrowRight, UserRound, PageHeading, StatusBadge },
    computed: {
        ...mapStores(useStore),
        s() { return this.shopStore; },
        latest() { return this.s.myOrders.slice(0, 3); },
        downloads() {
            return this.s.myOrders
                .filter(order => order.paymentStatus === 'paid')
                .reduce((count, order) => count + order.items
                    .filter(item => item.type === 'digital')
                    .reduce((total, item) => total + item.quantity, 0), 0);
        },
        likes() {
            return this.s.liked[this.s.user ? this.s.user.id : '']?.length || 0;
        }
    },
    async mounted() {
        try { await refreshCustomerData(this.s); }
        catch (error) { this.loadError = error.message; }
    },
    data() { return { loadError: '' }; }
};
</script>

<template>
  <div class="container section">

    <!-- Page heading -->
    <PageHeading
      eyebrow="YOUR SPACE"
      :title="`Hello, ${s.user?.name?.split(' ')[0]}!`"
      description="Good to have you back. Here's what's happening with your ShopNest account."
    />

    <!-- Dashboard statistics -->
    <div class="row g-3 mb-5">

      <!-- Total Orders -->
      <div class="col-md-4">
        <div class="metric-card">
          <ShoppingBag aria-hidden="true" />
          <span>Total orders</span>
          <strong>{{ s.myOrders.length }}</strong>
        </div>
      </div>

      <!-- Digital Items Purchased - Clickable -->
      <div class="col-md-4">
        <RouterLink
          to="/orders?type=digital"
          class="metric-card-link"
          aria-label="View purchased digital products in purchase history"
        >
          <div class="metric-card clickable-metric">
            <Package aria-hidden="true" />

            <span>Digital items purchased</span>

            <strong>{{ downloads }}</strong>
          </div>
        </RouterLink>
      </div>

      <!-- Liked Products - Clickable -->
      <div class="col-md-4">
        <RouterLink
          to="/catalog?liked=true"
          class="metric-card-link"
          aria-label="View your liked products"
        >
          <div class="metric-card clickable-metric">
            <Heart aria-hidden="true" />

            <span>Liked products</span>

            <strong>{{ likes }}</strong>
          </div>
        </RouterLink>
      </div>

    </div>

    <!-- Recent Orders and Account -->
    <div class="row g-4">

      <!-- Recent Orders -->
      <div class="col-lg-8">
        <div class="panel">

          <div class="panel-header">
            <h2>Recent orders</h2>

            <RouterLink to="/orders" class="text-cta">
              See all
              <ArrowRight :size="17" aria-hidden="true" />
            </RouterLink>
          </div>

          <div v-if="latest.length">

            <div
              v-for="o in latest"
              :key="o.id"
              class="order-row"
            >
              <div>
                <RouterLink
                  :to="'/orders/' + o.id"
                  class="fw-bold"
                >
                  {{ o.id }}
                </RouterLink>

                <div class="muted small">
                  {{
                    new Date(o.createdAt)
                      .toLocaleDateString('en-MY')
                  }}
                  · {{ o.items.length }} product(s)
                </div>
              </div>

              <strong>
                RM {{ o.total.toFixed(2) }}
              </strong>

              <StatusBadge :value="o.fulfillmentStatus" />
            </div>

          </div>

          <div v-else class="empty-inline">
            No orders yet.

            <RouterLink to="/catalog">
              Find something you love →
            </RouterLink>
          </div>

        </div>
      </div>

      <!-- My Account -->
      <div class="col-lg-4">
        <div class="panel">

          <div class="panel-header">
            <h2>My account</h2>
            <UserRound :size="20" aria-hidden="true" />
          </div>

          <p>
            <strong>{{ s.user?.name }}</strong>
            <br />
            <span class="muted">
              {{ s.user?.email }}
            </span>
          </p>

          <hr />

          <RouterLink
            to="/orders"
            class="d-flex justify-content-between"
          >
            Purchase history
            <ArrowRight :size="17" aria-hidden="true" />
          </RouterLink>

          <hr />

          <RouterLink
            to="/catalog"
            class="d-flex justify-content-between"
          >
            Browse collection
            <ArrowRight :size="17" aria-hidden="true" />
          </RouterLink>

        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
/* Entire digital and liked products cards are clickable */
.metric-card-link {
  display: block;
  height: 100%;
  color: inherit;
  text-decoration: none;
  border-radius: 16px;
}

/* Keep original card appearance */
.clickable-metric {
  height: 100%;
  cursor: pointer;

  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease;
}

/* Hover effect */
.metric-card-link:hover .clickable-metric {
  border-color: #234f9c;
  box-shadow: 0 5px 18px rgba(35, 79, 156, 0.12);
  transform: translateY(-2px);
}

/* Keyboard accessibility */
.metric-card-link:focus-visible {
  outline: 3px solid #234f9c;
  outline-offset: 4px;
}

/* Keep text consistent */
.metric-card-link:hover {
  color: inherit;
  text-decoration: none;
}

/* Reduced motion accessibility */
@media (prefers-reduced-motion: reduce) {
  .clickable-metric {
    transition: none;
  }

  .metric-card-link:hover .clickable-metric {
    transform: none;
  }
}
</style>
