/**
 * „Internet-Links" to a species — a button, and under it the grouped list
 * of web addresses (api/v1/web_links.php, includes/web_links.php).
 *
 * 10.10.2026: in „Bestimmung", where the species portraits stand
 * once a key has led to a species, „zusätzlich … eine Linkliste … hinter
 * einem Button, dass es nicht gleich alles live geladen werden muss". So
 * the list is fetched on the FIRST press only, and kept after that.
 *
 * ONE PIECE FOR ANY PAGE: webLinksButton() returns an element the
 * caller places where it wants; it brings its own list and needs nothing
 * from the page but the species.
 *
 * NO api.js (found on 10.10.2026): api.js reads
 * window.START_DATA.baseUrl the moment it loads, and on a page without
 * START_DATA that killed the whole importing module — the Bestimmung stayed
 * blank. A GET needs neither the form token nor the queue, so the address
 * comes from where this file itself lies (assets/js/ → api/v1/), and the
 * button works on any page that loads it.
 *
 * THE GROUPS are Pilz-Link's: Suche, Verbreitungskarte, Porträt,
 * Wissenschaftlich, DNA, Weitere. Every link opens in a new tab.
 */

import { t } from './i18n.js';
import { icon } from './icons.js';

/**
 * @param {{name?: string, gbif?: number, dgfm?: number}} species
 * @returns {HTMLElement}
 */
export function webLinksButton(species) {
    const box = document.createElement('div');
    box.className = 'web-links';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'button subtle small';
    button.setAttribute('aria-expanded', 'false');
    button.append(icon('link'), document.createTextNode(t('Internet-Links')));

    const list = document.createElement('div');
    list.className = 'web-links-list';
    list.hidden = true;

    let loaded = false;
    button.addEventListener('click', async () => {
        const open = list.hidden;
        list.hidden = !open;
        button.setAttribute('aria-expanded', String(open));
        if (!open || loaded) return;
        loaded = true;
        loaded = await fillWebLinks(list, species);
    });

    box.append(button, list);
    return box;
}

/**
 * Fetch the list into an element of the caller's — for a page that has its
 * own place for it instead of the button (the Bestimmung shows it as a tab
 * beside the portraits). Says whether it worked, so a caller can
 * try again on the next look.
 *
 * @param {HTMLElement} list
 * @param {{name?: string, gbif?: number, dgfm?: number}} species
 * @returns {Promise<boolean>}
 */
export async function fillWebLinks(list, species) {
    list.classList.add('web-links-list');
    list.textContent = t('Lädt …');
    try {
        const params = new URLSearchParams();
        if (species.name) params.set('name', species.name);
        if (species.gbif) params.set('gbif', String(species.gbif));
        if (species.dgfm) params.set('dgfm', String(species.dgfm));
        const response = await fetch(new URL(`../../api/v1/web_links.php?${params}`, import.meta.url),
            { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const answer = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(answer.error ?? String(response.status));
        draw(list, answer.groups ?? []);
        return true;
    } catch (error) {
        list.textContent = t('Die Links ließen sich nicht laden: %s', error.message);
        return false;
    }
}

function draw(list, groups) {
    list.textContent = '';
    if (groups.length === 0) {
        list.textContent = t('Zu dieser Art sind keine Links bekannt.');
        return;
    }
    for (const g of groups) {
        const head = document.createElement('h4');
        head.className = 'web-links-head';
        head.textContent = t(g.category);
        const chips = document.createElement('div');
        chips.className = 'web-links-chips';
        for (const link of g.links) {
            if (link.multi) {
                // One chip for a site with several pages, its parts inside.
                const chip = document.createElement('span');
                chip.className = 'web-links-chip is-multi';
                chip.append(document.createTextNode(link.label));
                for (const part of link.multi) chip.append(anchor(part.label, part.url));
                chips.append(chip);
            } else {
                const a = anchor(link.label, link.url);
                a.classList.add('web-links-chip');
                chips.append(a);
            }
        }
        list.append(head, chips);
    }
}

function anchor(label, url) {
    const a = document.createElement('a');
    a.href = url;
    a.target = '_blank';
    a.rel = 'noopener';
    a.textContent = label;
    return a;
}
