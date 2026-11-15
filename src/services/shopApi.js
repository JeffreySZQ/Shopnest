const API_BASE = import.meta.env.DEV
    ? '/api'
    : `${import.meta.env.BASE_URL}resources/shopnest-api.php`;

let sessionPromise = null;

async function request(endpoint, options = {}) {
    const headers = { ...(options.headers || {}) };
    let body = options.body;

<<<<<<< HEAD
    
=======
>>>>>>> origin/main
    const isFormData =
        typeof FormData !== 'undefined' &&
        body instanceof FormData;

    if (
        body !== undefined &&
        body !== null &&
        typeof body !== 'string' &&
        !isFormData
    ) {
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
<<<<<<< HEAD
        throw new Error('The PHP API returned a non-JSON response. Check the Mercury PHP error output.');
    }

    if (!response.ok || data.success === false) {
        throw new Error(data.message || `API request failed (HTTP ${response.status}).`);
=======
        throw new Error(
            'The PHP API returned a non-JSON response. Check the Mercury PHP error output.'
        );
    }

    if (!response.ok || data.success === false) {
        throw new Error(
            data.message || `API request failed (HTTP ${response.status}).`
        );
>>>>>>> origin/main
    }

    return data;
}

<<<<<<< HEAD

=======
>>>>>>> origin/main
export const api = {
    get(endpoint) {
        return request(endpoint, { method: 'GET' });
    },

    post(endpoint, body = {}) {
<<<<<<< HEAD
        return request(endpoint, {
            method: 'POST',
            body
        });
    },

    put(endpoint, body = {}) {
        return request(endpoint, {
            method: 'PUT',
            body
        });
    },

    patch(endpoint, body = {}) {
        return request(endpoint, {
            method: 'PATCH',
            body
        });
    },

    delete(endpoint) {
        return request(endpoint, {
            method: 'DELETE'
        });
=======
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
>>>>>>> origin/main
    },

    upload(endpoint, formData) {
        return request(endpoint, {
            method: 'POST',
            body: formData
        });
    }
};

<<<<<<< HEAD

=======
>>>>>>> origin/main
export function setStoreUser(store, user) {
    if (user) {
        const safeUser = {
            id: Number(user.id),
            name: user.name,
            email: user.email,
            role: user.role
        };
<<<<<<< HEAD
=======

>>>>>>> origin/main
        store.users = [safeUser];
        store.currentUserId = safeUser.id;
    } else {
        store.users = [];
        store.currentUserId = null;
    }

<<<<<<< HEAD
    // These collections are reloaded from MariaDB after authentication.
=======
    // Account cart/order/wishlist data is fetched from the server.
    // Do not clear guestCart here: it must survive authentication
    // until the idempotent server-side merge succeeds.
>>>>>>> origin/main
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

<<<<<<< HEAD
=======
// Merge a guest's locally persisted cart into the authenticated account.
// The same token is reused after network failures, making retries safe.
export async function mergeGuestCart(store) {
    if (!store.user || !store.guestCart || !store.guestCart.length) {
        return { merged: false, reason: 'empty' };
    }

    const token = store.ensureGuestCartToken();
    const items = store.guestCart.map(item => ({
        productId: Number(item.productId),
        quantity: Number(item.quantity)
    }));

    const result = await api.post('/cart/merge', {
        mergeToken: token,
        items
    });

    // Clear local guest data only after the server confirms that the
    // token was processed (including an already-processed retry).
    store.clearGuestCart();
    store.guestCartError = '';

    await refreshCart(store);

    return result;
}

>>>>>>> origin/main
export async function refreshLikes(store) {
    const result = await api.get('/likes');
    const ids = result.productIds.map(Number);
    const key = String(store.currentUserId);
    store.liked = { [key]: ids };
    return ids;
}

export async function refreshOrders(store) {
    const result = await api.get('/orders');
<<<<<<< HEAD
    // shop.js's myOrders getter reverses the store array. Keep the store
    // oldest-first so myOrders continues to display newest-first.
=======
    // Keep the store oldest-first; the myOrders getter reverses it.
>>>>>>> origin/main
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
<<<<<<< HEAD
    // Admin order table reverses store.orders to show newest first.
    store.orders = results[1].orders.slice().reverse();
    store.logs = results[2].logs;
=======
    store.orders = results[1].orders.slice().reverse();
    store.logs = results[2].logs;

>>>>>>> origin/main
    return results;
}

export async function bootstrapShop(store) {
    store.init();

<<<<<<< HEAD
    // Remove stale browser-demo records from the active application state.
    // The existing Pinia store implementation itself remains unchanged.
=======
    // Server-owned collections are never restored from old demo data.
>>>>>>> origin/main
    store.products = [];
    store.users = [];
    store.currentUserId = null;
    store.cart = [];
    store.orders = [];
    store.liked = {};
    store.logs = [];

    await restoreSession(store, true);
    await refreshProducts(store);

<<<<<<< HEAD
    if (store.user && store.user.role === 'admin') {
        await refreshAdminData(store);
    } else if (store.user) {
        await refreshCustomerData(store);
=======
    if (store.user) {
        if (store.user.role === 'customer') {
            try {
                await mergeGuestCart(store);
            } catch (error) {
                // Keep the guest cart and token so the merge can be retried.
                store.guestCartError = error.message;
                console.warn('Guest cart merge will be retried:', error);
            }
        }

        if (store.user.role === 'admin') {
            await refreshAdminData(store);
        } else {
            await refreshCustomerData(store);
        }
>>>>>>> origin/main
    }
}

export function clearStoreSession(store) {
    setStoreUser(store, null);
    store.cart = [];
    store.orders = [];
    store.liked = {};
<<<<<<< HEAD
=======
    // The separate guest cart is intentionally preserved.
>>>>>>> origin/main
}
