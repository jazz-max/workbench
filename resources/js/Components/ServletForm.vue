<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    params: Array,
    servletKey: String,
    running: Boolean,
});

const emit = defineEmits(['submitted', 'action']);
const toast = useToast();

const formData = ref({});
const fileInputs = ref({});
const submitting = ref(false);

// Initialize form data from params
for (const p of props.params) {
    if (p.type === 'file') {
        // handled separately via fileInputs
    } else if (p.type === 'checkbox' || p.type === 'checkboxCatalog') {
        formData.value[p.name] = p.value === 'on' || p.checked === 'checked';
    } else if (p.type !== 'button') {
        formData.value[p.name] = p.value || '';
    }
}

function onFileChange(event, name) {
    fileInputs.value[name] = event.target.files;
}

async function submitForm() {
    submitting.value = true;

    const data = new FormData();

    // Add params
    for (const [key, value] of Object.entries(formData.value)) {
        if (typeof value === 'boolean') {
            data.append(key, value ? 'on' : '');
        } else {
            data.append(key, value);
        }
    }

    // Add files
    for (const [name, files] of Object.entries(fileInputs.value)) {
        if (files && files.length) {
            for (const file of files) {
                data.append('files[]', file);
            }
        }
    }

    try {
        await axios.post(route('servlets.upload', props.servletKey), data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        toast.success('Сохранено');
        emit('submitted');
    } catch (err) {
        toast.error(err.response?.data?.message || err.message);
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <form @submit.prevent="submitForm" class="space-y-4">
        <template v-for="p in params" :key="p.name">
            <!-- hidden -->
            <input
                v-if="p.type === 'hidden'"
                type="hidden"
                :name="p.name"
                v-model="formData[p.name]"
            />

            <!-- text / password -->
            <div v-else-if="p.type === 'text' || p.type === 'password'" class="space-y-1">
                <label :for="p.name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ p.label }}
                </label>
                <input
                    :id="p.name"
                    :type="p.type"
                    :name="p.name"
                    v-model="formData[p.name]"
                    :required="p.required === 'true'"
                    class="w-full rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-2"
                />
            </div>

            <!-- textarea -->
            <div v-else-if="p.type === 'textarea'" class="space-y-1">
                <label :for="p.name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ p.label }}
                </label>
                <textarea
                    :id="p.name"
                    :name="p.name"
                    v-model="formData[p.name]"
                    rows="4"
                    class="w-full rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-2"
                />
            </div>

            <!-- checkbox -->
            <div v-else-if="p.type === 'checkbox'" class="flex items-center gap-2">
                <input
                    :id="p.name"
                    type="checkbox"
                    :name="p.name"
                    v-model="formData[p.name]"
                    class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 dark:bg-gray-700"
                />
                <label :for="p.name" class="text-sm text-gray-700 dark:text-gray-300">
                    {{ p.clicklabel || p.label }}
                </label>
            </div>

            <!-- checkboxCatalog (with separator before first) -->
            <template v-else-if="p.type === 'checkboxCatalog'">
                <div
                    v-if="params.findIndex(x => x.type === 'checkboxCatalog') === params.indexOf(p)"
                    class="border-t border-gray-200 dark:border-gray-700 pt-3 mt-2"
                >
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Каталоги</h3>
                </div>
                <div class="flex items-center gap-2 ml-2">
                    <input
                        :id="p.name"
                        type="checkbox"
                        :name="p.name"
                        v-model="formData[p.name]"
                        class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 dark:bg-gray-700"
                    />
                    <label :for="p.name" class="text-sm text-gray-700 dark:text-gray-300">
                        {{ p.clicklabel || p.label }}
                    </label>
                </div>
            </template>

            <!-- file -->
            <div v-else-if="p.type === 'file'" class="space-y-1">
                <label :for="p.name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ p.label }}
                </label>
                <input
                    :id="p.name"
                    type="file"
                    :name="p.name"
                    multiple
                    @change="onFileChange($event, p.name)"
                    class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 dark:file:bg-blue-900/30 file:text-blue-700 dark:file:text-blue-300 hover:file:bg-blue-100 dark:hover:file:bg-blue-900/50"
                />
            </div>

            <!-- button (action) -->
            <div v-else-if="p.type === 'button'">
                <button
                    type="button"
                    :disabled="running"
                    @click="emit('action', p.name)"
                    class="px-4 py-2 text-sm rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ p.clicklabel || p.label }}
                </button>
            </div>
        </template>

        <!-- Submit -->
        <div class="pt-4 border-t border-gray-200 dark:border-gray-700 flex items-center gap-3">
            <button
                type="submit"
                :disabled="submitting"
                class="px-6 py-2 rounded bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
            >
                {{ submitting ? 'Сохранение...' : 'Сохранить' }}
            </button>
        </div>
    </form>
</template>
