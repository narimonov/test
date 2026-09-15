<template>
    <div>
        <div class="mb-4">
            <h4 class="page-title">Messages</h4>
            <p class="page-lede">Talk directly, share documents, keep it in one place.</p>
        </div>

        <AlertBox :message="error" />

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header py-2">Conversations</div>
                    <div v-if="loading" class="empty-state py-4">Loading…</div>
                    <div v-else-if="!conversations.length" class="empty-state py-4">
                        No conversations yet.
                    </div>
                    <div v-else class="list-group list-group-flush">
                        <button v-for="item in conversations" :key="item.id"
                                class="list-group-item list-group-item-action text-start"
                                :class="{ active: selectedId === item.id }"
                                @click="select(item.id)">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="fw-semibold">{{ title(item) }}</span>
                                <span v-if="item.unread" class="badge bg-danger">{{ item.unread }}</span>
                            </div>
                            <div class="label-mono" :class="selectedId === item.id ? 'text-white-50' : ''">
                                {{ item.job ? item.job.title : item.type }}
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div v-if="!selectedId" class="card">
                    <div class="empty-state">Pick a conversation to read it.</div>
                </div>

                <div v-else class="card">
                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                        <span>{{ current ? title(current) : '' }}</span>
                        <span class="label-mono">{{ messages.length }} messages</span>
                    </div>

                    <div ref="scroll" class="chat-scroll">
                        <div v-for="message in messages" :key="message.id"
                             class="bubble" :class="{ 'is-mine': isMine(message) }">
                            <span class="bubble-who">{{ message.author_name }}</span>
                            <template v-if="message.body">{{ message.body }}</template>

                            <div v-if="message.attachments && message.attachments.length" class="mt-2 d-flex flex-wrap gap-2">
                                <button v-for="file in message.attachments" :key="file.id"
                                        class="btn btn-sm"
                                        :class="isMine(message) ? 'btn-outline-light' : 'btn-outline-secondary'"
                                        @click="openAttachment(file)">
                                    {{ file.is_image ? 'Image' : 'PDF' }} · {{ file.original_name }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-white">
                        <form @submit.prevent="send">
                            <div class="d-flex gap-2 mb-2">
                                <input v-model="draft" type="text" class="form-control"
                                       placeholder="Write a message…">
                                <button class="btn btn-primary" :disabled="sending || (!draft.trim() && !files.length)">
                                    {{ sending ? 'Sending…' : 'Send' }}
                                </button>
                            </div>
                            <input ref="files" type="file" class="form-control form-control-sm"
                                   accept="application/pdf,image/jpeg,image/png,image/webp" multiple
                                   @change="pickFiles">
                            <div class="form-text">Attach PDFs or images, up to 5 files.</div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../api';
import AlertBox from '../components/AlertBox.vue';
import { useAuthStore } from '../stores/auth';

export default {
    name: 'ConversationsPage',

    components: { AlertBox },

    setup() {
        return { auth: useAuthStore() };
    },

    data() {
        return {
            conversations: [],
            messages: [],
            selectedId: null,
            draft: '',
            files: [],
            loading: true,
            sending: false,
            error: null,
            timer: null,
        };
    },

    computed: {
        current() {
            return this.conversations.find((item) => item.id === this.selectedId) || null;
        },
    },

    created() {
        this.load();
    },

    beforeUnmount() {
        if (this.timer) clearInterval(this.timer);
    },

    methods: {
        async load() {
            try {
                const { data } = await api.get('/conversations');
                this.conversations = data.conversations;

                const wanted = Number(this.$route.query.id) || null;

                if (wanted && this.conversations.some((item) => item.id === wanted)) {
                    await this.select(wanted);
                } else if (!this.selectedId && this.conversations.length) {
                    await this.select(this.conversations[0].id);
                }
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        async select(id) {
            this.selectedId = id;
            await this.loadMessages();

            // New messages arrive from the other side without a page reload.
            if (this.timer) clearInterval(this.timer);
            this.timer = setInterval(() => this.loadMessages(true), 8000);
        },

        async loadMessages(quiet = false) {
            if (!this.selectedId) return;

            try {
                const { data } = await api.get(`/conversations/${this.selectedId}`);
                this.messages = data.messages;

                if (!quiet) this.scroll();
            } catch (e) {
                if (!quiet) this.error = e.friendly;
            }
        },

        pickFiles(event) {
            this.files = Array.from(event.target.files || []).slice(0, 5);
        },

        async send() {
            this.sending = true;
            this.error = null;

            const payload = new FormData();

            if (this.draft.trim()) payload.append('body', this.draft.trim());
            this.files.forEach((file) => payload.append('attachments[]', file));

            try {
                await api.post(`/conversations/${this.selectedId}/messages`, payload);
                this.draft = '';
                this.files = [];

                if (this.$refs.files) this.$refs.files.value = '';

                await this.loadMessages();
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.sending = false;
            }
        },

        /** Attachments are behind a permission check, so fetch then open. */
        async openAttachment(file) {
            try {
                const response = await api.get(`/attachments/${file.id}`, { responseType: 'blob' });
                const url = URL.createObjectURL(response.data);

                window.open(url, '_blank', 'noopener');
                setTimeout(() => URL.revokeObjectURL(url), 60000);
            } catch (e) {
                this.error = e.friendly;
            }
        },

        isMine(message) {
            return message.user_id === this.auth.user?.id;
        },

        title(item) {
            if (this.auth.isCarrier) {
                return item.driver
                    ? `${item.driver.first_name} ${item.driver.last_name}`.trim()
                    : (item.subject || 'Conversation');
            }

            return item.carrier ? item.carrier.company_name : (item.subject || 'Conversation');
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
