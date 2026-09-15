<template>
    <span class="badge" :class="`bg-${entry.variant}`">{{ entry.label }}</span>
</template>

<script>
export default {
    name: 'StatusBadge',

    props: {
        status: { type: String, default: null },
        options: { type: Array, required: true },
    },

    computed: {
        entry() {
            return this.options.find((item) => item.value === this.status)
                || { label: this.status || '—', variant: 'secondary' };
        },
    },
};
</script>
