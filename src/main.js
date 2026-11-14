
import { createApp } from 'vue';
import { createPinia } from 'pinia';

import App from './App.vue';
import router from './router/index.js';
import vuetify from './plugins/vuetify.js';

// Bootstrap CSS
import 'bootstrap/dist/css/bootstrap.min.css';

// Vuetify CSS
import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';

// ShopNest custom CSS
import './styles.css';

createApp(App)
    .use(createPinia())
    .use(router)
    .use(vuetify)
    .mount('#app');
