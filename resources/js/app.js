require('./bootstrap');

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';

createApp(App)
    .use(createPinia())
    .use(router)
    .mount('#app');

/*
 * Installable web app. Registered after load so it never competes with the
 * first paint, and skipped on http (other than localhost) where the browser
 * would refuse it anyway.
 */
if ('serviceWorker' in navigator
    && (window.location.protocol === 'https:' || window.location.hostname === 'localhost')) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js').catch(() => {
            // An app that works without offline support is fine.
        });
    });
}
