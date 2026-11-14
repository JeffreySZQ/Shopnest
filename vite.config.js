
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';
import { fileURLToPath, URL } from 'node:url';

// Mercury deployment directory.
const mercuryProjectPath = '/cos30043/s105557394/project';

export default defineConfig({

    // Production deployment base path.
    base: `${mercuryProjectPath}/`,

    // Vue and Vuetify plugins.
    plugins: [
        vue(),

        vuetify({
            autoImport: true
        })
    ],

    // Source directory alias.
    resolve: {
        alias: {
            '@': fileURLToPath(
                new URL('./src', import.meta.url)
            )
        }
    },

    // Development API proxy.
    server: {
        proxy: {
            '/api': {
                target: 'https://mercury.swin.edu.au',

                changeOrigin: true,
                secure: true,

                cookieDomainRewrite: '',
                cookiePathRewrite: '/',

                rewrite: path => path.replace(
                    /^\/api/,
                    `${mercuryProjectPath}/resources/shopnest-api.php`
                )
            }
        }
    }

});
