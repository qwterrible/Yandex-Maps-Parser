import { createRouter, createWebHistory } from 'vue-router';

import Login from './pages/Login.vue';
import Settings from './pages/Settings.vue';
import Reviews from './pages/Reviews.vue';

const routes = [
    { path: '/',         redirect: '/settings' },
    { path: '/login',    component: Login,    meta: { guest: true } },
    { path: '/settings', component: Settings, meta: { auth: true } },
    { path: '/reviews',  component: Reviews,  meta: { auth: true } },
];

const baseUrl = document.querySelector('base')?.getAttribute('href') || '/';

const router = createRouter({
    history: createWebHistory(baseUrl),
    routes,
});
router.beforeEach(async (to) => {
    if (to.meta.auth || to.meta.guest) {
        try {
            await window.axios.get('/me');
        } catch (e) {
            // не залогинен
            if (to.meta.auth) return '/login';
            return true;
        }
        // залогинен
        if (to.meta.guest) return '/settings';
    }
    return true;
});

export default router;