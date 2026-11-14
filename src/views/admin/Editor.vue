<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStore } from '@/stores/shop.js';
import { ArrowLeft, Save, History } from 'lucide-vue-next';

const s = useStore(),
    route = useRoute(),
    router = useRouter();

const existing = computed(() =>
    s.products.find(p => p.id === Number(route.params.id))
);

const model = reactive({
    name: '',
    sku: '',
    category: 'Leadership',
    type: 'physical',
    price: 0,
    stock: 0,
    status: 'draft',
    short: '',
    description: '',
    featuresText: ''
});

const error = ref('');

watch(
    [existing, () => route.query.draftVersion],
    ([p, draftVersion]) => {
        const draft = draftVersion
            ? s.getProductVersions(p?.id).find(
                v =>
                    v.version === Number(draftVersion) &&
                    v.status === 'draft'
            )
            : null;

        if (draft) {
            p = { ...p, ...draft.snapshot };
        }

        if (p) {
            Object.assign(model, {
                name: p.name,
                sku: p.sku,
                category: p.category,
                type: p.type,
                price: p.price,
                stock: p.stock,
                status: p.status,
                short: p.short || '',
                description: p.description || '',
                featuresText: (p.features || []).join('\n')
            });
        }
    },
    { immediate: true }
);

function save() {
    error.value = '';

    if (
        !model.name.trim() ||
        !model.sku.trim() ||
        !model.short.trim()
    ) {
        error.value = 'Please complete the required fields.';
        return;
    }

    if (Number(model.price) < 0 || Number(model.stock) < 0) {
        error.value = 'Price and stock cannot be negative.';
        return;
    }

    if (
        s.products.some(
            p =>
                p.sku.toLowerCase() === model.sku.toLowerCase() &&
                p.id !== existing.value?.id
        )
    ) {
        error.value = 'SKU must be unique.';
        return;
    }

    try {
        s.upsertProduct({
            ...(existing.value ? { id: existing.value.id } : {}),
            name: model.name.trim(),
            sku: model.sku.trim(),
            category: model.category,
            type: model.type,
            price: Number(model.price),
            stock: model.type === 'digital'
                ? 999
                : Number(model.stock),
            status: model.status,
            short: model.short,
            description: model.description,
            features: model.featuresText
                .split('\n')
                .map(x => x.trim())
                .filter(Boolean)
        });

        router.push('/admin/products');
    } catch (e) {
        error.value = e.message;
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
            Saving will create a new live product version. Review before saving.
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

                            <select
                                id="p-category"
                                v-model="model.category"
                                class="form-select"
                            >
                                <option>Leadership</option>
                                <option>Personal Growth</option>
                                <option>Coaching</option>
                                <option>Digital Tools</option>
                            </select>
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

                    <label
                        for="p-features"
                        class="form-label mt-3"
                    >
                        Features (one per line)
                    </label>

                    <textarea
                        id="p-features"
                        v-model="model.featuresText"
                        class="form-control"
                        rows="4"
                    ></textarea>
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
                            Illustrations are generated using built-in visual
                            styles. File uploads require backend storage.
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
                >
                    <Save :size="17" />
                    Save product
                </button>
            </div>
        </form>
    </div>
</template>