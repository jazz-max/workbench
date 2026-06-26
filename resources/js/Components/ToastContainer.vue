<script setup>
import { useToast } from '@/composables/useToast';

const { toasts, remove } = useToast();

const styles = {
    success: 'bg-green-600 text-white',
    error: 'bg-red-600 text-white',
    warning: 'bg-yellow-500 text-white',
    info: 'bg-gray-700 text-white',
};

const icons = {
    success: 'M5 13l4 4L19 7',
    error: 'M6 18L18 6M6 6l12 12',
    warning: 'M12 9v2m0 4h.01M12 3l9 16H3L12 3z',
    info: 'M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z',
};
</script>

<template>
    <Teleport to="body">
        <div class="fixed top-4 right-4 z-50 flex flex-col gap-2 pointer-events-none">
            <TransitionGroup
                enter-from-class="translate-x-full opacity-0"
                enter-active-class="transition duration-300"
                leave-to-class="translate-x-full opacity-0"
                leave-active-class="transition duration-200"
            >
                <div
                    v-for="t in toasts"
                    :key="t.id"
                    class="pointer-events-auto flex items-center gap-2 px-4 py-3 rounded-lg shadow-lg text-sm max-w-sm"
                    :class="styles[t.type]"
                >
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="icons[t.type]" />
                    </svg>
                    <span class="flex-1">{{ t.message }}</span>
                    <button @click="remove(t.id)" class="shrink-0 opacity-70 hover:opacity-100">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
