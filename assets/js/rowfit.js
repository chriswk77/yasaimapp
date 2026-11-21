/**
 * PICTURES IN ROWS THAT FILL A BOX WITHOUT A SCROLL BAR — the arithmetic
 * alone, no page in it.
 *
 * Every way of cutting the sequence into 1…n rows is tried, each row as high
 * as the box's width allows for the pictures in it (their proportions
 * summed), the whole shrunk to the height there is, and the cut with the
 * most picture area wins: two tall plates side by side, a wide one above a
 * narrow pair, nine photos in three rows. The order stays the order given.
 *
 * The same arithmetic lays out a portrait's pictures in the identification
 * module (16.10.2026); it was written
 * there first. This module came with the Fundbrowser's overview
 * (23.10.2026: „die Bilder der rechten Spalte und die Tafel so
 * zu skalieren, dass wir keinen Scrollbalken haben") and is the one to
 * reach for next time.
 *
 * @param {number[]} ratios  width / height of each picture, in order
 * @param {number} width     the box's width
 * @param {number} height    the height the pictures may take
 * @param {number} gap       between pictures and between rows
 * @returns {{rows: number[][], heights: number[]}} the pictures' indices per
 *   row and each row's height; a picture's width is its row's height × its ratio
 */
export function fitRows(ratios, width, height, gap) {
    let best = null;
    for (let k = 1; k <= ratios.length; k++) {
        const rows = cutRows(ratios, k);
        if (!rows) continue;
        const heights = rows.map((r) => (width - gap * (r.length - 1)) / r.reduce((a, i) => a + ratios[i], 0));
        const tall = heights.reduce((a, h) => a + h, 0) + gap * (k - 1);
        const scale = Math.min(1, height / tall);
        const area = rows.reduce((a, r, j) => a + r.reduce((b, i) => b + (heights[j] * scale) ** 2 * ratios[i], 0), 0);
        if (!best || area > best.area) best = { rows, heights: heights.map((h) => h * scale), area };
    }
    return best ? { rows: best.rows, heights: best.heights } : { rows: [], heights: [] };
}

/**
 * The sequence cut into k rows of about equal summed proportion, in order;
 * null where k rows cannot be made.
 *
 * @returns {?number[][]}
 */
export function cutRows(ratios, k) {
    const n = ratios.length;
    const total = ratios.reduce((a, r) => a + r, 0);
    const rows = [];
    let row = [];
    let sum = 0;
    let left = total;
    ratios.forEach((r, i) => {
        row.push(i);
        sum += r;
        const rowsLeft = k - rows.length - 1;
        const itemsLeft = n - i - 1;
        if (rowsLeft > 0 && itemsLeft >= rowsLeft && (sum >= left / (rowsLeft + 1) || itemsLeft === rowsLeft)) {
            rows.push(row);
            left -= sum;
            row = [];
            sum = 0;
        }
    });
    if (row.length) rows.push(row);
    return rows.length === k ? rows : null;
}

/**
 * THE OTHER WAY ROUND: how wide a box must be for the pictures to fill a
 * given height exactly, in 1…n rows — the widest first (all in one row),
 * the narrowest last (one above the other). The Fundbrowser's overview
 * sizes its plates' column so (finds.js, fitFace): the height is the
 * window's, the width follows.
 *
 * Each row j is (w − gap·(n_j − 1)) / R_j high (R_j its proportions
 * summed); the rows and the gaps between them add up to the height, and
 * that is linear in w.
 *
 * @returns {{k: number, width: number}[]}
 */
export function widthsForHeight(ratios, height, gap) {
    const out = [];
    for (let k = 1; k <= ratios.length; k++) {
        const rows = cutRows(ratios, k);
        if (!rows) continue;
        const sums = rows.map((r) => r.reduce((a, i) => a + ratios[i], 0));
        const inverse = sums.reduce((a, R) => a + 1 / R, 0);
        const gapsInRows = rows.reduce((a, r, j) => a + gap * (r.length - 1) / sums[j], 0);
        out.push({ k, width: (height - gap * (k - 1) + gapsInRows) / inverse });
    }
    return out;
}
