/**
 * Shared drag-and-drop reorder for List and Kanban views.
 * Expects SortableJS and window.ProjectReorder = { projectId, csrfToken, canUpdate }.
 * Call initEpicSortable / initTaskSortables when canUpdate is true.
 */
import Sortable from 'sortablejs';

function showToast(message, isError = false) {
    const existing = document.getElementById('reorder-toast');
    if (existing) existing.remove();
    const el = document.createElement('div');
    el.id = 'reorder-toast';
    el.setAttribute('role', 'alert');
    el.className = 'fixed bottom-4 right-4 px-4 py-3 rounded-lg shadow-lg z-50 text-sm ' +
        (isError ? 'bg-red-600 text-white' : 'bg-green-600 text-white');
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}

let saveTaskOrderDebounce = null;

function buildColumnsPayload() {
    const columns = [];
    document.querySelectorAll('.task-sortable').forEach((container) => {
        const epicId = container.dataset.epicId || container.getAttribute('data-epic-id');
        if (!epicId) return;
        const taskIds = [];
        container.querySelectorAll('.task-row').forEach((row) => {
            const id = row.dataset.taskId || row.getAttribute('data-task-id');
            if (id) taskIds.push(parseInt(id, 10));
        });
        columns.push({ epic_id: parseInt(epicId, 10), task_ids: taskIds });
    });
    return columns;
}

function saveTaskOrder(projectId, csrfToken) {
    const columns = buildColumnsPayload();
    const body = JSON.stringify({ columns });
    fetch(`/projects/${projectId}/tasks/reorder`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body,
    })
        .then((res) => {
            if (res.ok) {
                showToast('Order saved.');
                return;
            }
            return res.json().then((data) => {
                const msg = data.message || data.errors ? Object.values(data.errors).flat().join(' ') : 'Failed to save order.';
                showToast(msg, true);
            });
        })
        .catch(() => showToast('Failed to save order.', true));
}

function debouncedSaveTaskOrder(projectId, csrfToken, delay = 200) {
    if (saveTaskOrderDebounce) clearTimeout(saveTaskOrderDebounce);
    saveTaskOrderDebounce = setTimeout(() => {
        saveTaskOrderDebounce = null;
        saveTaskOrder(projectId, csrfToken);
    }, delay);
}

function initEpicSortable(projectId, csrfToken) {
    const listContainer = document.getElementById('epic-sortable');
    const kanbanContainer = document.getElementById('kanban-columns');
    const container = listContainer || kanbanContainer;
    if (!container || !projectId || !csrfToken) return;

    const isKanban = !!kanbanContainer;
    const itemSelector = isKanban ? '.kanban-column' : '.epic-row';
    const handleSelector = isKanban ? '.kanban-column-handle' : '.epic-handle';

    Sortable.create(container, {
        draggable: isKanban ? '.kanban-column' : undefined,
        handle: handleSelector,
        animation: 150,
        onEnd() {
            const epicIds = Array.from(container.querySelectorAll(itemSelector))
                .map((row) => row.dataset.epicId || row.getAttribute('data-epic-id'))
                .filter(Boolean)
                .map((id) => parseInt(id, 10));
            if (epicIds.length === 0) return;

            fetch(`/projects/${projectId}/epics/reorder`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ epic_ids: epicIds }),
            })
                .then((res) => {
                    if (res.ok) {
                        showToast('Order saved.');
                        return;
                    }
                    return res.json().then((data) => {
                        const msg = data.message || (data.errors && data.errors.epic_ids ? data.errors.epic_ids.join(' ') : 'Failed to save order.');
                        showToast(msg, true);
                    });
                })
                .catch(() => showToast('Failed to save order.', true));
        },
    });
}

function initTaskSortables(projectId, csrfToken, debounceMs = 200) {
    if (!projectId || !csrfToken) return;

    const onEnd = () => debouncedSaveTaskOrder(projectId, csrfToken, debounceMs);

    document.querySelectorAll('.task-sortable').forEach((container) => {
        const handle = container.closest('.kanban-column')
            ? '.kanban-card-handle'
            : '.task-handle';
        Sortable.create(container, {
            group: 'tasks',
            handle,
            animation: 150,
            onEnd,
        });
    });
}

// Expose for inline script / global init
window.initEpicSortable = initEpicSortable;
window.initTaskSortables = initTaskSortables;
window.saveTaskOrder = saveTaskOrder;
window.showReorderToast = showToast;

// Auto-init when config is set by blade
document.addEventListener('DOMContentLoaded', () => {
    const config = window.ProjectReorder;
    if (!config || !config.canUpdate || !config.projectId || !config.csrfToken) return;
    if (config.viewMode === 'kanban') {
        initEpicSortable(config.projectId, config.csrfToken);
        initTaskSortables(config.projectId, config.csrfToken);
    } else {
        initEpicSortable(config.projectId, config.csrfToken);
        initTaskSortables(config.projectId, config.csrfToken);
    }
});
