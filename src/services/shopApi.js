const API_BASE = import.meta.env.DEV
    ? '/api'
    : `${import.meta.env.BASE_URL}resources/shopnest-api.php`;

let sessionPromise = null;

async function request(endpoint, options = {}) {
    const headers = { ...(options.headers || {}) };
    let body = options.body;

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
        throw new Error(
            'The PHP API returned a non-JSON response. Check the Mercury PHP error output.'
        );
    }

    if (!response.ok || data.success === false) {
        throw new Error(
            data.message || `API request failed (HTTP ${response.status}).`
        );
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
    },

    upload(endpoint, formData) {
        return request(endpoint, {
            method: 'POST',
            body: formData
        });
    }
};

// Updates the authenticated user information in the Pinia store.
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

    // Account cart/order/wishlist data is fetched from the server.
    store.cart = [];
    store.orders = [];
    store.liked = {};
    sessionPromise = Promise.resolve(user || null);
}

// Restores the user's login session and updates the Pinia store.
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

// Retrieves product data and updates the Pinia store.
export async function refreshProducts(store) {
    const result = await api.get('/products');
    store.products = result.products.map(normaliseProduct);
    return store.products;
}

// Retrieves the customer's cart and updates the Pinia state.
export async function refreshCart(store) {
    const result = await api.get('/cart');
    store.cart = result.items.map(item => ({
        productId: Number(item.productId),
        quantity: Number(item.quantity)
    }));
    return store.cart;
}

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

export async function refreshLikes(store) {
    const result = await api.get('/likes');
    const ids = result.productIds.map(Number);
    const key = String(store.currentUserId);
    store.liked = { [key]: ids };
    return ids;
}

export async function refreshOrders(store) {
    const result = await api.get('/orders');
    // Keep the store oldest-first; the myOrders getter reverses it.
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
    store.orders = results[1].orders.slice().reverse();
    store.logs = results[2].logs;

    return results;
}

export async function bootstrapShop(store) {
    store.init();

    // Server-owned collections are never restored from old demo data.
    store.products = [];
    store.users = [];
    store.currentUserId = null;
    store.cart = [];
    store.orders = [];
    store.liked = {};
    store.logs = [];

    await restoreSession(store, true);
    await refreshProducts(store);

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
    }
}

export function clearStoreSession(store) {
    setStoreUser(store, null);
    store.cart = [];
    store.orders = [];
    store.liked = {};
    // The separate guest cart is intentionally preserved.
}
