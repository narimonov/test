<template>
    <div>
        <button v-if="!open" class="support-launcher" @click="openPanel">
            Support
            <span v-if="unread" class="badge bg-danger ms-1">{{ unread }}</span>
        </button>

        <div v-else class="support-panel">
            <div class="support-head">
                <div>
                    <div class="fw-semibold">Support</div>
                    <div class="label-mono" style="color: rgba(255,255,255,.55)">
                        {{ escalated ? 'With an agent' : 'Assistant' }}
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" @click="open = false"></button>
            </div>

            <div ref="body" class="support-body">
                <div v-if="loading" class="text-center text-muted small py-3">Loading…</div>

                <div v-for="message in messages" :key="message.id"
                     class="bubble" :class="bubbleClass(message)">
                    <span v-if="message.author_type !== 'system'" class="bubble-who">
                        {{ message.author_name || authorLabel(message.author_type) }}
                    </span>
                    {{ message.body }}
                </div>

                <div v-if="sending" class="text-muted small">Sending…</div>
            </div>

            <div class="support-foot">
                <form class="d-flex gap-2" @submit.prevent="send">
                    <input v-model="draft" type="text" class="form-control form-control-sm"
                           placeholder="Ask a question…" :disabled="sending">
                    <button class="btn btn-sm btn-primary" :disabled="sending || !draft.trim()">Send</button>
                </form>
                <button v-if="!escalated" class="btn btn-sm btn-link px-0 mt-1" @click="escalate">
                    Talk to a person instead
                </button>
                <div v-else class="label-mono mt-1">An agent will reply here</div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../api';

/**
 * Sits on every page. The assistant answers first; once a human agent takes
 * over, their replies arrive from Telegram and we poll for them.
 */
export default {
    name: 'SupportWidget',

    data() {
        return {
            open: false,
            loading: false,
            sending: false,
            escalated: false,
            messages: [],
            draft: '',
            unread: 0,
            timer: null,
            lastSeenId: null,
        };
    },

    beforeUnmount() {
        this.stopPolling();
    },

    methods: {
        async openPanel() {
            this.open = true;
            this.unread = 0;
            await this.load();
            this.startPolling();
        },

        async load() {
            this.loading = this.messages.length === 0;

            try {
                const { data } = await api.get('/support');
                this.apply(data);
            } catch (e) {
                // A support widget that throws is worse than one that waits.
            } finally {
                this.loading = false;
            }
        },

        apply(data) {
            this.messages = data.messages;
            this.escalated = data.conversation.escalated;
            this.lastSeenId = this.messages.length ? this.messages[this.messages.length - 1].id : null;
            this.scroll();
        },

        async send() {
            const body = this.draft.trim();

            if (!body) return;

            this.sending = true;
            this.draft = '';

            try {
                const { data } = await api.post('/support', { body });
                this.apply(data);
            } catch (e) {
                this.draft = body;
            } finally {
                this.sending = false;
            }
        },

        async escalate() {
            try {
                const { data } = await api.post('/support/escalate');
                this.apply(data);
            } catch (e) {
                // ignore
            }
        },

        /**
         * Agent replies come in through Telegram, so the only way to see them
         * is to ask. Polling stops when the panel is closed.
         */
        startPolling() {
            this.stopPolling();
            this.timer = setInterval(() => this.poll(), 6000);
        },

        stopPolling() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },

        async poll() {
            if (!this.open) {
                this.stopPolling();

                return;
            }

            try {
                const { data } = await api.get('/support/poll');
                const latest = data.messages.length ? data.messages[data.messages.length - 1].id : null;

                if (latest !== this.lastSeenId) {
                    this.apply(data);
                }
            } catch (e) {
                // ignore
            }
        },

        bubbleClass(message) {
            return {
                'is-mine': message.author_type === 'user',
                'is-ai': message.author_type === 'ai',
                'is-agent': message.author_type === 'agent',
                'is-system': message.author_type === 'system',
            };
        },

        authorLabel(type) {
            return { ai: 'Assistant', agent: 'Support agent', system: '' }[type] || 'You';
        },

        scroll() {
            this.$nextTick(() => {
                const body = this.$refs.body;

                if (body) body.scrollTop = body.scrollHeight;
            });
        },
    },
};
</script>
