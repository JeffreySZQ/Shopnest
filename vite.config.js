import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

const mercuryProjectPath = '/cos30043/s105557394/project';

export default defineConfig({
    plugins: [vue()],
    base: `${mercuryProjectPath}/`,
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url))
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
