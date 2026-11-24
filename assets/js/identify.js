/**
 * THE FIND'S „BESTIMMUNG" WITHOUT A KEY TOOL (pages/identify.php) — what
 * every account may see to the name of a find: the Internet-Links
 * (weblinks.js) and the finds of the species with a picture
 * (speciesfinds.js).
 *
 * (24.11.2026: „Bestimmung hat ja Artporträts, hat Links und eben auch
 * Schlüssel … bei denen, die das Modul nicht freigegeben haben, eben nur
 * die Links … und die Funde könnten wir quasi immer anzeigen".) Where a
 * module offers a key tool for the account, its page stands in this one's
 * place (includes/modules.php, key_tool) and shows the same two beside its
 * own content.
 *
 * NOT identification.js: that one reads the open marks of a title (cf.,
 * aff., sp.) and has nothing to do with this page.
 *
 * THE TABS ARE DRAWN HERE, not printed by the page: tabs.js wires every
 * tab bar it finds at load and keeps its choice in the address and the
 * settings — right for the administration, wrong for a page inside a
 * frame. Two tabs need a click handler and nothing else. The links load at
 * once, they are the first tab; the finds are asked for at once too, so
 * their count stands on the tab before one looks („wenn hinter Funde
 * Klammer 0 steht, dann weiß ich, dass ich das nicht anklicken brauche",
 * 01.11.2026).
 */

import { t } from './i18n.js';
import { iconHtml } from './icons.js';
import { toast } from './toast.js';
import { fillWebLinks } from './weblinks.js';
import { loadSpeciesFinds, speciesFindsCount, speciesFindsHtml, wireSpeciesFinds, olderPictureCaption } from './speciesfinds.js';
import { mountFigureWindow } from './figurewindow.js';
import { archiveExporter } from './archiveexport.js';

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const root = document.getElementById('identify');
if (root) {
    const { name } = root.dataset;
    const species = root.dataset.species === '1';
    const embedded = root.dataset.embedded === '1' && window.parent !== window;
    const current = Number(root.dataset.find) || 0;

    /* A genus has links but no finds of its own to show: the finds are
       matched by the species in a find's title. One tab alone is no tab
       bar — a head over a single block names it twice. */
    const tabs = [['links', 'link', t('Internet-Links')], ...(species ? [['finds', 'photo', t('Funde')]] : [])];
    root.insertAdjacentHTML('beforeend', `
        ${tabs.length > 1 ? `<div class="tab identify-tabs" role="tablist" data-tabs="row">${tabs.map(([which, symbol, label], i) =>
            `<button type="button" role="tab" data-identify-tab="${which}" aria-selected="${i === 0}">${iconHtml(symbol)} <span>${esc(label)}</span></button>`).join('')}</div>` : ''}
        ${tabs.map(([which], i) => `<div role="tabpanel" data-identify-panel="${which}"${i === 0 ? '' : ' hidden'}></div>`).join('')}`);

    const panel = (which) => root.querySelector(`[data-identify-panel="${which}"]`);
    fillWebLinks(panel('links'), { name, gbif: Number(root.dataset.gbif) || undefined, dgfm: Number(root.dataset.dgfm) || undefined });

    if (species) {
        const window_ = document.getElementById('keys-figure-window');
        const figures = window_ ? mountFigureWindow(window_, { caption: olderPictureCaption, exportPicture: archiveExporter() }) : null;
        const finds = panel('finds');
        const fill = (answer) => {
            finds.innerHTML = speciesFindsHtml(answer, { name, embedded, current });
            wireSpeciesFinds(finds, figures);
            const label = root.querySelector('[data-identify-tab="finds"] span');
            if (label) label.textContent = t('Funde (%d)', speciesFindsCount(answer));
        };
        const ask = () => {
            finds.innerHTML = `<p class="hint-text small">${esc(t('Wird geladen …'))}</p>`;
            return loadSpeciesFinds(name).then(fill);
        };
        // At once, for the count; a failure waits for the first look and says then what went wrong.
        let failed = false;
        ask().catch(() => { failed = true; finds.innerHTML = ''; });

        root.addEventListener('click', (e) => {
            const tab = e.target.closest('[data-identify-tab]');
            if (!tab) return;
            for (const b of root.querySelectorAll('[data-identify-tab]')) b.setAttribute('aria-selected', String(b === tab));
            for (const p of root.querySelectorAll('[data-identify-panel]')) p.hidden = p.dataset.identifyPanel !== tab.dataset.identifyTab;
            if (tab.dataset.identifyTab === 'finds' && failed) {
                failed = false;
                ask().catch((err) => { failed = true; finds.innerHTML = ''; toast(err.message, 'error'); });
            }
        });
    }
}
