<?php
/**
 * THE PICTURE WINDOW for pictures one only looks at — a book's figures and
 * portraits in Bestimmung, and since 02.11.2026 the older pictures of the
 * picture archives in the Fundbrowser's „Bildarchiv" („Mach
 * Variante 1, nenn es Bildarchiv"). One markup and one module
 * (assets/js/figurewindow.js) for both pages; it moved out of
 * the identification module's page so the Fundbrowser need not carry a copy that drifts.
 *
 * The ids keep their keys- prefix: the styles in app.css name them, and a
 * rename would buy nothing. The picture large in the frame imagezoom.js
 * works in, its neighbours as a strip on the left, a line under it, and
 * the zoom's buttons in the footer.
 *
 * PHOTOGRAPHS GET A STAGE OF THEIR OWN (13.11.2026, for the
 * picture archives' photos): the whole window, black behind the picture,
 * closing at the top left, „1:1" and „eingepasst" at the top right and
 * nothing else of the zoom, the neighbours as a strip along the bottom and
 * two arrows on the picture — both only while the mouse moves. The bar and
 * the arrows below are that stage's; outside it (a book's figures) they do
 * not show, and its footer does not show on the stage (app.css, is-photo).
 */

declare(strict_types=1);

require_once __DIR__ . '/icons.php';

function figure_window_render(): void
{
    ?>
    <dialog class="form-window keys-figure-window" id="keys-figure-window">
        <form method="dialog">
            <?php /* The photo stage's bar: the way back on the left — the arrow the
                 Fundbrowser's open find carries, not a cross
                 (13.11.2026) —, not in the accent: it ends a look, it saves
                 nothing. On the right the export and the two sizes. */ ?>
            <div class="keys-figure-bar">
                <button class="button subtle square" value="close" title="<?= h(t('Zurück')) ?>" aria-label="<?= h(t('Zurück')) ?>"><?= icon('arrow-left') ?></button>
                <div class="keys-figure-bar-zoom">
                    <button type="button" class="button subtle square" data-figure-export hidden title="<?= h(t('Exportieren')) ?>" aria-label="<?= h(t('Exportieren')) ?>"><?= icon('import') ?></button>
                    <button type="button" class="button subtle" data-zoom="original" title="<?= h(t('Das Bild in seiner echten Größe zeigen')) ?>" aria-label="<?= h(t('Originalgröße')) ?>">1:1</button>
                    <button type="button" class="button subtle square" data-zoom="fit" title="<?= h(t('Eingepasst')) ?>" aria-label="<?= h(t('Eingepasst')) ?>"><?= icon('fit') ?></button>
                </div>
            </div>
            <button type="button" class="keys-figure-arrow is-prev" data-figure-step="-1" title="<?= h(t('Voriges Bild')) ?>" aria-label="<?= h(t('Voriges Bild')) ?>"><?= icon('arrow-left') ?></button>
            <button type="button" class="keys-figure-arrow is-next" data-figure-step="1" title="<?= h(t('Nächstes Bild')) ?>" aria-label="<?= h(t('Nächstes Bild')) ?>"><?= icon('arrow-right') ?></button>
            <?php /* Where several pictures belong together (a statement's, a portrait's),
                 a strip of small ones on the left switches between them. */ ?>
            <div class="keys-figure-body">
                <div class="keys-figure-strip" id="keys-figure-strip" hidden></div>
                <div class="keys-figure-main">
                    <?php /* The frame imagezoom.js works in: wheel, two fingers, a drag
                         to move, a double click for whole or threefold. */ ?>
                    <div class="keys-figure-frame" id="keys-figure-frame">
                        <img alt="" id="keys-figure-image">
                    </div>
                    <p class="keys-figure-caption" id="keys-figure-caption"></p>
                </div>
            </div>
            <div class="dialog-footer">
                <?php /* ZOOM BY BUTTON TOO (18.10.2026: „einen Zoom … per
                     Mausrad oder Touchgeste und vielleicht auch ein Plus-Minus-Knopf.
                     Und ein Zurück auf Ursprung"). Peer actions, so on the left;
                     the number between them says how large the picture stands
                     against its own pixels — 100 % is one to one. */ ?>
                <div class="keys-figure-zoom">
                    <button type="button" class="button subtle square" data-zoom="out" title="<?= h(t('Verkleinern')) ?>" aria-label="<?= h(t('Verkleinern')) ?>"><?= icon('minus') ?></button>
                    <span class="keys-figure-scale" id="keys-figure-scale" title="<?= h(t('Größe im Verhältnis zum Originalbild')) ?>"></span>
                    <button type="button" class="button subtle square" data-zoom="in" title="<?= h(t('Vergrößern')) ?>" aria-label="<?= h(t('Vergrößern')) ?>"><?= icon('plus') ?></button>
                    <button type="button" class="button subtle" data-zoom="original" title="<?= h(t('Das Bild in seiner echten Größe zeigen')) ?>"><?= h(t('Originalgröße')) ?></button>
                    <button type="button" class="button subtle square" data-zoom="fit" title="<?= h(t('Ganzes Bild')) ?>" aria-label="<?= h(t('Ganzes Bild')) ?>"><?= icon('fit') ?></button>
                </div>
                <button class="button" value="close"><?= h(t('Schließen')) ?></button>
            </div>
        </form>
    </dialog>
    <?php
    // The photo stage's „Exportieren" asks the find window's export window (export_window.php).
    require_once __DIR__ . '/export_window.php';
    export_window_render();
}
