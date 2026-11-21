/**
 * A find's other files — the sub-tab „Dateien" under „Belege"
 * (05.10.2026; includes/point_files.php says why and where they
 * lie).
 *
 * „Ein komplett komfortabler Tab": files and whole folders dropped in or
 * picked, sent one after another straight from the browser into the
 * person's cloud (cloudsend.js, the recordings' way — this server never
 * holds the bytes), a list like a small file browser with the folders as
 * they were dropped, and per file a look where one goes — a text read in a
 * window, a picture larger, a PDF in a tab of its own —, a download, and
 * a delete.
 *
 * ITS OWN MODULE AND NOT A BLOCK IN map.js (the gradual split, 24.09.2026):
 * it knows nothing of points but the id the host hands it, and the host
 * learns from it only how many files there are.
 */

import { t } from './i18n.js';
import { iconHtml } from './icons.js';
import { sendToCloud } from './cloudsend.js';
import { confirm } from './dialogs.js';
import { toast } from './toast.js';
import { readableDate } from './dates.js';

/**
 * @param {{request: Function, bytes: (n: number) => string,
 *          count: (n: number) => void, baseUrl: string,
 *          changed?: (files: object[]) => void}} host
 *   changed: the list as it stands after every drawing — the Fundbrowser's
 *   overview shows the same files to open and fetch (finds.js, drawFace).
 */
export function mountPointFiles(host) {
    const panel       = document.getElementById('d-subpanel-files');
    const tree        = document.getElementById('d-files');
    const drop        = document.getElementById('d-files-drop');
    const input       = document.getElementById('d-files-input');
    const folderInput = document.getElementById('d-files-folder');
    const progress    = document.getElementById('d-files-progress');
    const progressText = document.getElementById('d-files-progress-text');
    const progressBar  = document.getElementById('d-files-progress-bar');
    const preview     = document.getElementById('file-preview');
    if (!panel || !tree) return { show() {}, view() {}, download() {}, lookable: () => false };

    let pointId = 0;
    let files = [];
    let busy = false;
    // The folders opened — by their path; a new find starts folded.
    const opened = new Set();

    /** The tab's content for a find — null documents means none to offer. */
    function show(id, documents) {
        if (id !== pointId) opened.clear();
        pointId = id;
        files = documents ?? [];
        draw();
    }

    /* --- The list ------------------------------------------------------------
       FOLDERS FIRST, THEN FILES, each by name — a tree built from the paths
       the rows carry; the folders are rows of their own with the fold
       triangle and the number of files beneath, indented by --depth like
       every tree here. */
    function draw() {
        host.count(files.length);
        host.changed?.(files);
        tree.textContent = '';
        const root = { folders: new Map(), files: [] };
        for (const f of files) {
            let node = root;
            let path = '';
            for (const part of (f.dir || '').split('/').filter(Boolean)) {
                path = path ? `${path}/${part}` : part;
                if (!node.folders.has(part)) node.folders.set(part, { path, folders: new Map(), files: [] });
                node = node.folders.get(part);
            }
            node.files.push(f);
        }
        const all = (node) => node.files.concat(...[...node.folders.values()].map(all));
        const walk = (node, depth) => {
            const names = [...node.folders.keys()].sort((a, b) => a.localeCompare(b, 'de'));
            for (const name of names) {
                const folder = node.folders.get(name);
                const inside = all(folder);
                tree.append(folderRow(name, folder.path, inside, depth));
                if (opened.has(folder.path)) walk(folder, depth + 1);
            }
            for (const f of [...node.files].sort((a, b) => a.name.localeCompare(b.name, 'de'))) {
                tree.append(fileRow(f, depth));
            }
        };
        walk(root, 0);
    }

    function folderRow(name, path, inside, depth) {
        const row = document.createElement('div');
        row.className = 'file-row is-folder';
        row.style.setProperty('--depth', depth);
        const open = opened.has(path);
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'file-name';
        toggle.setAttribute('aria-expanded', String(open));
        toggle.innerHTML = `<span class="fold${open ? ' is-open' : ''}">${iconHtml('fold-triangle')}</span>`
            + iconHtml('folder');
        toggle.append(textBlock(name, inside.length === 1 ? t('1 Datei') : t('%d Dateien', inside.length)));
        toggle.addEventListener('click', () => {
            if (opened.has(path)) opened.delete(path); else opened.add(path);
            draw();
        });
        row.append(toggle, actions([
            button('delete', t('Ordner löschen'), () => remove(inside.map(f => f.id),
                t('Der Ordner „%s" und die %d Dateien darin werden aus deiner Cloud gelöscht.', name, inside.length)),
                'file-delete'),
        ]));
        return row;
    }

    function fileRow(f, depth) {
        const row = document.createElement('div');
        row.className = 'file-row';
        row.style.setProperty('--depth', depth);
        const look = f.kind === 'image' || f.kind === 'text' || f.kind === 'pdf';   // the same as lookable() below
        const name = document.createElement(look ? 'button' : 'span');
        name.className = 'file-name';
        if (look) {
            name.type = 'button';
            name.title = t('Ansehen');
            name.addEventListener('click', () => view(f));
        }
        if (f.kind === 'image') {
            const img = document.createElement('img');
            img.className = 'file-thumb';
            img.loading = 'lazy';
            img.alt = '';
            img.src = `${host.baseUrl}pages/point_file.php?id=${f.id}`;
            img.addEventListener('error', () => img.replaceWith(iconElement('file')), { once: true });
            name.append(img);
        } else {
            name.append(iconElement('file'));
        }
        name.append(textBlock(f.name, [f.size != null ? host.bytes(f.size) : '', readableDate(f.uploaded_at)]
            .filter(Boolean).join(' · ')));
        // Drive keeps its files behind its own sign-in: there the file opens
        // in Drive, which offers the download itself.
        const drive = f.provider === 'gdrive';
        row.append(name, actions([
            button('import', drive ? t('In Google Drive öffnen') : t('Herunterladen'), () => fetchOut(f, 'download')),
            button('delete', t('Löschen'), () => remove([f.id], t('„%s" wird aus deiner Cloud gelöscht.', f.name)), 'file-delete'),
        ]));
        return row;
    }

    /* THE NAME ON ITS OWN LINE, size and day beneath it — side by side the
       sidebar left a raw file's name four letters (measured: „Bestim…"). */
    function textBlock(label, meta) {
        const block = document.createElement('span');
        block.className = 'file-text';
        const top = document.createElement('span');
        top.className = 'file-label';
        top.textContent = label;
        top.title = label;
        const below = document.createElement('span');
        below.className = 'file-meta';
        below.textContent = meta;
        block.append(top, below);
        return block;
    }

    function iconElement(name) {
        const span = document.createElement('span');
        span.className = 'file-icon';
        span.innerHTML = iconHtml(name);
        return span;
    }

    function actions(buttons) {
        const box = document.createElement('span');
        box.className = 'file-actions';
        box.append(...buttons);
        return box;
    }

    function button(iconName, label, act, kind = '') {
        const b = document.createElement('button');
        b.type = 'button';
        // The rows' small flat symbols, as at the sequences (map.css).
        b.className = `symbol-button small${kind ? ' ' + kind : ''}`;
        b.title = label;
        b.setAttribute('aria-label', label);
        b.innerHTML = iconHtml(iconName);
        b.addEventListener('click', act);
        return b;
    }

    /* --- Looking, fetching, deleting --------------------------------------- */

    function form(fields) {
        const body = new FormData();
        for (const [key, value] of Object.entries(fields)) body.append(key, String(value ?? ''));
        return body;
    }

    async function view(f) {
        if (f.kind === 'pdf') {
            fetchOut(f, 'view');
            return;
        }
        const nameEl = document.getElementById('file-preview-name');
        const textEl = document.getElementById('file-preview-text');
        const imageEl = document.getElementById('file-preview-image');
        nameEl.textContent = f.name;
        textEl.hidden = true;
        imageEl.hidden = true;
        if (f.kind === 'image') {
            imageEl.src = `${host.baseUrl}pages/point_file.php?id=${f.id}&large=1`;
            imageEl.hidden = false;
            preview.showModal();
            return;
        }
        try {
            const answer = await host.request(`point_files.php?id=${pointId}&action=text`,
                { method: 'POST', body: form({ file: f.id }) });
            textEl.textContent = answer.text;
            textEl.hidden = false;
            preview.showModal();
        } catch (e) {
            toast(e.message, 'error');
        }
    }
    preview?.addEventListener('close', () => {
        document.getElementById('file-preview-image').removeAttribute('src');
        document.getElementById('file-preview-text').textContent = '';
    });

    /**
     * A file out of the cloud and into the browser — never through this
     * server (point_file_way says how per provider).
     *
     * pCLOUD'S LINK THE BROWSER ASKS FOR ITSELF, the link being made out
     * to the asking IP; with forcedownload the answer is a download, and a
     * frame nobody sees takes it without leaving the page. Everything else
     * goes to a tab of its own, OPENED AT ONCE, inside the click — a tab
     * opened after an await is a popup the browser blocks.
     */
    async function fetchOut(f, mode) {
        const quiet = f.provider === 'pcloud' && mode === 'download';
        const tab = quiet ? null : window.open('about:blank', '_blank');
        try {
            const way = await host.request(`point_files.php?id=${pointId}&action=way`,
                { method: 'POST', body: form({ file: f.id }) });
            let url = way.url;
            if (way.kind === 'pcloud') {
                const ask = new URLSearchParams({ access_token: way.token, fileid: way.fileid,
                    forcedownload: mode === 'download' ? '1' : '0' });
                const answer = await (await fetch(`${way.url}?${ask}`)).json();
                if (answer.result !== 0 || !answer.hosts?.length) {
                    throw new Error(answer.error || t('pCloud gibt die Datei nicht heraus.'));
                }
                url = `https://${answer.hosts[0]}${answer.path}`;
            }
            if (!url) throw new Error(t('Die Cloud nennt keinen Weg zur Datei.'));
            if (tab) {
                tab.location.href = url;
            } else {
                const frame = document.createElement('iframe');
                frame.hidden = true;
                frame.src = url;
                document.body.append(frame);
                setTimeout(() => frame.remove(), 60000);
            }
        } catch (e) {
            tab?.close();
            toast(e.message, 'error');
        }
    }

    async function remove(ids, text) {
        const yes = await confirm({ title: t('Löschen?'), text, button: t('Löschen'), danger: true });
        if (!yes) return;
        try {
            const answer = await host.request(`point_files.php?id=${pointId}&action=delete`,
                { method: 'POST', body: form({ ids: ids.join(',') }) });
            files = answer.files ?? [];
            draw();
            toast(ids.length === 1 ? t('Gelöscht.') : t('%d Dateien gelöscht.', ids.length));
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    /* --- Uploading ----------------------------------------------------------
       ONE AFTER ANOTHER, with one bar for the whole batch — a raw set is a
       few large files, and two at once would only halve each one's line.
       A file that fails is named at the end; the others go on. Hidden
       files of the operating system (.DS_Store, ._…) stay home. */
    async function upload(entries) {
        entries = entries.filter(({ file }) => !file.name.startsWith('.'));
        if (busy || pointId <= 0 || entries.length === 0) return;
        busy = true;
        const at = pointId;
        const total = entries.reduce((sum, { file }) => sum + file.size, 0) || 1;
        let done = 0;
        const failed = [];
        progress.hidden = false;
        for (const [i, { file, dir }] of entries.entries()) {
            const say = (share) => {
                progressText.textContent = t('%d von %d: %s', i + 1, entries.length, file.name);
                progressBar.value = (done + share * file.size) / total;
            };
            say(0);
            try {
                const target = await host.request(`point_files.php?id=${at}&action=target`, {
                    method: 'POST',
                    body: form({ name: file.name, type: file.type, size: file.size,
                                 mtime: Math.floor((file.lastModified || 0) / 1000), dir }),
                });
                const up = await sendToCloud(target, file, say);
                const answer = await host.request(`point_files.php?id=${at}&action=record`, {
                    method: 'POST',
                    body: form({ fileid: up.fileid, folderid: target.folderid, name: up.name,
                                 size: up.size ?? file.size, type: file.type, dir: target.dir ?? dir }),
                });
                if (pointId === at) {
                    files = answer.files ?? files;
                    draw();
                }
            } catch (e) {
                console.warn('Datei nicht hochgeladen:', file.name, e);
                failed.push(file.name);
            }
            done += file.size;
        }
        busy = false;
        progress.hidden = true;
        if (failed.length > 0) {
            toast(t('%d von %d Dateien gingen nicht hoch: %s', failed.length, entries.length,
                failed.slice(0, 3).join(', ') + (failed.length > 3 ? ' …' : '')), 'error');
        } else {
            toast(entries.length === 1 ? t('Hochgeladen.') : t('%d Dateien hochgeladen.', entries.length));
        }
    }

    /** The folder part of a picked folder's relative path. */
    const dirOf = (relative) => relative.split('/').slice(0, -1).join('/');

    /**
     * What was dropped — files, and folders read through with their paths.
     * THE ENTRIES ARE TAKEN AT ONCE: the drop's item list is emptied the
     * moment the handler awaits anything, so every root is fetched before
     * the first folder is read.
     */
    async function dropped(transfer) {
        const roots = [...(transfer.items ?? [])]
            .filter((item) => item.kind === 'file')
            .map((item) => item.webkitGetAsEntry?.())
            .filter(Boolean);
        if (roots.length === 0) return [...(transfer.files ?? [])].map((file) => ({ file, dir: '' }));
        const out = [];
        const walk = async (entry, dir) => {
            if (entry.isFile) {
                out.push({ file: await new Promise((ok, no) => entry.file(ok, no)), dir });
                return;
            }
            if (!entry.isDirectory) return;
            const path = dir ? `${dir}/${entry.name}` : entry.name;
            const reader = entry.createReader();
            // readEntries gives a hundred at a time — asked until it is empty.
            for (;;) {
                const batch = await new Promise((ok, no) => reader.readEntries(ok, no));
                if (batch.length === 0) break;
                for (const child of batch) await walk(child, path);
            }
        };
        for (const root of roots) await walk(root, '');
        return out;
    }

    document.getElementById('d-files-pick')?.addEventListener('click', () => input.click());
    document.getElementById('d-files-pick-folder')?.addEventListener('click', () => folderInput.click());
    input?.addEventListener('change', () => {
        upload([...input.files].map((file) => ({ file, dir: '' })));
        input.value = '';
    });
    folderInput?.addEventListener('change', () => {
        upload([...folderInput.files].map((file) => ({ file, dir: dirOf(file.webkitRelativePath || '') })));
        folderInput.value = '';
    });

    // THE WHOLE PANEL TAKES THE DROP, not only the box — a file let go a
    // little beside it should not open in the browser instead.
    const carriesFiles = (e) => [...(e.dataTransfer?.types ?? [])].includes('Files');
    panel.addEventListener('dragover', (e) => {
        if (!carriesFiles(e)) return;
        e.preventDefault();
        drop.classList.add('is-over');
    });
    panel.addEventListener('dragleave', (e) => {
        if (!panel.contains(e.relatedTarget)) drop.classList.remove('is-over');
    });
    panel.addEventListener('drop', async (e) => {
        if (!carriesFiles(e)) return;
        e.preventDefault();
        drop.classList.remove('is-over');
        upload(await dropped(e.dataTransfer));
    });

    return {
        show,
        /** A file shown where one goes — a text or a picture in the window, a PDF in a tab. */
        view,
        /** A file fetched out of the cloud into the browser. */
        download: (f) => fetchOut(f, 'download'),
        /** Whether a file can be looked at here, or only fetched. */
        lookable: (f) => f.kind === 'image' || f.kind === 'text' || f.kind === 'pdf',
    };
}
