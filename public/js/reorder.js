/**
 * Shared drag-and-drop reorder for List and Kanban views.
 * Expects: Sortable on window (from CDN) and window.ProjectReorder = { projectId, csrfToken, canUpdate }.
 */
(function () {
    'use strict';
    var Sortable = window.Sortable;
    if (!Sortable) return;

    function showToast(message, isError) {
        isError = !!isError;
        var existing = document.getElementById('reorder-toast');
        if (existing) existing.remove();
        var el = document.createElement('div');
        el.id = 'reorder-toast';
        el.setAttribute('role', 'alert');
        el.className = 'fixed bottom-4 right-4 px-4 py-3 rounded-lg shadow-lg z-50 text-sm ' +
            (isError ? 'bg-red-600 text-white' : 'bg-green-600 text-white');
        el.textContent = message;
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 3000);
    }

    var saveTaskOrderDebounce = null;

    function buildColumnsPayload() {
        var columns = [];
        document.querySelectorAll('.task-sortable').forEach(function (container) {
            var epicId = container.dataset.epicId || container.getAttribute('data-epic-id');
            if (!epicId) return;
            var taskIds = [];
            container.querySelectorAll('.task-row').forEach(function (row) {
                var id = row.dataset.taskId || row.getAttribute('data-task-id');
                if (id) taskIds.push(parseInt(id, 10));
            });
            columns.push({ epic_id: parseInt(epicId, 10), task_ids: taskIds });
        });
        return columns;
    }

    function saveTaskOrder(projectId, csrfToken) {
        var columns = buildColumnsPayload();
        var body = JSON.stringify({ columns: columns });
        fetch('/projects/' + projectId + '/tasks/reorder', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body
        })
            .then(function (res) {
                if (res.ok) {
                    showToast('Order saved.');
                    return;
                }
                return res.json().then(function (data) {
                    var msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Failed to save order.');
                    showToast(msg, true);
                });
            })
            .catch(function () { showToast('Failed to save order.', true); });
    }

    function debouncedSaveTaskOrder(projectId, csrfToken, delay) {
        delay = delay || 200;
        if (saveTaskOrderDebounce) clearTimeout(saveTaskOrderDebounce);
        saveTaskOrderDebounce = setTimeout(function () {
            saveTaskOrderDebounce = null;
            saveTaskOrder(projectId, csrfToken);
        }, delay);
    }

    function initEpicSortable(projectId, csrfToken) {
        var listContainer = document.getElementById('epic-sortable');
        var kanbanContainer = document.getElementById('kanban-columns');
        var container = listContainer || kanbanContainer;
        if (!container || !projectId || !csrfToken) return;

        var isKanban = !!kanbanContainer;
        var itemSelector = isKanban ? '.kanban-column' : '.epic-row';
        var handleSelector = isKanban ? '.kanban-column-handle' : '.epic-handle';

        Sortable.create(container, {
            draggable: isKanban ? '.kanban-column' : undefined,
            handle: handleSelector,
            animation: 150,
            onEnd: function () {
                var nodes = container.querySelectorAll(itemSelector);
                var epicIds = Array.prototype.map.call(nodes, function (row) {
                    return parseInt(row.dataset.epicId || row.getAttribute('data-epic-id'), 10);
                }).filter(Boolean);
                if (epicIds.length === 0) return;

                fetch('/projects/' + projectId + '/epics/reorder', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ epic_ids: epicIds })
                })
                    .then(function (res) {
                        if (res.ok) {
                            showToast('Order saved.');
                            return;
                        }
                        return res.json().then(function (data) {
                            var msg = data.message || (data.errors && data.errors.epic_ids ? data.errors.epic_ids.join(' ') : 'Failed to save order.');
                            showToast(msg, true);
                        });
                    })
                    .catch(function () { showToast('Failed to save order.', true); });
            }
        });
    }

    function initTaskSortables(projectId, csrfToken, debounceMs) {
        debounceMs = debounceMs || 200;
        if (!projectId || !csrfToken) return;

        var onEnd = function () { debouncedSaveTaskOrder(projectId, csrfToken, debounceMs); };

        document.querySelectorAll('.task-sortable').forEach(function (container) {
            var handle = container.closest('.kanban-column') ? '.kanban-card-handle' : '.task-handle';
            Sortable.create(container, {
                group: 'tasks',
                handle: handle,
                animation: 150,
                onEnd: onEnd
            });
        });
    }

    window.initEpicSortable = initEpicSortable;
    window.initTaskSortables = initTaskSortables;
    window.saveTaskOrder = saveTaskOrder;
    window.showReorderToast = showToast;

    document.addEventListener('DOMContentLoaded', function () {
        var config = window.ProjectReorder;
        if (!config || !config.canUpdate || !config.projectId || !config.csrfToken) return;
        initEpicSortable(config.projectId, config.csrfToken);
        initTaskSortables(config.projectId, config.csrfToken);
    });
})();
