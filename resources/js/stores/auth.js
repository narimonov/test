import { defineStore } from 'pinia';
import api, { getToken, setToken } from '../api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loading: false,
        booted: false,
    }),

    getters: {
        isAuthenticated: (state) => !!state.user,
        isDriver: (state) => state.user?.role === 'driver',
        isCarrier: (state) => state.user?.role === 'carrier',
        isAdmin: (state) => state.user?.role === 'admin',
        isFmcsaVerified: (state) => !!state.user?.fmcsa_verified,
        isVerified: (state) => !!state.user?.is_verified,
        hasSubscription: (state) => !!state.user?.carrier?.has_active_subscription,
        homeRoute() {
            if (!this.user) return { name: 'landing' };
            if (this.isAdmin) return { name: 'admin.overview' };
            if (this.isCarrier) {
                return this.isFmcsaVerified ? { name: 'carrier.dashboard' } : { name: 'carrier.verify' };
            }

            return { name: 'driver.jobs' };
        },
    },

    actions: {
        /** Sahifa yangilanganda tokenni tekshirib, userni tiklaydi. */
        async boot() {
            if (this.booted) return;

            if (getToken()) {
                try {
                    const { data } = await api.get('/auth/me');
                    this.user = data.user;
                } catch (e) {
                    setToken(null);
                    this.user = null;
                }
            }

            this.booted = true;
        },

        async login(credentials) {
            const { data } = await api.post('/auth/login', credentials);
            setToken(data.token);
            this.user = data.user;
            return data;
        },

        async register(payload) {
            const { data } = await api.post('/auth/register', payload);
            setToken(data.token);
            this.user = data.user;
            return data;
        },

        async refresh() {
            const { data } = await api.get('/auth/me');
            this.user = data.user;
        },

        async logout() {
            try {
                await api.post('/auth/logout');
            } finally {
                setToken(null);
                this.user = null;
            }
        },
    },
});
