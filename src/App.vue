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
    <main v-if="loading">
        Loading ShopNest data...
    </main>

    <main v-else-if="error">
        {{ error }}
    </main>

    <v-app v-else>
        <RouterView />
    </v-app>
</template>
