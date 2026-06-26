<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useHiddenServlets } from '@/composables/useHiddenServlets';

defineOptions({ layout: AppLayout });

const props = defineProps({
    servletList: Array,
});

const { showHidden, hiddenCount, filterServletList, toggleHide, isHidden } = useHiddenServlets();

const visibleList = computed(() => filterServletList(props.servletList));
</script>

<template>
    <Head title="Сервлеты" />

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
            Сервлеты
        </h1>
        <button
            v-if="hiddenCount > 0"
            @click="showHidden = !showHidden"
            class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex items-center gap-1.5"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                <path v-if="showHidden" d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                <path v-if="showHidden" fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                <path v-if="!showHidden" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" />
                <path v-if="!showHidden" d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
            </svg>
            {{ showHidden ? 'Скрыть неактивные' : `Показать скрытые (${hiddenCount})` }}
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
            v-for="s in visibleList"
            :key="s.key"
            class="group relative bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-md dark:shadow-gray-900/30 transition"
            :class="{ 'opacity-50': isHidden(s.key) }"
        >
            <Link
                :href="route('servlets.show', s.key)"
                class="block p-4"
            >
                <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ s.title }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 truncate">{{ s.url }}</p>
                <span class="inline-block mt-2 text-xs px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                    {{ s.category }}
                </span>
            </Link>
            <button
                @click.prevent="toggleHide(s.key)"
                class="absolute top-2 right-2 p-1.5 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 opacity-0 group-hover:opacity-100 transition-opacity"
                :title="isHidden(s.key) ? 'Показать' : 'Скрыть'"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                    <path v-if="!isHidden(s.key)" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" />
                    <path v-if="!isHidden(s.key)" d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                    <path v-if="isHidden(s.key)" d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                    <path v-if="isHidden(s.key)" fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    </div>
</template>
