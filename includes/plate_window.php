<?php
/**
 * The plate editor's markup — the full-screen <dialog id="plate-window">
 * (migration 0155; assets/js/plates.js wires it up, assets/css/plates.css
 * dresses it). Its own file like the photo window's, so the map prints it
 * with one line.
 *
 * THREE COLUMNS AT THE DESK: the find's pictures on the left to drag from,
 * the A4 page in the middle, on the right what is selected and then the
 * plate's own settings. Whoever only looks (not the maker, or a phone)
 * gets the page alone — the window carries .is-reading then.
 */

declare(strict_types=1);

require_once __DIR__ . '/icons.php';

function plate_window_render(): void
{
    ?>
    <dialog class="plate-window" id="plate-window" tabindex="-1" aria-labelledby="plate-window-title">
        <div class="plate-head">
            <button type="button" class="button subtle small window-x" id="plate-close-top"
                    title="<?= h(t('Schließen')) ?>" aria-label="<?= h(t('Schließen')) ?>"><?= icon('close') ?></button>
            <h2 class="plate-head-title" id="plate-window-title"><?= h(t('Tafel')) ?></h2>
            <div class="plate-pages" id="plate-pages" role="group" aria-label="<?= h(t('Seiten')) ?>"></div>
            <div class="plate-head-tools">
                <?php /* Only the plus (09.11.2026: „Das Seite würde ich da
                     entfernen. Ich würde nur einen Button mit plus machen") —
                     beside the pages it says what it adds; the word stays as
                     tooltip and for the screen reader. */ ?>
                <button type="button" class="button subtle small plate-page-add" id="plate-page-add"
                        title="<?= h(t('Seite hinzufügen')) ?>" aria-label="<?= h(t('Seite hinzufügen')) ?>"><?= icon('plus') ?></button>
                <button type="button" class="button subtle small" id="plate-page-remove"
                        title="<?= h(t('Diese Seite entfernen')) ?>" aria-label="<?= h(t('Diese Seite entfernen')) ?>"><?= icon('delete') ?></button>
            </div>
            <?php /* THE WAYS OUT stand for everybody who sees the plate, the maker
                 or not: a plate is made to be printed and passed on. Every
                 page at 300 dpi (plateprint.js) — the PDF holds them all,
                 the picture is the page in sight. */ ?>
            <?php /* WHOSE PLATE, where it is not one's own — in the head since the
                 footer went (30.10.2026, see below). */ ?>
            <span class="hint-text small plate-note" id="plate-note"></span>
            <div class="plate-head-right">
                <?php /* NO FOOTER ANY MORE (30.10.2026: „Ich würde hier
                     die Tafel löschen oben in die Kopfzeile legen, auch die
                     Statusmeldung irgendwo anders hinpacken. So dass wir keine
                     Fußzeile mehr haben"). It held three things — how the
                     autosave stands, „Tafel löschen", „Schließen" — and took a
                     whole bar of height from the page for them. The line and
                     the button came up here; „Schließen" went, the cross at the
                     head's left is the way out over the map, „Zurück" in the
                     Fundbrowser.

                     NO SAVE BUTTON: the plate saves itself a second after the
                     last change, as the measures do (plates.js, autosave), and
                     this quiet line says how it stands — „Gespeichert" only for
                     a moment, then it goes (plates.js, savedSay). */ ?>
                <span class="plate-saved" id="plate-saved" aria-live="polite"></span>
                <?php /* THE PAGE'S SIZE ON THE SCREEN (06.10.2026: „dass ich
                     die komplette Seite an sich gar nicht vergrößern kann … oder wir
                     machen oben einfach einen Slider"). The slider, and since
                     30.10.2026 the wheel and the touchpad over the page as in the
                     viewer; a zoomed page is moved by a drag in the grey around it
                     or with the space bar held (plates.js, zoomTo and the pan). */ ?>
                <label class="plate-zoom" title="<?= h(t('Größe der Seite auf dem Bildschirm')) ?>">
                    <?= icon('magnifier') ?>
                    <input type="range" id="plate-zoom" min="100" max="400" step="10" value="100"
                           aria-label="<?= h(t('Seite vergrößern')) ?>">
                    <output id="plate-zoom-value">100 %</output>
                </label>
                <button type="button" class="button subtle small plate-square" id="plate-zoom-fit" hidden
                        title="<?= h(t('Einpassen (Taste Y)')) ?>" aria-label="<?= h(t('Einpassen')) ?>"><?= icon('fit') ?></button>
                <?php /* ONE BUTTON „EXPORT", the two files beneath it
                     (07.10.2026: „PDF, Bild und Drucken würde ich gruppieren in
                     ein Dropdown, das man Export nennt … wir bieten kein
                     Drucken an. Nur Export PDF und Bild"). Printing goes
                     through the PDF, which the PDF line says — a printed web
                     page never promised A4 at its true size anyway. The
                     arrow into the tray is the one Belege → Dateien uses for
                     „onto the device". */ ?>
                <div class="plate-export">
                    <button type="button" class="button subtle small" id="plate-export"
                            aria-haspopup="true" aria-expanded="false"><?= icon('import') ?><?= h(t('Export')) ?></button>
                    <div class="plate-export-menu" id="plate-export-menu" hidden>
                        <button type="button" class="button subtle" id="plate-pdf"><?= icon('file') ?><span class="plate-export-text"><?= h(t('PDF')) ?><span class="plate-export-hint"><?= h(t('Alle Seiten in einer Datei, auch zum Drucken')) ?></span></span></button>
                        <button type="button" class="button subtle" id="plate-image"><?= icon('photo') ?><span class="plate-export-text"><?= h(t('Bild')) ?><span class="plate-export-hint"><?= h(t('Nur die Seite, die du gerade siehst')) ?></span></span></button>
                    </div>
                </div>
                <?php /* QUIET UNTIL THE POINTER IS ON IT, then red, as „Foto
                     löschen" in the viewer (30.10.2026); the
                     question before it stays (plates.js). Last in the head, set
                     apart from Export. */ ?>
                <button type="button" class="button subtle small plate-delete" id="plate-delete" hidden><?= icon('delete') ?><?= h(t('Tafel löschen')) ?></button>
            </div>
        </div>

        <div class="plate-main">
            <aside class="plate-strip" id="plate-strip" aria-label="<?= h(t('Bilder des Funds')) ?>"></aside>
            <?php /* The strip's width is dragged here (plates.js, stripEdge). */ ?>
            <span class="plate-strip-edge" id="plate-strip-edge" aria-hidden="true"></span>
            <div class="plate-stage" id="plate-stage">
                <div class="plate-page" id="plate-page"></div>
            </div>
            <?php /* TWO TABS ON THE RIGHT (07.10.2026: „die
                 Einstellungsleiste rechts würde ich in zwei Tabs unterteilen.
                 Einmal für die aktuelle Kachel und einmal allgemeine
                 Einstellungen, wo auch die Beschreibung ist, wie das geht").
                 Choosing something on the page turns to „Kachel" by itself
                 (plates.js, sideTab); the tabs stand still above, the
                 panels scroll beneath them. */ ?>
            <aside class="plate-side">
                <div class="plate-side-tabs" role="tablist" aria-label="<?= h(t('Einstellungen')) ?>">
                    <button type="button" role="tab" id="plate-tab-cell" aria-controls="plate-panel-cell"
                            aria-selected="false"><?= h(t('Kachel')) ?></button>
                    <button type="button" role="tab" id="plate-tab-general" aria-controls="plate-panel-general"
                            aria-selected="true" class="is-active"><?= h(t('Allgemein')) ?></button>
                </div>
                <div class="plate-side-panel" id="plate-panel-cell" role="tabpanel" aria-labelledby="plate-tab-cell" hidden>
                    <section class="plate-selection" id="plate-selection" hidden></section>
                    <p class="plate-side-empty" id="plate-selection-none"><?= h(t('Klicke auf der Seite auf ein Bild, einen Freitext, auf Werte, Fundangaben, eine Verteilung oder eine Reihe. Dann stellst du hier ein, wie sie aussehen.')) ?></p>
                </div>
                <div class="plate-side-panel" id="plate-panel-general" role="tabpanel" aria-labelledby="plate-tab-general">
                    <h3 class="plate-side-title"><?= h(t('Die Tafel')) ?></h3>
                    <?php /* ONE LINE EACH, as every choice of the panel (plates.js,
                         choiceLine; 20.11.2026: „Vieles geht als
                         Einzeiler"). Radios still, because their wiring reads
                         them, dressed as the panel's buttons: the radio
                         hidden in its label, the label the button. */ ?>
                    <fieldset class="field question plate-swatch-group plate-radio-line">
                        <legend class="plate-question"><?= h(t('Linien zwischen den Bildern')) ?></legend>
                        <span class="plate-choices">
                            <label class="plate-ink plate-word"><input type="radio" name="plate-gutter" value="0"><?= h(t('keine')) ?></label>
                            <label class="plate-ink plate-word"><input type="radio" name="plate-gutter" value="0.6"><?= h(t('fein')) ?></label>
                            <label class="plate-ink plate-word"><input type="radio" name="plate-gutter" value="1.5"><?= h(t('breiter')) ?></label>
                        </span>
                    </fieldset>
                    <fieldset class="field question plate-swatch-group plate-radio-line">
                        <legend class="plate-question"><?= h(t('Farbe der Linien')) ?></legend>
                        <span class="plate-choices">
                            <label class="plate-ink is-white" title="<?= h(t('weiß')) ?>"><input type="radio" name="plate-gutter-color" value="#ffffff" aria-label="<?= h(t('weiß')) ?>"></label>
                            <label class="plate-ink is-black" title="<?= h(t('schwarz')) ?>"><input type="radio" name="plate-gutter-color" value="#000000" aria-label="<?= h(t('schwarz')) ?>"></label>
                        </span>
                    </fieldset>
                    <div class="field question plate-inline">
                        <p class="plate-question"><?= h(t('Schriftgröße der Beschriftungen')) ?></p>
                        <div class="plate-stepper">
                            <button type="button" class="button subtle small" id="plate-note-smaller" title="<?= h(t('Kleiner')) ?>">A−</button>
                            <output id="plate-note-size"></output>
                            <button type="button" class="button subtle small" id="plate-note-bigger" title="<?= h(t('Größer')) ?>">A+</button>
                        </div>
                    </div>
                    <?php /* ON THE PUBLIC SITE (plate_publish.php, 25.11.2026: „eine
                         Option veröffentlichen … Beim Veröffentlichen setze ich
                         dort die Lizenz"). Only for the account that owns the
                         site, on its own plate — plates.js shows the section
                         when the server sent a `publish` with the plate. The
                         other photographers stand as ticks of their own: a
                         friend's picture is his, and the plate goes out only
                         when each of them is confirmed. Every change is sent at
                         once and answered with a toast. */ ?>
                    <section class="plate-publish" id="plate-publish" hidden>
                        <h3 class="plate-side-title"><?= h(t('Veröffentlichen')) ?></h3>
                        <fieldset class="field question plate-ticks">
                            <legend id="plate-publish-question"></legend>
                            <label class="choice-row"><input type="checkbox" id="plate-publish-on"><span><?= h(t('Tafel veröffentlichen')) ?></span></label>
                            <p class="hint-text small" id="plate-publish-licence"></p>
                        </fieldset>
                        <fieldset class="field question plate-ticks" id="plate-publish-others" hidden>
                            <legend><?= h(t('Bilder von anderen auf dieser Tafel')) ?></legend>
                            <div id="plate-publish-people"></div>
                            <p class="hint-text small"><?= h(t('Hake jeden an, der einverstanden ist. Vorher geht die Tafel nicht hinaus.')) ?></p>
                        </fieldset>
                        <div class="field question">
                            <label class="plate-question" for="plate-publish-note"><?= h(t('Zusatz zur Lizenz')) ?></label>
                            <input type="text" id="plate-publish-note" maxlength="500"
                                   placeholder="<?= h(t('z. B. Foto unten links mit freundlicher Genehmigung')) ?>">
                        </div>
                        <p class="plate-publish-state" id="plate-publish-state" aria-live="polite"></p>
                    </section>
                </div>
            </aside>
        </div>
    </dialog>
    <?php
}
