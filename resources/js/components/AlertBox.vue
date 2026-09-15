<template>
    <div v-if="message" class="alert" :class="`alert-${variant}`" role="alert">
        {{ message }}
        <ul v-if="errorList.length" class="mb-0 mt-2 small">
            <li v-for="error in errorList" :key="error">{{ error }}</li>
        </ul>
    </div>
</template>

<script>
export default {
    name: 'AlertBox',

    props: {
        message: { type: String, default: null },
        errors: { type: Object, default: () => ({}) },
        variant: { type: String, default: 'danger' },
    },

    computed: {
        errorList() {
            return Object.values(this.errors || {}).flat();
        },
    },
};
</script>
