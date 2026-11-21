/**
 * A stamp from the database the way the pages write a day — „14.08.2026".
 *
 * Its own module since 30.09.2026, when the picture viewer began to serve
 * the administration too: it writes „Aufgenommen 14.08.2026" under every
 * photo, and the only copy lived in map.js. Two hosts handing the viewer
 * the same four lines would have been two copies waiting to drift apart.
 *
 * A stamp the browser cannot read comes back as it went in: an odd date on
 * the screen says what the row holds, an empty space would hide it.
 */
export function readableDate(mysqlTime) {
    const d = new Date(mysqlTime.replace(' ', 'T'));
    return Number.isNaN(d.getTime())
        ? mysqlTime
        : d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
