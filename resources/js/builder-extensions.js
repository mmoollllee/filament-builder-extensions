/**
 * filament-builder-extensions: the browser half of three tools, loaded where a field or
 * action asks for it (`x-load-js`), never on every page.
 *
 * Transfer (`->transferGroup()`): every list of a Builder or Repeater is a SortableJS list
 * of its own (Filament's `x-sortable`), which reads its group once, when it starts. A press
 * on an item's handle therefore joins every list of the item's transfer group (in the same
 * Livewire component) into one SortableJS group, so the item can be dropped into another
 * list; an empty list renders no list at all, so while an item is dragged a zone stands in
 * for it. The drop is the server's to carry out: the target's `transferItem` action, mounted
 * as Filament mounts its own reorder, renders both lists anew. The dragged element is
 * dropped, and so is Filament's reorder of the list it came from, which would only put it
 * back.
 *
 * Clipboard (`CopyItemAction`, `PasteItemAction`): an item's state as the form holds it, as
 * JSON with its kind, in the clipboard and, for a day, in localStorage, where the browser
 * refuses the clipboard (no secure context, no focus, no permission): `{kind, item}`, a
 * Builder item as Filament holds it, `{kind, type, data}`. A paste reads it back and mounts
 * the paste action with it; the server takes from it what it may.
 *
 * Keyboard (`->clipboardShortcuts()`): a click on an item, outside its fields, selects it;
 * ⌘C copies it and ⌘V pastes after it (Ctrl elsewhere), with the list's own copy and paste
 * actions. The browser does the copying and pasting itself, into and out of a textarea
 * focused for that instant.
 */
(() => {
    if (window.FilamentBuilderExtensions) {
        return
    }

    const zones = new Set()

    /** A field wrapper's own list: in a table repeater, inside its table. */
    const listOf = (wrapper) =>
        wrapper.querySelector(':scope > [x-sortable], :scope > table > [x-sortable]')

    /** The field wrapper of a transfer group a list (or a stand-in zone) belongs to. */
    const wrapperOf = (list) => {
        const wrapper = list?.closest?.('[data-transfer-group]')

        return wrapper && (listOf(wrapper) === list || zones.has(list)) ? wrapper : null
    }

    const componentOf = (element) => element.closest('[wire\\:id]')

    /** One SortableJS group per transfer group and Livewire component. */
    const sortableGroupOf = (wrapper) =>
        `${wrapper.dataset.transferGroup}@${componentOf(wrapper)?.getAttribute('wire:id')}`

    const wrappersOfGroup = (wrapper) => {
        const component = componentOf(wrapper)

        return [...(component ?? document).querySelectorAll('[data-transfer-group]')].filter(
            (other) => other.dataset.transferGroup === wrapper.dataset.transferGroup && componentOf(other) === component,
        )
    }

    const removeZones = () => {
        zones.forEach((zone) => {
            zone.sortable?.destroy()
            zone.remove()
        })

        zones.clear()
    }

    // Before SortableJS takes the press: the capture phase runs ahead of its own listener.
    document.addEventListener(
        'pointerdown',
        (event) => {
            const handle = event.target.closest?.('[x-sortable-handle]')
            const wrapper = handle ? wrapperOf(handle.closest('[x-sortable]')) : null

            if (!wrapper) {
                return
            }

            const group = sortableGroupOf(wrapper)

            // A list that may not lose items gives none away; it still takes the others'.
            wrappersOfGroup(wrapper).forEach((other) =>
                listOf(other)?.sortable?.option('group', { name: group, pull: other.dataset.transferPull !== 'false', put: true }),
            )
        },
        true,
    )

    // Once the drag is on: a zone in every empty list of the group.
    document.addEventListener(
        'start',
        (event) => {
            const wrapper = wrapperOf(event.from)

            if (!wrapper || !event.item || !window.Sortable) {
                return
            }

            const group = sortableGroupOf(wrapper)

            wrappersOfGroup(wrapper).forEach((other) => {
                if (listOf(other) || event.item.contains(other)) {
                    return
                }

                const zone = document.createElement('ul')
                zone.className = 'fi-be-drop-zone'
                zone.dataset.label = other.dataset.transferDropLabel ?? ''

                // Where the list would be: after the "collapse all" row, before the add button.
                const actions = other.querySelector(
                    ':scope > .fi-fo-builder-actions, :scope > .fi-fo-repeater-actions',
                )
                other.insertBefore(zone, actions ? actions.nextSibling : other.firstChild)

                zone.sortable = window.Sortable.create(zone, {
                    group,
                    draggable: '[x-sortable-item]',
                    handle: '[x-sortable-handle]',
                    dataIdAttr: 'x-sortable-item',
                    ghostClass: 'fi-sortable-ghost',
                    animation: 150,
                })

                zones.add(zone)
            })
        },
        true,
    )

    document.addEventListener(
        'end',
        (event) => {
            const from = wrapperOf(event.from)

            if (!from) {
                return
            }

            const to = wrapperOf(event.to)

            // After SortableJS is done with them, whatever the drop.
            queueMicrotask(removeZones)

            if (
                !to ||
                to === from ||
                to.dataset.transferGroup !== from.dataset.transferGroup ||
                componentOf(to) !== componentOf(from)
            ) {
                return
            }

            // Filament's reorder of the list the item left would only put it back.
            event.stopPropagation()

            // Since Filament 5.10 the list's own component tracks the drag it began, waiting
            // for that reorder: it ends here, or the list's move buttons stay blocked.
            if (from.hasAttribute('data-reorder-owner')) {
                window.Alpine?.$data(from)?.finish?.()
            }

            const item = event.item
            const fromItems = event.from.sortable.toArray()
            const toItems = event.to.sortable.toArray()

            // After SortableJS has placed it: the server's render takes its place.
            queueMicrotask(() => item.remove())

            window.Livewire.find(componentOf(to).getAttribute('wire:id')).mountAction(
                'transferItem',
                {
                    from: from.dataset.transferKey,
                    moved: item.getAttribute('x-sortable-item'),
                    fromItems,
                    toItems,
                    toIndex: event.newDraggableIndex,
                },
                { schemaComponent: to.dataset.transferKey },
            )
        },
        true,
    )

    const storageKey = (kind) => `filament-builder-extensions.${kind}`

    const notify = (title, status) =>
        title &&
        window.FilamentNotification &&
        new window.FilamentNotification().title(title)[status]().send()

    /**
     * The item a copy of this kind carries, null for anything else. A Builder item is taken
     * as Filament holds it, `{type, data}`, with this kind or none: so also a bare item, as
     * copies made without this package hold it.
     */
    const itemIn = (text, kind, builder) => {
        try {
            const payload = JSON.parse(text ?? '')

            if (!payload || typeof payload !== 'object') {
                return null
            }

            if (builder) {
                const item = payload.item ?? payload

                return (payload.kind ?? kind) === kind &&
                    typeof item.type === 'string' &&
                    item.data &&
                    typeof item.data === 'object'
                    ? { type: item.type, data: item.data }
                    : null
            }

            return payload.kind === kind && payload.item && typeof payload.item === 'object'
                ? payload.item
                : null
        } catch (error) {
            return null
        }
    }

    /** What a copy of the item at `path` carries: a Builder item as Filament holds it, `{type, data}`, its kind beside it. */
    const payloadOf = ($wire, { path, kind, builder }) => {
        const item = $wire.get(path)

        return JSON.stringify(builder ? { kind, ...item } : { kind, item })
    }

    /** How long localStorage keeps a copy for a paste: a day, not for whoever uses the browser next. */
    const keptFor = 24 * 60 * 60 * 1000

    const remember = (kind, payload) => {
        try {
            localStorage.setItem(storageKey(kind), JSON.stringify({ copied: Date.now(), payload }))
        } catch (error) {
            // Private mode: the clipboard alone.
        }
    }

    /** The copy of this kind localStorage keeps, null for none; an older one, or one without its date, goes. */
    const recall = (kind) => {
        try {
            let kept = null

            try {
                kept = JSON.parse(localStorage.getItem(storageKey(kind)))
            } catch (error) {
                // No copy of ours: it goes below.
            }

            if (typeof kept?.payload === 'string' && Date.now() - kept.copied <= keptFor) {
                return kept.payload
            }

            localStorage.removeItem(storageKey(kind))
        } catch (error) {
            // Private mode: nothing there.
        }

        return null
    }

    /**
     * A textarea for an instant, out of sight, focused with `value` selected: what the
     * browser's own copy copies and its own paste pastes into while no field has the focus.
     * That works in every browser, Safari included, without a permission or a secure context.
     * It sits in `host`, the list's wrapper, so a modal's focus trap around the list lets it
     * have the focus. `release` removes it and gives the focus back, once.
     */
    const withProxy = (value, host, use) => {
        const previous = document.activeElement
        const proxy = document.createElement('textarea')

        proxy.value = value
        proxy.dataset.builderExtensionsProxy = ''
        proxy.setAttribute('aria-hidden', 'true')
        proxy.style.cssText =
            'position: fixed; top: 0; left: 0; width: 1px; height: 1px; padding: 0; border: 0; opacity: 0; pointer-events: none;'
        host.append(proxy)
        proxy.focus({ preventScroll: true })
        proxy.select()

        let released = false

        use(proxy, () => {
            if (released) {
                return false
            }

            released = true
            proxy.remove()

            if (previous instanceof HTMLElement && previous !== document.body && previous.isConnected) {
                previous.focus({ preventScroll: true })
            }

            return true
        })
    }

    const api = {
        async copy($wire, { path, kind, builder, notification }) {
            const payload = payloadOf($wire, { path, kind, builder })

            try {
                await navigator.clipboard.writeText(payload)
            } catch (error) {
                // Refused: localStorage still carries it within this browser.
            }

            remember(kind, payload)
            notify(notification, 'success')
        },

        /**
         * `text` is what a paste event found in the clipboard; without it the clipboard is
         * read as a button reads it. localStorage stands in for a clipboard without a copy,
         * with one of the last day; each paste lets an older one go.
         */
        async paste($wire, { name, arguments: args, context, kind, builder, notification }, text = null) {
            let item = null

            if (text !== null) {
                item = itemIn(text, kind, builder)
            } else {
                try {
                    item = itemIn(await navigator.clipboard.readText(), kind, builder)
                } catch (error) {
                    // Refused: localStorage below.
                }
            }

            const kept = recall(kind)

            item ??= itemIn(kept, kind, builder)

            if (!item) {
                notify(notification, 'warning')

                return null
            }

            return $wire.mountAction(name, { ...args, pasted: item }, context)
        },
    }

    window.FilamentBuilderExtensions = api

    // Keyboard (`->clipboardShortcuts()`): ⌘C copies the item a click selected, ⌘V pastes
    // after it (Ctrl+C / Ctrl+V on other systems). In a field, or with text selected, the
    // keys stay the browser's.
    const isApple = /Mac|iPhone|iPad/.test(navigator.platform ?? '')

    /**
     * The selected item, by where it lives — its Livewire component, its list's state path,
     * its key —, not by its element, which a render may change or put another in its place:
     * the element is looked up when a key needs it, and a style rule frames whichever it is.
     */
    let selection = null

    const frame = document.createElement('style')

    const shortcutsOf = (wrapper) => {
        try {
            return JSON.parse(wrapper.dataset.clipboardShortcuts)
        } catch (error) {
            return null
        }
    }

    /** The wrapper of the list `item` is an item of, when that list takes shortcuts. */
    const shortcutWrapperOf = (item) => {
        const wrapper = item?.parentElement?.closest('[data-clipboard-shortcuts]')

        return wrapper && listOf(wrapper) === item.parentElement ? wrapper : null
    }

    /** The innermost item around `node` whose list takes shortcuts. */
    const selectableItemOf = (node) => {
        for (let item = node?.closest?.('[x-sortable-item]'); item; item = item.parentElement?.closest('[x-sortable-item]')) {
            if (shortcutWrapperOf(item)) {
                return item
            }
        }

        return null
    }

    const isEditable = (node) =>
        !!node?.closest?.('input, textarea, select, [contenteditable]:not([contenteditable="false"])')

    /** The item at `selection` in the page, as the list renders it: in a table repeater, a row of its table. */
    const selector = ({ component, path, key }) => {
        const wrapper = `[wire\\:id="${CSS.escape(component)}"] [data-clipboard-shortcuts][data-state-path="${CSS.escape(path)}"]`
        const item = `[x-sortable-item="${CSS.escape(key)}"]`

        return `${wrapper} > [x-sortable] > ${item}, ${wrapper} > table > [x-sortable] > ${item}`
    }

    const selected = () => (selection ? document.querySelector(selector(selection)) : null)

    const select = (next) => {
        if ((next?.component === selection?.component) && (next?.path === selection?.path) && (next?.key === selection?.key)) {
            return
        }

        selection = next
        frame.textContent = next
            ? `${selector(next)} { outline: 2px solid var(--primary-500, rgb(245 158 11)); outline-offset: 2px; }`
            : ''

        // Navigating (`wire:navigate`) renews the head.
        if (!frame.isConnected) {
            document.head.append(frame)
        }
    }

    const selectionOf = (item) => {
        const wrapper = item ? shortcutWrapperOf(item) : null
        const component = wrapper ? componentOf(wrapper)?.getAttribute('wire:id') : null

        return component
            ? { component, path: wrapper.dataset.statePath, key: item.getAttribute('x-sortable-item') }
            : null
    }

    // A click on an item, outside its fields, selects it; any other click lets it go.
    document.addEventListener(
        'pointerdown',
        (event) => select(isEditable(event.target) ? null : selectionOf(selectableItemOf(event.target))),
        true,
    )

    // So does the focus moving on, say into a modal — not into the item or the proxy, nor onto
    // an element around the item, which a click inside it may focus.
    document.addEventListener('focusin', (event) => {
        const item = selected()

        if (
            item &&
            !item.contains(event.target) &&
            !event.target.contains?.(item) &&
            !('builderExtensionsProxy' in (event.target.dataset ?? {}))
        ) {
            select(null)
        }
    })

    /** The letter a shortcut names: on a layout without Latin letters, the key in its place. */
    const letterOf = (event) =>
        /^[a-z]$/i.test(event.key ?? '') ? event.key.toLowerCase() : { KeyC: 'c', KeyV: 'v' }[event.code]

    const copyFromKeyboard = ($wire, copy, path, wrapper) => {
        const payload = payloadOf($wire, { ...copy, path })

        remember(copy.kind, payload)

        // The browser's own copy of the proxy's selection follows this keydown.
        withProxy(payload, wrapper, (proxy, release) => {
            proxy.addEventListener('copy', () => setTimeout(release))
            setTimeout(release, 500)
        })

        notify(copy.notification, 'success')
    }

    const pasteFromKeyboard = ($wire, paste, after, wrapper) => {
        const pastedInto = selection

        // The browser's own paste into the proxy follows this keydown; a browser that sends no
        // paste event there reads the clipboard as the button does.
        withProxy('', wrapper, (proxy, release) => {
            const finish = (text) => {
                if (!release()) {
                    return
                }

                // The action answers with the pasted item's key: that item is the selected one then.
                Promise.resolve(api.paste($wire, { ...paste, arguments: { ...paste.arguments, item: after } }, text)).then((key) => {
                    if ((key !== null) && (key !== undefined) && (selection === pastedInto)) {
                        select({ ...pastedInto, key: String(key) })
                    }
                })
            }

            proxy.addEventListener('paste', (event) => {
                event.preventDefault()
                finish(event.clipboardData?.getData('text/plain') ?? '')
            })

            setTimeout(() => finish(null), 500)
        })
    }

    document.addEventListener(
        'keydown',
        (event) => {
            if (!selection || event.isComposing) {
                return
            }

            const item = selected()

            if (!item) {
                select(null)

                return
            }

            if (event.key === 'Escape' && !isEditable(event.target)) {
                select(null)

                return
            }

            const letter = letterOf(event)

            if (!(isApple ? event.metaKey : event.ctrlKey) || event.shiftKey || event.altKey || (letter !== 'c' && letter !== 'v')) {
                return
            }

            if (isEditable(event.target) || (letter === 'c' && String(window.getSelection?.() ?? '') !== '')) {
                return
            }

            const wrapper = shortcutWrapperOf(item)
            const shortcuts = wrapper ? shortcutsOf(wrapper) : null
            const action = letter === 'c' ? shortcuts?.copy : shortcuts?.paste

            // An item action acts only where the item shows it.
            if (!action || (action.items && !action.items.map(String).includes(selection.key))) {
                return
            }

            const $wire = window.Livewire.find(selection.component)

            if (letter === 'c') {
                copyFromKeyboard($wire, action, `${selection.path}.${selection.key}`, wrapper)
            } else {
                pasteFromKeyboard($wire, action, selection.key, wrapper)
            }
        },
        true,
    )
})()
