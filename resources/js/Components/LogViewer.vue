<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch } from 'vue';

const props = defineProps({
    servletKey: String,
    userId: Number,
});

const logs = ref([]);
const autoScroll = ref(true);
const logContainer = ref(null);

// Level filters — all enabled by default except debug
const filters = ref({
    danger: true,
    warning: true,
    normal: true,
    notice: true,
    debug: false,
});

const levelMap = {
    3: 'danger',
    4: 'warning',
    5: 'notice',
    6: 'normal',
    7: 'debug',
};

const levelLabels = {
    danger: 'Ошибки',
    warning: 'Предупр.',
    normal: 'Обычные',
    notice: 'Важные',
    debug: 'Отладка',
};

const levelColors = {
    danger: 'bg-red-600',
    warning: 'bg-yellow-500',
    normal: 'bg-blue-500',
    notice: 'bg-green-600',
    debug: 'bg-gray-400',
};

const textColors = {
    danger: 'text-red-400',
    warning: 'text-yellow-300',
    normal: 'text-gray-200',
    notice: 'text-green-400',
    debug: 'text-gray-500',
};

function addLog(message, level) {
    const levelName = levelMap[level] || 'normal';
    logs.value.push({
        message,
        level: levelName,
        time: new Date().toLocaleTimeString('ru-RU'),
    });

    if (autoScroll.value) {
        nextTick(() => {
            if (logContainer.value) {
                logContainer.value.scrollTop = logContainer.value.scrollHeight;
            }
        });
    }
}

function clearLogs() {
    logs.value = [];
}

function toggleFilter(level) {
    filters.value[level] = !filters.value[level];
}

function filteredLogs() {
    return logs.value.filter(log => filters.value[log.level]);
}

function filterCount(level) {
    return logs.value.filter(log => log.level === level).length;
}

// Listen to WebSocket events
let channel = null;

onMounted(() => {
    const channelName = `servlet.${props.userId}.${props.servletKey}`;
    channel = window.Echo.channel(channelName);

    channel.listen('.log', (e) => {
        addLog(e.message, e.level);
    });

    channel.listen('.started', () => {
        addLog('Сервлет запущен', 5);
    });

    channel.listen('.finished', () => {
        addLog('Обработка завершена', 5);
    });
});

onUnmounted(() => {
    if (channel) {
        window.Echo.leave(`servlet.${props.userId}.${props.servletKey}`);
    }
});

defineExpose({ addLog, clearLogs });
</script>

<template>
    <div>
        <!-- Log output -->
        <div
            ref="logContainer"
            class="bg-gray-900 text-sm font-mono rounded-t-lg overflow-y-auto"
            style="height: 400px;"
        >
            <ul class="divide-y divide-gray-800 p-2">
                <li v-if="filteredLogs().length === 0" class="text-gray-600 py-2 text-center">
                    Нет записей
                </li>
                <li
                    v-for="(log, i) in filteredLogs()"
                    :key="i"
                    class="py-1 px-2 flex gap-2"
                    :class="textColors[log.level]"
                >
                    <span class="text-gray-600 flex-shrink-0">{{ log.time }}</span>
                    <span class="break-all">{{ log.message }}</span>
                </li>
            </ul>
        </div>

        <!-- Filter buttons -->
        <div class="bg-gray-800 rounded-b-lg p-2 flex items-center gap-1 flex-wrap">
            <button
                v-for="(label, level) in levelLabels"
                :key="level"
                @click="toggleFilter(level)"
                class="px-3 py-1 text-xs rounded font-medium transition-opacity text-white"
                :class="[levelColors[level], filters[level] ? 'opacity-100' : 'opacity-30']"
            >
                {{ label }}
                <span v-if="filterCount(level)" class="ml-1 opacity-70">({{ filterCount(level) }})</span>
            </button>

            <div class="flex-1"></div>

            <label class="flex items-center gap-1 text-xs text-gray-400 cursor-pointer">
                <input type="checkbox" v-model="autoScroll" class="rounded text-blue-600 border-gray-600 bg-gray-700" />
                Автопрокрутка
            </label>

            <button
                @click="clearLogs"
                class="px-3 py-1 text-xs rounded bg-gray-700 text-gray-300 hover:bg-gray-600"
            >
                Очистить
            </button>
        </div>
    </div>
</template>
