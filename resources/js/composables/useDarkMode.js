import { ref, watch } from 'vue';

const STORAGE_KEY = 'workbench_dark_mode';

function getInitial() {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored !== null) return stored === 'true';
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

const isDark = ref(getInitial());

function apply() {
    document.documentElement.classList.toggle('dark', isDark.value);
}

apply();

watch(isDark, () => {
    localStorage.setItem(STORAGE_KEY, isDark.value);
    apply();
});

export function useDarkMode() {
    function toggle() {
        isDark.value = !isDark.value;
    }

    return { isDark, toggle };
}
