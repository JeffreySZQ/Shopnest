<script>
import { mapStores } from 'pinia';
import { useStore } from '@/stores/shop.js';
import { SlidersHorizontal, Search } from 'lucide-vue-next';
import ProductCard from '@/components/ProductCard.vue';
import PageHeading from '@/components/PageHeading.vue';

function parseCategories(value) {
    if (Array.isArray(value)) {
        return value.flatMap(item => String(item).split(',')).filter(Boolean);
    }
    return String(value || '').split(',').filter(Boolean);
}

function parseType(value) {
    const selected = Array.isArray(value) ? value[0] : value;
    const type = String(selected || '');
    return ['physical', 'digital'].includes(type) ? type : '';
}

export default {
    name: 'CatalogView',
    components: { SlidersHorizontal, Search, ProductCard, PageHeading },
    data() {
        return {
            query: String(this.$route.query.q || ''),
            category: parseCategories(this.$route.query.category),
            type: parseType(this.$route.query.type),
            sort: String(this.$route.query.sort || 'featured'),
            page: Math.max(1, Number(this.$route.query.page || 1) || 1),
            filtersOpen: false,
            perPage: 9
        };
    },
    computed: {
        ...mapStores(useStore),
        s() { return this.shopStore; },
        categories() {
            return [...new Set(this.s.published.map(product => product.category).filter(Boolean))].sort();
        },
        onlyLiked() {
            return this.$route.query.liked === 'true';
        },
        filtered() {
            const likedIds = this.s.liked[this.s.user ? this.s.user.id : ''] || [];
            const searchText = this.query.trim().toLowerCase();

            const items = this.s.published.filter(product => {
                const searchable = [product.name, product.category, product.short || ''].join(' ').toLowerCase();
                const matchesSearch = !searchText || searchable.includes(searchText);
                const matchesCategory = !this.category.length || this.category.includes(product.category);
                const matchesType = !this.type || product.type === this.type;
                const matchesLiked = !this.onlyLiked || likedIds.some(id => String(id) === String(product.id));
                return matchesSearch && matchesCategory && matchesType && matchesLiked;
            });

            if (this.sort === 'featured' || this.sort === 'popular') {
                items.sort((a, b) => Number(b.likes || 0) - Number(a.likes || 0));
            } else if (this.sort === 'price-low') {
                items.sort((a, b) => a.price - b.price);
            } else if (this.sort === 'price-high') {
                items.sort((a, b) => b.price - a.price);
            } else if (this.sort === 'name') {
                items.sort((a, b) => a.name.localeCompare(b.name));
            }

            return items;
        },
        pages() {
            return Math.max(1, Math.ceil(this.filtered.length / this.perPage));
        },
        shown() {
            const safePage = Math.min(this.page, this.pages);
            return this.filtered.slice((safePage - 1) * this.perPage, safePage * this.perPage);
        },
        hasActiveFilters() {
            return this.query.trim() !== '' || this.category.length > 0 || this.type !== '' || this.sort !== 'featured';
        },
        sortOptions() {
            return [
                { title: 'Featured', value: 'featured' },
                { title: 'Price: low to high', value: 'price-low' },
                { title: 'Price: high to low', value: 'price-high' },
                { title: 'Most liked', value: 'popular' },
                { title: 'Name A–Z', value: 'name' }
            ];
        }
    },
    watch: {
        query() { this.page = 1; this.sync(); },
        category: { deep: true, handler() { this.page = 1; this.sync(); } },
        type() { this.page = 1; this.sync(); },
        sort() { this.page = 1; this.sync(); },
        page() { this.sync(); },
        onlyLiked() { this.page = 1; },
        '$route.query'(query) {
            const nextQuery = String(query.q || '');
            const nextCategories = parseCategories(query.category);
            const nextType = parseType(query.type);
            const nextSort = String(query.sort || 'featured');
            const nextPage = Math.max(1, Number(query.page || 1) || 1);

            if (this.query !== nextQuery) this.query = nextQuery;
            if (this.category.join(',') !== nextCategories.join(',')) this.category = nextCategories;
            if (this.type !== nextType) this.type = nextType;
            if (this.sort !== nextSort) this.sort = nextSort;
            if (this.page !== nextPage) this.page = nextPage;
        }
    },
    methods: {
        sync() {
            const nextQuery = {
                q: this.query || undefined,
                category: this.category.length ? this.category.join(',') : undefined,
                type: this.type || undefined,
                sort: this.sort !== 'featured' ? this.sort : undefined,
                page: this.page > 1 ? String(this.page) : undefined,
                liked: this.onlyLiked ? 'true' : undefined
            };

            const cleaned = Object.fromEntries(
                Object.entries(nextQuery).filter(([, value]) => value !== undefined)
            );
            const current = this.$route.query;
            const same = Object.keys(cleaned).length === Object.keys(current).length &&
                Object.entries(cleaned).every(([key, value]) => String(current[key] || '') === String(value));

            if (!same) this.$router.replace({ query: cleaned });
        },
        clear() {
            this.query = '';
            this.category = [];
            this.type = '';
            this.sort = 'featured';
            this.page = 1;
            this.sync();
        }
    }
};
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
      <div class="sn-field-group catalog-search-group">
        <label for="catalog-search" class="sn-field-label">
          Search products
        </label>
        <v-text-field
          id="catalog-search"
          v-model="query"
          placeholder="Search planners, workbooks, templates..."
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          density="comfortable"
          clearable
          hide-details
          class="catalog-search"
        />
      </div>

      <!-- Mobile filters button -->
      <v-btn
        variant="outlined"
        color="primary"
        class="mobile-filter-btn"
        :aria-expanded="filtersOpen"
        aria-controls="catalog-filter-panel"
        @click="filtersOpen = !filtersOpen"
      >
        <SlidersHorizontal :size="17" /> Filters
      </v-btn>

      <!-- Sorting dropdown -->
      <div class="sn-field-group catalog-sort-group">
        <label for="catalog-sort" class="sn-field-label">
          Sort by
        </label>
        <v-select
          id="catalog-sort"
          v-model="sort"
          :items="sortOptions"
          item-title="title"
          item-value="value"
          variant="outlined"
          density="comfortable"
          hide-details
          class="catalog-sort"
        />
      </div>

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
          <v-checkbox
            v-for="c in categories"
            :key="c"
            v-model="category"
            :label="c"
            :value="c"
            density="compact"
            hide-details
            class="catalog-filter-check"
          />
        </fieldset>

        <!-- PRODUCT TYPE: SINGLE SELECTION -->
        <fieldset class="filter-group">
          <legend>Product type</legend>
          <v-radio-group v-model="type" hide-details density="compact">
            <v-radio label="All products" value="" />
            <v-radio label="Physical" value="physical" />
            <v-radio label="Digital" value="digital" />
          </v-radio-group>
        </fieldset>

        <!-- Clear All Filters -->
        <v-btn block variant="outlined" color="primary" class="clear-filters-btn" @click="clear">
          Clear All Filters
        </v-btn>

        <!-- Mobile show results -->
        <v-btn color="primary" block class="d-lg-none mt-2" @click="filtersOpen = false">
          Show Results
        </v-btn>

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

          <v-btn v-if="hasActiveFilters" color="primary" @click="clear">
            Clear filters
          </v-btn>

          <RouterLink
            v-else
            to="/catalog"
            class="btn btn-outline-primary"
          >
            View all products
          </RouterLink>
        </div>

        <!-- Pagination -->
        <div v-if="pages > 1" class="d-flex justify-content-center mt-4" aria-label="Catalog pagination">
          <v-pagination
            v-model="page"
            :length="pages"
            :total-visible="5"
            rounded="circle"
            density="comfortable"
          />
        </div>

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
