import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createVuetify } from 'vuetify';

import { aliases, mdi } from 'vuetify/iconsets/mdi';

import App from './App.vue';
import router from './router/index.js';

// Bootstrap CSS
import 'bootstrap/dist/css/bootstrap.min.css';

// Vuetify CSS
import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';

// ShopNest custom CSS
import './styles.css';

// Configure Vuetify
const vuetify = createVuetify({
    theme: {
        defaultTheme: 'shopNestLight',

        themes: {
            shopNestLight: {
                dark: false,

                colors: {
                    primary: '#234F9C',
                    secondary: '#086F69',
                    background: '#F7F9FC',
                    surface: '#FFFFFF',
                    success: '#16835D',
                    warning: '#B7791F',
                    error: '#B42318',
                    info: '#2563EB'
                }
            }
        }
    },

    icons: {
        defaultSet: 'mdi',

        aliases,

        sets: {
            mdi
        }
    }
});

// Create Vue application
createApp(App)
    .use(createPinia())
    .use(router)
    .use(vuetify)
    .mount('#app');
