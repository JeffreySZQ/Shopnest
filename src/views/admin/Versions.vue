<script>
import { ArrowLeft, RotateCcw, CheckCircle2, GitCompare, Image as ImageIcon } from 'lucide-vue-next';
import ProductArt from '@/components/ProductArt.vue';
import { api } from '@/services/shopApi.js';

export default {
    name: 'AdminProductVersionsView',
    components: { ArrowLeft, RotateCcw, CheckCircle2, GitCompare, ImageIcon, ProductArt },
    data() {
        return {
            product: null,
            versions: [],
            olderId: null,
            newerId: null,
            isConfirming: false,
            error: '',
            loading: true,
            comparable: [
                { key: 'name', label: 'Product name' },
                { key: 'sku', label: 'SKU' },
                { key: 'category', label: 'Category' },
                { key: 'type', label: 'Product type' },
                { key: 'status', label: 'Publication' },
                { key: 'short', label: 'Short description' },
                { key: 'description', label: 'Long description' },
                { key: 'price', label: 'Unit price' },
                { key: 'stock', label: 'Stock' },
                { key: 'imagePath', label: 'Image path' },
                { key: 'imageAlt', label: 'Image description' }
            ]
        };
    },
    computed: {
        oldVersion() { return this.versions.find(version => Number(version.version) === Number(this.olderId)); },
        newVersion() { return this.versions.find(version => Number(version.version) === Number(this.newerId)); },
        oldSnap() { return this.oldVersion ? this.oldVersion.snapshot : {}; },
        newSnap() { return this.newVersion ? this.newVersion.snapshot : {}; },
        changedCount() { return this.comparable.filter(field => this.changed(field.key)).length; },
        versionOptions() { return this.versions.map(version => ({ title: 'v' + version.version + ' · ' + this.formatted(version.createdAt) + ' (' + version.status + ')', value: Number(version.version) })); }
    },
    watch: {
        '$route.params.id'() { this.loadVersions(); },
        versions: {
            deep: true,
            handler(list) {
                if (!list.length) return;
                if (!list.some(version => Number(version.version) === Number(this.newerId))) {
                    this.newerId = Number(list[0].version);
                }
                if (!list.some(version => Number(version.version) === Number(this.olderId)) || Number(this.olderId) === Number(this.newerId)) {
                    const previous = list.find(version => Number(version.version) !== Number(this.newerId));
                    this.olderId = previous ? Number(previous.version) : Number(list[0].version);
                }
            }
        }
    },
    mounted() { this.loadVersions(); },
    methods: {
        async loadVersions() {
            this.loading = true;
            this.error = '';
            try {
                const id = Number(this.$route.params.id);
                const productResult = await api.get('/products/' + id);
                this.product = productResult.product;
                const versionResult = await api.get(`/admin/products/${id}/versions`);
                this.versions = (versionResult.versions || []).map(version => ({
                    ...version,
                    version: Number(version.version),
                    snapshot: version.snapshot || {}
                }));
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        displayValue(snapshot, key) {
            if (key === 'price') return `RM ${Number(snapshot.price || 0).toFixed(2)}`;
            if (key === 'stock') return String(Number(snapshot.stock || 0));
            const value = snapshot[key];
            return String(value === undefined || value === null || value === '' ? 'Not specified' : value);
        },
        changed(key) {
            return this.displayValue(this.oldSnap, key) !== this.displayValue(this.newSnap, key);
        },
        async restore() {
            if (!this.product || !this.olderId) return;
            this.error = '';
            try {
                const result = await api.post(
                    `/admin/products/${this.product.id}/restore/${this.olderId}`,
                    {}
                );
                this.isConfirming = false;
                await this.$router.push({
                    name: 'admin-product-edit',
                    params: { id: this.product.id },
                    query: { draftVersion: String(result.draftVersion) }
                });
            } catch (error) {
                this.error = error.message;
            }
        },
        formatted(date) {
            return new Date(date).toLocaleString('en-MY', {
                dateStyle: 'medium',
                timeStyle: 'short'
            });
        }
    }
};
</script>

<template>
    <div v-if="loading" class="version-page"><p>Loading version history...</p></div>
    <div v-else-if="error" class="version-page"><p class="text-danger" role="alert">{{ error }}</p></div>
    <div v-else-if="product" class="version-page">
        <RouterLink
            class="back-link"
            :to="'/admin/products/' + product.id + '/edit'"
        >
            <ArrowLeft :size="17" />
            Back to product editor
        </RouterLink>

        <div class="admin-page-top">
            <div>
                <p class="eyebrow">PRODUCT VERSION CONTROL</p>

                <h1>Compare product versions</h1>

                <p class="muted">
                    Review changes and restore an earlier version without
                    replacing the live listing.
                </p>
            </div>

            <RouterLink
                :to="'/admin/products/' + product.id + '/edit'"
                class="btn btn-outline-primary"
            >
                Return to editor
            </RouterLink>
        </div>

        <div class="panel version-product-header">
            <div class="version-product-icon">
                <ProductArt :product="product" />
            </div>

            <div>
                <strong>{{ product.name }}</strong>

                <div class="small muted">
                    {{ product.sku }} · {{ product.type }} · {{ product.category }}
                </div>
            </div>

            <span class="ms-auto fw-bold">
                RM {{ product.price.toFixed(2) }} / unit
            </span>
        </div>

        <p class="version-notice">Version history is retrieved from the ShopNest database. Restoring a version creates a draft for review; the live product is not changed until you save the draft.</p>

        <div class="version-layout">
            <aside class="panel version-sidebar">
                <h2>Version history</h2>

                <p class="small muted">
                    Choose exactly two versions to compare.
                </p>

                <v-btn
                    v-for="v in versions"
                    :key="v.version"
                    class="version-tile"
                    variant="text"
                    block
                    :class="{ selected: v.version === olderId || v.version === newerId }"
                    @click="v.version === newerId ? olderId = v.version : newerId = v.version"
                >
                    <span class="d-flex justify-content-between">
                        <strong>v{{ v.version }}</strong>
                        <span class="small">{{ v.status }}</span>
                    </span>

                    <small>{{ formatted(v.createdAt) }}</small>
                    <small>{{ v.actor }}</small>
                    <small>{{ v.note }}</small>
                </v-btn>
            </aside>

            <div class="version-main">
                <div class="panel version-selectors">
                    <div
                        class="d-flex justify-content-between align-items-center gap-2 flex-wrap"
                    >
                        <h2>Compare exactly two versions</h2>

                        <span class="version-chip">
                            {{ changedCount }} changed fields
                        </span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <v-select
                                v-model="olderId"
                                :items="versionOptions"
                                item-title="title"
                                item-value="value"
                                label="Earlier / restore source"
                                variant="outlined"
                                density="comfortable"
                            />
                        </div>

                        <div class="col-md-6">
                            <v-select
                                v-model="newerId"
                                :items="versionOptions"
                                item-title="title"
                                item-value="value"
                                label="Compare with"
                                variant="outlined"
                                density="comfortable"
                            />
                        </div>
                    </div>

                    <p class="small muted mb-0 mt-3">
                        Highlighted values are different. Unchanged values
                        appear without highlighting.
                    </p>
                </div>

                <div
                    v-if="oldVersion && newVersion"
                    class="version-diff panel"
                >
                    <div class="version-diff-head">
                        <div>
                            <strong>
                                Earlier · v{{ oldVersion.version }}
                            </strong>

                            <small>
                                {{ oldVersion.actor }} ·
                                {{ formatted(oldVersion.createdAt) }}
                            </small>

                            <small>{{ oldVersion.status }}</small>
                        </div>

                        <div>
                            <strong>
                                Compared · v{{ newVersion.version }}
                            </strong>

                            <small>
                                {{ newVersion.actor }} ·
                                {{ formatted(newVersion.createdAt) }}
                            </small>

                            <small>{{ newVersion.status }}</small>
                        </div>
                    </div>

                    <section
                        v-for="field in comparable"
                        :key="field.key"
                        class="version-field"
                    >
                        <div class="version-field-label">
                            <strong>{{ field.label }}</strong>

                            <span
                                :class="
                                    changed(field.key)
                                        ? 'change-tag'
                                        : 'unchanged-tag'
                                "
                            >
                                {{ changed(field.key) ? 'Changed' : 'Unchanged' }}
                            </span>
                        </div>

                        <div class="version-value-grid">
                            <div
                                :class="{
                                    'version-removed': changed(field.key)
                                }"
                            >
                                <template v-if="field.key === 'imagePath'">
                                    <ProductArt
                                        :product="{ ...product, ...oldSnap }"
                                    />
                                </template>

                                <div class="version-text">
                                    {{ displayValue(oldSnap, field.key) }}
                                </div>
                            </div>

                            <div
                                :class="{
                                    'version-added': changed(field.key)
                                }"
                            >
                                <template v-if="field.key === 'imagePath'">
                                    <ProductArt
                                        :product="{ ...product, ...newSnap }"
                                    />
                                </template>

                                <div class="version-text">
                                    {{ displayValue(newSnap, field.key) }}
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="panel version-restore">
                    <div>
                        <h2>
                            Restore earlier version v{{ olderId }}
                        </h2>

                        <p class="muted mb-0">
                            Create a new draft based on this snapshot.
                            Current product content, stock, orders and sales
                            will stay unchanged until you explicitly save
                            the draft in the editor.
                        </p>
                    </div>

                    <v-btn color="primary" :disabled="olderId === newerId || !olderId" @click="isConfirming = true">
                        <RotateCcw :size="16" /> Review restore
                    </v-btn>
                </div>

                <div
                    v-if="isConfirming"
                    class="panel version-confirm"
                    role="region"
                    aria-label="Confirm version restore"
                >
                    <h2>
                        <CheckCircle2 :size="21" />
                        Confirm restore to a new draft?
                    </h2>

                    <p>
                        The content from
                        <strong>v{{ olderId }}</strong>
                        will be copied into a new draft version.
                        The published product will not change.
                        You will be taken to the editor to review
                        and save the draft.
                    </p>

                    <p
                        v-if="error"
                        role="alert"
                        class="text-danger"
                    >
                        {{ error }}
                    </p>

                    <div class="d-flex gap-2 flex-wrap">
                        <v-btn variant="outlined" color="primary" @click="isConfirming = false">Cancel</v-btn>
                        <v-btn color="primary" @click="restore">
                            <RotateCcw :size="16" /> Confirm restore
                        </v-btn>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-else class="panel">
        <h1>Product not found</h1>

        <RouterLink to="/admin/products">
            Return to products
        </RouterLink>
    </div>
</template>