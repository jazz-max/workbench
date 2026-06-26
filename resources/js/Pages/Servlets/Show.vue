<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ServletForm from '@/Components/ServletForm.vue';
import InputFileList from '@/Components/InputFileList.vue';
import ResultFileList from '@/Components/ResultFileList.vue';
import LogViewer from '@/Components/LogViewer.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

defineOptions({ layout: AppLayout });

const props = defineProps({
    servlet: Object,
    inputFiles: Object,
    resultFiles: Object,
    isRunning: Boolean,
    userId: Number,
});

const toast = useToast();
const running = ref(props.isRunning);
const logViewer = ref(null);
const statusText = ref(props.isRunning ? 'Обрабатывается...' : 'Готов');
const showBasket = ref(false);
const basketUrl = ref('');

// Опциональная proxy-фича (корзина источника) — выключена по умолчанию.
// Включается через VITE_ENABLE_PROXY=true вместе с ServletController::proxy()
// и роутом servlet/*/proxy/* (см. README).
const proxyEnabled = import.meta.env.VITE_ENABLE_PROXY === 'true';
const showSupplierBasket = computed(() => proxyEnabled && props.servlet.category === 'uploaders');

let channel = null;

function reload() {
    router.reload({ only: ['inputFiles', 'resultFiles', 'servlet'] });
}

async function startServlet() {
    if (running.value) return;

    try {
        await axios.post(route('servlets.run', props.servlet.key));
        running.value = true;
        statusText.value = 'Обрабатывается...';
        toast.info('Сервлет запущен');
    } catch (err) {
        if (err.response?.status === 409) {
            running.value = true;
            statusText.value = 'Обрабатывается...';
        } else {
            toast.error(err.response?.data?.error || err.message);
        }
    }
}

async function stopServlet() {
    if (!running.value) return;

    try {
        await axios.post(route('servlets.stop', props.servlet.key));
        toast.warning('Остановка сервлета...');
    } catch (err) {
        // finished event will update state
    }
}

function openBasket() {
    const path = props.servlet.basketPath || 'basket.html';
    basketUrl.value = route('servlets.proxy', { name: props.servlet.key, path });
    showBasket.value = true;
}

async function runAction(method) {
    if (running.value) return;

    try {
        await axios.post(route('servlets.action', [props.servlet.key, method]));
        running.value = true;
        statusText.value = 'Обрабатывается...';
        toast.info(`Действие "${method}" запущено`);
    } catch (err) {
        toast.error(err.response?.data?.error || err.message);
    }
}

onMounted(() => {
    const channelName = `servlet.${props.userId}.${props.servlet.key}`;
    channel = window.Echo.channel(channelName);

    channel.listen('.log', (e) => {
        if (e.message && e.message.includes('reloadSettings')) {
            reload();
        }
    });

    channel.listen('.finished', () => {
        running.value = false;
        statusText.value = 'Готов';
        toast.success('Обработка завершена');
        setTimeout(() => reload(), 500);
    });

    channel.listen('.started', () => {
        running.value = true;
        statusText.value = 'Обрабатывается...';
    });
});

onUnmounted(() => {
    if (channel) {
        window.Echo.leave(`servlet.${props.userId}.${props.servlet.key}`);
    }
});
</script>

<template>
    <Head :title="servlet.title" />

    <div class="max-w-3xl">
        <h1 class="text-2xl font-bold mb-2 text-gray-900 dark:text-gray-100">{{ servlet.title }}</h1>

        <p class="mb-4">
            <a
                :href="servlet.url"
                target="_blank"
                rel="noopener"
                class="text-blue-600 dark:text-blue-400 hover:underline text-sm"
            >
                {{ servlet.url }}
            </a>
        </p>

        <div
            v-if="servlet.description"
            class="prose prose-sm dark:prose-invert mb-6 text-gray-600 dark:text-gray-300"
            v-html="servlet.description"
        />

        <!-- Params form -->
        <div v-if="servlet.params && servlet.params.length" class="bg-white dark:bg-gray-800 rounded-lg shadow dark:shadow-gray-900/30 p-6">
            <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Параметры</h2>
            <ServletForm
                :params="servlet.params"
                :servlet-key="servlet.key"
                :running="running"
                @submitted="reload"
                @action="runAction"
            />
        </div>

        <!-- Input files -->
        <div v-if="inputFiles && inputFiles.totalCount > 0" class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow dark:shadow-gray-900/30 p-6">
            <InputFileList :servlet-key="servlet.key" :input-files="inputFiles" @cleared="reload" />
        </div>

        <!-- Execution -->
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow dark:shadow-gray-900/30 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Обработка</h2>
                <code
                    class="text-xs px-2 py-1 rounded"
                    :class="running
                        ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400'
                        : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'"
                >
                    {{ statusText }}
                </code>
            </div>

            <!-- Control buttons -->
            <div class="flex gap-2 mb-4">
                <button
                    v-if="!running"
                    @click="startServlet"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm rounded bg-green-600 text-white hover:bg-green-700 font-medium"
                >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><polygon points="5,3 19,10 5,17" /></svg>
                    Старт
                </button>

                <button
                    v-if="running"
                    @click="stopServlet"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm rounded bg-red-600 text-white hover:bg-red-700 font-medium"
                >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><rect x="4" y="4" width="12" height="12" /></svg>
                    Стоп
                </button>
            </div>

            <!-- Log Viewer -->
            <LogViewer ref="logViewer" :servlet-key="servlet.key" :userId="userId" />
        </div>

        <!-- Result files -->
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow dark:shadow-gray-900/30 p-6">
            <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Результат</h2>
            <ResultFileList :servlet-key="servlet.key" :result-files="resultFiles" @cleared="reload" />
        </div>

        <!-- Supplier basket (опциональная proxy-фича, выключена по умолчанию) -->
        <div v-if="showSupplierBasket" class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow dark:shadow-gray-900/30 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Корзина поставщика</h2>
                <button
                    @click="openBasket"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm rounded bg-blue-600 text-white hover:bg-blue-700 font-medium"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                    </svg>
                    Показать корзину
                </button>
            </div>
            <iframe
                v-if="showBasket"
                :src="basketUrl"
                class="w-full border border-gray-200 dark:border-gray-600 rounded"
                style="min-height: 600px"
            />
        </div>
    </div>
</template>
