<template>
    <footer class="site-footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-6">
                    <RouterLink
                        class="footer-logo"
                        to="/"
                    >
                        ◈ ShopNest.
                    </RouterLink>

                    <p class="footer-intro">
                        Thoughtful tools for meaningful growth.
                        Find inspiration in every page, prompt, and possibility.
                    </p>
                </div>

                <div class="col-6 col-md-3">
                    <h2>Explore</h2>

                    <RouterLink to="/">
                        Home
                    </RouterLink>

                    <RouterLink to="/catalog">
                        Shop all products
                    </RouterLink>

                    <RouterLink to="/cart">
                        Your cart
                    </RouterLink>
                </div>

                <div class="col-6 col-md-3">
                    <h2>Account</h2>

                    <RouterLink to="/login">
                        Sign in
                    </RouterLink>

                    <RouterLink to="/signup">
                        Create account
                    </RouterLink>

                    <RouterLink to="/orders">
                        Your orders
                    </RouterLink>
                </div>
            </div>

            <div class="footer-bottom">
                © 2026 ShopNest · Student project / demonstration store · Prices in MYR
            </div>
        </div>
    </footer>
</template>