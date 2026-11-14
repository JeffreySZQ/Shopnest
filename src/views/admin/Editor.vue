<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStore } from '@/stores/shop.js';
import { ArrowLeft, Save, History } from 'lucide-vue-next';
import { api, refreshAdminData } from '@/services/shopApi.js';

const s = useStore();
const route = useRoute();
const router = useRouter();
const error = ref('');
const saving = ref(false);

const existing = computed(() =>
    s.products.find(p => Number(p.id) === Number(route.params.id)) || null
);

const categories = computed(() =>
    Array.from(new Set(s.products.map(p => p.category).filter(Boolean))).sort()
);

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
        <RouterLink
            class="back-link"
            to="/admin/products"
        >
            <ArrowLeft :size="17" />
            Products
        </RouterLink>

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

            <RouterLink
                v-if="existing"
                class="btn btn-outline-primary"
                :to="'/admin/products/' + existing.id + '/versions'"
            >
                <History :size="17" />
                Version history & compare
            </RouterLink>
        </div>

        <p
            v-if="route.query.draftVersion"
            class="alert alert-info"
        >
            You are editing restored draft v{{ route.query.draftVersion }}.
            Saving will create a new product version. Review before saving.
        </p>

        <form
            class="panel editor-form"
            @submit.prevent="save"
        >
            <div class="row g-4">
                <div class="col-lg-8">
                    <h2>Product details</h2>

                    <label
                        class="form-label"
                        for="p-name"
                    >
                        Product name *
                    </label>

                    <input
                        id="p-name"
                        v-model="model.name"
                        class="form-control"
                        required
                    />

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label
                                for="p-sku"
                                class="form-label"
                            >
                                SKU *
                            </label>

                            <input
                                id="p-sku"
                                v-model="model.sku"
                                class="form-control"
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

                    <label
                        for="p-short"
                        class="form-label mt-3"
                    >
                        Short description *
                    </label>

                    <input
                        id="p-short"
                        v-model="model.short"
                        class="form-control"
                        required
                        maxlength="160"
                    />

                    <label
                        for="p-description"
                        class="form-label mt-3"
                    >
                        Description
                    </label>

                    <textarea
                        id="p-description"
                        v-model="model.description"
                        class="form-control"
                        rows="5"
                    ></textarea>

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

                <div class="col-lg-4">
                    <div class="editor-side">
                        <h2>Store settings</h2>

                        <label
                            for="p-status"
                            class="form-label"
                        >
                            Publication status
                        </label>

                        <select
                            id="p-status"
                            v-model="model.status"
                            class="form-select"
                        >
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>

                        <label
                            for="p-type"
                            class="form-label mt-3"
                        >
                            Product type
                        </label>

                        <select
                            id="p-type"
                            v-model="model.type"
                            class="form-select"
                        >
                            <option value="physical">Physical</option>
                            <option value="digital">Digital</option>
                        </select>

                        <label
                            for="p-price"
                            class="form-label mt-3"
                        >
                            Price (MYR)
                        </label>

                        <input
                            id="p-price"
                            v-model.number="model.price"
                            type="number"
                            min="0"
                            step="0.01"
                            class="form-control"
                            required
                        />

                        <template v-if="model.type === 'physical'">
                            <label
                                for="p-stock"
                                class="form-label mt-3"
                            >
                                Available stock
                            </label>

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

            <p
                v-if="error"
                class="field-error mt-3"
                role="alert"
            >
                {{ error }}
            </p>

            <div class="editor-actions">
                <RouterLink
                    to="/admin/products"
                    class="btn btn-outline-primary"
                >
                    Cancel
                </RouterLink>

                <button
                    type="submit"
                    class="btn btn-primary"
                    :disabled="saving"
                >
                    <Save :size="17" />
                    {{ saving ? 'Saving...' : 'Save product' }}
                </button>
            </div>
        </form>
    </div>
</template>