import axios from 'axios';

/**
 * API klienti. Sanctum token localStorage'da saqlanadi va har so'rovga
 * Authorization header sifatida qo'shiladi.
 */
const api = axios.create({
    baseURL: '/api',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

export const TOKEN_KEY = 'driverhub_token';

export function setToken(token) {
    if (token) {
        localStorage.setItem(TOKEN_KEY, token);
    } else {
        localStorage.removeItem(TOKEN_KEY);
    }
}

export function getToken() {
    return localStorage.getItem(TOKEN_KEY);
}

api.interceptors.request.use((config) => {
    const token = getToken();

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

/**
 * Validatsiya va ruxsat xatolarini bitta ko'rinishga keltiramiz, shunda
 * har bir sahifa xatolarni bir xil ko'rsatadi.
 */
api.interceptors.response.use(
    (response) => response,
    (error) => {
        const { response } = error;

        if (!response) {
            error.friendly = 'Serverga ulanib bo\'lmadi. Internetni tekshiring.';
            return Promise.reject(error);
        }

        if (response.status === 401) {
            setToken(null);
        }

        error.friendly = response.data?.message || 'Kutilmagan xatolik yuz berdi.';
        error.errors = response.data?.errors || {};
        error.code = response.data?.code || null;

        return Promise.reject(error);
    }
);

export default api;
