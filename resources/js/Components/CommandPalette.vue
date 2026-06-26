<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useHiddenServlets } from '@/composables/useHiddenServlets';

const page = usePage();
const { isHidden } = useHiddenServlets();

const open = ref(false);
const query = ref('');
const activeIndex = ref(0);
const inputRef = ref(null);
const listRef = ref(null);

const servlets = computed(() => page.props.servlets || {});

const ICON_PATHS = {
    folder: [
        'M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z',
    ],
    globe: [
        'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418',
    ],
    document: [
        'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
    ],
    upload: [
        'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 7.5m0 0L7.5 12m4.5-4.5v12',
    ],
    database: [
        'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125',
    ],
};

const CATEGORY_ICON = {
    parsers: 'globe',
    invoices: 'document',
    uploaders: 'upload',
    '1с': 'database',
};

function iconPathsFor(category) {
    const key = (category || '').toLowerCase();
    return ICON_PATHS[CATEGORY_ICON[key]] || ICON_PATHS.folder;
}

const allItems = computed(() => {
    const result = [];
    for (const [category, items] of Object.entries(servlets.value)) {
        for (const [key, s] of Object.entries(items)) {
            if (isHidden(key)) continue;
            result.push({
                key,
                title: s.title || key,
                url: s.url || '',
                category,
            });
        }
    }
    return result;
});

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return allItems.value;
    return allItems.value.filter((i) =>
        i.title.toLowerCase().includes(q) ||
        i.key.toLowerCase().includes(q) ||
        (i.url || '').toLowerCase().includes(q) ||
        i.category.toLowerCase().includes(q),
    );
});

const grouped = computed(() => {
    const cats = {};
    const order = [];
    let idx = 0;
    for (const item of filtered.value) {
        if (!cats[item.category]) {
            cats[item.category] = { category: item.category, items: [] };
            order.push(cats[item.category]);
        }
        cats[item.category].items.push({ ...item, _idx: idx++ });
    }
    return order;
});

watch(query, () => {
    activeIndex.value = 0;
});

watch(filtered, (list) => {
    if (activeIndex.value >= list.length) {
        activeIndex.value = Math.max(0, list.length - 1);
    }
});

function openPalette() {
    open.value = true;
    query.value = '';
    activeIndex.value = 0;
    nextTick(() => inputRef.value?.focus());
}

function closePalette() {
    open.value = false;
}

function selectItem(item) {
    closePalette();
    router.visit(route('servlets.show', item.key));
}

function scrollActiveIntoView() {
    nextTick(() => {
        const el = listRef.value?.querySelector('[data-active="true"]');
        if (el && typeof el.scrollIntoView === 'function') {
            el.scrollIntoView({ block: 'nearest' });
        }
    });
}

function onKeyDown(e) {
    const mod = e.metaKey || e.ctrlKey;
    if (mod && (e.key === 'k' || e.key === 'K' || e.key === 'л' || e.key === 'Л')) {
        e.preventDefault();
        if (open.value) closePalette();
        else openPalette();
        return;
    }
    if (!open.value) return;

    if (e.key === 'Escape') {
        e.preventDefault();
        closePalette();
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!filtered.value.length) return;
        activeIndex.value = (activeIndex.value + 1) % filtered.value.length;
        scrollActiveIntoView();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!filtered.value.length) return;
        activeIndex.value = (activeIndex.value - 1 + filtered.value.length) % filtered.value.length;
        scrollActiveIntoView();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const item = filtered.value[activeIndex.value];
        if (item) selectItem(item);
    }
}

onMounted(() => window.addEventListener('keydown', onKeyDown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeyDown));

defineExpose({ open: openPalette, close: closePalette });
</script>

<template>
    <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="open" class="fixed inset-0 z-50" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500/25 dark:bg-gray-900/50" @click="closePalette" />

            <div class="fixed inset-0 w-screen overflow-y-auto p-4 sm:p-6 md:p-20" @click.self="closePalette">
                <div class="mx-auto max-w-2xl transform divide-y divide-gray-500/10 overflow-hidden rounded-xl bg-white/80 shadow-2xl outline outline-1 outline-black/5 backdrop-blur backdrop-filter dark:divide-white/10 dark:bg-gray-900/80 dark:-outline-offset-1 dark:outline-white/10">
                    <div class="grid grid-cols-1">
                        <input
                            ref="inputRef"
                            v-model="query"
                            type="text"
                            class="col-start-1 row-start-1 h-12 w-full bg-transparent pl-11 pr-4 text-base text-gray-900 outline-none placeholder:text-gray-500 sm:text-sm dark:text-white dark:placeholder:text-gray-400"
                            placeholder="Поиск сервлета..."
                            autocomplete="off"
                            spellcheck="false"
                        />
                        <svg
                            class="pointer-events-none col-start-1 row-start-1 ml-4 size-5 self-center text-gray-900/40 dark:text-gray-500"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                        </svg>
                    </div>

                    <ul
                        v-if="filtered.length > 0"
                        ref="listRef"
                        class="max-h-80 scroll-py-2 divide-y divide-gray-500/10 overflow-y-auto dark:divide-white/5"
                    >
                        <li v-for="group in grouped" :key="group.category" class="p-2">
                            <h2 class="mb-2 mt-2 px-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                {{ group.category }}
                            </h2>
                            <ul class="text-sm text-gray-700 dark:text-gray-300">
                                <li
                                    v-for="item in group.items"
                                    :key="item.key"
                                    :data-active="item._idx === activeIndex"
                                    :class="['flex cursor-pointer select-none items-center rounded-md px-3 py-2', item._idx === activeIndex && 'bg-gray-900/5 text-gray-900 outline-none dark:bg-white/5 dark:text-white']"
                                    @click="selectItem(item)"
                                    @mouseenter="activeIndex = item._idx"
                                >
                                    <svg
                                        :class="['size-6 flex-none', item._idx === activeIndex ? 'text-gray-900 dark:text-white' : 'text-gray-900/40 dark:text-gray-500']"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            v-for="d in iconPathsFor(item.category)"
                                            :key="d"
                                            :d="d"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                    <span class="ml-3 flex-auto truncate">{{ item.title }}</span>
                                    <span
                                        v-if="item._idx === activeIndex"
                                        class="ml-3 flex-none text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        Перейти ↵
                                    </span>
                                </li>
                            </ul>
                        </li>
                    </ul>

                    <div
                        v-if="query !== '' && filtered.length === 0"
                        class="px-6 py-14 text-center sm:px-14"
                    >
                        <svg
                            class="mx-auto size-6 text-gray-900/40 dark:text-gray-500"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                        </svg>
                        <p class="mt-4 text-sm text-gray-900 dark:text-gray-300">
                            Ничего не нашлось. Попробуйте другой запрос.
                        </p>
                    </div>

                    <div class="flex items-center justify-between px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ filtered.length }} {{ filtered.length === 1 ? 'сервлет' : 'сервлетов' }}</span>
                        <span class="flex items-center gap-3">
                            <span class="flex items-center gap-1"><kbd class="rounded bg-gray-200/60 px-1.5 py-0.5 font-sans dark:bg-white/10">↑</kbd><kbd class="rounded bg-gray-200/60 px-1.5 py-0.5 font-sans dark:bg-white/10">↓</kbd> навигация</span>
                            <span class="flex items-center gap-1"><kbd class="rounded bg-gray-200/60 px-1.5 py-0.5 font-sans dark:bg-white/10">↵</kbd> открыть</span>
                            <span class="flex items-center gap-1"><kbd class="rounded bg-gray-200/60 px-1.5 py-0.5 font-sans dark:bg-white/10">esc</kbd> закрыть</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>
