<?php
/**
 * THE EXPORT WINDOW — how a picture leaves the app as a file: format,
 * quality, size, sharpening, a watermark template, what the file carries,
 * its name. Asked before every download (assets/js/exportwindow.js).
 *
 * TWO CALLERS, ONE WINDOW (13.11.2026: „genauso wie bei dem
 * Bildexport beim Bildbetrachter … Wasserzeichen … Größe, Koordinaten …
 * beim Verkleinern einschärfen"): the photo window's „Bild exportieren"
 * (photowindow.js, a find's developed picture) and the picture archives'
 * photo stage (figure_window.php, an older picture). It lived inside
 * photo_window.php until then; a copy would drift at the first new
 * question. The ids keep their photo-export prefix — app.css names them.
 *
 * ONCE PER PAGE: the Fundbrowser prints both windows, so the second call
 * prints nothing.
 */

declare(strict_types=1);

require_once __DIR__ . '/icons.php';

function export_window_render(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
    <?php /* „BILD EXPORTIEREN" asks two things before the download
         (08.11.2026: „den Komprimierungsfaktor … das neue Google … Bildformat
         ist auch ziemlich interessant, weil es platzsparend ist"): the
         format, as radios in sight, and the quality. Both kept on the
         device. WebP is offered only where the browser can write it
         (photowindow.js, webpCan); otherwise the line says so. */ ?>
    <dialog class="form-window is-wide pinned-footer" id="photo-export-window">
        <form id="photo-export-form">
            <h2><?= h(t('Bild exportieren')) ?></h2>
            <p class="hint-text"><?= h(t('Das Bild wird so heruntergeladen, wie du es hier siehst. Das Original bleibt, wie es ist.')) ?></p>
            <?php /* TWO COLUMNS AT THE DESK (08.11.2026: „Das Fenster
                 ist viel zu schmal … mit Scrollbalken versehen, obwohl rechts
                 und links mein Bildschirm genügend Platz hätte"): what the
                 file IS on the left — format, quality, size, sharpening —,
                 what it CARRIES on the right — watermark and name. One
                 column on the phone. */ ?>
            <div class="photo-export-columns">
                <div class="photo-export-column">
            <fieldset class="field question">
                <legend><?= h(t('Format')) ?></legend>
                <label class="choice-row">
                    <input type="radio" name="photo-export-format" value="jpeg">
                    <span><?= h(t('JPEG – jedes Programm kann es öffnen.')) ?></span>
                </label>
                <label class="choice-row">
                    <input type="radio" name="photo-export-format" value="webp">
                    <span><?= h(t('WebP – kleinere Datei bei gleichem Aussehen. Manche ältere Programme können es nicht öffnen.')) ?></span>
                </label>
                <p class="hint-text small" id="photo-export-webp-none" hidden><?= h(t('Dieser Browser kann kein WebP speichern.')) ?></p>
            </fieldset>
            <div class="field question">
                <p class="photo-export-question"><?= h(t('Qualität')) ?></p>
                <div class="photo-export-quality">
                    <input type="range" id="photo-export-quality" min="50" max="100" step="1" value="92">
                    <output id="photo-export-quality-shown">92 %</output>
                </div>
                <p class="hint-text small"><?= h(t('Weniger Qualität macht die Datei kleiner. Bis etwa 85 % sieht man meist keinen Unterschied.')) ?></p>
            </div>
            <?php /* THE SIZE (08.11.2026: „für Instagram … verkleinern
                 … %50 Größe oder lange Kante 1000 Pixel"). Never larger than
                 the original; the quick buttons fill the long edge. */ ?>
            <fieldset class="field question">
                <legend><?= h(t('Größe')) ?></legend>
                <label class="choice-row">
                    <input type="radio" name="photo-export-size" value="full">
                    <span id="photo-export-size-full"><?= h(t('Volle Größe')) ?></span>
                </label>
                <label class="choice-row">
                    <input type="radio" name="photo-export-size" value="edge">
                    <span><?= h(t('Lange Kante')) ?></span>
                    <input type="number" class="photo-export-number" id="photo-export-edge" min="100" max="8192" step="1" inputmode="numeric">
                    <span><?= h(t('Pixel')) ?></span>
                </label>
                <div class="photo-export-quick" id="photo-export-quick">
                    <button type="button" class="button subtle small" data-edge="1080"><?= h(t('1080 (Instagram)')) ?></button>
                    <button type="button" class="button subtle small" data-edge="2048">2048</button>
                    <button type="button" class="button subtle small" data-edge="4096">4096</button>
                </div>
                <label class="choice-row">
                    <input type="radio" name="photo-export-size" value="share">
                    <span><?= h(t('Anteil')) ?></span>
                    <input type="number" class="photo-export-number" id="photo-export-share" min="5" max="100" step="1" inputmode="numeric">
                    <span>%</span>
                </label>
                <p class="hint-text small" id="photo-export-size-result"></p>
            </fieldset>
            <?php /* SHARPENING AFTER MAKING SMALLER — a shrunk picture looks soft. */ ?>
            <fieldset class="field question" id="photo-export-sharpen-field">
                <legend><?= h(t('Nachschärfen')) ?></legend>
                <label class="choice-row"><input type="radio" name="photo-export-sharpen" value="none"><span><?= h(t('aus')) ?></span></label>
                <label class="choice-row"><input type="radio" name="photo-export-sharpen" value="light"><span><?= h(t('leicht')) ?></span></label>
                <label class="choice-row"><input type="radio" name="photo-export-sharpen" value="strong"><span><?= h(t('stark')) ?></span></label>
                <p class="hint-text small"><?= h(t('Nur beim Verkleinern: Ein stark verkleinertes Bild wirkt sonst etwas weich.')) ?></p>
            </fieldset>
                </div>
                <div class="photo-export-column">
            <?php /* THE WATERMARK: none, or one of the account's templates
                 (Einstellungen → Fotos → Export). Drawn by photowindow.js. */ ?>
            <fieldset class="field question">
                <legend><?= h(t('Wasserzeichen')) ?></legend>
                <div id="photo-export-marks"></div>
                <p class="hint-text small"><?= sprintf(h(t('Vorlagen legst du an unter %s.')),
                    '<a href="' . h(url('pages/administration.php')) . '#export" target="_blank" rel="noopener">'
                    . h(t('Einstellungen → Fotos → Export')) . '</a>') ?></p>
            </fieldset>
            <?php /* WHAT STANDS IN THE FILE (12.11.2026: „eigentlich wäre
                 es natürlich sinnvoller, wenn wir das als Option anbieten. Also
                 eine Option, ob auch andere Exif-Daten entfernt werden sollen.
                 Dass die nichts verraten, wenn man irgendwo postet"). Three
                 ticks, kept on the device; the place is off unless ticked, as
                 it always was. Without the place the note in the file leaves
                 the original's name out too — a cloud recording is called
                 „<Art> <Datum> <Ort>" (exifstamp.js, noteText). The author is
                 no choice: „ein Export geht nicht ohne den Urheber". */ ?>
            <fieldset class="field question" id="photo-export-carry">
                <legend><?= h(t('Was steht in der Datei?')) ?></legend>
                <label class="choice-row"><input type="checkbox" name="photo-export-carry" value="place"><span><?= h(t('Fundort (Koordinaten des Funds)')) ?></span></label>
                <label class="choice-row"><input type="checkbox" name="photo-export-carry" value="camera"><span><?= h(t('Kamera und Belichtung')) ?></span></label>
                <label class="choice-row"><input type="checkbox" name="photo-export-carry" value="taken"><span><?= h(t('Aufnahmezeitpunkt')) ?></span></label>
                <p class="hint-text small"><?= h(t('Der Urheber steht immer drin.')) ?></p>
            </fieldset>
            <?php /* THE FILE'S NAME, put together right here
                 (08.11.2026: „den Dateinamen … machen wir gleich beim Fenster
                 schon, im Fenster. Wie der zusammengesetzt sein soll"): the
                 pattern with its placeholders, a button for each to put it
                 in, and the name it makes for this picture beneath. The
                 pattern is the account's (Einstellungen → Fotos → Export
                 holds the same field); changed here, it is kept there. */ ?>
            <div class="field question">
                <p class="photo-export-question"><?= h(t('Dateiname')) ?></p>
                <input type="text" id="photo-export-name" maxlength="120" autocomplete="off" spellcheck="false">
                <div class="photo-export-chips" id="photo-export-chips"></div>
                <p class="hint-text small" id="photo-export-name-result"></p>
            </div>
                </div>
            </div>
            <div class="dialog-footer">
                <button type="button" class="button subtle" id="photo-export-cancel"><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button"><?= icon('import') ?><?= h(t('Exportieren')) ?></button>
            </div>
        </form>
    </dialog>
    <?php
}
