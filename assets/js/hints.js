/**
 * Verwaltung → Gemeinsam → „Direkt geteilt" (includes/hints_content.php
 * says what stands there and why).
 *
 * THE SAME DOOR AS THE POINT WINDOW'S TILE: one tick, one friend, saved at
 * once through api/v1/hints.php, and the last tick asks before the find is
 * shown to nobody any more. The fold shows the unticked friends; shut, a
 * row names only the friends who see the find.
 *
 * Bound by administration.js for every fragment that arrives (onTabContent),
 * so the lazily loaded tab finds its listeners too.
 */

import { request } from './api.js';
import { confirm } from './dialogs.js';
import { toast } from './toast.js';
import { t } from './i18n.js';

export function mountHints(root) {
    for (const row of root.querySelectorAll('.hint-row[data-hint-point]')) {
        if (row.dataset.bound) continue;
        row.dataset.bound = '1';
        const fold = row.querySelector('.hint-fold');
        const shut = () => {
            const open = fold.getAttribute('aria-expanded') === 'true';
            for (const label of row.querySelectorAll('.hint-friend')) {
                label.hidden = !open && !label.querySelector('input').checked;
            }
            fold.classList.toggle('is-open', open);
            const words = open ? t('Nur die Freunde, mit denen du ihn teilst') : t('Alle Freunde aufklappen');
            fold.title = words;
            fold.setAttribute('aria-label', words);
        };
        fold.addEventListener('click', () => {
            fold.setAttribute('aria-expanded', String(fold.getAttribute('aria-expanded') !== 'true'));
            shut();
        });
        for (const input of row.querySelectorAll('.hint-friend input')) {
            input.addEventListener('change', () => toggle(row, input, shut));
        }
    }
    for (const button of root.querySelectorAll('.hint-leave')) {
        if (button.dataset.bound) continue;
        button.dataset.bound = '1';
        button.addEventListener('click', () => leave(button));
    }
}

async function toggle(row, input, shut) {
    const on = input.checked;
    const name = input.dataset.name;
    const ticked = row.querySelectorAll('.hint-friend input:checked').length;
    if (!on && ticked === 0) {
        const ok = await confirm({
            title: t('Mit niemandem mehr teilen?'),
            text: t('%s sieht diesen Punkt dann nicht mehr unter „Direkt geteilt".', name),
            button: t('Nicht mehr teilen'),
            danger: true,
        });
        if (!ok) {
            input.checked = true;
            return;
        }
    }
    input.disabled = true;
    try {
        const answer = await request('hints.php', { method: 'POST',
            body: JSON.stringify({ point_id: Number(row.dataset.hintPoint), friend_id: Number(input.dataset.friend), on }) });
        toast(on ? t('%s sieht diesen Punkt jetzt unter „Direkt geteilt".', name) : t('%s sieht diesen Punkt nicht mehr unter „Direkt geteilt".', name));
        if ((answer?.recipients ?? []).length === 0) {
            // Shown to nobody: the row has nothing left to say.
            const list = row.parentElement;
            row.remove();
            if (list && !list.querySelector('.hint-row')) {
                list.hidden = true;
                list.parentElement.querySelector('[data-hint-given-empty]')?.removeAttribute('hidden');
            }
            return;
        }
    } catch (e) {
        input.checked = !on;
        toast(e?.message ?? t('Speichern fehlgeschlagen.'), 'error');
    }
    input.disabled = false;
    shut();
}

async function leave(button) {
    const row = button.closest('[data-hint-point]');
    const who = button.dataset.giver;
    const ok = await confirm({
        title: t('Aus „Direkt geteilt" entfernen?'),
        text: t('Der Punkt verschwindet aus „Direkt geteilt". Liegt er in einer Liste, die %s mit dir teilt, siehst du ihn dort weiter. Wieder direkt teilen kann ihn nur %s.', who, who),
        button: t('Entfernen'),
        danger: true,
    });
    if (!ok) return;
    try {
        await request('hints.php', { method: 'POST',
            body: JSON.stringify({ action: 'leave', point_id: Number(row.dataset.hintPoint) }) });
    } catch (e) {
        toast(t('Der Punkt ließ sich nicht aus „Direkt geteilt" entfernen: %s', e.message), 'error');
        return;
    }
    toast(t('Aus „Direkt geteilt" entfernt.'));
    const list = row.parentElement;
    row.remove();
    if (list && !list.querySelector('[data-hint-point]')) {
        list.hidden = true;
        list.parentElement.querySelector('[data-hint-received-empty]')?.removeAttribute('hidden');
    }
}
