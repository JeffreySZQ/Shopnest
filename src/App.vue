<script setup>
import { onMounted, ref } from 'vue';
import { useStore } from '@/stores/shop.js';
import { bootstrapShop } from '@/services/shopApi.js';

const shop = useStore();
const loading = ref(true);
const error = ref('');

onMounted(async () => {
    try {
        await bootstrapShop(shop);
        console.log('ShopNest data loaded from Mercury MariaDB.');
    } catch (e) {
        console.error('ShopNest initialisation failed:', e);
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});

function retry() {
    window.location.reload();
}
</script>

<template>
    <main v-if="loading" class="container section">
        <p>Loading ShopNest data...</p>
    </main>

    <main v-else-if="error" class="container section">
        <h2>Unable to load ShopNest</h2>
        <p role="alert">{{ error }}</p>
        <button class="btn btn-primary" type="button" @click="retry">
            Retry
        </button>
    </main>

    <v-app>
        <RouterView v-else />
    </v-app>
</template>
