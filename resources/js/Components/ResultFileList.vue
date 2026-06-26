<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    servletKey: String,
    resultFiles: Object,
});

const emit = defineEmits(['cleared']);
const toast = useToast();
const clearing = ref(false);

function downloadFile(filename) {
    window.location.href = route('servlets.download', { name: props.servletKey, file: filename });
}

function downloadZip() {
    window.location.href = route('servlets.downloadZip', props.servletKey);
    toast.info('Подготовка архива...');
}

async function clearOutputFiles() {
    if (!confirm('Очистить все выходные файлы?')) return;
    clearing.value = true;
    try {
        await axios.delete(route('servlets.clearOut', props.servletKey));
        toast.success('Выходные файлы очищены');
        emit('cleared');
    } catch (err) {
        toast.error('Ошибка очистки: ' + (err.response?.data?.message || err.message));
    } finally {
        clearing.value = false;
    }
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Выходные файлы</h3>
            <button
                v-if="resultFiles?.files?.length"
                @click="downloadZip"
                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs rounded bg-blue-600 text-white hover:bg-blue-700"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Скачать ZIP
            </button>
        </div>

        <template v-if="resultFiles?.files?.length">
            <div class="bg-gray-50 dark:bg-gray-900/50 rounded border border-gray-200 dark:border-gray-700 max-h-96 overflow-auto">
                <table class="w-full text-sm">
                    <tbody>
                        <tr
                            v-for="file in resultFiles.files"
                            :key="file.filename"
                            class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50"
                        >
                            <td class="px-3 py-1.5 w-8 text-gray-400">
                                <svg v-if="file.icon === 'image'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <svg v-else-if="file.icon === 'archive'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                <svg v-else-if="file.icon === 'folder'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            </td>
                            <td class="px-2 py-1.5 text-gray-900 dark:text-gray-200">{{ file.filename }}</td>
                            <td class="px-3 py-1.5 text-right text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ file.sizeFormatted }}</td>
                            <td class="px-3 py-1.5 w-8">
                                <button
                                    v-if="!file.isDir"
                                    @click="downloadFile(file.filename)"
                                    class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400"
                                    title="Скачать"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <button
                @click="clearOutputFiles"
                :disabled="clearing"
                class="mt-2 inline-flex items-center gap-1 px-3 py-1.5 text-xs rounded border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-50"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Очистить
            </button>
        </template>

        <p v-else class="text-sm text-gray-400 dark:text-gray-500">Нет выходных файлов</p>
    </div>
</template>
