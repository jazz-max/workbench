import { ref, computed } from 'vue';

const STORAGE_KEY = 'workbench_hidden_servlets';
const showHidden = ref(false);

function loadHidden() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
    } catch {
        return [];
    }
}

const hiddenKeys = ref(loadHidden());

function save() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(hiddenKeys.value));
}

export function useHiddenServlets() {
    function toggleHide(key) {
        const idx = hiddenKeys.value.indexOf(key);
        if (idx === -1) {
            hiddenKeys.value.push(key);
        } else {
            hiddenKeys.value.splice(idx, 1);
        }
        save();
    }

    function isHidden(key) {
        return hiddenKeys.value.includes(key);
    }

    function filterServlets(servletsObj) {
        if (showHidden.value) return servletsObj;

        const result = {};
        for (const [category, items] of Object.entries(servletsObj)) {
            const filtered = {};
            for (const [key, servlet] of Object.entries(items)) {
                if (!isHidden(key)) {
                    filtered[key] = servlet;
                }
            }
            if (Object.keys(filtered).length > 0) {
                result[category] = filtered;
            }
        }
        return result;
    }

    function filterServletList(list) {
        if (showHidden.value) return list;
        return list.filter(s => !isHidden(s.key));
    }

    const hiddenCount = computed(() => hiddenKeys.value.length);

    return {
        showHidden,
        hiddenKeys,
        hiddenCount,
        toggleHide,
        isHidden,
        filterServlets,
        filterServletList,
    };
}
