
import { createVuetify } from 'vuetify';
import { aliases, mdi } from 'vuetify/iconsets/mdi';

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

export default vuetify;
