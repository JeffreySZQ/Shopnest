import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';
import { fileURLToPath, URL } from 'node:url';

const mercuryProjectPath = '/cos30043/s105557394/project';

export default defineConfig({
<<<<<<< HEAD
    plugins: [
        vue(),
        vuetify({
            autoImport: true
        })
    ],

=======
    plugins: [vue()],
    base: `${mercuryProjectPath}/`,
>>>>>>> 7971e5f794b1c47b2fa3826b3956cecdb7ea3de9
    resolve: {
        alias: {
            '@': fileURLToPath(
                new URL('./src', import.meta.url)
            )
        }
    },
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
