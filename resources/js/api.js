import axios from 'axios';

/**
 * API client. The Sanctum token lives in localStorage and is attached to
 * every request as an Authorization header.
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
 * Normalise validation and permission errors so every page reports them the
 * same way.
 */
api.interceptors.response.use(
    (response) => response,
    (error) => {
        const { response } = error;

        if (!response) {
            error.friendly = 'Could not reach the server. Check your connection.';
            return Promise.reject(error);
        }

        if (response.status === 401) {
            setToken(null);
        }

        error.friendly = response.data?.message || 'Something went wrong.';
        error.errors = response.data?.errors || {};
        error.code = response.data?.code || null;

        return Promise.reject(error);
    }
);

export default api;
