import { defineStore } from 'pinia';
import { seedProducts } from '@/data/products.js';

const KEY = 'shopnest-demo-v1';

const startingUsers = [
    {
        id: 1,
        name: 'Customer',
        email: 'customer@shopnest.test',
        password: 'Customer123!',
        role: 'customer'
    },
    {
        id: 2,
        name: 'Demo Admin',
        email: 'admin@shopnest.test',
        password: 'Admin123!',
        role: 'admin'
    }
];

const initial = () => ({
    products: structuredClone(seedProducts),
    users: structuredClone(startingUsers),
    currentUserId: null,
    cart: [],
    orders: [],
    liked: {},
    logs: [
        {
            id: 1,
            time: new Date().toISOString(),
            actor: 'System',
            action: 'Store initialized',
            resource: 'System',
            detail: 'Demo catalog ready'
        }
    ],
    nextOrder: 1001,
    productVersions: {}
});

export const useStore = defineStore('shop', {
    state: () => ({
        ready: false,
        ...initial()
    }),

    getters: {
        user: s =>
            s.users.find(u => u.id === s.currentUserId) || null,

        cartItems: s =>
            s.cart
                .map(item => ({
                    ...item,
                    product: s.products.find(
                        p => p.id === item.productId
                    )
                }))
                .filter(item => item.product),

        cartCount: s =>
            s.cart.reduce((n, i) => n + i.quantity, 0),

        cartSubtotal() {
            return this.cartItems.reduce(
                (n, i) => n + i.product.price * i.quantity,
                0
            );
        },

        myOrders() {
            return this.orders
                .filter(o => o.userId === this.currentUserId)
                .slice()
                .reverse();
        },

        published: s =>
            s.products.filter(p => p.status === 'published'),

        sales: s =>
            s.orders
                .filter(o => o.paymentStatus === 'paid')
                .reduce((n, o) => n + o.total, 0)
    },

    actions: {
        init() {
            if (this.ready) return;

            try {
                const raw = localStorage.getItem(KEY);

                if (raw) {
                    const data = JSON.parse(raw);

                    for (const k of Object.keys(initial())) {
                        if (data[k] !== undefined) {
                            this[k] = data[k];
                        }
                    }
                }
            } catch (e) {
                console.warn('Could not restore demo data', e);
            }

            this.ready = true;
            this.ensureProductVersions();
        },

        ensureProductVersions() {
            if (
                !this.productVersions ||
                typeof this.productVersions !== 'object'
            ) {
                this.productVersions = {};
            }

            for (const product of this.products) {
                const key = String(product.id);

                if (
                    !Array.isArray(this.productVersions[key]) ||
                    !this.productVersions[key].length
                ) {
                    const current = this.productSnapshot(product);

                    // The first product includes explicitly illustrative earlier content for the demo.
                    if (product.id === 1) {
                        const old = {
                            ...current,
                            price: 29,
                            description: 'The Executive Daily Planner helps people plan their day, set priorities, and reflect on progress.',
                            features: [
                                '200 guided pages',
                                'Hardcover journal',
                                'Monthly goal reviews'
                            ],
                            tone: 'sand'
                        };

                        this.productVersions[key] = [
                            {
                                version: 1,
                                createdAt: '2026-10-14T10:15:00+08:00',
                                actor: 'Demo Catalog Editor',
                                status: 'historical',
                                note: 'Illustrative original listing',
                                snapshot: old
                            },
                            {
                                version: 2,
                                createdAt: '2026-11-05T09:40:00+08:00',
                                actor: 'Demo Catalog Editor',
                                status: 'live',
                                note: 'Current example listing',
                                snapshot: current
                            }
                        ];
                    } else {
                        this.productVersions[key] = [
                            {
                                version: 1,
                                createdAt: new Date().toISOString(),
                                actor: 'Demo data',
                                status: 'live',
                                note: 'Initial product snapshot',
                                snapshot: current
                            }
                        ];
                    }
                }
            }

            this.save();
        },

        productSnapshot(p) {
            const {
                id,
                sku,
                name,
                category,
                type,
                price,
                short,
                description,
                features,
                emoji,
                tone,
                status
            } = p;

            return JSON.parse(
                JSON.stringify({
                    id,
                    sku,
                    name,
                    category,
                    type,
                    price,
                    short,
                    description,
                    features: features || [],
                    emoji,
                    tone,
                    status
                })
            );
        },

        getProductVersions(id) {
            return this.productVersions?.[String(id)] || [];
        },

        restoreProductVersion(id, version) {
            const product = this.products.find(
                p => p.id === Number(id)
            );

            const history = this.getProductVersions(id);

            const previous = history.find(
                h => h.version === Number(version)
            );

            if (!product || !previous) {
                throw Error('Product version could not be found.');
            }

            const next = Math.max(
                ...history.map(h => h.version),
                0
            ) + 1;

            history.push({
                version: next,
                createdAt: new Date().toISOString(),
                actor: this.user?.name || 'Admin',
                status: 'draft',
                note: `Restored draft from v${version}; live product unchanged`,
                snapshot: {
                    ...structuredClone(previous.snapshot),
                    status: 'draft'
                }
            });

            this.log(
                'Product version restored to draft',
                'Products',
                `${product.name}: v${version} -> v${next} (draft)`
            );

            return next;
        },

        save() {
            const out = {};

            for (const k of Object.keys(initial())) {
                out[k] = this[k];
            }

            localStorage.setItem(KEY, JSON.stringify(out));
        },

        log(action, resource, detail) {
            this.logs.unshift({
                id: Date.now() + Math.random(),
                time: new Date().toISOString(),
                actor: this.user?.name || 'Guest',
                action,
                resource,
                detail
            });

            this.save();
        },

        login(email, password) {
            const user = this.users.find(
                u =>
                    u.email.toLowerCase() === email.toLowerCase().trim() &&
                    u.password === password
            );

            if (!user) {
                throw Error('Incorrect email or password.');
            }

            this.currentUserId = user.id;

            this.log(
                'Signed in',
                'Account',
                user.email
            );

            return user;
        },

        signup(name, email, password) {
            if (
                this.users.some(
                    u => u.email.toLowerCase() === email.toLowerCase()
                )
            ) {
                throw Error('An account with this email already exists.');
            }

            const user = {
                id: Date.now(),
                name,
                email: email.toLowerCase(),
                password,
                role: 'customer'
            };

            this.users.push(user);
            this.currentUserId = user.id;

            this.log(
                'Account registered',
                'Account',
                email
            );

            return user;
        },

        logout() {
            this.log(
                'Signed out',
                'Account',
                this.user?.email || ''
            );

            this.currentUserId = null;
            this.save();
        },

        addCart(id, qty = 1) {
            const p = this.products.find(x => x.id === id);

            if (!p || p.status !== 'published') {
                throw Error('Product unavailable');
            }

            const item = this.cart.find(
                x => x.productId === id
            );

            const n = (item?.quantity || 0) + qty;

            if (p.type === 'physical' && n > p.stock) {
                throw Error('Not enough stock available.');
            }

            if (item) {
                item.quantity = n;
            } else {
                this.cart.push({
                    productId: id,
                    quantity: qty
                });
            }

            this.save();
        },

        setCartQty(id, qty) {
            if (qty <= 0) {
                this.cart = this.cart.filter(
                    i => i.productId !== id
                );
            } else {
                const p = this.products.find(
                    x => x.id === id
                );

                if (p?.type === 'physical' && qty > p.stock) {
                    throw Error('Not enough stock.');
                }

                const i = this.cart.find(
                    x => x.productId === id
                );

                if (i) {
                    i.quantity = qty;
                }
            }

            this.save();
        },

        isLiked(id) {
            return !!(
                this.currentUserId &&
                this.liked[this.currentUserId]?.includes(id)
            );
        },

        toggleLike(id) {
            if (!this.user) {
                throw Error('Please sign in to like products.');
            }

            const k = this.currentUserId;
            const liked = this.liked[k] || [];

            const p = this.products.find(
                x => x.id === id
            );

            if (!p) return;

            if (liked.includes(id)) {
                this.liked[k] = liked.filter(
                    x => x !== id
                );

                p.likes = Math.max(0, p.likes - 1);
            } else {
                this.liked[k] = [...liked, id];
                p.likes++;
            }

            this.save();
        },

        checkout(details) {
            if (!this.user) {
                throw Error('Please sign in first.');
            }

            if (!this.cart.length) {
                throw Error('Your cart is empty.');
            }

            for (const i of this.cartItems) {
                if (
                    i.product.status !== 'published' ||
                    (
                        i.product.type === 'physical' &&
                        i.quantity > i.product.stock
                    )
                ) {
                    throw Error('Cart contains unavailable items.');
                }
            }

            const shipping = this.cartItems.some(
                i => i.product.type === 'physical'
            ) ? 8 : 0;

            const subtotal = this.cartSubtotal;

            const order = {
                id: `SN-${this.nextOrder++}`,
                userId: this.user.id,
                customer: this.user.name,
                email: details.email,
                items: this.cartItems.map(i => ({
                    productId: i.productId,
                    name: i.product.name,
                    type: i.product.type,
                    price: i.product.price,
                    quantity: i.quantity
                })),
                subtotal,
                shipping,
                total: subtotal + shipping,
                paymentStatus: 'paid',
                fulfillmentStatus: this.cartItems.every(
                    i => i.product.type === 'digital'
                ) ? 'digital delivered' : 'processing',
                address: details.address || '',
                city: details.city || '',
                postcode: details.postcode || '',
                createdAt: new Date().toISOString(),
                simulation: true
            };

            for (const i of this.cartItems) {
                if (i.product.type === 'physical') {
                    i.product.stock -= i.quantity;
                }
            }

            this.orders.push(order);
            this.cart = [];

            this.log(
                'Demo order created',
                'Orders',
                order.id
            );

            return order;
        },

        upsertProduct(product) {
            if (product.id) {
                const ix = this.products.findIndex(
                    p => p.id === product.id
                );

                if (ix < 0) {
                    throw Error('Product missing');
                }

                this.products[ix] = {
                    ...this.products[ix],
                    ...product
                };

                const history =
                    this.productVersions[String(product.id)] || [];

                for (const h of history) {
                    if (h.status === 'live') {
                        h.status = 'historical';
                    }
                }

                const version = Math.max(
                    0,
                    ...history.map(h => h.version)
                ) + 1;

                history.push({
                    version,
                    createdAt: new Date().toISOString(),
                    actor: this.user?.name || 'Admin',
                    status: 'live',
                    note: 'Product saved in editor',
                    snapshot: this.productSnapshot(this.products[ix])
                });

                this.productVersions[String(product.id)] = history;

                this.log(
                    'Product updated',
                    'Products',
                    `${product.name}: v${version}`
                );
            } else {
                const id = Math.max(
                    0,
                    ...this.products.map(p => p.id)
                ) + 1;

                this.products.unshift({
                    ...product,
                    id,
                    likes: 0,
                    emoji: '📦',
                    tone: 'blue'
                });

                this.productVersions[String(id)] = [
                    {
                        version: 1,
                        createdAt: new Date().toISOString(),
                        actor: this.user?.name || 'Admin',
                        status: 'live',
                        note: 'Product created',
                        snapshot: this.productSnapshot(this.products[0])
                    }
                ];

                this.log(
                    'Product created',
                    'Products',
                    product.name
                );
            }

            this.save();
        },

        deleteProduct(id) {
            const p = this.products.find(
                p => p.id === id
            );

            if (!p) return;

            this.products = this.products.filter(
                x => x.id !== id
            );

            this.cart = this.cart.filter(
                i => i.productId !== id
            );

            this.log(
                'Product deleted',
                'Products',
                p.name
            );
        },

        requestCancellation(id) {
            const order = this.orders.find(
                o => o.id === id && o.userId === this.currentUserId
            );

            if (!order) {
                throw Error('Order not found.');
            }

            if (
                !['processing', 'digital delivered'].includes(
                    order.fulfillmentStatus
                )
            ) {
                throw Error('This order can no longer be cancelled online.');
            }

            if (order.cancellationRequestedAt) {
                throw Error('A cancellation request is already pending.');
            }

            order.cancellationRequestedAt = new Date().toISOString();
            order.cancellationStatus = 'pending';

            this.log(
                'Cancellation requested',
                'Orders',
                order.id
            );
        },

        resolveCancellation(id, approve) {
            if (this.user?.role !== 'admin') {
                throw Error('Administrator access required.');
            }

            const order = this.orders.find(
                o => o.id === id
            );

            if (!order || order.cancellationStatus !== 'pending') {
                throw Error('No pending cancellation request.');
            }

            if (approve) {
                order.cancellationStatus = 'approved';
                order.fulfillmentStatus = 'cancelled';

                // Only a demonstration status: no actual money is returned.
                order.paymentStatus = 'refund simulated';

                for (const item of order.items) {
                    if (item.type === 'physical') {
                        const product = this.products.find(
                            p => p.id === item.productId
                        );

                        if (product) {
                            product.stock += item.quantity;
                        }
                    }
                }
            } else {
                order.cancellationStatus = 'rejected';
            }

            this.log(
                approve
                    ? 'Demo cancellation approved'
                    : 'Cancellation declined',
                'Orders',
                order.id
            );
        },

        fulfill(id, status) {
            const o = this.orders.find(
                o => o.id === id
            );

            if (!o) return;

            o.fulfillmentStatus = status;

            this.log(
                'Order status updated',
                'Orders',
                `${id}: ${status}`
            );
        },

        reset() {
            const fresh = initial();

            for (const k of Object.keys(fresh)) {
                this[k] = fresh[k];
            }

            this.save();
        }
    }
});