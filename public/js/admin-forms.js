(function () {
    'use strict';

    function appendCollectionRow(holder, html) {
        if (holder.tagName === 'TBODY') {
            holder.insertAdjacentHTML('beforeend', html);
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const row = wrapper.firstElementChild;
        if (row) {
            holder.appendChild(row);
        }
    }

    function initCollectionAddButton(holder, addBtn, rowSelector) {
        if (!holder || !addBtn || holder.dataset.collectionBound === '1') {
            return;
        }

        holder.dataset.collectionBound = '1';

        let index = holder.querySelectorAll(rowSelector).length;
        const prototype = holder.dataset.prototype;

        if (!prototype) {
            return;
        }

        addBtn.addEventListener('click', function (event) {
            event.preventDefault();
            const html = prototype.replace(/__name__/g, String(index));
            appendCollectionRow(holder, html);
            index += 1;
            document.querySelector('.menu-dishes-empty-row')?.remove();
            refreshSortOrder(holder, rowSelector);
        });

        holder.addEventListener('click', function (event) {
            const removeBtn = event.target.closest('[data-collection-remove]');
            if (!removeBtn) {
                return;
            }
            event.preventDefault();
            removeBtn.closest(rowSelector)?.remove();
            refreshSortOrder(holder, rowSelector);
        });
    }

    function refreshSortOrder(holder, rowSelector) {
        holder.querySelectorAll(rowSelector).forEach(function (row, idx) {
            const orderInput = row.querySelector('input[name$="[sortOrder]"]');
            if (orderInput) {
                orderInput.value = String(idx + 1);
            }
        });
    }

    function initSortableRows(holder, rowSelector) {
        if (!holder || holder.dataset.sortableBound === '1' || holder.tagName !== 'TBODY') {
            return;
        }

        holder.dataset.sortableBound = '1';
        let draggingRow = null;

        holder.querySelectorAll(rowSelector).forEach(function (row) {
            row.draggable = false;
        });

        holder.addEventListener('mousedown', function (event) {
            const handle = event.target.closest('[data-drag-handle]');
            const row = event.target.closest(rowSelector);
            if (row) {
                row.draggable = !!handle;
            }
        });

        holder.addEventListener('dragstart', function (event) {
            const row = event.target.closest(rowSelector);
            if (!row) {
                return;
            }
            draggingRow = row;
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', '');
        });

        holder.addEventListener('dragover', function (event) {
            if (!draggingRow) {
                return;
            }
            event.preventDefault();
            const row = event.target.closest(rowSelector);
            if (!row || row === draggingRow) {
                return;
            }
            const rect = row.getBoundingClientRect();
            const before = event.clientY < rect.top + rect.height / 2;
            if (before) {
                holder.insertBefore(draggingRow, row);
            } else {
                holder.insertBefore(draggingRow, row.nextSibling);
            }
        });

        holder.addEventListener('dragend', function () {
            if (!draggingRow) {
                return;
            }
            draggingRow.classList.remove('is-dragging');
            draggingRow = null;
            holder.querySelectorAll(rowSelector).forEach(function (row) {
                row.draggable = false;
            });
            refreshSortOrder(holder, rowSelector);
        });
    }

    function initRowLinks() {
        document.querySelectorAll('[data-row-link]').forEach(function (row) {
            if (row.dataset.rowLinkBound === '1') {
                return;
            }
            row.dataset.rowLinkBound = '1';
            row.classList.add('is-clickable-row');

            row.addEventListener('click', function (event) {
                if (event.target.closest('a, button, input, select, textarea, label')) {
                    return;
                }
                const url = row.dataset.rowLink;
                if (url) {
                    window.location.href = url;
                }
            });

            row.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                event.preventDefault();
                const url = row.dataset.rowLink;
                if (url) {
                    window.location.href = url;
                }
            });
        });
    }

    function isDishIndexPage() {
        return window.location.pathname.replace(/\/+$/, '') === '/admin/dish';
    }

    function persistDishIndexOrder(tbody) {
        const ids = Array.from(tbody.querySelectorAll('tr[data-id]'))
            .map(function (row) { return Number(row.dataset.id); })
            .filter(Number.isFinite);

        if (ids.length === 0) {
            return;
        }

        fetch('/admin/dishes/sort', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ ids }),
        }).catch(function () {
            // Silent fail: visual order already changed; server order can be retried on next drag.
        });
    }

    function initDishIndexUX() {
        if (!isDishIndexPage()) {
            return;
        }

        document.body.classList.add('ganesha-dish-index');

        const tbody = document.querySelector('table.datagrid tbody');
        if (!tbody || tbody.dataset.dishSortBound === '1') {
            return;
        }
        tbody.dataset.dishSortBound = '1';

        let draggingRow = null;
        let suppressClickUntil = 0;
        tbody.querySelectorAll('tr[data-id]').forEach(function (row) {
            row.draggable = true;
            row.classList.add('dish-sort-row');
        });

        tbody.addEventListener('click', function (event) {
            const row = event.target.closest('tr[data-id]');
            if (!row) {
                return;
            }
            if (Date.now() < suppressClickUntil) {
                return;
            }
            if (event.target.closest('a, button, input, label, .actions, .batch-actions-selector')) {
                return;
            }
            const id = row.dataset.id;
            if (id) {
                window.location.href = '/admin/dish/' + id + '/edit';
            }
        });

        tbody.addEventListener('dragstart', function (event) {
            const row = event.target.closest('tr[data-id]');
            if (!row) {
                return;
            }
            draggingRow = row;
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', row.dataset.id || '');
        });

        tbody.addEventListener('dragover', function (event) {
            if (!draggingRow) {
                return;
            }
            event.preventDefault();
            const row = event.target.closest('tr[data-id]');
            if (!row || row === draggingRow) {
                return;
            }
            const rect = row.getBoundingClientRect();
            const before = event.clientY < rect.top + rect.height / 2;
            if (before) {
                tbody.insertBefore(draggingRow, row);
            } else {
                tbody.insertBefore(draggingRow, row.nextSibling);
            }
        });

        tbody.addEventListener('dragend', function () {
            if (!draggingRow) {
                return;
            }
            draggingRow.classList.remove('is-dragging');
            draggingRow = null;
            suppressClickUntil = Date.now() + 300;
            persistDishIndexOrder(tbody);
        });
    }

    function initMenuDayDateColumnLinks() {
        if (window.location.pathname.replace(/\/+$/, '') !== '/admin/menu-day') {
            return;
        }

        document.querySelectorAll('table.datagrid tbody tr[data-id] td[data-column=\"date\"]').forEach(function (cell) {
            if (cell.dataset.menuDayDateBound === '1') {
                return;
            }
            cell.dataset.menuDayDateBound = '1';
            cell.classList.add('is-clickable-cell');

            cell.addEventListener('click', function (event) {
                if (event.target.closest('a, button, input, select, textarea, label')) {
                    return;
                }
                const row = cell.closest('tr[data-id]');
                const id = row.dataset.id;
                if (id) {
                    window.location.href = '/admin/menu-day-form/' + id;
                }
            });
        });
    }

    function boot() {
        document.querySelectorAll('[data-collection-holder]').forEach(function (holder) {
            const addSelector = holder.dataset.collectionAdd;
            const addBtn = addSelector ? document.querySelector(addSelector) : null;
            const rowSelector = holder.dataset.collectionRow || '.collection-row';
            initCollectionAddButton(holder, addBtn, rowSelector);
            initSortableRows(holder, rowSelector);
            refreshSortOrder(holder, rowSelector);
        });

        initRowLinks();
        initDishIndexUX();
        initMenuDayDateColumnLinks();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.GaneshaAdminForms = { initCollectionAddButton, initSortableRows, refreshSortOrder, initRowLinks, initDishIndexUX, initMenuDayDateColumnLinks, boot };
})();
