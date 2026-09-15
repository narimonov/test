<template>
    <div>
        <h4 class="page-title mb-3">Users</h4>

        <AlertBox :message="error" />
        <AlertBox :message="notice" variant="success" />

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-5">
                        <input v-model="filters.q" type="search" class="form-control"
                               placeholder="Name, email or phone" @keyup.enter="load()">
                    </div>
                    <div class="col-md-3">
                        <select v-model="filters.role" class="form-select" @change="load()">
                            <option :value="null">All roles</option>
                            <option value="driver">Driver</option>
                            <option value="carrier">Carrier</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select v-model="filters.blocked" class="form-select" @change="load()">
                            <option :value="null">All</option>
                            <option :value="true">Blocked only</option>
                            <option :value="false">Active only</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-outline-secondary w-100" @click="load()">Search</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="loading" class="empty-state">Loading…</div>

        <div v-else-if="!users.length" class="empty-state">No users found.</div>

        <div v-else class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr class="text-muted small">
                            <th>User</th>
                            <th>Role</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id">
                            <td>
                                <div class="fw-semibold">{{ user.name }}</div>
                                <div class="text-muted small">{{ user.email }}</div>
                            </td>
                            <td><span class="badge bg-light text-dark">{{ user.role }}</span></td>
                            <td class="text-muted small">
                                <template v-if="user.carrier">
                                    {{ user.carrier.company_name }}
                                    <div>DOT {{ user.carrier.dot_number || '—' }}</div>
                                </template>
                                <template v-else>—</template>
                            </td>
                            <td>
                                <span v-if="user.is_blocked" class="badge bg-danger" :title="user.blocked_reason">
                                    Blocked
                                </span>
                                <span v-else class="badge bg-success">Active</span>
                            </td>
                            <td class="text-end">
                                <button v-if="user.is_blocked" class="btn btn-sm btn-outline-secondary"
                                        @click="unblock(user)">Unblock</button>
                                <button v-else-if="user.role !== 'admin'" class="btn btn-sm btn-outline-danger"
                                        @click="block(user)">Block</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <nav v-if="meta.last_page > 1" class="mt-3">
            <ul class="pagination justify-content-center">
                <li class="page-item" :class="{ disabled: meta.current_page === 1 }">
                    <button class="page-link" @click="load(meta.current_page - 1)">Previous</button>
                </li>
                <li class="page-item disabled">
                    <span class="page-link">{{ meta.current_page }} / {{ meta.last_page }}</span>
                </li>
                <li class="page-item" :class="{ disabled: meta.current_page === meta.last_page }">
                    <button class="page-link" @click="load(meta.current_page + 1)">Next</button>
                </li>
            </ul>
        </nav>
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';

export default {
    name: 'AdminUsersPage',

    components: { AlertBox },

    data() {
        return {
            users: [],
            meta: { current_page: 1, last_page: 1 },
            filters: { q: '', role: null, blocked: null },
            loading: true,
            error: null,
            notice: null,
        };
    },

    created() {
        this.load();
    },

    methods: {
        async load(page = 1) {
            if (page < 1 || page > this.meta.last_page) return;

            this.loading = true;
            this.error = null;

            const params = Object.fromEntries(
                Object.entries({ ...this.filters, page }).filter(([, value]) => value !== null && value !== '')
            );

            try {
                const { data } = await api.get('/admin/users', { params });
                this.users = data.data;
                this.meta = { current_page: data.current_page, last_page: data.last_page };
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        async block(user) {
            const reason = window.prompt(`Why is ${user.name} being blocked?`);

            if (!reason) return;

            try {
                const { data } = await api.post(`/admin/users/${user.id}/block`, { reason });
                this.notice = data.message;
                await this.load(this.meta.current_page);
            } catch (e) {
                this.error = e.friendly;
            }
        },

        async unblock(user) {
            try {
                const { data } = await api.delete(`/admin/users/${user.id}/block`);
                this.notice = data.message;
                await this.load(this.meta.current_page);
            } catch (e) {
                this.error = e.friendly;
            }
        },
    },
};
</script>
