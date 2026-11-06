
<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useStore } from '@/stores/shop.js'
import { Search, Download } from 'lucide-vue-next'
import { downloadReceipt } from '@/utils/receipt.js'
import PageHeading from '@/components/PageHeading.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const route = useRoute()
const s = useStore()

const search = ref('')

// Automatically select Digital delivered
// when arriving from Digital items purchased
const status = ref(
  route.query.type === 'digital' ? 'digital delivered' : ''
)

const page = ref(1)
const size = 5

// Detect digital purchases URL
const onlyDigital = computed(() => route.query.type === 'digital')

// Update selected status when switching between
// digital purchases and normal purchase history
watch(onlyDigital, (isDigital) => {
  status.value = isDigital ? 'digital delivered' : ''
  page.value = 1
})

// Filter orders
const results = computed(() =>
  s.myOrders.filter(o =>
    (!onlyDigital.value ||
      o.items.some(i => i.type === 'digital')) &&

    (!search.value ||
      o.id.toLowerCase().includes(search.value.toLowerCase())) &&

    (!status.value ||
      o.fulfillmentStatus === status.value)
  )
)

// Pagination
const pages = computed(() =>
  Math.max(1, Math.ceil(results.value.length / size))
)

const shown = computed(() =>
  results.value.slice(
    (page.value - 1) * size,
    page.value * size
  )
)

// PDF receipt generation
const error = ref('')

function getReceipt(order) {
  try {
    error.value = ''
    downloadReceipt(order)
  } catch (e) {
    error.value = e.message
  }
}
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

            <button
              v-if="o.paymentStatus === 'paid'"
              class="btn btn-outline-primary btn-sm"
              @click="getReceipt(o)"
            >
              <Download :size="15" aria-hidden="true" />
              Receipt
            </button>

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

      </div>

    </div>
  </div>
</template>
