/**
 * Choose by pointing, with a guard against grazing — the rails' group
 * menus (13.11.2026: „man geht mit der Maus auf irgendwas drauf
 * und es wird automatisch gewechselt. Das hätte ich gerne auch links").
 *
 * THE CHANGE GUARD (the same day: „bin ich in der linken Leiste und fahre
 * nach rechts und streife aus Versehen noch den oberen oder unteren Punkt,
 * darf nicht sofort umgesprungen werden"). An item is chosen once the
 * pointer has rested on it for a moment. Running up or down the column
 * that moment is short (REST), so a hand flips through the groups as fast
 * as it moves. Heading out sideways — on its way to the pictures — the
 * item it grazes waits (REST_SIDEWAYS), and is dropped the moment the
 * pointer leaves it. While it waits it carries `is-waiting`.
 *
 * ONLY A MOUSE OR A PEN: a finger has no hover, and its tap is a click,
 * which chooses at once as it always did.
 *
 * WHICH WAY IS „OUT" depends on the rail's side: the left rails' pictures
 * lie to the right, the sliders of „Bearbeiten" to the left of its rail
 * (photowindow.js, railPage) — `away` names it.
 */
const REST = 110;
const REST_SIDEWAYS = 380;
// Pixels per millisecond to the right that count as heading out.
const OUTWARD = 0.15;

/**
 * @param {HTMLElement} menu  the column the items stand in
 * @param {(item: HTMLElement) => boolean} isChosen  whether it is chosen already
 * @param {(item: HTMLElement) => void} pick  choose it
 * @param {string} selector  the items
 * @param {'right'|'left'} away  where the content lies the pointer heads for
 */
export function hoverPick(menu, isChosen, pick, selector = 'button', away = 'right') {
    const sign = away === 'left' ? -1 : 1;
    let waiting = null;
    let timer = null;
    let last = null;
    let vx = 0;
    let vy = 0;
    const outward = () => sign * vx > OUTWARD && sign * vx > Math.abs(vy) * 1.2;
    const drop = () => {
        clearTimeout(timer);
        waiting?.classList.remove('is-waiting');
        waiting = null;
    };
    const wait = (item) => {
        drop();
        if (!item || isChosen(item)) return;
        waiting = item;
        item.classList.add('is-waiting');
        const arm = (ms) => {
            timer = setTimeout(() => {
                if (waiting !== item) return;
                // Still heading out: look again instead of switching.
                if (outward()) { arm(REST_SIDEWAYS); return; }
                drop();
                pick(item);
            }, ms);
        };
        arm(outward() ? REST_SIDEWAYS : REST);
    };
    menu.addEventListener('pointermove', (e) => {
        if (e.pointerType === 'touch') return;
        const now = performance.now();
        if (last) {
            const dt = Math.max(1, now - last.t);
            vx = vx * .6 + ((e.clientX - last.x) / dt) * .4;
            vy = vy * .6 + ((e.clientY - last.y) / dt) * .4;
        }
        last = { x: e.clientX, y: e.clientY, t: now };
        const item = e.target.closest?.(selector);
        if (item && menu.contains(item) && item !== waiting && !isChosen(item)) wait(item);
        if (!item && waiting) drop();
    });
    menu.addEventListener('pointerleave', () => { drop(); last = null; vx = vy = 0; });
    // A click chooses at once; nothing waiting may switch after it.
    menu.addEventListener('click', drop, true);
}
