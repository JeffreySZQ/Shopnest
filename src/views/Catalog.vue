<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { SlidersHorizontal, Search } from 'lucide-vue-next'
import { useStore } from '@/stores/shop.js'
import ProductCard from '@/components/ProductCard.vue'
import PageHeading from '@/components/PageHeading.vue'

const s = useStore()
const route = useRoute()
const router = useRouter()

// Convert URL category values into an array
function parseCategories(value) {
  if (Array.isArray(value)) {
    return value
      .flatMap(v => String(v).split(','))
      .filter(Boolean)
  }

  return String(value || '')
    .split(',')
    .filter(Boolean)
}

// Product type allows only one selection
function parseType(value) {
  const selected = Array.isArray(value)
    ? value[0]
    : value

  const typeValue = String(selected || '')

  return ['physical', 'digital'].includes(typeValue)
    ? typeValue
    : ''
}

// Filter states
const query = ref(String(route.query.q || ''))

// Category: multiple selections
const category = ref(parseCategories(route.query.category))

// Product type: single selection
const type = ref(parseType(route.query.type))

const sort = ref(String(route.query.sort || 'featured'))

const page = ref(
  Math.max(1, Number(route.query.page || 1) || 1)
)

const filtersOpen = ref(false)

const perPage = 9

const categories = computed(() =>
  [...new Set(s.published.map(product => product.category).filter(Boolean))].sort()
)

// Detect liked products page
const onlyLiked = computed(
  () => route.query.liked === 'true'
)

// Synchronize filters with URL
function sync() {
  const nextQuery = {
    q: query.value || undefined,

    // Multiple selected categories
    category: category.value.length > 0
      ? category.value.join(',')
      : undefined,

    // Only one product type
    type: type.value || undefined,

    sort: sort.value !== 'featured'
      ? sort.value
      : undefined,

    page: page.value > 1
      ? String(page.value)
      : undefined,

    // Preserve liked products view
    liked: onlyLiked.value ? 'true' : undefined
  }

  const cleanedQuery = Object.fromEntries(
    Object.entries(nextQuery)
      .filter(([, value]) => value !== undefined)
  )

  // Avoid unnecessary router updates
  const currentQuery = route.query

  const isSame =
    Object.keys(cleanedQuery).length ===
      Object.keys(currentQuery).length &&
    Object.entries(cleanedQuery).every(
      ([key, value]) =>
        String(currentQuery[key] || '') === String(value)
    )

  if (!isSame) {
    router.replace({ query: cleanedQuery })
  }
}

// Reset pagination when search or filters change
watch(
  [query, category, type, sort],
  () => {
    page.value = 1
    sync()
  },
  { deep: true }
)

// Update URL when changing pages
watch(page, sync)

// Synchronize state when navigating using browser history
watch(
  () => route.query,
  q => {
    const nextQuery = String(q.q || '')

    const nextCategories = parseCategories(q.category)

    const nextType = parseType(q.type)

    const nextSort = String(q.sort || 'featured')

    const nextPage = Math.max(
      1,
      Number(q.page || 1) || 1
    )

    if (query.value !== nextQuery) {
      query.value = nextQuery
    }

    if (
      category.value.join(',') !==
      nextCategories.join(',')
    ) {
      category.value = nextCategories
    }

    if (type.value !== nextType) {
      type.value = nextType
    }

    if (sort.value !== nextSort) {
      sort.value = nextSort
    }

    if (page.value !== nextPage) {
      page.value = nextPage
    }
  }
)

// Reset pagination when liked view changes
watch(onlyLiked, () => {
  page.value = 1
})

// Filter products
const filtered = computed(() => {
  const likedIds = s.liked[s.user?.id] || []

  const searchText = query.value
    .trim()
    .toLowerCase()

  const arr = s.published.filter(p => {
    // Search
    const searchableText = [
      p.name,
      p.category,
      p.short || ''
    ].join(' ').toLowerCase()

    const matchesSearch =
      !searchText ||
      searchableText.includes(searchText)

    // Multiple categories can be selected
    const matchesCategory =
      category.value.length === 0 ||
      category.value.includes(p.category)

    // Only one product type can be selected
    const matchesType =
      !type.value ||
      p.type === type.value

    // Liked products filtering
    const matchesLiked =
      !onlyLiked.value ||
      likedIds.some(
        id => String(id) === String(p.id)
      )

    return (
      matchesSearch &&
      matchesCategory &&
      matchesType &&
      matchesLiked
    )
  })

  // Default 'Featured' order uses the database product_likes count.
  if (sort.value === 'featured') {
    arr.sort((a, b) => (b.likes || 0) - (a.likes || 0))
  }

  // Sort by price: low to high
  if (sort.value === 'price-low') {
    arr.sort((a, b) => a.price - b.price)
  }

  // Sort by price: high to low
  if (sort.value === 'price-high') {
    arr.sort((a, b) => b.price - a.price)
  }

  // Sort by popularity
  if (sort.value === 'popular') {
    arr.sort(
      (a, b) =>
        (b.likes || 0) - (a.likes || 0)
    )
  }

  // Sort alphabetically
  if (sort.value === 'name') {
    arr.sort(
      (a, b) => a.name.localeCompare(b.name)
    )
  }

  return arr
})

// Pagination
const pages = computed(() =>
  Math.max(
    1,
    Math.ceil(filtered.value.length / perPage)
  )
)

const shown = computed(() => {
  const safePage = Math.min(page.value, pages.value)

  return filtered.value.slice(
    (safePage - 1) * perPage,
    safePage * perPage
  )
})

// Clear all filters
// Preserve liked-products view when applicable
function clear() {
  query.value = ''
  category.value = []
  type.value = ''
  sort.value = 'featured'
  page.value = 1

  sync()
}

// Check whether filters are currently active
const hasActiveFilters = computed(() =>
  query.value.trim() !== '' ||
  category.value.length > 0 ||
  type.value !== '' ||
  sort.value !== 'featured'
)
</script>

<template>
  <div class="container section">

    <!-- Page heading -->
    <PageHeading
      :eyebrow="
        onlyLiked
          ? 'YOUR FAVORITES'
          : 'YOUR NEXT FAVORITE'
      "
      :title="
        onlyLiked
          ? 'Liked products'
          : 'The collection'
      "
      :description="
        onlyLiked
          ? 'Browse all the products you have liked.'
          : 'Practical tools for everyday growth, thoughtful work, and meaningful conversations.'
      "
    />

    <!-- Return to all products -->
    <div v-if="onlyLiked" class="mb-4">
      <RouterLink
        to="/catalog"
        class="btn btn-outline-primary"
      >
        ← View All Products
      </RouterLink>
    </div>

    <!-- Search and sorting -->
    <div class="catalog-toolbar">

      <!-- Search bar -->
      <label class="search-box">
        <Search
          :size="20"
          aria-hidden="true"
        />

        <span class="visually-hidden">
          Search products
        </span>

        <input
          v-model="query"
          placeholder="Search planners, workbooks, templates…"
          type="search"
        />
      </label>

      <!-- Mobile filters button -->
      <button
        type="button"
        class="btn btn-outline-primary mobile-filter-btn"
        :aria-expanded="filtersOpen"
        aria-controls="catalog-filter-panel"
        @click="filtersOpen = !filtersOpen"
      >
        <SlidersHorizontal
          :size="17"
          aria-hidden="true"
        />

        Filters
      </button>

      <!-- Sorting dropdown -->
      <label class="sort-label">
        Sort by

        <select
          v-model="sort"
          class="form-select"
        >
          <option value="featured">
            Featured
          </option>

          <option value="price-low">
            Price: low to high
          </option>

          <option value="price-high">
            Price: high to low
          </option>

          <option value="popular">
            Most liked
          </option>

          <option value="name">
            Name A–Z
          </option>
        </select>
      </label>

    </div>

    <!-- Catalog layout -->
    <div class="catalog-layout">

      <!-- Compact filter sidebar -->
      <aside
        id="catalog-filter-panel"
        class="catalog-filters compact-filters"
        :class="{ show: filtersOpen }"
      >

        <!-- Filter heading -->
        <div class="filter-header">
          <h2 class="filter-title">
            Filter products
          </h2>
        </div>

        <!-- CATEGORY: MULTIPLE SELECTION -->
        <fieldset class="filter-group">
          <legend>Category</legend>

          <label
            v-for="c in categories"
            :key="c"
            class="filter-option"
          >
            <input
              v-model="category"
              type="checkbox"
              :value="c"
            />

            <span>{{ c }}</span>
          </label>
        </fieldset>

        <!-- PRODUCT TYPE: SINGLE SELECTION -->
        <fieldset class="filter-group">
          <legend>Product type</legend>

          <!-- All products -->
          <label class="filter-option">
            <input
              v-model="type"
              type="radio"
              value=""
              name="product-type"
            />

            <span>All products</span>
          </label>

          <!-- Physical -->
          <label class="filter-option">
            <input
              v-model="type"
              type="radio"
              value="physical"
              name="product-type"
            />

            <span>Physical</span>
          </label>

          <!-- Digital -->
          <label class="filter-option">
            <input
              v-model="type"
              type="radio"
              value="digital"
              name="product-type"
            />

            <span>Digital</span>
          </label>
        </fieldset>

        <!-- Clear All Filters -->
        <button
          type="button"
          class="btn btn-outline-primary clear-filters-btn"
          @click="clear"
        >
          Clear All Filters
        </button>

        <!-- Mobile show results -->
        <button
          type="button"
          class="btn btn-primary w-100 d-lg-none mt-2"
          @click="filtersOpen = false"
        >
          Show Results
        </button>

      </aside>

      <!-- Product results -->
      <div class="catalog-results">

        <!-- Results count -->
        <div class="results-top">
          <span>
            {{ filtered.length }} products found
          </span>

          <span
            v-if="onlyLiked"
            class="muted"
          >
            Your liked collection
          </span>

          <span
            v-else-if="
              category.length > 0 || type !== ''
            "
            class="muted"
          >
            Filtered collection
          </span>
        </div>

        <!-- Products grid -->
        <div
          v-if="shown.length"
          class="row g-4"
        >
          <div
            v-for="p in shown"
            :key="p.id"
            class="col-12 col-sm-6 col-xl-4"
          >
            <ProductCard :product="p" />
          </div>
        </div>

        <!-- Empty state -->
        <div
          v-else
          class="empty-state"
        >
          <Search
            :size="36"
            aria-hidden="true"
          />

          <h2>
            {{
              onlyLiked
                ? 'No liked products found'
                : 'No products found'
            }}
          </h2>

          <p>
            {{
              onlyLiked
                ? 'No liked products match your current filters. Try changing your selections.'
                : 'Try a different search or remove some filters.'
            }}
          </p>

          <button
            v-if="hasActiveFilters"
            type="button"
            class="btn btn-primary"
            @click="clear"
          >
            Clear filters
          </button>

          <RouterLink
            v-else
            to="/catalog"
            class="btn btn-outline-primary"
          >
            View all products
          </RouterLink>
        </div>

        <!-- Pagination -->
        <nav
          v-if="pages > 1"
          class="page-controls"
          aria-label="Catalog pagination"
        >
          <button
            type="button"
            class="btn btn-outline-primary"
            :disabled="page <= 1"
            @click="page--"
          >
            Previous
          </button>

          <span>
            Page {{ Math.min(page, pages) }}
            of {{ pages }}
          </span>

          <button
            type="button"
            class="btn btn-outline-primary"
            :disabled="page >= pages"
            @click="page++"
          >
            Next
          </button>
        </nav>

      </div>
    </div>
  </div>
</template>

<style scoped>
/* Compact filter sidebar */

.compact-filters {
  padding: 18px !important;
  border-radius: 12px;
  align-self: start;
}

/* Filter heading */

.filter-header {
  margin-bottom: 14px;
}

.filter-title {
  font-size: 20px;
  font-weight: 700;
  line-height: 1.3;
  margin: 0;
}

/* Category and product type sections */

.filter-group {
  min-width: 0;
  margin: 0 0 18px;
  padding: 0;
  border: 0;
}

.filter-group legend {
  float: none;
  width: 100%;
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 10px;
  padding: 0 0 8px;
  border-bottom: 1px solid
    var(--color-border, #dce3eb);
}

/* Filter options */

.compact-filters .filter-option {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 32px;
  margin: 4px 0;
  font-size: 14px;
  line-height: 1.4;
  cursor: pointer;
}

/* Checkbox and radio styling */

.compact-filters .filter-option input {
  width: 18px;
  height: 18px;
  margin: 0;
  accent-color: #234f9c;
  flex-shrink: 0;
  cursor: pointer;
}

.compact-filters .filter-option span {
  flex: 1;
}

/* Clear All Filters button */

.clear-filters-btn {
  display: block;
  width: 100%;
  min-height: 44px;
  font-size: 14px;
  font-weight: 600;
  border-radius: 8px;
}

/* Narrower desktop sidebar */

@media (min-width: 992px) {
  .catalog-layout {
    grid-template-columns:
      220px minmax(0, 1fr) !important;
    gap: 24px;
  }
}

/* Mobile spacing */

@media (max-width: 991.98px) {
  .compact-filters {
    padding: 18px !important;
  }
}
</style>
