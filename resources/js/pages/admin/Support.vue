<template>
    <div>
        <div class="mb-4">
            <h4 class="page-title">Support queue</h4>
            <p class="page-lede">
                Conversations the assistant handed over. These also go to Telegram when a bot is
                configured, but they land here either way — nothing is lost.
            </p>
        </div>

        <AlertBox :message="error" />

        <div class="btn-group mb-3">
            <button v-for="option in filters" :key="option.value" class="btn btn-sm"
                    :class="filter === option.value ? 'btn-primary' : 'btn-outline-primary'"
                    @click="filter = option.value; load()">
                {{ option.label }}
            </button>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header py-2">{{ conversations.length }} conversations</div>

                    <div v-if="loading" class="empty-state py-4">Loading…</div>
                    <div v-else-if="!conversations.length" class="empty-state py-4">Nothing here.</div>

                    <div v-else class="list-group list-group-flush">
                        <button v-for="item in conversations" :key="item.id"
                                class="list-group-item list-group-item-action text-start"
                                :class="{ active: selectedId === item.id }"
                                @click="open(item.id)">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="fw-semibold">{{ item.user ? item.user.name : 'Unknown' }}</span>
                                <span v-if="item.waiting" class="badge bg-danger">Waiting</span>
                            </div>
                            <div class="label-mono" :class="selectedId === item.id ? 'text-white-50' : ''">
                                {{ item.user ? item.user.role : '—' }} · {{ item.messages_count }} messages
                            </div>
                            <div class="small text-truncate" :class="selectedId === item.id ? 'text-white-50' : 'text-muted'">
                                {{ item.last_message }}
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div v-if="!selectedId" class="card">
                    <div class="empty-state">Pick a conversation.</div>
                </div>

                <div v-else class="card">
                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                        <span>{{ current && current.user ? current.user.email : 'Conversation' }}</span>
                        <span v-if="current && current.escalated" class="badge bg-warning text-dark">Escalated</span>
                    </div>

                    <div ref="scroll" class="chat-scroll">
                        <div v-for="message in messages" :key="message.id"
                             class="bubble" :class="bubbleClass(message)">
                            <span v-if="message.author_type !== 'system'" class="bubble-who">
                                {{ message.author_name }}
                            </span>
                            {{ message.body }}
                        </div>
                    </div>

                    <div class="card-footer bg-white">
                        <form class="d-flex gap-2" @submit.prevent="send">
                            <input v-model="draft" type="text" class="form-control"
                                   placeholder="Reply as support…">
                            <button class="btn btn-primary" :disabled="sending || !draft.trim()">
                                {{ sending ? 'Sending…' : 'Reply' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';

export default {
    name: 'AdminSupportPage',

    components: { AlertBox },

    data() {
        return {
            conversations: [],
            messages: [],
            current: null,
            selectedId: null,
            draft: '',
            filter: 'escalated',
            filters: [
                { value: 'escalated', label: 'Needs a person' },
                { value: 'bot', label: 'Assistant only' },
                { value: 'all', label: 'All' },
            ],
            loading: true,
            sending: false,
            error: null,
        };
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            this.loading = true;
            this.error = null;

            try {
                const { data } = await api.get('/admin/support', { params: { status: this.filter } });
                this.conversations = data.conversations;
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        async open(id) {
            this.selectedId = id;

            try {
                const { data } = await api.get(`/admin/support/${id}`);
                this.current = data.conversation;
                this.messages = data.messages;
                this.scroll();
            } catch (e) {
                this.error = e.friendly;
            }
        },

        async send() {
            this.sending = true;
            this.error = null;

            try {
                const { data } = await api.post(`/admin/support/${this.selectedId}/reply`, {
                    body: this.draft.trim(),
                });

                this.messages = data.messages;
                this.draft = '';
                this.scroll();
                await this.load();
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.sending = false;
            }
        },

        bubbleClass(message) {
            return {
                // The user's own words sit on the left; ours on the right.
                'is-right': ['agent', 'ai'].includes(message.author_type),
                'is-ai': message.author_type === 'ai',
                'is-agent': message.author_type === 'agent',
                'is-system': message.author_type === 'system',
            };
        },

        scroll() {
            this.$nextTick(() => {
                const el = this.$refs.scroll;

                if (el) el.scrollTop = el.scrollHeight;
            });
        },
    },
};
</script>
