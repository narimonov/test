<template>
    <div>
        <p class="text-muted small mb-2">
            Drag over anything you do not want carriers to see — the licence number, date of
            birth, home address. Whatever you cover is <strong>destroyed, not blurred</strong>,
            so it cannot be recovered.
        </p>

        <div
            ref="frame"
            class="redaction-frame"
            @mousedown="start"
            @mousemove="move"
            @mouseup="finish"
            @mouseleave="finish"
            @touchstart.prevent="start"
            @touchmove.prevent="move"
            @touchend.prevent="finish"
        >
            <img ref="image" :src="src" alt="Document" draggable="false" @load="$emit('loaded')">

            <div
                v-for="(box, index) in boxes"
                :key="index"
                class="redaction-box"
                :style="styleFor(box)"
            >
                <button type="button" class="redaction-remove" title="Remove"
                        @mousedown.stop @touchstart.stop.prevent @click.stop="remove(index)">&times;</button>
            </div>

            <div v-if="draft" class="redaction-box drafting" :style="styleFor(draft)"></div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-2">
            <span class="text-muted small">{{ boxes.length }} area(s) marked</span>
            <button v-if="boxes.length" type="button" class="btn btn-sm btn-link" @click="clear">
                Clear all
            </button>
        </div>
    </div>
</template>

<script>
/**
 * Rasm ustida berkitiladigan to'rtburchaklarni chizish.
 *
 * Koordinatalar rasm o'lchamiga nisbatan 0..1 oralig'ida saqlanadi, shuning
 * uchun ekran o'lchami yoki zoom natijaga ta'sir qilmaydi.
 */
export default {
    name: 'RedactionCanvas',

    props: {
        src: { type: String, required: true },
        modelValue: { type: Array, default: () => [] },
    },

    emits: ['update:modelValue', 'loaded'],

    data() {
        return {
            draft: null,
            origin: null,
        };
    },

    computed: {
        boxes() {
            return this.modelValue;
        },
    },

    methods: {
        /** Hodisadan rasmga nisbatan 0..1 koordinatani oladi. */
        point(event) {
            const rect = this.$refs.image.getBoundingClientRect();
            const source = event.touches ? event.touches[0] : event;

            return {
                x: Math.min(1, Math.max(0, (source.clientX - rect.left) / rect.width)),
                y: Math.min(1, Math.max(0, (source.clientY - rect.top) / rect.height)),
            };
        },

        start(event) {
            this.origin = this.point(event);
            this.draft = { x: this.origin.x, y: this.origin.y, w: 0, h: 0 };
        },

        move(event) {
            if (!this.origin) return;

            const current = this.point(event);

            this.draft = {
                x: Math.min(this.origin.x, current.x),
                y: Math.min(this.origin.y, current.y),
                w: Math.abs(current.x - this.origin.x),
                h: Math.abs(current.y - this.origin.y),
            };
        },

        finish() {
            if (!this.draft) return;

            // Tasodifiy bosishlarni belgilash deb hisoblamaymiz.
            if (this.draft.w > 0.01 && this.draft.h > 0.01) {
                this.$emit('update:modelValue', [...this.boxes, this.rounded(this.draft)]);
            }

            this.draft = null;
            this.origin = null;
        },

        rounded(box) {
            const round = (value) => Math.round(value * 10000) / 10000;

            return { x: round(box.x), y: round(box.y), w: round(box.w), h: round(box.h) };
        },

        styleFor(box) {
            return {
                left: `${box.x * 100}%`,
                top: `${box.y * 100}%`,
                width: `${box.w * 100}%`,
                height: `${box.h * 100}%`,
            };
        },

        remove(index) {
            this.$emit('update:modelValue', this.boxes.filter((_, i) => i !== index));
        },

        clear() {
            this.$emit('update:modelValue', []);
        },
    },
};
</script>

<style scoped>
.redaction-frame {
    position: relative;
    display: inline-block;
    max-width: 100%;
    line-height: 0;
    cursor: crosshair;
    touch-action: none;
    user-select: none;
    border: 1px solid rgba(15, 23, 42, 0.12);
    border-radius: 0.5rem;
    overflow: hidden;
}

.redaction-frame img {
    max-width: 100%;
    height: auto;
    pointer-events: none;
}

.redaction-box {
    position: absolute;
    background: rgba(15, 23, 42, 0.82);
    border: 1px solid #0f172a;
}

.redaction-box.drafting {
    background: rgba(31, 79, 216, 0.35);
    border-style: dashed;
}

.redaction-remove {
    position: absolute;
    top: -9px;
    right: -9px;
    width: 20px;
    height: 20px;
    line-height: 1;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: #d33b3b;
    color: #fff;
    font-size: 14px;
    cursor: pointer;
}
</style>
