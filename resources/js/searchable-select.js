/*
 * Searchable selects — a progressive enhancement over every native <select>.
 *
 * The real <select> stays in the form as the source of truth: it is what gets
 * posted, what `old()` pre-selects, what `x-model` binds and what validation
 * marks invalid. It is only hidden; a button styled with the select's own
 * classes takes its place, and opens a listbox panel with a type-to-filter
 * box (shown once a select has more than SEARCH_THRESHOLD options; smaller
 * lists still get type-ahead). Choosing an option sets the select's value and
 * fires `input` + `change`, so x-model, filter bars (submit on change) and any
 * @change handler behave exactly as with the native control.
 *
 * Enhancement is automatic: on load, after every Turbo render, and for selects
 * added later (Alpine x-for/x-if, fetched dialogs, in-page region updates),
 * via one MutationObserver. Each select is enhanced once (data-ss).
 * Opt out with data-native on the select or on any ancestor.
 *
 * The panel is rendered into <body> with fixed positioning so overflow-hidden
 * cards and scrolling tables never clip it; clicks and keys inside it don't
 * propagate, so a parent popover's @click.outside / @keydown.escape.window
 * doesn't close around it. Below 640px it becomes a bottom sheet.
 */

const SEARCH_THRESHOLD = 6;
const SKIP = 'select[multiple], select[size]:not([size="1"]), select.board-move, select[data-native], [data-native] select';
const PANEL_GAP = 6;

let uid = 0;
let current = null; // the open controller, if any

/*
 * Visual cues next to option labels, so a pick is recognisable at a glance:
 *  - people (assignee, member, user… selects): an initials avatar in the same
 *    tone x-avatar gives that user id;
 *  - teams and projects: their colour dot, from the #ss-swatches JSON the
 *    layout renders (layouts/select-swatches);
 *  - any option with data-color="#hex": that dot.
 * A select can force a kind with data-ss-kind="person|team|project|none".
 */
const PERSON = /(^|[_[])(assignee|user|member|causer|author|attendee|lead|owner|person)(_?ids?)?\]?(\[\])?$/;
const TEAM = /(^|[_[])team(_?ids?)?\]?(\[\])?$/;
const PROJECT = /(^|[_[])project(_?ids?)?\]?(\[\])?$/;

function kindOf(select) {
    if (select.dataset.ssKind) return select.dataset.ssKind;
    const name = select.name || select.id || '';
    if (PERSON.test(name)) return 'person';
    if (TEAM.test(name)) return 'team';
    if (PROJECT.test(name)) return 'project';
    return 'none';
}

function swatches() {
    try {
        return JSON.parse(document.getElementById('ss-swatches')?.textContent || '{}');
    } catch (e) {
        return {};
    }
}

/** The leading cue for an option (or null): a colour dot or an avatar. */
function cueFor(kind, option, palette) {
    if (option.value === '') return null;
    const color = option.dataset?.color || (kind === 'team' ? palette.teams?.[option.value] : kind === 'project' ? palette.projects?.[option.value] : null);
    if (color) {
        const dot = document.createElement('span');
        dot.className = 'ss-dot';
        dot.style.backgroundColor = color;
        return dot;
    }
    if (kind === 'person' && /^\d+$/.test(option.value)) {
        const name = option.textContent.replace(/\(me\)/i, '').trim();
        const initials = name.split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0].toUpperCase()).join('') || '?';
        const avatar = document.createElement('span');
        avatar.className = 'ss-avatar';
        avatar.dataset.tone = String(Number(option.value) % 8); // = x-avatar's tone for that user
        avatar.textContent = initials;
        return avatar;
    }
    return null;
}

const normalize = (s) => s.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().trim();
const nextId = (prefix) => `${prefix}-${++uid}`;

const CHEVRON = '<svg class="ss-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>';
const CHECK = '<svg class="ss-check" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0l-3.5-3.5a1 1 0 111.4-1.4l2.8 2.79 6.8-6.79a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>';
const SEARCH = '<svg class="ss-search-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.45 4.39l3.08 3.08a.75.75 0 11-1.06 1.06l-3.08-3.08A7 7 0 012 9z" clip-rule="evenodd"/></svg>';

const controllers = new WeakMap();

/*
 * Keep the button in step with the select however its value changes. Scripts
 * set .value or .selectedIndex, and Alpine's x-model sets option.selected on
 * each option. None of these fire events or mutations, so the setters
 * themselves notify the select's controller (batched to one update per tick).
 */
function hookSetter(proto, prop, selectOf) {
    const descriptor = Object.getOwnPropertyDescriptor(proto, prop);
    Object.defineProperty(proto, prop, {
        ...descriptor,
        set(v) {
            descriptor.set.call(this, v);
            const select = selectOf(this);
            if (select) controllers.get(select)?.scheduleSync();
        },
    });
}
hookSetter(HTMLSelectElement.prototype, 'value', (el) => el);
hookSetter(HTMLSelectElement.prototype, 'selectedIndex', (el) => el);
hookSetter(HTMLOptionElement.prototype, 'selected', (el) => el.closest('select'));

class SearchableSelect {
    constructor(select) {
        this.select = select;
        this.options = [];
        this.filtered = [];
        this.active = -1;
        this.typed = '';
        this.typedAt = 0;
        this.panel = null;
        this.build();
    }

    // ---- setup -----------------------------------------------------------

    build() {
        const select = this.select;
        select.dataset.ss = '1';

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.id = nextId('ss-trigger');
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.innerHTML = `<span class="ss-cue" aria-hidden="true"></span><span class="ss-value" id="${nextId('ss-value')}"></span>`
            + `<span class="ss-clear" aria-hidden="true" title="Clear">&times;</span>${CHEVRON}`;
        this.trigger = trigger;
        this.kind = kindOf(select);
        this.cueEl = trigger.querySelector('.ss-cue');
        this.valueEl = trigger.querySelector('.ss-value');
        this.clearEl = trigger.querySelector('.ss-clear');
        // Mouse: the × in the field. Keyboard: Delete/Backspace on the field.
        // (Not a nested button — the whole field already is one.)
        this.clearEl.addEventListener('pointerdown', (e) => e.preventDefault());
        this.clearEl.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.clear();
        });
        this.listId = nextId('ss-list');

        // Measure the native control before hiding it, so an auto-width select
        // (sized to its widest option) keeps that width.
        const nativeWidth = select.getBoundingClientRect().width;
        select.after(trigger);
        this.syncAttributes();
        this.syncLabel();
        if (nativeWidth > trigger.getBoundingClientRect().width + 1) {
            trigger.style.minWidth = `${Math.ceil(nativeWidth)}px`;
        }

        select.classList.add('ss-native');
        select.tabIndex = -1;

        this.observer = new MutationObserver(() => {
            this.syncAttributes();
            this.syncLabel();
        });
        this.observer.observe(select, { attributes: true, childList: true, subtree: true, characterData: true });

        select.addEventListener('change', () => { this.clearInvalid(); this.syncLabel(); });
        // A label's `for` focuses the select; hand focus to the button.
        select.addEventListener('focus', () => trigger.focus());
        // Native validation can't anchor its bubble to a hidden control, so
        // show the message under the button instead.
        select.addEventListener('invalid', (e) => this.showInvalid(e));
        select.form?.addEventListener('reset', () => setTimeout(() => this.syncLabel()));

        trigger.addEventListener('click', () => (this.panel ? this.close() : this.open()));
        trigger.addEventListener('keydown', (e) => this.onTriggerKey(e));
    }

    /** Mirror the select's look and state onto the button. */
    syncAttributes() {
        const { select, trigger } = this;
        trigger.className = `${select.className.replace(/\bss-native\b/, '').trim()} ss-trigger`;
        trigger.disabled = select.disabled;
        trigger.hidden = select.hidden || select.style.display === 'none';
        for (const attr of ['aria-invalid', 'aria-describedby', 'title']) {
            select.hasAttribute(attr) ? trigger.setAttribute(attr, select.getAttribute(attr)) : trigger.removeAttribute(attr);
        }
        if (this.invalidMessage) trigger.setAttribute('aria-invalid', 'true');

        const label = select.labels?.[0];
        if (label) {
            label.id ||= nextId('ss-label');
            trigger.setAttribute('aria-labelledby', `${label.id} ${this.valueEl.id}`);
            trigger.removeAttribute('aria-label');
        } else if (select.getAttribute('aria-label')) {
            trigger.setAttribute('aria-label', select.getAttribute('aria-label'));
        }
    }

    scheduleSync() {
        if (this.syncQueued) return;
        this.syncQueued = true;
        queueMicrotask(() => {
            this.syncQueued = false;
            this.syncLabel();
        });
    }

    syncLabel() {
        const option = this.select.selectedOptions?.[0];
        this.valueEl.textContent = option ? option.textContent.trim() : '';
        this.trigger.classList.toggle('ss-placeholder', !option || option.value === '');
        const cue = option ? cueFor(this.kind, option, swatches()) : null;
        this.cueEl.replaceChildren(...(cue ? [cue] : []));
        this.trigger.classList.toggle('ss-has-cue', !!cue);
        this.trigger.classList.toggle('ss-clearable', this.clearable());
    }

    /** Optional fields with an empty choice ("All teams", "Unassigned") can be cleared. */
    clearable() {
        const { select } = this;
        return !select.required && !select.disabled && select.value !== ''
            && [...select.options].some((o) => o.value === '' && !o.disabled);
    }

    clear() {
        if (!this.clearable()) return;
        const empty = [...this.select.options].find((o) => o.value === '' && !o.disabled);
        empty.selected = true;
        this.syncLabel();
        this.select.dispatchEvent(new Event('input', { bubbles: true }));
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
        this.trigger.focus({ preventScroll: true });
    }

    // ---- options ---------------------------------------------------------

    readOptions() {
        this.options = [...this.select.querySelectorAll('option')]
            .filter((o) => !o.hidden)
            .map((o, i) => {
                const group = o.parentElement.tagName === 'OPTGROUP' ? o.parentElement : null;
                return {
                    el: o,
                    id: `${this.listId}-o${i}`,
                    label: o.textContent.trim(),
                    value: o.value,
                    key: normalize(o.textContent),
                    group: group?.label ?? null,
                    disabled: o.disabled || !!group?.disabled,
                    selected: o.selected,
                };
            });
        this.palette = swatches();
    }

    applyFilter(query) {
        const q = normalize(query);
        this.query = q;
        this.filtered = q ? this.options.filter((o) => o.key.includes(q)) : this.options.slice();
        const selected = this.filtered.findIndex((o) => o.selected && !o.disabled);
        this.active = q ? this.firstEnabled(0, 1) : (selected >= 0 ? selected : this.firstEnabled(0, 1));
        this.renderList();
    }

    firstEnabled(from, step) {
        for (let i = from; i >= 0 && i < this.filtered.length; i += step) {
            if (!this.filtered[i].disabled) return i;
        }
        return -1;
    }

    // ---- panel -----------------------------------------------------------

    open(seed = '') {
        if (this.trigger.disabled || this.panel) return;
        current?.close(false);
        current = this;
        this.readOptions();
        const searchable = this.options.length > SEARCH_THRESHOLD;

        const panel = document.createElement('div');
        panel.className = 'ss-panel';
        panel.innerHTML = `
            ${searchable ? `<div class="ss-search">${SEARCH}<input type="text" class="ss-input" placeholder="Search…" autocomplete="off" spellcheck="false"
                role="combobox" aria-expanded="true" aria-autocomplete="list" aria-controls="${this.listId}" aria-label="Search options"></div>` : ''}
            <ul class="ss-list" id="${this.listId}" role="listbox" tabindex="-1"></ul>`;
        this.panel = panel;
        this.input = panel.querySelector('.ss-input');
        this.list = panel.querySelector('.ss-list');
        const labelledBy = this.trigger.getAttribute('aria-labelledby')?.split(' ')[0];
        labelledBy ? this.list.setAttribute('aria-labelledby', labelledBy) : this.list.setAttribute('aria-label', this.trigger.getAttribute('aria-label') || 'Options');

        // Small screens: a bottom sheet with a dimmed backdrop.
        this.sheet = window.matchMedia('(max-width: 639px)').matches;
        if (this.sheet) {
            this.backdrop = document.createElement('div');
            this.backdrop.className = 'ss-backdrop';
            this.backdrop.addEventListener('click', () => this.close());
            document.body.append(this.backdrop);
            panel.classList.add('ss-sheet');
        }
        document.body.append(panel);

        // Interactions inside the panel stay inside it (see header comment).
        for (const type of ['click', 'mousedown', 'pointerdown', 'keydown', 'keyup']) {
            panel.addEventListener(type, (e) => e.stopPropagation());
        }
        panel.addEventListener('keydown', (e) => this.onPanelKey(e));
        this.list.addEventListener('mousedown', (e) => e.preventDefault()); // keep focus in the search box
        this.list.addEventListener('click', (e) => {
            const li = e.target.closest('[role="option"]');
            if (li) this.choose(Number(li.dataset.index));
        });
        this.list.addEventListener('mousemove', (e) => {
            const li = e.target.closest('[role="option"]');
            if (li && Number(li.dataset.index) !== this.active && li.getAttribute('aria-disabled') !== 'true') {
                this.setActive(Number(li.dataset.index), false);
            }
        });
        this.input?.addEventListener('input', () => this.applyFilter(this.input.value));

        this.onOutside = (e) => {
            if (!panel.contains(e.target) && !this.trigger.contains(e.target)) this.close(false);
        };
        this.onReflow = (e) => {
            if (e?.type === 'scroll' && panel.contains(e.target)) return;
            this.position();
        };
        document.addEventListener('pointerdown', this.onOutside, true);
        window.addEventListener('scroll', this.onReflow, true);
        window.addEventListener('resize', this.onReflow);

        this.trigger.setAttribute('aria-expanded', 'true');
        this.trigger.setAttribute('aria-controls', this.listId);
        this.applyFilter('');
        this.position();

        if (this.input) {
            this.input.value = seed;
            if (seed) this.applyFilter(seed);
            this.input.focus({ preventScroll: true });
        } else {
            this.list.focus({ preventScroll: true });
            if (seed) this.typeAhead(seed);
        }
        this.syncActiveDescendant();
    }

    close(restoreFocus = true) {
        if (!this.panel) return;
        document.removeEventListener('pointerdown', this.onOutside, true);
        window.removeEventListener('scroll', this.onReflow, true);
        window.removeEventListener('resize', this.onReflow);
        // Fade out, then remove (at once when motion is reduced).
        const leaving = [this.panel, this.backdrop].filter(Boolean);
        const instant = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        leaving.forEach((el) => {
            el.classList.add('ss-leaving');
            el.style.pointerEvents = 'none';
            instant ? el.remove() : setTimeout(() => el.remove(), 120);
        });
        this.panel = this.backdrop = this.input = this.list = null;
        this.trigger.setAttribute('aria-expanded', 'false');
        this.trigger.removeAttribute('aria-controls');
        this.trigger.removeAttribute('aria-activedescendant');
        if (current === this) current = null;
        if (restoreFocus) this.trigger.focus({ preventScroll: true });
    }

    position() {
        if (!this.panel || this.sheet) return;
        const rect = this.trigger.getBoundingClientRect();
        if (rect.bottom < 0 || rect.top > window.innerHeight || !this.trigger.isConnected) {
            this.close(false);
            return;
        }
        const vw = document.documentElement.clientWidth;
        const vh = window.innerHeight;
        const width = Math.min(Math.max(rect.width, 224), vw - 16);
        const left = Math.min(Math.max(rect.left, 8), vw - width - 8);
        const below = vh - rect.bottom - PANEL_GAP - 8;
        const above = rect.top - PANEL_GAP - 8;
        const up = below < 240 && above > below;
        const room = Math.max(120, Math.min(up ? above : below, 360));

        Object.assign(this.panel.style, {
            left: `${left}px`,
            width: `${width}px`,
            top: up ? '' : `${rect.bottom + PANEL_GAP}px`,
            bottom: up ? `${vh - rect.top + PANEL_GAP}px` : '',
            maxHeight: `${room}px`,
        });
        this.panel.classList.toggle('ss-up', up);
    }

    renderList() {
        const list = this.list;
        list.replaceChildren();
        let group;
        this.filtered.forEach((o, i) => {
            if (o.group !== group) {
                group = o.group;
                if (group) {
                    const head = document.createElement('li');
                    head.setAttribute('role', 'presentation');
                    head.className = 'ss-group';
                    head.textContent = group;
                    list.append(head);
                }
            }
            const li = document.createElement('li');
            li.id = o.id;
            li.dataset.index = i;
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', o.selected ? 'true' : 'false');
            if (o.disabled) li.setAttribute('aria-disabled', 'true');
            li.className = 'ss-option';
            li.classList.toggle('is-active', i === this.active);
            const cue = cueFor(this.kind, o.el, this.palette);
            if (cue) li.append(cue);
            li.append(this.highlight(o.label));
            li.insertAdjacentHTML('beforeend', CHECK);
            list.append(li);
        });
        if (!this.filtered.length) {
            const empty = document.createElement('li');
            empty.setAttribute('role', 'presentation');
            empty.className = 'ss-empty';
            empty.textContent = 'No matches';
            list.append(empty);
        }
        this.syncActiveDescendant();
        this.scrollActive();
    }

    /** The label with the matched part of the query emphasised. */
    highlight(label) {
        const span = document.createElement('span');
        span.className = 'ss-label';
        const at = this.query ? normalize(label).indexOf(this.query) : -1;
        if (at < 0 || normalize(label).length !== label.length) {
            span.textContent = label;
            return span;
        }
        const mark = document.createElement('mark');
        mark.textContent = label.slice(at, at + this.query.length);
        span.append(label.slice(0, at), mark, label.slice(at + this.query.length));
        return span;
    }

    setActive(index, scroll = true) {
        this.active = index;
        this.list.querySelectorAll('.ss-option').forEach((li) => li.classList.toggle('is-active', Number(li.dataset.index) === index));
        this.syncActiveDescendant();
        if (scroll) this.scrollActive();
    }

    syncActiveDescendant() {
        const target = this.input || this.list;
        const id = this.filtered[this.active]?.id;
        for (const el of [target, this.trigger]) {
            if (!el) continue;
            id ? el.setAttribute('aria-activedescendant', id) : el.removeAttribute('aria-activedescendant');
        }
    }

    scrollActive() {
        this.list?.querySelector('.ss-option.is-active')?.scrollIntoView({ block: 'nearest' });
    }

    move(step) {
        if (!this.filtered.length) return;
        let i = this.active;
        for (let n = 0; n < this.filtered.length; n++) {
            i = Math.min(Math.max(i + step, 0), this.filtered.length - 1);
            if (!this.filtered[i].disabled) break;
            if (i === 0 || i === this.filtered.length - 1) return;
        }
        if (!this.filtered[i].disabled) this.setActive(i);
    }

    typeAhead(char) {
        const now = Date.now();
        this.typed = now - this.typedAt > 600 ? char : this.typed + char;
        this.typedAt = now;
        const q = normalize(this.typed);
        const i = this.filtered.findIndex((o) => !o.disabled && o.key.startsWith(q));
        if (i >= 0) this.setActive(i);
    }

    choose(index) {
        const option = this.filtered[index];
        if (!option || option.disabled) return;
        const changed = this.select.value !== option.value || !option.el.selected;
        option.el.selected = true;
        this.syncLabel();
        this.close();
        if (changed) {
            this.select.dispatchEvent(new Event('input', { bubbles: true }));
            this.select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    // ---- keyboard --------------------------------------------------------

    onTriggerKey(e) {
        if ((e.key === 'Delete' || e.key === 'Backspace') && this.clearable()) {
            e.preventDefault();
            this.clear();
            return;
        }
        if (e.altKey && e.key === 'ArrowDown') {
            e.preventDefault();
            this.open();
        } else if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key) && !e.metaKey && !e.ctrlKey) {
            e.preventDefault();
            this.open();
        } else if (e.key.length === 1 && !e.metaKey && !e.ctrlKey && !e.altKey && /\S/.test(e.key)) {
            // Start filtering (or type-ahead) straight from the closed control.
            e.preventDefault();
            e.stopPropagation(); // not a global shortcut (C, ?) while on a select
            this.open(e.key);
        }
    }

    onPanelKey(e) {
        switch (e.key) {
            case 'ArrowDown': e.preventDefault(); this.move(1); break;
            case 'ArrowUp': e.preventDefault(); this.move(-1); break;
            case 'PageDown': e.preventDefault(); this.move(8); break;
            case 'PageUp': e.preventDefault(); this.move(-8); break;
            case 'Home': if (!this.input) { e.preventDefault(); this.setActive(this.firstEnabled(0, 1)); } break;
            case 'End': if (!this.input) { e.preventDefault(); this.setActive(this.firstEnabled(this.filtered.length - 1, -1)); } break;
            case 'Enter': e.preventDefault(); this.choose(this.active); break;
            case 'Escape': e.preventDefault(); this.close(); break;
            case 'Tab': this.close(false); this.trigger.focus(); break;
            default:
                if (!this.input && e.key.length === 1 && !e.metaKey && !e.ctrlKey && !e.altKey) this.typeAhead(e.key);
        }
    }

    // ---- validation --------------------------------------------------------

    showInvalid(e) {
        e.preventDefault();
        this.clearInvalid();
        this.invalidMessage = document.createElement('p');
        this.invalidMessage.className = 'field-error';
        this.invalidMessage.id = nextId('ss-error');
        this.invalidMessage.textContent = this.select.validationMessage;
        this.trigger.after(this.invalidMessage);
        this.trigger.setAttribute('aria-invalid', 'true');
        this.trigger.setAttribute('aria-describedby', this.invalidMessage.id);
        // Invalid events fire in document order: only the first one takes focus.
        if (document.activeElement?.getAttribute('aria-invalid') !== 'true') this.trigger.focus();
    }

    clearInvalid() {
        if (!this.invalidMessage) return;
        this.invalidMessage.remove();
        this.invalidMessage = null;
        this.syncAttributes();
    }

    destroy() {
        this.close(false);
        this.observer.disconnect();
        this.trigger.remove();
        this.invalidMessage?.remove();
    }
}

export function enhance(root = document) {
    const selects = root instanceof HTMLSelectElement ? [root] : root.querySelectorAll?.('select') ?? [];
    for (const select of selects) {
        if (select.dataset.ss || select.matches(SKIP) || !select.isConnected) continue;
        controllers.set(select, new SearchableSelect(select));
    }
}

function cleanup(node) {
    const selects = node instanceof HTMLSelectElement ? [node] : node.querySelectorAll?.('select[data-ss]') ?? [];
    for (const select of selects) {
        const controller = controllers.get(select);
        if (controller && !select.isConnected) {
            controller.destroy();
            controllers.delete(select);
            delete select.dataset.ss;
        }
    }
}

// One observer on <html> survives Turbo's <body> swaps.
new MutationObserver((mutations) => {
    for (const m of mutations) {
        m.removedNodes.forEach((n) => n.nodeType === 1 && cleanup(n));
        m.addedNodes.forEach((n) => {
            if (n.nodeType !== 1 || n.classList.contains('ss-panel') || n.classList.contains('ss-trigger')) return;
            enhance(n);
        });
    }
}).observe(document.documentElement, { childList: true, subtree: true });

document.addEventListener('turbo:before-render', () => current?.close(false));
document.addEventListener('turbo:load', () => enhance());
enhance();
