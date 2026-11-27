/* Session IDs come from the existing IO/Ceres basket lifecycle. Product markup
 * contains only public variation choices. One dialog serves all product surfaces. */
O46Core.wishPicker = (function () {
    const selector = '[data-wish-product], [data-wish-entry]';
    const products = new Map();
    let saved = null, pending = null, dialog = null, session = 0;
    let modal, template, labels, fields, status, save, title, content;
    const noticeTimers = new WeakMap();

    function notice(product, message) {
        clearTimeout(noticeTimers.get(product));
        product.notice.textContent = message;
        if (message) noticeTimers.set(product, setTimeout(() => { product.notice.textContent = ''; }, 5000));
    }

    function selectedId(product) {
        if (!product.article) return '';
        const selected = [...product.article.querySelectorAll('[data-eg-attributes]')].map(group =>
            [group.dataset.egAttributes, group.querySelector('[data-selected]')?.dataset.egValue]);
        if (!selected.length || selected.some(([, value]) => !value)) return product.linked || '';
        const matches = product.ids.filter(id => {
            const attributes = product.rows[id][1];
            return Object.keys(attributes).length === selected.length &&
                selected.every(([key, value]) => String(attributes[key]) === value);
        });
        return matches.length === 1 ? matches[0] : matches.includes(product.linked) ? product.linked : '';
    }

    function renderHeart(product) {
        const {heart, ids} = product;
        const active = selectedId(product);
        const markedIds = saved ? ids.filter(id => product.rows[id][0] && saved.has(id)) : [];
        const marked = active ? markedIds.includes(active) : markedIds.length > 0;
        if (saved) heart.setAttribute('aria-pressed', String(marked));
        else heart.removeAttribute('aria-pressed');
        heart.setAttribute('aria-label', (marked ? labels.remove : labels.add) + (product.title ? ' · ' + product.title : ''));
        const busy = !!pending && ids.includes(pending.id);
        heart.setAttribute('aria-busy', String(busy || !saved));
        heart.setAttribute('aria-disabled', String(!!pending));
        const needsChoice = !active && ids.length > 1 && (!marked || markedIds.length > 1);
        if (needsChoice) {
            heart.setAttribute('aria-haspopup', 'dialog');
            heart.setAttribute('aria-controls', modal.id);
        } else {
            heart.removeAttribute('aria-haspopup');
            heart.removeAttribute('aria-controls');
        }
    }

    // The list page owns product documents in Vuex. Remove confirmed rows locally.
    // Only a newly added, unknown ID needs the list endpoint to supply its document.
    let listRequest = null;
    function reconcileList() {
        const store = window.ceresStore;
        const state = store?.state.wishList;
        if (!saved || !document.querySelector('[data-wish-list]') || !state?.isWishListInitiallyLoading || state.isLoading) return;
        const documents = state.wishListItems.filter(row => saved.has(String(row.id)));
        const inactive = (state.inactiveVariationIds || []).filter(id => saved.has(String(id)));
        if (documents.length !== state.wishListItems.length) store.commit('setWishListItems', documents);
        if (inactive.length !== state.inactiveVariationIds?.length) store.commit('setInactiveVariationIds', inactive);
        store.commit('setWishListIds', [...saved]);
        if (documents.length + inactive.length === saved.size || listRequest) return;
        const expected = new Set(saved);
        const owner = session;
        listRequest = expected;
        eGuardians.apiservice.get('/rest/io/itemWishList', {}, (response, code) => {
            listRequest = null;
            if (!saved) return;
            if (session !== owner) { reconcileList(); return; }
            if (saved.size !== expected.size || [...saved].some(id => !expected.has(id))) { reconcileList(); return; }
            const data = response?.data;
            if (code !== 200 || !Array.isArray(data?.documents)) return;
            const missing = data.inactiveVariationIds || [];
            const returned = new Set([...data.documents.map(row => row.id), ...missing].map(String));
            if (returned.size !== saved.size || [...saved].some(id => !returned.has(id))) {
                // Ceres can finish a pruning GET before this script mounts. Confirm the owner's IDs via IO.
                if ([...saved].some(id => !returned.has(id))) O46Core.basketHeader.refreshAfterWrite(true);
                return;
            }
            store.commit('setWishListItems', data.documents);
            store.commit('setInactiveVariationIds', missing);
        });
    }

    function sync(list) {
        if (list === null) { session++; pending = null; }
        const next = list === null ? null : new Set(list.map(String));
        if (next && saved && next.size === saved.size && [...next].every(id => saved.has(id))) return;
        saved = next;
        for (const product of products.values()) renderHeart(product);
        reconcileList();
        if (dialog) {
            if (!saved) closeDialog();
            else renderDialog();
        }
    }

    function write(product, id, remove) {
        if (pending || !saved || saved.has(id) !== remove || !product.rows[id] ||
            (!remove && (product.entry || !product.rows[id][0]))) return;
        const ticket = pending = {id, session};
        notice(product, '');
        for (const entry of products.values()) renderHeart(entry);
        if (dialog) { save.disabled = true; modal.setAttribute('aria-busy', 'true'); }
        function complete(response, code) {
            const ok = !response?.error && (remove ? code === 200 && response?.data === true :
                code === 201 && String(response?.data?.variationId) === id);
            if (pending !== ticket || session !== ticket.session) {
                // The write can finish after a new owner check. Read the current session without applying the old IDs.
                if (ok) O46Core.basketHeader.refreshAfterWrite(true);
                return;
            }
            pending = null;
            modal.removeAttribute('aria-busy');
            if (ok) {
                if (remove) saved.delete(id); else saved.add(id);
                if (dialog?.product === product) closeDialog();
                notice(product, window.messageServiceTranslations?.[remove ? 'wishlistminus' : 'wishlistplus'] || '');
                reconcileList();
                O46Core.basketHeader.refreshAfterWrite(true);
            } else if (dialog?.product === product) {
                dialog.error = labels.error;
            } else {
                notice(product, labels.error);
            }
            for (const entry of products.values()) renderHeart(entry);
            if (dialog) renderDialog();
        }
        const data = O46Core.basketHeader.wishParams({variationId:id, templateEvent:'tpl.item'});
        if (remove) eGuardians.apiservice.delete('/rest/io/itemWishList/' + id, data, complete);
        else eGuardians.apiservice.post('/rest/io/itemWishList/', data, complete);
    }

    // Build the picker only when opened. A leaf always points to one exact IO variation.
    function choiceGroups(ids, allIds, attributes, unit, filters, labels) {
        const compare = new Intl.Collator(document.documentElement.lang || 'de', {numeric:true, sensitivity:'base'});
        function sizeRank(text) {
            const match = /^(?:(\d+)X|(X*))([SML])(?=$|[\s(/-])/i.exec(text);
            if (!match) return null;
            const n = match[1] ? Number(match[1]) : match[2].length;
            return match[3].toUpperCase() === 'S' ? -1 - n : match[3].toUpperCase() === 'L' ? 1 + n : 0;
        }
        function numberSize(text) {
            const value = text.replace(/½/g,' 1/2').replace(/⅓/g,' 1/3').replace(/⅔/g,' 2/3').trim();
            const match = /^(\d+(?:[.,]\d+)?)(?:\s+(\d+)\/(\d+))?$/.exec(value);
            return match ? Number(match[1].replace(',','.')) + (match[2] ? match[2]/match[3] : 0) : null;
        }
        function sort(a,b) {
            for (const rank of [sizeRank, numberSize]) {
                const x = rank(a), y = rank(b);
                if (x !== null || y !== null) {
                    if (x === null) return 1;
                    if (y === null) return -1;
                    if (x !== y) return x-y;
                    break;
                }
            }
            return compare.compare(a,b);
        }
        const pack = {key:'pack', name:labels.pack, text:unit};
        const dimensions = [...attributes, pack].filter(dim => new Set(ids.map(dim.text)).size > 1);
        if (!dimensions.length && ids.length === 1) {
            const meaningful = attributes.find(dim => dim.text(ids[0]));
            if (meaningful) dimensions.push(meaningful);
        }
        const groups = [];
        let choices = ids;
        for (const [index, dim] of dimensions.entries()) {
            const options = new Map();
            for (const id of choices) {
                const text = dim.text(id) || labels.unspecified;
                if (!options.has(text)) options.set(text, []);
                options.get(text).push(id);
            }
            const leaf = index === dimensions.length - 1 && [...options.values()].every(group => group.length === 1);
            groups.push({name:dim.name, options:[...options.keys()].sort(sort).map(text => ({
                text, key:leaf ? 'id' : dim.key, value:leaf ? options.get(text)[0] : text, ids:options.get(text)
            }))});
            if (leaf || !options.has(filters[dim.key])) return groups;
            choices = options.get(filters[dim.key]);
        }
        // A catalog can contain identical public attributes and packs. Use a stable,
        // product-relative letter only for those rows, including in removal mode.
        function signature(id) { return JSON.stringify([...attributes.map(dim => dim.text(id)), unit(id)]); }
        function letter(index) {
            let result = '';
            do { result = String.fromCharCode(65 + index % 26) + result; index = Math.floor(index / 26) - 1; } while (index >= 0);
            return result;
        }
        if (choices.length) {
            const peers = new Map();
            for (const id of allIds) {
                const key = signature(id);
                if (!peers.has(key)) peers.set(key, []);
                peers.get(key).push(id);
            }
            groups.push({name:labels.variant, options:choices.map(id => {
                const same = peers.get(signature(id));
                const text = same.length > 1 ? labels.variant + ' ' + letter(same.indexOf(id)) :
                    attributes.map(dim => dim.text(id)).filter(Boolean).join(' · ') || unit(id) || labels.variant;
                return {text, key:'id', value:id, ids:[id]};
            })});
        }
        return groups;
    }


    function group(title) {
        const field = document.createElement('fieldset');
        const legend = document.createElement('legend');
        legend.textContent = title;
        field.append(legend);
        fields.append(field);
        return field;
    }
    function option(field, label, key, value, chosen) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'eg-wish-option';
        button.textContent = label;
        button.dataset.wishKey = key;
        button.dataset.wishValue = value;
        if (key === 'id') button.dataset.wishId = value;
        button.setAttribute('aria-pressed', String(chosen));
        field.append(button);
    }
    function renderDialog() {
        const {product, remove, filters} = dialog;
        const {rows, names} = product;
        let choices = product.ids.filter(id => rows[id][0] && saved.has(id) === remove);
        if (!choices.includes(dialog.selected)) dialog.selected = '';
        fields.replaceChildren();
        const attributes = Object.entries(names).map(([key, field]) => ({
            key, name:field.name, text:id => field.values?.[rows[id][1][key]] || ''
        }));
        const groups = choiceGroups(choices, product.ids, attributes, id => rows[id][2] || '', filters, labels);
        for (const {name, options} of groups) {
            const field = group(name);
            for (const {key, value, text} of options) {
                option(field, text, key, value, key === 'id' ? dialog.selected === value : filters[key] === value);
            }
        }
        content.classList.toggle('eg-wish-compact', choices.length <= 6);
        status.textContent = dialog.error || '';
        save.textContent = title.textContent = remove ? labels.remove : labels.add;
        save.disabled = !!pending || !dialog.selected;
    }

    function closeDialog() {
        if (!dialog?.parent) { $(modal).modal('hide'); return; }
        const {parent, product} = dialog;
        modal.querySelector('.modal-dialog').append(content);
        parent.original.hidden = false;
        parent.modal.setAttribute('aria-labelledby', parent.label);
        dialog = null;
        product.heart.focus({preventScroll:true});
    }

    function open(product) {
        product.heart.classList.remove('eg-wish-hover');
        if (pending || dialog || product.reading) return;
        notice(product, '');
        if (!saved) {
            product.reading = true;
            O46Core.basketHeader.refresh().then(ok => {
                product.reading = false;
                if (!ok) notice(product, labels.error);
                else if (product.heart.isConnected && !pending && !dialog) open(product);
            });
            return;
        }
        const active = selectedId(product);
        const marked = product.ids.filter(id => product.rows[id][0] && saved.has(id));
        const remove = active ? marked.includes(active) : marked.length > 0;
        const id = active || (remove && marked.length === 1 ? marked[0] : product.ids.length === 1 ? product.ids[0] : '');
        if (id) {
            if (!remove && !product.rows[id][0]) notice(product, labels.unavailable);
            else write(product, id, remove);
            return;
        }
        if (!remove && !product.ids.some(id => product.rows[id][0])) { notice(product, labels.unavailable); return; }
        dialog = {product, remove, filters: {}, selected: '', error: ''};
        renderDialog();
        const parent = product.heart.closest('.modal.show');
        if (parent) {
            const original = parent.querySelector('.modal-content');
            dialog.parent = {modal:parent, original, label:parent.getAttribute('aria-labelledby')};
            original.hidden = true;
            parent.setAttribute('aria-labelledby', title.id);
            original.after(content);
            (fields.querySelector('button') || content.querySelector('button')).focus();
        } else $(modal).modal('show', product.heart);
    }

    function mount(host) {
        if (products.has(host)) return;
        const entry = host.dataset.wishEntry;
        const original = entry && host.querySelector('[data-testing="remove-wlist-item"]');
        if (entry && !original) return;
        const {rows, names} = entry ? {rows:{[entry]:[true,{}]}, names:{}} : JSON.parse(host.dataset.wishProduct);
        const ids = Object.keys(rows);
        if (!ids.length) return;
        const controls = template.content.cloneNode(true);
        const heart = controls.querySelector('button');
        const liveNotice = controls.querySelector('[data-wish-notice]');
        if (original) {
            original.hidden = true;
            original.after(controls);
        } else host.replaceChildren(controls);
        const article = host.closest('article.single');
        const card = host.closest('.cat-item');
        const product = {rows, names, ids, entry, article: card ? null : article,
            title: (card?.querySelector('.cat-item-name') || article?.querySelector('[data-eg-item-title]'))?.textContent.trim(),
            heart, notice: liveNotice};
        if (product.article && !host.closest('.modal')) {
            const direct = location.pathname.match(/(?:\/|_)([0-9]+)_([0-9]+)\/?$/);
            if (direct && direct[1] === article.dataset.mainitemid && direct[2] === article.dataset.egActiveVariation && rows[direct[2]]) {
                product.linked = direct[2];
            }
        }
        products.set(host, product);
        renderHeart(product);
    }
    function visit(node, fn) {
        if (node.nodeType !== 1) return;
        if (node.matches(selector)) fn(node);
        node.querySelectorAll(selector).forEach(fn);
    }
    function start() {
        modal = document.getElementById('eg-wish-picker');
        template = document.getElementById('eg-wish-control');
        if (!modal || !template) return;
        labels = modal.dataset;
        content = modal.querySelector('.modal-content');
        title = modal.querySelector('.modal-title');
        content.addEventListener('click', event => {
            if (dialog?.parent && event.target.closest('[data-dismiss="modal"]')) {
                event.stopPropagation();
                closeDialog();
            }
        });
        content.addEventListener('keydown', event => {
            if (dialog?.parent && event.key === 'Escape') {
                event.stopPropagation();
                closeDialog();
            }
        });
        $('.modal-cross').on('hide.bs.modal', () => { if (dialog?.parent) closeDialog(); });
        fields = modal.querySelector('[data-wish-choices]');
        status = modal.querySelector('[data-wish-status]');
        save = modal.querySelector('[data-wish-save]');
        fields.addEventListener('click', event => {
            const button = event.target.closest('[data-wish-key]');
            if (!dialog || pending || !button) return;
            const {wishKey, wishValue} = button.dataset;
            dialog.error = '';
            if (wishKey === 'id') dialog.selected = wishValue;
            else {
                const keys = [...Object.keys(dialog.product.names), 'pack'];
                for (const later of keys.slice(keys.indexOf(wishKey) + 1)) delete dialog.filters[later];
                dialog.filters[wishKey] = wishValue;
                dialog.selected = '';
            }
            renderDialog();
            [...fields.querySelectorAll('button')].find(el => el.dataset.wishKey === wishKey && el.dataset.wishValue === wishValue)?.focus();
        });
        save.addEventListener('click', () => { if (dialog) write(dialog.product, dialog.selected, dialog.remove); });
        $(modal).on('hidden.bs.modal', () => { dialog = null; });
        document.addEventListener('click', event => {
            const heart = event.target.closest?.('.eg-wish-heart');
            if (!heart) return;
            event.preventDefault();
            event.stopPropagation();
            const product = products.get(heart.closest(selector));
            if (product) open(product);
        });
        for (const name of ['pointerover', 'pointerout']) document.addEventListener(name, event => {
            const heart = event.target.closest?.('.eg-wish-heart');
            if (heart && !heart.contains(event.relatedTarget)) {
                heart.classList.toggle('eg-wish-hover', name === 'pointerover' && event.pointerType === 'mouse');
            }
        });
        visit(document.body, mount);
        const observer = new MutationObserver(changes => {
            for (const change of changes) {
                if (change.type === 'attributes') {
                    const host = change.target.closest('article.single')?.querySelector(selector);
                    const product = products.get(host);
                    if (product) {
                        product.linked = '';
                        renderHeart(product);
                    }
                } else {
                    for (const node of change.removedNodes) visit(node, host => { if (!host.isConnected) products.delete(host); });
                    for (const node of change.addedNodes) if (node.isConnected) visit(node, mount);
                }
            }
        });
        // Ceres may replace #page-body itself on first mount.
        observer.observe(document.body, {childList:true, subtree:true, attributes:true, attributeFilter:['data-selected']});
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true});
    else queueMicrotask(start);
    return {sync, reconcileList};
})();
