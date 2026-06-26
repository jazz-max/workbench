<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    servletKey: String,
    inputFiles: Object,
});

const emit = defineEmits(['cleared']);
const toast = useToast();
const clearing = ref(false);
const subdir = ref('');
const subdirFiles = ref(null);
const loadingSubdir = ref(false);

const displayFiles = computed(() => {
    if (subdir.value && subdirFiles.value) {
        return subdirFiles.value;
    }
    return props.inputFiles;
});

const hasFiles = computed(() => {
    return displayFiles.value && displayFiles.value.totalCount > 0;
});

async function openSubdir(dirname) {
    loadingSubdir.value = true;
    try {
        const { data } = await axios.get(route('servlets.inputFiles', props.servletKey), {
            params: { subdir: dirname },
        });
        subdir.value = dirname;
        subdirFiles.value = data;
    } catch (err) {
        toast.error('Ошибка загрузки: ' + (err.response?.data?.message || err.message));
    } finally {
        loadingSubdir.value = false;
    }
}

function goBack() {
    subdir.value = '';
    subdirFiles.value = null;
}

async function clearInputFiles() {
    if (!confirm('Очистить все входные файлы?')) return;
    clearing.value = true;
    try {
        await axios.delete(route('servlets.clearIn', props.servletKey));
        toast.success('Входные файлы очищены');
        subdir.value = '';
        subdirFiles.value = null;
        emit('cleared');
    } catch (err) {
        toast.error('Ошибка очистки: ' + (err.response?.data?.message || err.message));
    } finally {
        clearing.value = false;
    }
}
</script>

<template>
    <div v-if="hasFiles || subdir">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                <template v-if="subdir">
                    <button
                        @click="goBack"
                        class="text-blue-600 dark:text-blue-400 hover:underline mr-1"
                    >Файлы</button>
                    <span class="text-gray-400 dark:text-gray-500">/</span>
                    <span class="ml-1">{{ subdir }}</span>
                </template>
                <template v-else>
                    Загруженные файлы
                </template>
                <span class="text-gray-400 dark:text-gray-500 font-normal ml-1">
                    {{ displayFiles.totalCount }} файл(ов)<template v-if="!subdir">, {{ displayFiles.totalSize }}</template>
                </span>
            </h3>
        </div>

        <div class="bg-gray-50 dark:bg-gray-900/50 rounded border border-gray-200 dark:border-gray-700 max-h-60 overflow-auto">
            <div v-if="loadingSubdir" class="px-3 py-4 text-sm text-gray-400 text-center">Загрузка...</div>
            <table v-else class="w-full text-sm">
                <tbody>
                    <tr
                        v-for="file in displayFiles.files"
                        :key="file.filename"
                        class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50"
                        :class="{ 'cursor-pointer': file.isDir }"
                        @click="file.isDir ? openSubdir(file.filename) : null"
                    >
                        <td class="px-3 py-1.5 w-8 text-gray-400">
                            <!-- folder icon -->
                            <svg v-if="file.icon === 'folder'" class="w-4 h-4 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            <!-- image icon -->
                            <svg v-else-if="file.icon === 'image'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <!-- generic file icon -->
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        </td>
                        <td class="px-2 py-1.5 text-gray-900 dark:text-gray-200">
                            <span :class="{ 'text-blue-600 dark:text-blue-400': file.isDir }">{{ file.filename }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-right text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ file.sizeFormatted }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <button
            v-if="!subdir"
            @click="clearInputFiles"
            :disabled="clearing"
            class="mt-2 inline-flex items-center gap-1 px-3 py-1.5 text-xs rounded border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-50"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Очистить входные файлы
        </button>
    </div>
</template>
