/**
 * The canvas a photo is redrawn on before it is stored — on the PROCESSOR,
 * never on the graphics card — and the check that what came out is a
 * picture at all.
 *
 * WHY (15.11.2026): an old Intel iMac with Firefox stored pure
 * black pictures from the microscope, while the check picture before was
 * right. The check picture is the untouched file; everything stored after
 * it was redrawn on a canvas and encoded anew — and with Firefox's canvas
 * acceleration (gfx.canvas.accelerated) on that machine's graphics card
 * every such redrawing came out black. He found the switch; the question
 * was whether it could be built so that it never happens again, with the
 * acceleration left on: „ja baue beide schritte".
 *
 * STEP ONE — THE CPU CANVAS. A 2D context asked for with
 * willReadFrequently is kept in memory and drawn by the processor, in
 * Firefox as in Chrome, whatever the acceleration says: the browser
 * expects its pixels to be read back and keeps them where reading is
 * cheap. The broken path is not taken at all. It costs a little time — a
 * fraction of a second on a large picture — which an upload never notices.
 * The live picture's brightness watch (microscope.js) has always drawn
 * that way, and it worked on that iMac.
 *
 * STEP TWO — THE CHECK. Should a browser still hand back black, the
 * result is compared with its source on a tiny sample: black where the
 * source was not means the redrawing failed, and the caller keeps the
 * source instead (cloudsend.js, shrunk; photos.js, prepare). A source that
 * is itself black — a dark field, a closed shutter — is left alone; that
 * is a picture, not a failure.
 *
 * Only for what is STORED. The development's WebGL („Bearbeiten"), the
 * plates and the view on the screen draw for the eye and stay as they are.
 */

/** A canvas of this size with its CPU-drawn 2D context — DOM or offscreen. */
export function cpuCanvas(width, height, offscreen = false) {
    const canvas = offscreen && typeof OffscreenCanvas === 'function'
        ? new OffscreenCanvas(width, height)
        : Object.assign(document.createElement('canvas'), { width, height });
    const g = canvas.getContext('2d', { willReadFrequently: true });
    return { canvas, g };
}

/** The canvas as a JPEG blob, whichever kind of canvas it is. */
export function canvasJpeg(canvas, quality) {
    if (typeof canvas.convertToBlob === 'function') return canvas.convertToBlob({ type: 'image/jpeg', quality });
    return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
}

/* The sample: 24 × 24 is enough to tell a picture from black, and drawing
   anything down to it costs nothing. „Black" leaves a little room for the
   noise of a JPEG — no channel brighter than 6 of 255 anywhere. */
const SAMPLE = 24;
const BLACK = 6;

/**
 * Is this picture black all over? A Blob is decoded first; an ImageBitmap,
 * a canvas or an image element is drawn as it is. Null where it could not
 * be read — the caller then has no reason to distrust anything.
 */
export async function isBlack(source) {
    let drawable = source;
    let own = null;
    try {
        if (source instanceof Blob) drawable = own = await createImageBitmap(source);
        const { g } = cpuCanvas(SAMPLE, SAMPLE);
        g.drawImage(drawable, 0, 0, SAMPLE, SAMPLE);
        const px = g.getImageData(0, 0, SAMPLE, SAMPLE).data;
        for (let i = 0; i < px.length; i += 4) {
            if (px[i] > BLACK || px[i + 1] > BLACK || px[i + 2] > BLACK) return false;
        }
        return true;
    } catch {
        return null;
    } finally {
        own?.close?.();
    }
}

/** Did the redrawing fail — the result black, its source not? */
export async function cameOutBlack(result, source) {
    return (await isBlack(result)) === true && (await isBlack(source)) === false;
}
