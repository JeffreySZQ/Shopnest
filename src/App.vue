<script>
import { mapStores } from 'pinia';
import { useStore } from '@/stores/shop.js';
import { bootstrapShop } from '@/services/shopApi.js';

export default {
    name: 'App',
    data() {
        return { loading: true, error: '' };
    },
    computed: {
        ...mapStores(useStore),
        shop() { return this.shopStore; }
    },
    async mounted() {
        try {
            await bootstrapShop(this.shop);
            console.log('ShopNest data loaded from Mercury MariaDB.');
        } catch (error) {
            console.error('ShopNest initialisation failed:', error);
            this.error = error.message;
        } finally {
            this.loading = false;
        }
    },
    methods: {
        retry() { window.location.reload(); }
    }
};
</script>

<template>
    <v-app>
        <main v-if="loading" class="app-loading" aria-live="polite">
            <v-progress-circular indeterminate color="primary" />
            <p>Loading ShopNest data...</p>
        </main>

        <main v-else-if="error" class="container section">
            <v-alert type="error" variant="tonal">
                {{ error }}
                <template #append>
                    <v-btn variant="text" @click="retry">Retry</v-btn>
                </template>
            </v-alert>
        </main>

        <RouterView v-else />
    </v-app>
</template>
