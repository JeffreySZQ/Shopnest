<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStore } from '@/stores/shop.js';
import { ArrowLeft, Save, History } from 'lucide-vue-next';
import { api, refreshAdminData } from '@/services/shopApi.js';

const s = useStore();
const route = useRoute();
const router = useRouter();
conszt error = ref('');
const saving = ref(false);

const existing = computed(() =>
    s.products.find(p => Number(p.id) === Number(route.params.id)) || null
);

const categories = computed(() =>
    Array.from(new Set(s.products.map(p => p.category).filter(Boolean))).sort()
);

const productForm = ref(null);
const error = ref('');

const model = reactive({
    name: '',
    sku: '',
    category: '',
    type: 'physical',
    price: 0,
    stock: 0,
    status: 'draft',
    short: '',
    description: '',
    imagePath: '',
    imageAlt: ''
});

async function loadEditor() {
    error.value = '';
    if (!route.params.id) {
        Object.assign(model, {
            name: '', sku: '', category: categories.value[0] || '',
            type: 'physical', price: 0, stock: 0, status: 'draft',
            short: '', description: '', imagePath: '', imageAlt: ''
        });
        return;
    }

    try {
        const result = await api.get('/products/' + route.params.id);
        let product = result.product;

        if (route.query.draftVersion) {
            const history = await api.get(`/admin/products/${route.params.id}/versions`);
            const draft = history.versions.find(v =>
                Number(v.version) === Number(route.query.draftVersion)
            );
            if (draft && draft.snapshot) {
                product = { ...product, ...draft.snapshot };
            }
        }

        Object.assign(model, {
            name: product.name || '',
            sku: product.sku || '',
            category: product.category || '',
            type: product.type || 'physical',
            price: Number(product.price || 0),
            stock: Number(product.stock || 0),
            status: product.status || 'draft',
            short: product.short || '',
            description: product.description || '',
            imagePath: product.imagePath || '',
            imageAlt: product.imageAlt || ''
        });
    } catch (e) {
        error.value = e.message;
    }
}

watch(
    () => [route.params.id, route.query.draftVersion],
    loadEditor,
    { immediate: true }
);

async function save() {
    error.value = '';
    if (!model.name.trim() || !model.sku.trim() || !model.category.trim()) {
        error.value = 'Please complete the product name, SKU and category.';
        return;
    }
    if (model.price < 0 || model.stock < 0) {
        error.value = 'Price and stock cannot be negative.';
        return;
    }

    const duplicate = s.products.some(p =>
        String(p.sku).toLowerCase() === model.sku.trim().toLowerCase() &&
        Number(p.id) !== Number(route.params.id || 0)
    );
    if (duplicate) {
        error.value = 'SKU must be unique.';
        return;
    }

    saving.value = true;
    try {
        const payload = {
            name: model.name.trim(),
            sku: model.sku.trim(),
            category: model.category.trim(),
            type: model.type,
            price: Number(model.price),
            stock: model.type === 'digital' ? 999 : Number(model.stock),
            status: model.status,
            short: model.short.trim(),
            description: model.description,
            imagePath: model.imagePath.trim(),
            imageAlt: model.imageAlt.trim() || model.name.trim()
        };

        if (route.params.id) {
            await api.put('/products/' + route.params.id, payload);
        } else {
            await api.post('/products', payload);
        }

        await refreshAdminData(s);
        router.push('/admin/products');

    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div>

        <!-- Back Button -->
        <RouterLink
            class="back-link"
            to="/admin/products"
        >
            <ArrowLeft :size="17" />
            Products
        </RouterLink>

        <!-- Page Heading -->
        <div class="admin-page-top">
            <div>
                <p class="eyebrow">PRODUCT EDITOR</p>

                <h1>
                    {{ existing ? 'Edit product' : 'Add product' }}
                </h1>

                <p class="muted">
                    Keep your catalog accurate, helpful and easy to explore.
                </p>
            </div>

            <v-btn
                v-if="existing"
                variant="outlined"
                color="primary"
                prepend-icon="mdi-history"
                :to="'/admin/products/' + existing.id + '/versions'"
            >
                Version history & compare
            </v-btn>
        </div>

        <!-- Restored Draft Notice -->
        <v-alert
            v-if="route.query.draftVersion"
            type="info"
            variant="tonal"
            class="mb-4"
        >
            You are editing restored draft v{{ route.query.draftVersion }}.
            Saving will create a new product version. Review before saving.
        </p>

        <!-- Vuetify Form -->
        <v-form
            ref="productForm"
            class="panel editor-form admin-vuetify-form"
            @submit.prevent="save"
        >

            <div class="row g-4">

                <!-- Product Details -->
                <div class="col-lg-8">

                    <h2>Product details</h2>

                    <v-text-field
                        v-model="model.name"
                        label="Product name"
                        variant="outlined"
                        density="comfortable"
                        :rules="[rules.required]"
                        required
                    />

                    <div class="row g-3">

                        <div class="col-md-6">
                            <v-text-field
                                v-model="model.sku"
                                label="SKU"
                                variant="outlined"
                                density="comfortable"
                                :rules="[
                                    rules.required,
                                    rules.uniqueSku
                                ]"
                                required
                            />
                        </div>

                        <div class="col-md-6">
                            <label
                                for="p-category"
                                class="form-label"
                            >
                                Category
                            </label>

                            <input
                                id="p-category"
                                v-model="model.category"
                                class="form-control"
                                list="shopnest-categories"
                                required
                            />
                            <datalist id="shopnest-categories">
                                <option v-for="category in categories" :key="category" :value="category" />
                            </datalist>
                        </div>

                    </div>

                    <v-text-field
                        v-model="model.short"
                        label="Short description"
                        variant="outlined"
                        density="comfortable"
                        counter="160"
                        :rules="[
                            rules.required,
                            rules.maxShort
                        ]"
                        required
                    />

                    <v-textarea
                        v-model="model.description"
                        label="Product description"
                        variant="outlined"
                        rows="5"
                        auto-grow
                        hint="Enter the full product description."
                        persistent-hint
                    />

                    <label for="p-image-path" class="form-label mt-3">
                        Product image path
                    </label>
                    <input
                        id="p-image-path"
                        v-model="model.imagePath"
                        class="form-control"
                        maxlength="255"
                        placeholder="images/products/product-name.jpg"
                    />

                    <label for="p-image-alt" class="form-label mt-3">
                        Product image description
                    </label>
                    <input
                        id="p-image-alt"
                        v-model="model.imageAlt"
                        class="form-control"
                        maxlength="255"
                    />
                </div>

                <!-- Store Settings -->
                <div class="col-lg-4">

                    <div class="editor-side">

                        <h2>Store settings</h2>

                        <v-select
                            v-model="model.status"
                            :items="[
                                {
                                    title: 'Draft',
                                    value: 'draft'
                                },
                                {
                                    title: 'Published',
                                    value: 'published'
                                }
                            ]"
                            item-title="title"
                            item-value="value"
                            label="Publication status"
                            variant="outlined"
                            density="comfortable"
                        />

                        <v-select
                            v-model="model.type"
                            :items="[
                                {
                                    title: 'Physical',
                                    value: 'physical'
                                },
                                {
                                    title: 'Digital',
                                    value: 'digital'
                                }
                            ]"
                            item-title="title"
                            item-value="value"
                            label="Product type"
                            variant="outlined"
                            density="comfortable"
                        />

                        <v-text-field
                            v-model.number="model.price"
                            label="Price (MYR)"
                            type="number"
                            min="0"
                            step="0.01"
                            variant="outlined"
                            density="comfortable"
                            :rules="[rules.nonNegative]"
                            required
                        />

                        <v-text-field
                            v-if="model.type === 'physical'"
                            v-model.number="model.stock"
                            label="Available stock"
                            type="number"
                            min="0"
                            step="1"
                            variant="outlined"
                            density="comfortable"
                            :rules="[rules.nonNegative]"
                            required
                        />

                            <input
                                id="p-stock"
                                v-model.number="model.stock"
                                type="number"
                                min="0"
                                step="1"
                                class="form-control"
                                required
                            />
                        </template>

                        <p class="small muted mt-3">
                            Image paths are saved in the existing image_path and image_alt database columns. Upload image files separately to the project images folder.
                        </p>

                    </div>

                </div>

            </div>

            <!-- Error Message -->
            <v-alert
                v-if="error"
                type="error"
                variant="tonal"
                class="mt-3"
            >
                {{ error }}
            </v-alert>

            <!-- Form Actions -->
            <div class="editor-actions">

                <v-btn
                    to="/admin/products"
                    variant="outlined"
                    color="primary"
                >
                    Cancel
                </v-btn>

                <v-btn
                    type="submit"
                    class="btn btn-primary"
                    :disabled="saving"
                >
                    <Save :size="17" />
                    {{ saving ? 'Saving...' : 'Save product' }}
                </button>
            </div>

        </v-form>

    </div>
</template>
