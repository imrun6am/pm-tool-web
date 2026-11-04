import Alpine from 'alpinejs';

/*
 * People analytics tables: one set of tabs choosing which figures show, and
 * sorting by any column. Rows carry their sort value in the cell (data-sort), so
 * sorting needs no reload and a blank (nothing to quote) always sorts last. The
 * chosen tab is a per-tab convenience, so it survives opening a person and
 * coming back; a blocked store just means it starts on the first tab.
 */
const STORAGE_KEY = 'people-analytics-group';

Alpine.data('peopleTable', (groups = []) => ({
    group: groups[0] ?? null,
    sorts: {},

    init() {
        try {
            const saved = sessionStorage.getItem(STORAGE_KEY);
            if (groups.includes(saved)) this.group = saved;
        } catch {
            // Private windows may block storage; the first tab is fine.
        }
    },

    show(group) {
        this.group = group;
        try {
            sessionStorage.setItem(STORAGE_KEY, group);
        } catch {
            // As above.
        }
    },

    ariaSort(table, key) {
        const sort = this.sorts[table];
        if (sort?.key !== key) return 'none';

        return sort.dir === 1 ? 'ascending' : 'descending';
    },

    // Names read best A to Z first; figures biggest first.
    sortBy(table, key, text = false) {
        const current = this.sorts[table];
        const dir = current?.key === key ? -current.dir : (text ? 1 : -1);
        this.sorts = { ...this.sorts, [table]: { key, dir } };

        const body = this.$root.querySelector(`[data-table="${table}"] tbody`);
        if (!body) return;

        const read = (row) => row.querySelector(`[data-col="${key}"]`)?.dataset.sort ?? '';
        [...body.rows]
            .sort((a, b) => {
                const [x, y] = [read(a), read(b)];
                if (x === '' || y === '') return (x === '') - (y === '');

                return dir * (text ? x.localeCompare(y) : Number(x) - Number(y));
            })
            .forEach((row) => body.appendChild(row));
    },
}));
