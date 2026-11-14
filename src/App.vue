
<script setup>
import { onMounted } from 'vue';
import { useStore } from '@/stores/shop.js';

const shop = useStore();

const API_URL =
    'https://mercury.swin.edu.au/cos30043/s105557394/project/resources/products.php';

onMounted(async () => {
    // Initialise the existing store first.
    shop.init();

    // Retrieve the products from the Mercury database.
    try {
        const response = await fetch(API_URL);

        if (!response.ok) {
            throw new Error(
                'API request failed: ' + response.status
            );
        }

        const data = await response.json();

        if (!data.success || !Array.isArray(data.products)) {
            throw new Error(
                data.message || 'Invalid products API response.'
            );
        }

        // Replace the locally stored product list.
        shop.products = data.products;

        // Save the database products into the existing store.
        shop.save();

        console.log(
            'ShopNest products loaded from database:',
            data.count
        );

    } catch (error) {
        console.error(
            'Could not load database products:',
            error
        );
    }
});
</script>

<template>
    <RouterView />
</template>
