import { ref } from 'vue';

const toasts = ref([]);
let nextId = 0;

export function useToast() {
    function add(message, type = 'info', duration = 3000) {
        const id = nextId++;
        toasts.value.push({ id, message, type });
        if (duration > 0) {
            setTimeout(() => remove(id), duration);
        }
    }

    function remove(id) {
        const idx = toasts.value.findIndex(t => t.id === id);
        if (idx !== -1) toasts.value.splice(idx, 1);
    }

    function success(message, duration) { add(message, 'success', duration); }
    function error(message, duration) { add(message, 'error', duration ?? 5000); }
    function warning(message, duration) { add(message, 'warning', duration); }
    function info(message, duration) { add(message, 'info', duration); }

    return { toasts, add, remove, success, error, warning, info };
}
