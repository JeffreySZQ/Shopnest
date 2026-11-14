
<script setup>
import { onMounted, ref } from 'vue';
import { useStore } from '@/stores/shop.js';

const shop = useStore();

const loading = ref(true);
const error = ref('');

const API_URL =
    'https://mercury.swin.edu.au/cos30043/s105557394/project/resources/products.php';

onMounted(async () => {
    // Initialise the existing Pinia store.
    shop.init();

    // Do not display old products while waiting for the database.
    shop.products = [];

    try {
        const response = await fetch(API_URL);

        if (!response.ok) {
            throw new Error(
                'The products API returned HTTP ' + response.status
            );
        }

        const data = await response.json();

        if (!data.success || !Array.isArray(data.products)) {
            throw new Error(
                data.message || 'The API returned an invalid response.'
            );
        }

        // Replace the store's products with the database products.
        shop.products = data.products.map(product => ({
            ...product,

            // These properties are used by existing frontend components.
            features: [],
            emoji: '📦',
            tone: 'blue',
            featured: false
        }));

        // Save the latest database products in localStorage.
        shop.save();

        console.log(
            'Products successfully loaded from the database:',
            data.count
        );
    } catch (err) {
        console.error('Unable to load database products:', err);
        error.value = err.message;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <main v-if="loading" class="container section">
        <p>Loading products from the database...</p>
    </main>

    <main v-else-if="error" class="container section">
        <h2>Unable to load products</h2>
        <p>{{ error }}</p>
        <p>Please refresh the page or try again later.</p>
    </main>

    <RouterView v-else />
</template>
