
<script setup>
import { ref, computed, watch } from 'vue';
import { useStore } from '@/stores/shop.js';

const s = useStore();

const search = ref('');
const type = ref('');
const status = ref('');
const page = ref(1);
const pageSize = ref(7);

const typeOptions = [
    { title: 'All types', value: '' },
    { title: 'Physical', value: 'physical' },
    { title: 'Digital', value: 'digital' }
];

const statusOptions = [
    { title: 'All statuses', value: '' },
    { title: 'Published', value: 'published' },
    { title: 'Draft', value: 'draft' }
];

const pageSizes = [5, 7, 10, 20];

const filtered = computed(() =>
    s.products.filter(p =>
        (
            !search.value ||
            (p.name + ' ' + p.sku)
                .toLowerCase()
                .includes(search.value.toLowerCase())
        ) &&
        (!type.value || p.type === type.value) &&
        (!status.value || p.status === status.value)
    )
);

const totalPages = computed(() =>
    Math.max(
        1,
        Math.ceil(filtered.value.length / pageSize.value)
    )
);

const shown = computed(() =>
    filtered.value.slice(
        (page.value - 1) * pageSize.value,
        page.value * pageSize.value
    )
);

watch(totalPages, total => {
    if (page.value > total) {
        page.value = total;
    }
});

function resetPage() {
    page.value = 1;
}

function remove(p) {
    if (
        confirm(
            `Delete ${p.name}? This will remove the store listing, but historical orders remain.`
        )
    ) {
        s.deleteProduct(p.id);
    }
}
</script>

<template>
    <div>
        <!-- Page Heading -->
        <div class="admin-page-top">
            <div>
                <p class="eyebrow">CATALOG MANAGEMENT</p>

                <h1>Products</h1>

                <p class="muted">
                    Manage listings, prices, visibility and stock.
                </p>
            </div>

            <v-btn
                to="/admin/products/new"
                color="primary"
                prepend-icon="mdi-plus"
            >
                Add product
            </v-btn>
        </div>

        <div class="panel">

            <!-- Search and Filters -->
            <div class="row g-3 align-items-center mb-4">

                <div class="col-12 col-lg-5">
                    <v-text-field
                        v-model="search"
                        label="Search product name or SKU"
                        prepend-inner-icon="mdi-magnify"
                        variant="outlined"
                        density="comfortable"
                        clearable
                        hide-details
                        @update:model-value="resetPage"
                    />
                </div>

                <div class="col-6 col-lg-3">
                    <v-select
                        v-model="type"
                        :items="typeOptions"
                        item-title="title"
                        item-value="value"
                        label="Product type"
                        variant="outlined"
                        density="comfortable"
                        hide-details
                        @update:model-value="resetPage"
                    />
                </div>

                <div class="col-6 col-lg-2">
                    <v-select
                        v-model="status"
                        :items="statusOptions"
                        item-title="title"
                        item-value="value"
                        label="Status"
                        variant="outlined"
                        density="comfortable"
                        hide-details
                        @update:model-value="resetPage"
                    />
                </div>

                <div class="col-6 col-lg-2">
                    <v-select
                        v-model="pageSize"
                        :items="pageSizes"
                        label="Rows per page"
                        variant="outlined"
                        density="comfortable"
                        hide-details
                        @update:model-value="resetPage"
                    />
                </div>

            </div>

            <!-- Product Table -->
            <div class="table-responsive">
                <v-table
                    class="admin-table admin-vuetify-table"
                    density="comfortable"
                >
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="p in shown"
                            :key="p.id"
                        >
                            <td>
                                <strong>{{ p.name }}</strong>

                                <div class="small text-medium-emphasis">
                                    {{ p.sku }}
                                </div>
                            </td>

                            <td>
                                {{ p.type }}
                            </td>

                            <td>
                                RM {{ p.price.toFixed(2) }}
                            </td>

                            <td>
                                {{
                                    p.type === 'digital'
                                        ? 'Digital'
                                        : p.stock
                                }}
                            </td>

                            <td>
                                <v-chip
                                    size="small"
                                    variant="tonal"
                                    :color="
                                        p.status === 'published'
                                            ? 'success'
                                            : 'warning'
                                    "
                                >
                                    {{ p.status }}
                                </v-chip>
                            </td>

                            <td>
                                <div class="table-actions flex-wrap">

                                    <v-btn
                                        size="small"
                                        variant="outlined"
                                        color="primary"
                                        prepend-icon="mdi-pencil"
                                        :to="'/admin/products/' + p.id + '/edit'"
                                    >
                                        Edit
                                    </v-btn>

                                    <v-btn
                                        size="small"
                                        variant="outlined"
                                        color="primary"
                                        prepend-icon="mdi-history"
                                        :to="'/admin/products/' + p.id + '/versions'"
                                    >
                                        Versions
                                    </v-btn>

                                    <v-btn
                                        size="small"
                                        variant="outlined"
                                        color="error"
                                        prepend-icon="mdi-delete"
                                        @click="remove(p)"
                                    >
                                        Delete
                                    </v-btn>

                                </div>
                            </td>
                        </tr>
                    </tbody>
                </v-table>
            </div>

            <!-- Empty State -->
            <v-alert
                v-if="!shown.length"
                type="info"
                variant="tonal"
                class="mt-4"
            >
                No products match your filters.
            </v-alert>

            <!-- Pagination -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-4">

                <span class="small text-medium-emphasis">
                    {{ filtered.length }} products found
                </span>

                <v-pagination
                    v-if="totalPages > 1"
                    v-model="page"
                    :length="totalPages"
                    :total-visible="5"
                    rounded="circle"
                    density="comfortable"
                />

                <span class="small text-medium-emphasis">
                    Page {{ page }} of {{ totalPages }}
                </span>

            </div>

        </div>
    </div>
</template>
