<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useHiddenServlets } from '@/composables/useHiddenServlets';
import { useDarkMode } from '@/composables/useDarkMode';
import { useToast } from '@/composables/useToast';
import ToastContainer from '@/Components/ToastContainer.vue';
import CommandPalette from '@/Components/CommandPalette.vue';

const page = usePage();
const servlets = computed(() => page.props.servlets);
const user = computed(() => page.props.auth?.user);

const toast = useToast();

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;
        if (flash.success) toast.success(flash.success);
        if (flash.error)   toast.error(flash.error);
        if (flash.warning) toast.warning(flash.warning);
        if (flash.info)    toast.info(flash.info);
    },
    { immediate: true, deep: true },
);

const { showHidden, hiddenCount, filterServlets, toggleHide, isHidden } = useHiddenServlets();
const { isDark, toggle: toggleDark } = useDarkMode();

const visibleServlets = computed(() => filterServlets(servlets.value));

// Command palette
const paletteRef = ref(null);
const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad|iPod/.test(navigator.platform);

// Mobile sidebar
const sidebarOpen = ref(false);

// Close sidebar on navigation
router.on('navigate', () => { sidebarOpen.value = false; });

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="min-h-screen flex bg-gray-100 dark:bg-gray-900">
        <!-- Mobile backdrop -->
        <Transition
            enter-from-class="opacity-0" enter-active-class="transition duration-200"
            leave-to-class="opacity-0" leave-active-class="transition duration-200"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 bg-black/50 z-30 lg:hidden"
                @click="sidebarOpen = false"
            />
        </Transition>

        <!-- Sidebar -->
        <aside
            class="fixed lg:static inset-y-0 left-0 z-40 w-64 bg-gray-800 dark:bg-gray-950 text-gray-200 flex-shrink-0 overflow-y-auto flex flex-col transform transition-transform duration-200 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex items-center justify-between p-4">
                <Link :href="route('servlets.index')" class="text-lg font-bold text-white hover:text-gray-300">
                    Workbench
                </Link>
                <button @click="sidebarOpen = false" class="lg:hidden p-1 text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Search trigger (opens command palette) -->
            <div class="px-3 pb-2">
                <button
                    type="button"
                    @click="paletteRef?.open()"
                    class="group w-full flex items-center gap-2 pl-2.5 pr-2 py-1.5 text-sm bg-gray-700 dark:bg-gray-900 text-gray-400 hover:text-gray-200 border border-gray-600 dark:border-gray-700 rounded hover:border-gray-500"
                >
                    <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <span class="flex-auto text-left">Поиск сервлета…</span>
                    <kbd class="hidden sm:inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-sans bg-gray-800 dark:bg-gray-800 border border-gray-600 dark:border-gray-700 rounded text-gray-400 group-hover:text-gray-200">
                        <span>{{ isMac ? '⌘' : 'Ctrl' }}</span><span>K</span>
                    </kbd>
                </button>
            </div>

            <nav class="flex-1 pb-4">
                <div v-for="(items, category) in visibleServlets" :key="category" class="mb-2">
                    <h3 class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-gray-400">
                        {{ category }}
                    </h3>
                    <div
                        v-for="(servlet, key) in items"
                        :key="key"
                        class="group flex items-center pr-1"
                    >
                        <Link
                            :href="route('servlets.show', key)"
                            class="flex-1 block px-4 py-1.5 text-sm hover:bg-gray-700 truncate"
                            :class="{
                                'bg-gray-700 text-white': $page.url === `/servlet/${key}`,
                                'opacity-50': isHidden(key),
                            }"
                        >
                            {{ servlet.title }}
                        </Link>
                        <button
                            @click.prevent="toggleHide(key)"
                            class="p-1 text-gray-500 hover:text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity shrink-0"
                            :title="isHidden(key) ? 'Показать' : 'Скрыть'"
                        >
                            <svg v-if="!isHidden(key)" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" />
                                <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            </nav>

            <!-- Bottom section -->
            <div class="border-t border-gray-700">
                <button
                    v-if="hiddenCount > 0"
                    @click="showHidden = !showHidden"
                    class="w-full px-4 py-2 text-xs text-gray-400 hover:text-gray-200 text-left flex items-center gap-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path v-if="showHidden" d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                        <path v-if="showHidden" fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                        <path v-if="!showHidden" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" />
                        <path v-if="!showHidden" d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                    </svg>
                    {{ showHidden ? 'Скрыть неактивные' : `Показать скрытые (${hiddenCount})` }}
                </button>

                <div class="px-4 py-3 flex items-center justify-between">
                    <span v-if="user" class="text-sm text-gray-400 truncate">
                        {{ user.name }}
                    </span>
                    <div class="flex items-center gap-1 shrink-0">
                        <!-- Dark mode toggle -->
                        <button
                            @click="toggleDark"
                            class="p-1.5 rounded text-gray-400 hover:text-gray-200 hover:bg-gray-700"
                            :title="isDark ? 'Светлая тема' : 'Тёмная тема'"
                        >
                            <!-- Sun -->
                            <svg v-if="isDark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <!-- Moon -->
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                        </button>
                        <!-- Logout -->
                        <button
                            v-if="user"
                            @click="logout"
                            class="p-1.5 rounded text-gray-400 hover:text-gray-200 hover:bg-gray-700"
                            title="Выйти"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main area -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Mobile header -->
            <header class="lg:hidden flex items-center gap-3 px-4 py-3 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <button @click="sidebarOpen = true" class="p-1 text-gray-600 dark:text-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <Link :href="route('servlets.index')" class="font-bold text-gray-900 dark:text-white">Workbench</Link>
            </header>

            <!-- Main content -->
            <main class="flex-1 p-4 sm:p-6 overflow-y-auto">
                <slot />
            </main>
        </div>

        <ToastContainer />
        <CommandPalette ref="paletteRef" />
    </div>
</template>
