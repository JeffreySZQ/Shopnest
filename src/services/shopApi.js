const API_BASE = import.meta.env.DEV
    ? '/api'
    : `${import.meta.env.BASE_URL}resources/shopnest-api.php`;

let sessionPromise = null;

async function request(endpoint, options = {}) {
    const headers = { ...(options.headers || {}) };
    let body = options.body;

    if (body !== undefined && body !== null && typeof body !== 'string') {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(body);
    }

    const response = await fetch(`${API_BASE}${endpoint}`, {
        ...options,
        headers,
        body,
        credentials: 'include'
    });

    const text = await response.text();
    let data;

    try {
        data = text ? JSON.parse(text) : {};
    } catch (error) {
        throw new Error('The PHP API returned a non-JSON response. Check the Mercury PHP error output.');
    }

    if (!response.ok || data.success === false) {
        throw new Error(data.message || `API request failed (HTTP ${response.status}).`);
    }

    return data;
}

export const api = {
    get(endpoint) {
        return request(endpoint, { method: 'GET' });
    },
    post(endpoint, body = {}) {
        return request(endpoint, { method: 'POST', body });
    },
    put(endpoint, body = {}) {
        return request(endpoint, { method: 'PUT', body });
    },
    patch(endpoint, body = {}) {
        return request(endpoint, { method: 'PATCH', body });
    },
    delete(endpoint) {
        return request(endpoint, { method: 'DELETE' });
    }
};

export function setStoreUser(store, user) {
    if (user) {
        const safeUser = {
            id: Number(user.id),
            name: user.name,
            email: user.email,
            role: user.role
        };
        store.users = [safeUser];
        store.currentUserId = safeUser.id;
    } else {
        store.users = [];
        store.currentUserId = null;
    }

    // These collections are reloaded from MariaDB after authentication.
    store.cart = [];
    store.orders = [];
    store.liked = {};
    sessionPromise = Promise.resolve(user || null);
}

export async function restoreSession(store, force = false) {
    if (!force && sessionPromise) {
        return sessionPromise;
    }

    sessionPromise = api.get('/auth/me')
        .then(result => {
            setStoreUser(store, result.user || null);
            return result.user || null;
        })
        .catch(error => {
            sessionPromise = null;
            setStoreUser(store, null);
            throw error;
        });

    return sessionPromise;
}

function normaliseProduct(product) {
    return {
        ...product,
        id: Number(product.id),
        price: Number(product.price),
        stock: Number(product.stock),
        likes: Number(product.likes || 0),
        features: [],
        emoji: '📦',
        tone: 'blue',
        featured: false
    };
}

export async function refreshProducts(store) {
    const result = await api.get('/products');
    store.products = result.products.map(normaliseProduct);
    return store.products;
}

export async function refreshCart(store) {
    const result = await api.get('/cart');
    store.cart = result.items.map(item => ({
        productId: Number(item.productId),
        quantity: Number(item.quantity)
    }));
    return store.cart;
}

export async function refreshLikes(store) {
    const result = await api.get('/likes');
    const ids = result.productIds.map(Number);
    const key = String(store.currentUserId);
    store.liked = { [key]: ids };
    return ids;
}

export async function refreshOrders(store) {
    const result = await api.get('/orders');
    // shop.js's myOrders getter reverses the store array. Keep the store
    // oldest-first so myOrders continues to display newest-first.
    store.orders = result.orders.slice().reverse();
    return store.orders;
}

export async function refreshCustomerData(store) {
    if (!store.user) {
        store.cart = [];
        store.orders = [];
        store.liked = {};
        return;
    }

    await Promise.all([
        refreshCart(store),
        refreshLikes(store),
        refreshOrders(store)
    ]);
}

export async function refreshAdminData(store) {
    const results = await Promise.all([
        api.get('/admin/products'),
        api.get('/admin/orders'),
        api.get('/admin/activity')
    ]);

    store.products = results[0].products.map(normaliseProduct);
    // Admin order table reverses store.orders to show newest first.
    store.orders = results[1].orders.slice().reverse();
    store.logs = results[2].logs;
    return results;
}

export async function bootstrapShop(store) {
    store.init();

    // Remove stale browser-demo records from the active application state.
    // The existing Pinia store implementation itself remains unchanged.
    store.products = [];
    store.users = [];
    store.currentUserId = null;
    store.cart = [];
    store.orders = [];
    store.liked = {};
    store.logs = [];

    await restoreSession(store, true);
    await refreshProducts(store);

    if (store.user && store.user.role === 'admin') {
        await refreshAdminData(store);
    } else if (store.user) {
        await refreshCustomerData(store);
    }
}

export function clearStoreSession(store) {
    setStoreUser(store, null);
    store.cart = [];
    store.orders = [];
    store.liked = {};
}
