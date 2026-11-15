<script>
import { mapStores } from 'pinia';
import { useStore } from '@/stores/shop.js';
import ProductCard from '@/components/ProductCard.vue';
import { ArrowRight, Truck, ShieldCheck, Sparkles, Download, BookOpen, HeartHandshake, Lightbulb } from 'lucide-vue-next';

const categoryIcons = {
    Leadership: BookOpen,
    'Personal Growth': Sparkles,
    Coaching: HeartHandshake,
    Productivity: Lightbulb
};
const categoryDescriptions = {
    Leadership: 'Lead with purpose',
    'Personal Growth': 'Make time to reflect',
    Coaching: 'Spark conversations',
    Productivity: 'Make every day count'
};

export default {
    name: 'HomeView',
    components: { ProductCard, ArrowRight, Truck, ShieldCheck, Sparkles, Download, BookOpen, HeartHandshake, Lightbulb },
    computed: {
        ...mapStores(useStore),
        s() { return this.shopStore; },
        featured() {
            return this.s.published.slice().sort((a, b) => Number(b.likes || 0) - Number(a.likes || 0)).slice(0, 4);
        },
        cats() {
            const names = Array.from(new Set(this.s.published.map(product => product.category).filter(Boolean)));
            return names.map(name => ({
                name,
                icon: categoryIcons[name] || Lightbulb,
                copy: categoryDescriptions[name] || 'Explore the collection'
            }));
        }
    }
};
</script>

<template>
    <div>
        <section class="hero">
            <div class="container hero-grid">
                <div class="hero-copy">
                    <div class="eyebrow">
                        THE SHOPNEST COLLECTION
                        <span class="mini-line"></span>
                        MADE FOR YOUR NEXT CHAPTER
                    </div>

                    <h1>
                        Small tools.<br />
                        <em>Meaningful</em> growth.
                    </h1>

                    <p>
                        Beautifully practical resources to help you think clearly,
                        connect better and make room for what matters.
                    </p>

                    <div class="hero-ctas">
                        <RouterLink
                            to="/catalog"
                            class="btn btn-primary btn-lg"
                        >
                            Explore the collection
                            <ArrowRight :size="18" />
                        </RouterLink>

                        <RouterLink
                            to="/catalog?type=digital"
                            class="text-cta"
                        >
                            Browse digital tools
                            <ArrowRight :size="16" />
                        </RouterLink>
                    </div>

                    <div class="hero-trust">
                        <span>✦ Thoughtfully designed</span>
                        <span>✦ Digital + physical</span>
                    </div>
                </div>

                <div
                    class="hero-visual"
                    aria-label="Illustrated assortment of journals and learning tools"
                >
                    <div class="hero-shape hero-shape-1"></div>
                    <div class="hero-shape hero-shape-2"></div>

                    <div class="hero-book">
                        <div class="hero-book-top">
                            SHOPNEST / NOTES
                        </div>

                        <div class="hero-book-title">
                            THE ART<br />
                            OF<br />
                            GROWING.
                        </div>

                        <div class="hero-book-bottom">
                            A thoughtful daily practice
                        </div>
                    </div>

                    <div class="hero-sticker">
                        Make space<br />
                        to grow ✳
                    </div>
                </div>
            </div>
        </section>

        <section class="value-strip">
            <div class="container value-row">
                <div>
                    <Truck :size="23" />
                    <span>Carefully packed products</span>
                </div>

                <div>
                    <Download :size="23" />
                    <span>Instant digital access*</span>
                </div>

                <div>
                    <ShieldCheck :size="23" />
                    <span>Simple, mindful shopping</span>
                </div>
            </div>
        </section>

        <section class="section container">
            <div class="section-head">
                <div>
                    <p class="eyebrow">FIND YOUR THING</p>
                    <h2>Explore by intention</h2>
                </div>

                <RouterLink to="/catalog" class="text-cta">
                    Browse all
                    <ArrowRight :size="17" />
                </RouterLink>
            </div>

            <div class="category-grid">
                <RouterLink
                    v-for="c in cats"
                    :key="c.name"
                    :to="'/catalog?category=' + encodeURIComponent(c.name)"
                    class="category-card"
                >
                    <component :is="c.icon" :size="29" />

                    <h3>{{ c.name }}</h3>

                    <span>
                        {{ c.copy }}
                        <ArrowRight :size="15" />
                    </span>
                </RouterLink>
            </div>
        </section>

        <section class="section section-tint">
            <div class="container">
                <div class="section-head">
                    <div>
                        <p class="eyebrow">POPULAR RIGHT NOW</p>
                        <h2>Made to make a difference</h2>
                        <p class="muted">
                            A few favorites from the collection.
                        </p>
                    </div>

                    <RouterLink to="/catalog" class="text-cta">
                        Shop everything
                        <ArrowRight :size="17" />
                    </RouterLink>
                </div>

                <div class="row g-4">
                    <div
                        v-for="p in featured"
                        :key="p.id"
                        class="col-12 col-sm-6 col-lg-3"
                    >
                        <ProductCard :product="p" />
                    </div>
                </div>
            </div>
        </section>

        <section class="section container">
            <div class="story-panel">
                <div>
                    <p class="eyebrow">WHY SHOPNEST</p>

                    <h2>
                        More than things.<br />
                        <em>Tools for becoming.</em>
                    </h2>

                    <p>
                        From guided journals to downloadable frameworks,
                        every piece has one purpose: helping you make
                        small improvements that last.
                    </p>

                    <RouterLink
                        to="/catalog"
                        class="btn btn-outline-primary"
                    >
                        Find your next tool
                        <ArrowRight :size="17" />
                    </RouterLink>
                </div>

                <div class="story-art">
                    <span>✳</span>
                    <span>
                        MAKE<br />
                        IT<br />
                        MATTER.
                    </span>
                </div>
            </div>
        </section>

        <p class="container small muted pb-4">
            *Digital access is simulated in this student demonstration.
        </p>
    </div>
</template>