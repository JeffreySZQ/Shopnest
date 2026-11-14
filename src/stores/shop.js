import { defineStore } from 'pinia';

const GUEST_CART_KEY = 'shopnest-guest-cart-v1';

function createToken() {
    const bytes = new Uint8Array(32);

    if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
        crypto.getRandomValues(bytes);
    } else {
        for (let i = 0; i < bytes.length; i++) {
            bytes[i] = Math.floor(Math.random() * 256);
        }
    }

    return Array.from(bytes)
        .map(value => value.toString(16).padStart(2, '0'))
        .join('');
}

export const useStore = defineStore('shop', {
    state: () => ({
        ready: false,
        products: [],
        users: [],
        currentUserId: null,
        cart: [],
        guestCart: [],
        guestCartToken: '',
        guestCartError: '',
        orders: [],
        liked: {},
        logs: []
    }),

    getters: {
        user: state =>
            state.users.find(user => Number(user.id) === Number(state.currentUserId)) || null,

        // The cart shown in the UI is the account cart when signed in,
        // otherwise it is the locally persisted guest cart.
        cartItems() {
            const source = this.user ? this.cart : this.guestCart;

            return source
                .map(item => ({
                    ...item,
                    product: this.products.find(
                        product => Number(product.id) === Number(item.productId)
                    )
                }))
                .filter(item => item.product);
        },

        cartCount() {
            return this.cartItems.reduce(
                (total, item) => total + Number(item.quantity || 0),
                0
            );
        },

        cartSubtotal() {
            return this.cartItems.reduce(
                (total, item) => total + item.product.price * item.quantity,
                0
            );
        },

        myOrders() {
            return this.orders
                .filter(order => Number(order.userId) === Number(this.currentUserId))
                .slice()
                .reverse();
        },

        published: state =>
            state.products.filter(product => product.status === 'published'),

        sales: state =>
            state.orders
                .filter(order => order.paymentStatus === 'paid')
                .reduce((total, order) => total + Number(order.total || 0), 0)
    },

    actions: {
        init() {
            if (this.ready) return;

            try {
                const saved = localStorage.getItem(GUEST_CART_KEY);

                if (saved) {
                    const parsed = JSON.parse(saved);
                    this.guestCart = Array.isArray(parsed.items)
                        ? parsed.items
                            .map(item => ({
                                productId: Number(item.productId),
                                quantity: Math.max(1, Math.min(99, Number(item.quantity) || 1))
                            }))
                            .filter(item => Number.isInteger(item.productId) && item.productId > 0)
                        : [];
                    this.guestCartToken = typeof parsed.token === 'string'
                        ? parsed.token
                        : '';
                }
            } catch (error) {
                console.warn('Could not restore the guest cart:', error);
                this.guestCart = [];
                this.guestCartToken = '';
            }

            this.ensureGuestCartToken();
            this.ready = true;
            this.persistGuestCart();
        },

        ensureGuestCartToken() {
            if (!/^[a-f0-9]{64}$/i.test(this.guestCartToken || '')) {
                this.guestCartToken = createToken();
                this.persistGuestCart();
            }
            return this.guestCartToken;
        },

        persistGuestCart() {
            try {
                localStorage.setItem(
                    GUEST_CART_KEY,
                    JSON.stringify({
                        token: this.guestCartToken,
                        items: this.guestCart
                    })
                );
            } catch (error) {
                console.warn('Could not save the guest cart:', error);
            }
        },

        addGuestCart(productId, quantity = 1) {
            if (this.guestCartError) {
                throw new Error('Please sign in again to retry merging your saved guest cart before changing it.');
            }

            const id = Number(productId);
            const amount = Number(quantity);
            const product = this.products.find(item => Number(item.id) === id);

            if (!product || product.status !== 'published') {
                throw new Error('Product is unavailable.');
            }

            if (!Number.isInteger(amount) || amount < 1 || amount > 99) {
                throw new Error('Choose a quantity between 1 and 99.');
            }

            const existing = this.guestCart.find(item => Number(item.productId) === id);
            const nextQuantity = (existing ? Number(existing.quantity) : 0) + amount;

            if (nextQuantity > 99) {
                throw new Error('Maximum quantity is 99.');
            }

            if (product.type === 'physical' && nextQuantity > Number(product.stock)) {
                throw new Error('Not enough stock available.');
            }

            if (existing) {
                existing.quantity = nextQuantity;
            } else {
                this.guestCart.push({ productId: id, quantity: amount });
            }

            this.ensureGuestCartToken();
            this.persistGuestCart();
        },

        setGuestCartQty(productId, quantity) {
            if (this.guestCartError) {
                throw new Error('Please sign in again to retry merging your saved guest cart before changing it.');
            }

            const id = Number(productId);
            const amount = Number(quantity);

            if (amount <= 0) {
                this.guestCart = this.guestCart.filter(
                    item => Number(item.productId) !== id
                );
                this.persistGuestCart();
                return;
            }

            if (!Number.isInteger(amount) || amount > 99) {
                throw new Error('Quantity must be between 1 and 99.');
            }

            const product = this.products.find(item => Number(item.id) === id);
            if (!product || product.status !== 'published') {
                throw new Error('Product is unavailable.');
            }

            if (product.type === 'physical' && amount > Number(product.stock)) {
                throw new Error('Not enough stock available.');
            }

            const item = this.guestCart.find(
                entry => Number(entry.productId) === id
            );

            if (item) item.quantity = amount;
            this.persistGuestCart();
        },

        clearGuestCart() {
            this.guestCart = [];
            // A new token is generated only after a successful merge.
            this.guestCartToken = createToken();
            this.guestCartError = '';
            this.persistGuestCart();
        },

        isLiked(productId) {
            const key = String(this.currentUserId || '');
            return !!(key && this.liked[key] && this.liked[key].includes(Number(productId)));
        },

        // Account data is refreshed from the PHP API; these setters only
        // update the reactive Pinia cache.
        setProducts(products) {
            this.products = Array.isArray(products) ? products : [];
        },

        reset() {
            this.products = [];
            this.users = [];
            this.currentUserId = null;
            this.cart = [];
            this.orders = [];
            this.liked = {};
            this.logs = [];
            this.guestCartError = '';
        }
    }
});
