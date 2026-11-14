
<script setup>
import {
    computed,
    reactive,
    ref,
    watch
} from 'vue';

import {
    useRoute,
    useRouter
} from 'vue-router';

import { useStore } from '@/stores/shop.js';

import {
    ArrowLeft,
    Save,
    History
} from 'lucide-vue-next';

const s = useStore();
const route = useRoute();
const router = useRouter();

const existing = computed(() =>
    s.products.find(
        p => p.id === Number(route.params.id)
    )
);

const productForm = ref(null);
const error = ref('');

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

// Vuetify validation rules
const rules = {
    required: value =>
        !!String(value ?? '').trim() ||
        'This field is required.',

    nonNegative: value =>
        (
            value !== '' &&
            value !== null &&
            value !== undefined &&
            Number(value) >= 0
        ) || 'Enter a zero or positive number.',

    maxShort: value =>
        String(value ?? '').length <= 160 ||
        'Maximum 160 characters allowed.',

    uniqueSku: value => {
        const sku = String(value ?? '')
            .trim()
            .toLowerCase();

        if (!sku) return true;

        const duplicate = s.products.some(
            p =>
                p.sku.toLowerCase() === sku &&
                p.id !== existing.value?.id
        );

        return !duplicate || 'SKU must be unique.';
    }
};

// Load existing product or restored draft
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
            p = {
                ...p,
                ...draft.snapshot
            };
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

// Save product
async function save() {
    error.value = '';

    const result = await productForm.value.validate();

    if (!result.valid) {
        error.value = 'Please correct the highlighted fields.';
        return;
    }

    if (
        Number(model.price) < 0 ||
        Number(model.stock) < 0
    ) {
        error.value = 'Price and stock cannot be negative.';
        return;
    }

    if (
        s.products.some(
            p =>
                p.sku.toLowerCase() ===
                    model.sku.toLowerCase() &&
                p.id !== existing.value?.id
        )
    ) {
        error.value = 'SKU must be unique.';
        return;
    }

    try {
        s.upsertProduct({
            ...(existing.value
                ? { id: existing.value.id }
                : {}),

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
            You are editing restored draft
            v{{ route.query.draftVersion }}.
            Saving will create a new live product version.
            Review before saving.
        </v-alert>

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
                            <v-select
                                v-model="model.category"
                                :items="[
                                    'Leadership',
                                    'Personal Growth',
                                    'Coaching',
                                    'Digital Tools'
                                ]"
                                label="Category"
                                variant="outlined"
                                density="comfortable"
                            />
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

                    <v-textarea
                        v-model="model.featuresText"
                        label="Features (one per line)"
                        variant="outlined"
                        rows="4"
                        auto-grow
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

                        <p class="small muted">
                            Illustrations are generated using built-in
                            visual styles. File uploads require backend storage.
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
                    color="primary"
                    prepend-icon="mdi-content-save"
                >
                    Save product
                </v-btn>

            </div>

        </v-form>

    </div>
</template>
