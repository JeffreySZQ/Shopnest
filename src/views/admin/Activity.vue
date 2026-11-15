<<<<<<< HEAD
<script setup>
import { computed, ref, onMounted } from 'vue';
=======
<script>
import { mapStores } from 'pinia';
>>>>>>> origin/main
import { useStore } from '@/stores/shop.js';
import { History, Search } from 'lucide-vue-next';
import { api } from '@/services/shopApi.js';

<<<<<<< HEAD
const s = useStore();
const q = ref('');
const resource = ref('');
const page = ref(1);
const size = 10;
const error = ref('');

onMounted(async () => {
    try {
        const result = await api.get('/admin/activity');
        s.logs = result.logs;
    } catch (e) {
        error.value = e.message;
    }
});

const filtered = computed(() =>
    s.logs.filter(log =>
        (!resource.value || log.resource === resource.value) &&
        (!q.value || (log.actor + ' ' + log.action + ' ' + log.resource + ' ' + log.detail).toLowerCase().includes(q.value.toLowerCase()))
    )
);

const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / size)));
const shown = computed(() => filtered.value.slice((page.value - 1) * size, page.value * size));
const resources = ['Products', 'Orders', 'Account', 'System'];
=======
export default {
    name: 'AdminActivityView',
    components: { History, Search },
    data() { return { q: '', resource: '', page: 1, size: 10, error: '' }; },
    computed: {
        ...mapStores(useStore),
        s() { return this.shopStore; },
        resources() { return ['Products', 'Orders', 'Account', 'System']; },
        resourceOptions() { return [{ title: 'All resources', value: '' }, ...this.resources.map(value => ({ title: value, value }))]; },
        filtered() {
            const keyword = this.q.toLowerCase();
            return this.s.logs.filter(log =>
                (!this.resource || log.resource === this.resource) &&
                (!keyword || (log.actor + ' ' + log.action + ' ' + log.resource + ' ' + log.detail).toLowerCase().includes(keyword))
            );
        },
        pages() { return Math.max(1, Math.ceil(this.filtered.length / this.size)); },
        shown() { return this.filtered.slice((this.page - 1) * this.size, this.page * this.size); }
    },
    watch: { q() { this.page = 1; }, resource() { this.page = 1; } },
    async mounted() {
        try {
            const result = await api.get('/admin/activity');
            this.s.logs = result.logs;
        } catch (error) { this.error = error.message; }
    }
};
>>>>>>> origin/main
</script>

<template>
    <div>
        <div class="admin-page-top">
            <div>
                <p class="eyebrow">AUDIT TRAIL</p>

                <h1>Activity log</h1>

                <p class="muted">
<<<<<<< HEAD
                    A read-only view of actions recorded in this browser demo.
=======
                    A read-only view of actions recorded by the ShopNest PHP API.
>>>>>>> origin/main
                </p>
            </div>

            <History :size="28" />
        </div>

        <div class="panel">
            <p v-if="error" class="text-danger" role="alert">{{ error }}</p>
            <div class="demo-panel mb-4">
                These records are retrieved from the ShopNest database and show actions recorded by the PHP backend.
                Real auditing requires append-only server records and role-based permissions.
            </div>

<<<<<<< HEAD
            <div class="toolbar">
                <label class="search-box">
                    <Search :size="18" />

                    <span class="visually-hidden">
                        Search activity
                    </span>

                    <input
                        v-model="q"
                        @input="page = 1"
                        placeholder="Search actor, action, record"
                    />
                </label>

                <label>
                    <span class="visually-hidden">
                        Filter resource
                    </span>

                    <select
                        v-model="resource"
                        class="form-select"
                        @change="page = 1"
                    >
                        <option value="">All resources</option>

                        <option
                            v-for="r in resources"
                            :key="r"
                        >
                            {{ r }}
                        </option>
                    </select>
                </label>
            </div>

            <div class="table-responsive">
                <table class="table admin-table align-middle">
=======
            <div class="row g-3 align-items-center mb-4">
                <div class="col-12 col-lg-7">
                    <v-text-field
                        v-model="q"
                        label="Search actor, action or record"
                        prepend-inner-icon="mdi-magnify"
                        variant="outlined"
                        density="comfortable"
                        clearable
                        hide-details
                    />
                </div>
                <div class="col-12 col-lg-5">
                    <v-select
                        v-model="resource"
                        :items="resourceOptions"
                        item-title="title"
                        item-value="value"
                        label="Filter resource"
                        variant="outlined"
                        density="comfortable"
                        hide-details
                    />
                </div>
            </div>

            <div class="table-responsive">
                <v-table class="table admin-table align-middle admin-vuetify-table" density="comfortable">
>>>>>>> origin/main
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Actor</th>
                            <th>Action</th>
                            <th>Resource</th>
                            <th>Details</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="log in shown"
                            :key="log.id"
                        >
                            <td class="small">
                                {{ new Date(log.time).toLocaleString('en-MY') }}
                            </td>

                            <td>
                                {{ log.actor }}
                            </td>

                            <td>
                                <strong>{{ log.action }}</strong>
                            </td>

                            <td>
                                {{ log.resource }}
                            </td>

                            <td>
                                {{ log.detail }}
                            </td>
                        </tr>
                    </tbody>
<<<<<<< HEAD
                </table>
=======
                </v-table>
>>>>>>> origin/main
            </div>

            <div
                v-if="!shown.length"
                class="empty-inline"
            >
                No activity matches your filters.
            </div>

<<<<<<< HEAD
            <div class="page-controls">
                <span>
                    {{ filtered.length }} events
                </span>

                <button
                    class="btn btn-outline-primary btn-sm"
                    :disabled="page === 1"
                    @click="page--"
                >
                    Previous
                </button>

                <span>
                    {{ page }} / {{ pages }}
                </span>

                <button
                    class="btn btn-outline-primary btn-sm"
                    :disabled="page >= pages"
                    @click="page++"
                >
                    Next
                </button>
=======
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-4">
                <span class="small text-medium-emphasis">{{ filtered.length }} events</span>
                <v-pagination
                    v-model="page"
                    :length="pages"
                    :total-visible="5"
                    density="comfortable"
                    rounded="circle"
                />
                <span class="small text-medium-emphasis">Page {{ page }} of {{ pages }}</span>
>>>>>>> origin/main
            </div>
        </div>
    </div>
</template>