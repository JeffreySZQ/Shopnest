import { createRouter, createWebHistory } from 'vue-router';
import { useStore } from '@/stores/shop.js';

import PublicLayout from '@/layouts/PublicLayout.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

const viewModules = import.meta.glob('../views/**/*.vue');

const pub = (path, name, view, meta = {}) => ({
    path,
    name,
    component: viewModules[`../views/${view}.vue`],
    meta
});

const routes = [
    {
        path: '/',
        component: PublicLayout,
        children: [
            pub('', 'home', 'Home'),
            pub('catalog', 'catalog', 'Catalog'),
            pub('products/:id', 'product', 'Product'),
            pub('cart', 'cart', 'Cart'),
            pub('login', 'login', 'Login'),
            pub('signup', 'signup', 'Signup'),
            pub('checkout', 'checkout', 'Checkout', { auth: true }),
            pub('order-confirmed/:id', 'confirmed', 'Confirmed', { auth: true }),
            pub('dashboard', 'dashboard', 'Dashboard', { auth: true }),
            pub('orders', 'orders', 'History', { auth: true }),
            pub('orders/:id', 'order-detail', 'OrderDetail', { auth: true })
        ]
    },
    {
        path: '/admin',
        component: AdminLayout,
        meta: { admin: true },
        children: [
            pub('', 'admin-dashboard', 'admin/Overview'),
            pub('products', 'admin-products', 'admin/Products'),
            pub('products/new', 'admin-product-new', 'admin/Editor'),
            pub('products/:id/edit', 'admin-product-edit', 'admin/Editor'),
            pub('products/:id/versions', 'admin-product-versions', 'admin/Versions'),
            pub('orders', 'admin-orders', 'admin/Orders'),
            pub('orders/:id', 'admin-order-detail', 'admin/OrderDetail'),
            pub('activity', 'admin-activity', 'admin/Activity')
        ]
    },
    {
        path: '/:pathMatch(.*)*',
        component: () => import('../views/NotFound.vue')
    }
];

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes,
    scrollBehavior: () => ({ top: 0 })
});

router.beforeEach(to => {
    const s = useStore();

    s.init();

    if (
        to.matched.some(m => m.meta.admin) &&
        s.user?.role !== 'admin'
    ) {
        return {
            name: 'login',
            query: { redirect: to.fullPath }
        };
    }

    if (
        to.matched.some(m => m.meta.auth) &&
        !s.user
    ) {
        return {
            name: 'login',
            query: { redirect: to.fullPath }
        };
    }

    return true;
});

export default router;