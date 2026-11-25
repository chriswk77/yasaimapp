<?php
/**
 * The picture viewer's markup — the full-screen <dialog id="photo-window">
 * and its two question windows (the calibration's length, the text for the
 * picture).
 *
 * OUT OF index.php ON 30.09.2026, as step two of ONE viewer for the map and
 * the administration: assets/js/photowindow.js looks these elements up when
 * it is mounted, so every page that mounts it prints them first, and this
 * is the one place they are written. Moved as they stood; the comments in
 * them are the ones they had.
 *
 * Styles in assets/css/photowindow.css, which a page loads with this.
 */

declare(strict_types=1);

require_once __DIR__ . '/icons.php';

/** Print the viewer and its question windows — before the page's scripts. */
function photo_window_render(): void
{
    ?>
    <?php /* A photo at full size.
         No form: there is nothing to enter here, only to look at and,
         if need be, to remove. */ ?>
    <?php /* tabindex="-1": THE WINDOW ITSELF TAKES THE FOCUS ON OPENING
         (11.09.2026: „wenn ich auf dem Desktop den
         Bildbetrachter öffne, liegt mir der Fokus gleich auf Motivname,
         und das Dropdown wird angezeigt, auch wenn schon ein Motivname
         gewählt wurde"). showModal() hands the focus to the first
         focusable element, and since the measuring row moved in on top
         that is the name field — which answers a focus with its list of
         suggestions. One opens this window to LOOK, not to rename;
         whoever wants the name taps into the field, and only then does
         the list belong there. The same cure as the map's list window
         (index.php), see photoWindow.focus() in photowindow.js. */ ?>
    <dialog class="photo-window" id="photo-window" tabindex="-1">
        <?php /* THE HEAD: everything about measuring lives up here
             (28.08.2026), the bottom row keeps the facts and the farewell
             buttons. The scale chooser applies a saved preset — minted per
             objective, „10x", „20x" — or starts the free calibration. The
             measuring part is hidden as a whole for a guest and while no
             picture can be measured (scaleTopbarSync).

             THE WAY OUT AT THE TOP LEFT (30.09.2026: „beim
             Bildbetrachter in der Desktop-Version auch oben links ein
             Schließen-X"). The window covers the whole screen, and the eye
             that wants out looks into the corner first. „Schließen" in the
             footer stays: it is the concluding button, this is the door in
             the corner — and it stands OUTSIDE the tool row, because a way
             out that comes and goes with the tools is no way out. On the
             phone the X goes (app.css, .window-x) and the head dissolves
             (display: contents): the tool row is the window's first row
             there, and „Schließen" lies under the thumb. */ ?>
        <div class="photo-window-head">
        <button type="button" class="button subtle small window-x" id="photo-close-top"
                title="<?= h(t('Schließen')) ?>" aria-label="<?= h(t('Schließen')) ?>"><?= icon('close') ?></button>
        <?php /* ONE BAR AT EVERY WIDTH SINCE 04.10.2026 („Der
             Bildbetrachter in der mobilen Version fasst ja über einen Knopf
             Motivname und Maßstab zusammen. Das finde ich eigentlich so gut,
             dass wir das auch für die Desktop-Version machen könnten. Ebenso
             die Schaltfläche zu den Werkzeugen"). What the phone found —
             the sliders for what one sets once, the tool chooser that shows
             what is on, the „Messung" chooser that is switched all the time
             — stands at the desk too, in the head beside the X, set apart
             from it by a wider gap („optisch von dem Schließen X trennen").
             Zoom moved up here to the right end, only „Einpassen" and „1:1"
             left: plus and minus are the wheel's and the fingers'.

             THE PRESETS DROP DOWN FROM THEIR BUTTON („dass es in
             der Fußzeile aufklappt, statt als Dropdown-Menü runterfährt.
             Das wäre schöner"), on the phone as at the desk — the box below
             is the reference for the panel. */ ?>
            <?php /* THE TOOL ROW, born on the phone (08.09.2026). It began as a
                 bar FLOATING over the picture and lasted half a day: „wenn ich
                 den Maßstab oben hinsetze, oben rechts in die Ecke, ist er
                 hinter den Buttons. Ich komm da nicht mehr dran … Die
                 Erklärungstexte für das Messen sind hinter den Buttons."

                 THE PICTURE CARRIES ITS OWN MOVABLE CHROME — the scale bar,
                 which the user parks in any of four corners, and the aiming
                 hint across the top. Anything of ours floating over it is
                 therefore guaranteed to cover something the user put there,
                 and covering a control means it cannot be moved out from
                 under us either. A row of its own costs the picture 46 px
                 once and settles it for good.

                 THE BUTTONS DO NO WORK OF THEIR OWN: „Messen" and „Mehr"
                 unfold the two rows that used to stand here permanently, the
                 sigma calls statsPaneFold(). Only the flaps are new state. */ ?>
            <div class="photo-phone-bar">
                <?php /* TWO MODES (06.11.2026: „einen zweiten Modus …
                     mit einem Umschalten … der klassische Bildbearbeiter,
                     ähnlich wie Lightroom"). „Messen" is the window as it
                     was; „Bearbeiten" lays the development over the picture
                     and puts its sliders into the sidebar on the right
                     (photowindow.js, the development). Desk only.
                     ONE SPLIT BUTTON OF TWO SIGNS since 07.11.2026 (
                     „ein geteilter Button mit 2 Symbolen … dessen rechte oder
                     linke Hälfte mit der Akzentfarbe belegt wird"): the ruler
                     for measuring, the sliders for editing; the half one is in
                     wears the accent. The words stay as titles. */ ?>
                <div class="photo-mode" id="photo-mode" role="group" aria-label="<?= h(t('Modus')) ?>">
                    <button type="button" class="is-active" data-mode="measure" aria-pressed="true"
                            title="<?= h(t('Messen')) ?>" aria-label="<?= h(t('Messen')) ?>"><?= icon('ruler') ?></button>
                    <button type="button" data-mode="develop" aria-pressed="false"
                            title="<?= h(t('Bearbeiten')) ?>" aria-label="<?= h(t('Bearbeiten')) ?>"><?= icon('sliders') ?></button>
                </div>
                <div class="photo-presets">
                    <?php /* „VORGABEN" AND NO LONGER „MESSEN" (09.09.2026:
                         „Der Button messen kann jetzt eigentlich ein anderes Symbol
                         haben, denn das ist ja irgendwie nur noch eine
                         Vorkonfiguration. Das eigentliche Messen passiert hier über
                         die andere Schaltfläche").

                         He is right, and has been since today: the tools have
                         moved into the tool chooser and the clearing into its
                         menu. What remained are the three things one settles
                         BEFORE measuring — Motivname, measuring layer, Maßstab. A
                         ruler over a flap in which one measures nothing is a
                         false promise.

                         In this project the sliders already say twice "this is
                         where you set what is in front of you right now"
                         (columns in Organisieren, administration) — the same
                         meaning, the same drawing. The cog would be wrong: that
                         belongs to the settings of the ACCOUNT.

                         The identifiers stay as they are: the flap IS the
                         measuring row, and the button opens it. */ ?>
                    <button type="button" class="button subtle small" id="photo-phone-measure"
                            title="<?= h(t('Vorgaben')) ?>" aria-label="<?= h(t('Vorgaben')) ?>"
                            aria-expanded="false"><?= icon('sliders') ?></button>
                    <div class="photo-window-top" id="photo-topbar">
                    <?php /* WHAT THE PICTURE SHOWS (30.08.2026: „dass man
                         die Bilder benennt, also Hauptmotiv, Habitat, Schnittbild
                         … für die Mikrobilder Sporen, Cheilozystiden …"), in
                         front of the scale. The suggestions are the stock lists
                         (photos.php, PHOTO_TITLES_MACRO / _MICRO); the list below the field is one
                         we draw ourselves — the why stands at the field itself.
                         Groundwork for renaming the files into a fixed pattern
                         later; photowindow.js fills, stores and syncs it. */ ?>
                    <label class="photo-topbar-scale">
                        <?php /* „Motivname" and not „Bild" (08.09.2026):
                             the field names WHAT is to be seen — Hauptmotiv,
                             Habitat, Schnittbild, Sporen — not the file. „Bild"
                             stood above a field in a window that shows nothing
                             but pictures anyway, and so said nothing. */ ?>
                        <span><?= h(t('Motivname')) ?></span>
                        <?php /* NO MORE <datalist> (10.09.2026: „in der
                             Mobilversion, wenn ich auf Motivname klicke, kommt ja
                             auch eine Vorschlagliste — diese schließt aber
                             augenblicklich wieder"). A datalist's drop-down is an
                             accessory of the browser: it lies outside the page,
                             and what closes it is not for this application to
                             decide. On the phone it closes the moment the
                             keyboard shrinks the visible section — the very
                             movement this project keeps --view-height for.

                             So the same list as for the species names: a <ul>
                             that we draw ourselves and close ourselves. Why it
                             closes on a tap BESIDE it and not on blur is written
                             at closeSpecies() in map.js — whoever reaches into a
                             list loses the caret in the field, and the list would
                             be gone before the finger had arrived. */ ?>
                        <?php /* data-no-clear: no cross to empty it — every picture has a
                             name (15.11.2026, photowindow.js). */ ?>
                        <input type="text" id="photo-title" maxlength="120" data-no-clear
                               autocomplete="off" role="combobox" aria-expanded="false"
                               aria-autocomplete="list" aria-controls="photo-title-list">
                        <?php /* WITHOUT THE RIGHT TO NAME, THE NAME STANDS AS TEXT
                             (06.10.2026, on a find shared with him
                             to read: „das Label zumindest müsste, wenn ich keine
                             Schreibrechte habe, einfach nur als Text dastehen")
                             — the house rule: a frame means one can write. */ ?>
                        <span class="photo-topbar-text" id="photo-title-text" hidden></span>
                        <?php /* At the desk the menu of our own (photowindow.js,
                             pickMount; the field above stays its wiring). */ ?>
                        <span class="photo-pick" id="photo-title-pick">
                            <button type="button" class="photo-pick-button" aria-haspopup="listbox" aria-expanded="false"
                                    title="<?= h(t('Motivname')) ?>"></button>
                            <span class="photo-pick-menu" role="listbox" hidden></span>
                        </span>
                    </label>
                    <?php /* THE DEVICE BEFORE THE OBJECTIVE (02.11.2026,
                         migration 0183: „dass man vorher erstmal sein
                         Mikroskop-Set wählt und dann die entsprechenden
                         Objektive vorgeschlagen kriegt"). Only where there
                         is more than one device to choose — with one, the
                         Maßstab below already offers all there is.
                         photowindow.js fills and shows it. */ ?>
                    <label class="photo-topbar-scale" id="photo-device-field" hidden>
                        <span><?= h(t('Gerät')) ?></span>
                        <select id="photo-device-select"></select>
                    </label>
                    <label class="photo-topbar-scale">
                        <span><?= h(t('Maßstab')) ?></span>
                        <select id="photo-scale-select">
                            <option value=""><?= h(t('ohne')) ?></option>
                        </select>
                        <?php /* At the desk one menu for device AND objective
                             (photowindow.js, pickMount) — the device field
                             above goes out of sight there. */ ?>
                        <span class="photo-pick" id="photo-scale-pick">
                            <button type="button" class="photo-pick-button" aria-haspopup="listbox" aria-expanded="false"
                                    title="<?= h(t('Maßstab')) ?>"></button>
                            <span class="photo-pick-menu" role="listbox" hidden></span>
                        </span>
                    </label>
                    <?php /* A PICTURE ONE MAY ONLY LOOK AT (06.10.2026): measuring
                         and another scale are offered all the same — „solange
                         das alles bei mir nur temporär läuft" — and this line
                         says so, before anyone wonders where his measures went. */ ?>
                    <p class="photo-topbar-note" id="photo-readonly-note" hidden><?= h(t('Nur zum Anschauen: Was du hier misst oder als Maßstab wählst, gilt nur in deinem Fenster und wird nicht gespeichert.')) ?></p>
                    <div class="photo-topbar-measure">
                        <?php /* Icon buttons since 29.08.2026 („Einfach
                             nur Icon Buttons") — the drawing says the gesture,
                             title and aria-label keep the words for hover and
                             screen reader. Only „Messungen leeren" keeps its
                             text: it is the dangerous one of the three. */ ?>
                        <button type="button" class="button subtle small" id="photo-measure-line"
                                title="<?= h(t('Strecke messen')) ?>"
                                aria-label="<?= h(t('Strecke messen')) ?>"><?= icon('measure-line') ?></button>
                        <?php /* The connected polyline (29.08.2026): a bent
                             paraphysis or an ascus is no straight line — tap along
                             it, a double tap closes the run, the label carries the
                             SUM of the segments. */ ?>
                        <button type="button" class="button subtle small" id="photo-measure-path"
                                title="<?= h(t('Gebogene Länge messen')) ?>"
                                aria-label="<?= h(t('Gebogene Länge messen')) ?>"><?= icon('measure-path') ?></button>
                        <?php /* THE LENGTHS FIRST, then length × breadth (07.10.2026) —
                             the same order as the tool list (PHONE_TOOLS). */ ?>
                        <button type="button" class="button subtle small" id="photo-measure-rect"
                                title="<?= h(t('Länge × Breite messen')) ?>"
                                aria-label="<?= h(t('Länge × Breite messen')) ?>"><?= icon('measure-rect') ?></button>
                        <?php /* Writing onto the picture: tap where it goes and
                             write — straight into the picture (05.10.2026). */ ?>
                        <button type="button" class="button subtle small" id="photo-annotate"
                                title="<?= h(t('Text schreiben')) ?>"
                                aria-label="<?= h(t('Text schreiben')) ?>"><?= icon('annotate') ?></button>
                        <?php /* An arrow that points at something: tail, then tip. */ ?>
                        <button type="button" class="button subtle small" id="photo-measure-arrow"
                                title="<?= h(t('Pfeil zeichnen')) ?>"
                                aria-label="<?= h(t('Pfeil zeichnen')) ?>"><?= icon('annotate-arrow') ?></button>
                        <?php /* A box of a fixed size, typed in µm or mm: pores per
                             square millimetre, lamellae per centimetre of cap
                             margin (06.10.2026). Locked without a scale. */ ?>
                        <button type="button" class="button subtle small" id="photo-annotate-box"
                                title="<?= h(t('Rahmen in fester Größe')) ?>"
                                aria-label="<?= h(t('Rahmen in fester Größe')) ?>"><?= icon('annotate-box') ?></button>
                        <button type="button" class="button subtle small" id="photo-measure-clear" hidden><?= h(t('Messungen leeren')) ?></button>
                    </div>
                    </div>
                </div>
                <?php /* THE TOOL CHOOSER, and it SHOWS what is on right now
                     (09.09.2026: „ein Dropdown mit den anderen
                     Messwerkzeugen und Beschriftungswerkzeugen, sodass diese
                     Schaltfläche immer das aktuelle Werkzeug anzeigt").

                     Yesterday's arrow becomes the first line of the list:
                     „Zeiger" is the state in which a tap sets nothing but
                     selects an existing measurement — at the desk the right
                     click, under the finger nothing at all until yesterday.

                     REAL BUTTONS AND NO select (09.09.2026:
                     „Kannst Du nicht richtige Schaltknöpfe nehmen?"). A
                     select can only do text; but these five are exactly the
                     drawings that stand in the measuring row at the desk, and
                     one recognises a tool by its picture faster than by its
                     name. Both are in there — drawing and word — and every
                     line is 44 px tall.

                     THE CONTENT COMES FROM photowindow.js, from the same table that
                     also labels the trigger. Two lists with the same five
                     tools would be the pair that drifts apart.

                     THAT IS WHY THE TOOLS NO LONGER STAND IN THE FLAP. What
                     stays there is what one SETS: Motivname, Messung,
                     Maßstab — and the clearing, which is no tool but the one
                     step that takes something away. */ ?>
                <div class="photo-phone-tools">
                    <button type="button" class="button subtle small" id="photo-phone-tool"
                            aria-haspopup="true" aria-expanded="false"
                            aria-label="<?= h(t('Werkzeug')) ?>"></button>
                    <div class="photo-phone-menu" id="photo-phone-menu" hidden></div>
                </div>
                <?php /* WHICH STRUCTURE is being measured (28.08.2026:
                     „eine Art Dropdown … um zu sagen, was man gerade misst",
                     and its place: „direkt hinter Maßstab in der Kopfzeile").
                     It stamps every new measure with its label AND filters
                     the statistics sidebar to exactly that label, across all
                     pictures of the find. „Frei" measures without saving —
                     the quick look at a dimension that is nobody's
                     statistic. Options built by photowindow.js, counts included. */ ?>
                <label class="photo-topbar-scale">
                    <span><?= h(t('Messung')) ?></span>
                    <select id="photo-stats-label"></select>
                    <?php /* AT THE DESK A MENU OF OUR OWN that opens under the
                         pointer (04.11.2026: „ein eigenes
                         CSS-Dropdown, das sich automatisch beim Überfahren
                         öffnet"). The <select> above stays the truth and
                         goes on being hidden wiring there: photowindow.js
                         (measurePick) draws this from its options and picks
                         through it, so every place that reads or sets the
                         layer goes on speaking to the select. The phone
                         keeps the select — no pointer hovers there, and its
                         own picker is the better one under a thumb. */ ?>
                    <span class="photo-pick" id="photo-stats-pick">
                        <button type="button" class="photo-pick-button" aria-haspopup="listbox" aria-expanded="false"></button>
                        <span class="photo-pick-menu" role="listbox" hidden></span>
                    </span>
                </label>
                <?php /* QUICK SWITCHES between the layers THIS picture holds
                     (06.10.2026: „hinter dem Dropdown Knöpfe
                     einblenden für das jeweilige Bild, auf denen schon
                     Messungen oder Text liegen … nur wenn mehrere
                     Messungsebenen auf einem Bild liegen … nur in der
                     Desktop-Version"). Drawn by photowindow.js
                     (layerChipsSync). */ ?>
                <div class="photo-layer-chips" id="photo-layer-chips" role="group"
                     aria-label="<?= h(t('Ebenen dieses Bildes')) ?>" hidden></div>
                <?php /* „EINPASSEN" AND „1:1" ONLY (04.10.2026: „das
                     Plus und das Minus für Einzelvergrößern und Verkleinern
                     kann komplett weg"). The wheel, the touchpad and two
                     fingers do that better (imagezoom.js). The two that stay
                     jump somewhere a gesture cannot aim: the whole picture,
                     and its own pixels. Desk only — on the phone the double
                     tap does both. */ ?>
                <div class="photo-window-zoom">
                    <?php /* THE MEDIUM, right in the head before the zoom
                         (06.11.2026: „als Button rechts oben in
                         der Kopfzeile vor den Zoom-Buttons, mit Aufklappmenü
                         um das Medium noch zu bearbeiten, zusammengeklappt
                         zeigt es das Medium"). Closed it says the picture's
                         media by their short labels, open it ticks them —
                         the microscope's list, common ones first
                         (photowindow.js, mediumPick). */ ?>
                    <span class="photo-pick photo-medium-pick" id="photo-medium-pick" data-multi hidden>
                        <button type="button" class="photo-pick-button" aria-haspopup="listbox" aria-expanded="false"
                                title="<?= h(t('Medium')) ?>"></button>
                        <span class="photo-pick-menu" role="listbox" aria-multiselectable="true" hidden></span>
                    </span>
                    <?php /* „BEARBEITUNG ★1 2 +" (11.11.2026): several edits of one
                         picture, the starred one valid everywhere; „0" is the
                         Original (15.11.2026 — it replaced the buttons
                         „Original" and „Zurücksetzen"). Built by photowindow.js
                         (versionsDraw); only while one develops and may. */ ?>
                    <span class="photo-versions" id="photo-versions" hidden></span>
                    <button type="button" class="button subtle small" id="photo-zoom-fit"
                            title="<?= h(t('Einpassen (Taste Y)')) ?>" aria-label="<?= h(t('Einpassen')) ?>"><?= icon('fit') ?></button>
                    <button type="button" class="button subtle small" id="photo-zoom-full"
                            title="<?= h(t('In voller Größe, ein Bildpunkt je Bildschirmpunkt')) ?>">1:1</button>
                </div>
            </div>
        </div>
        <div class="photo-window-middle">
            <?php /* All pictures of this find as a strip — for jumping between
                 them without falling back to the point window, and the
                 ground for measuring one spore print across several photos
                 (later).

                 AT THE DESK IT FOLDS AND WIDENS (05.10.2026:
                 „auch zuklappbar machen und Größen änderbar. Aber nur in der
                 Desktop-Version"). The pictures grow with the width and stay
                 ONE column („die Bilder rücken dadurch niemals
                 nebeneinander"), and once there is room the subject names
                 stand over their groups as in the point window's gallery.

                 THE GRIP SITS AT THE OUTER EDGE, the same day: „das Handle
                 außen an den Bildschirmrand, weil dann könnten wir ein
                 Überfahren zum Aufklappen leichter realisieren. Ansonsten
                 würde das Handle ja immer mitwandern" — and „diese
                 Vorschaubilder braucht man ja nur, um zu einem anderen Motiv
                 zu wechseln". So the grip never moves: a click folds or
                 pins, the mouse resting on it folded lets the strip slide
                 out OVER the picture for one choice, and the width is
                 dragged at the strip's other edge, the rule toward the
                 picture. The rail is only the box for the three; on the
                 phone it dissolves (display: contents) and the strip lies
                 under the picture as before. */ ?>
            <div class="photo-window-rail" id="photo-rail">
                <button type="button" class="photo-strip-toggle" id="photo-strip-toggle"
                        aria-label="<?= h(t('Bildleiste ein- und ausklappen')) ?>">‹</button>
                <div class="photo-window-strip" id="photo-strip"></div>
                <span class="photo-strip-resize" id="photo-strip-resize" aria-hidden="true"></span>
            </div>
            <?php /* THE TOOLS AS A RAIL, desk only (30.10.2026: „die
                 Mess- und Beschriftungstools links als Sidebar hin,
                 allerdings nur die Symbole"). Between the strip and the
                 picture, so the words that unfold under the pointer lie over
                 the picture and not over the thumbnails. Filled by
                 photowindow.js from the table that also fills the phone's
                 menu. */ ?>
            <div class="photo-tool-rail" id="photo-tool-rail" role="toolbar"
                 aria-orientation="vertical" aria-label="<?= h(t('Werkzeug')) ?>"></div>
        <div class="photo-window-image">
            <img id="photo-image" alt="">
            <?php /* THE PICTURE DEVELOPED (0190): drawn by assets/js/develop.js
                 over the untouched original, the same box and the same
                 zoom as the picture (imagezoom mirrors it). Only where the
                 picture is developed; never catches a pointer. */ ?>
            <canvas class="photo-develop-canvas" id="photo-develop-canvas" hidden></canvas>
            <?php /* THE MEASURING PLANE (28.08.2026, stage one of the tool):
                 an SVG lying exactly over the image, its viewBox in
                 ORIGINAL pixels, its transform mirrored from the zoom
                 (imagezoom.js, 'mirror') — so everything drawn in it lives
                 in image coordinates and rides every pinch and pan without
                 a line of conversion. pointer-events:none always: clicks
                 belong to the frame, drawings only show. */ ?>
            <svg class="photo-overlay" id="photo-overlay" aria-hidden="true"></svg>
            <?php /* The ellipses' outlines and grips while one develops — a plane
                 like the measuring one, in original pixels; only the grips
                 take the pointer. */ ?>
            <svg class="photo-develop-overlay" id="photo-develop-overlay" aria-hidden="true" hidden></svg>
            <?php /* THE KEYS OF A TEXT BEING WRITTEN (05.10.2026): the overlay
                 draws the letters where they will stand, this field only
                 holds them — invisible, over the words, so the phone brings
                 its keyboard and does not scroll away to find the field. A
                 TEXTAREA since 07.10.2026: Enter breaks the line. Marked as
                 already bound for autoheight.js, which grows every textarea
                 to its content — this one must stay a dot. */ ?>
            <textarea class="photo-text-input" id="photo-text-input" maxlength="200" rows="1"
                      autocomplete="off" spellcheck="false" tabindex="-1" hidden data-no-clear
                      data-autoheight="yes"
                      aria-label="<?= h(t('Text auf dem Bild')) ?>"></textarea>
            <?php /* The scale bar. OUTSIDE the zoomed plane on purpose: it
                 rests in a corner of the PICTURE in sight (29.10.2026; the window's
                 corner left it in the black beside a 4:3 picture on a wide
                 screen) and recomputes its round
                 length from the current zoom — zoomed in, the same bar
                 says "200 µm" where it said "1 mm". Drag it into another
                 corner; it snaps to the nearest. */ ?>
            <div class="photo-scalebar" id="photo-scalebar" hidden>
                <span class="photo-scalebar-line"></span>
                <span id="photo-scalebar-label"></span>
            </div>
            <?php /* The aiming hint FLOATS over the image instead of living in
                 the footer — measured 28.08.2026: a hint in the footer
                 rewraps it, the image area changes height, and the picture
                 JUMPS between the first and the second tap. A calibration
                 whose target moves mid-gesture measures garbage. */ ?>
            <p class="photo-frame-hint" id="photo-frame-hint" hidden></p>
            <?php /* THE HELP (25.11.2026): a small „?" over the picture. A new
                 sentence about how the tool is used unfolds beside it for a
                 few seconds and folds back; the pointer resting on it — or a
                 tap — brings it out again. The pill above keeps what one
                 operates. photowindow.js (helpSync) fills it from the pill's
                 marked sentences. */ ?>
            <div class="photo-help" id="photo-help" hidden>
                <button type="button" class="photo-help-sign" id="photo-help-sign"
                        aria-controls="photo-help-words" aria-expanded="false"
                        aria-label="<?= h(t('Hilfe zum Werkzeug')) ?>"><?= icon('question') ?></button>
                <span class="photo-help-words" id="photo-help-words"></span>
            </div>
            <?php /* Which QUALITY a cloud recording shows — „Vorschaubild"
                 until the original is loaded (28.08.2026: „das
                 wird momentan nicht richtig kommuniziert"). Voucher photos
                 have only the one version and carry no badge.
                 AND THE WAY TO THE ORIGINAL (29.10.2026: „über
                 den Cloud-Button, der dann aufklappt … umständlich"): the
                 badge is a button with a swap sign, a click on
                 „Vorschaubild" loads the original in place; its size stands
                 in the tooltip. Still never fetched unasked — several
                 megabytes over a mobile connection are a decision.
                 Once the original is there, the badge goes (08.11.2026):
                 it was clicked for, and nothing is left to warn of. */ ?>
            <button type="button" class="photo-quality" id="photo-quality" hidden></button>
        </div>
        <?php /* THE STATISTICS SIDEBAR (stage two, 28.08.2026: „das
             Statistikfenster … als Sidebar, auf der rechten Seite.
             Einklappbar"). Top the measured spores as small rotated
             cut-outs — deliberately WITHOUT numbers on them („Ohne Maße,
             nur die Bilder"), the numbers live below as the average and
             the Piximètre-style range, and under that the distribution:
             length against width, every spore a dot. It reads ACROSS all
             pictures of the find — that is what persisting the measures
             (migration 0092) is for.

             The plot SVG is a CANVAS like #photo-overlay, not an icon —
             tests/icons.php keeps both on record. */ ?>
        <aside class="photo-window-stats is-collapsed" id="photo-stats" hidden>
            <button type="button" class="photo-stats-toggle" id="photo-stats-toggle"
                    aria-label="<?= h(t('Statistik ein- und ausklappen')) ?>">‹</button>
            <div class="photo-stats-body" data-scroll-remember="photo:stats">
                <?php /* The pane's content follows the „Messung" chooser in
                     the TOPBAR: thumbnails, numbers and distribution all
                     belong to the label chosen up there. */ ?>
                <?php /* NUMBERS AND DISTRIBUTION FIRST (10.09.2026:
                     „wir sollten die Statistik und die Verteilung über die
                     Vorschaubildchen setzen"). They are the result, and
                     they are short; the thumbnails are the evidence, and
                     with 26 measured spores they are several screens long.
                     While they stood first, one had to scroll past the
                     evidence to reach the result — and exactly that is how
                     it came to light that the distribution at the very
                     bottom was out of reach. */ ?>
                <?php /* THE TABS: „Alles", then one per part measure
                     (07.10.2026: „in der Statistik-Sidebar oben Tabs").
                     „Alles" is the pane as it was; a part's tab shows its
                     numbers, its curve and its cut-outs — and the picture
                     then shows only that part's lines, so they need no
                     name beside their number. Filled by photowindow.js
                     (statsTabsSync), shown only where the layer holds a
                     part measure. */ ?>
                <div class="photo-stats-tabs" id="photo-stats-tabs" role="tablist"
                     aria-label="<?= h(t('Teilmaße')) ?>" hidden></div>
                <?php /* WHICH OF THE LAYER'S MEASURES COUNT
                     (07.10.2026): „Alle", one mark — „Chrysozystide" —, or
                     „ohne Merkmal". The same measure counts in „Alle" and in
                     its own mark; nobody has to choose a layer for a kind.
                     Filled by photowindow.js (markFilterSync), shown only
                     where the layer's measures carry a mark. */ ?>
                <div class="photo-stats-mark" id="photo-stats-mark" role="radiogroup"
                     aria-label="<?= h(t('Zeigen')) ?>" hidden></div>
                <?php /* THE LENGTH ALONG THE BEND OR STRAIGHT
                     (07.10.2026: „kleine allantoide Sporen wurden klassisch
                     nach Länge und Dicke gemessen … zwei Messstatistiken,
                     einmal die klassische, einmal die neue"). A length
                     traced as a polyline holds both: along its bend, or
                     from its first point to its last, as the books measure.
                     Above the numbers, because it decides what they mean;
                     „klassisch" is the word for the books' reading
                     (07.10.2026), not „gerade wie in Büchern". Shown only
                     where the layer holds such a length —
                     everywhere else both readings are one number. Per
                     layer, on the account (photowindow.js). */ ?>
                <?php /* THREE GROUNDS INSTEAD OF A RULE (20.11.2026: „auf
                     die Trennlinie zwischen der Statistik und der Verteilung
                     verzichten und stattdessen beiden Bereichen eine
                     Hintergrundfarbe geben. Sowie dem unteren Bereich … die
                     Ausschnittsbilder plus die Einstellungen"): the numbers,
                     the distribution, the cut-outs with their sliders —
                     each a .photo-stats-group of its own. */ ?>
                <div class="photo-stats-group">
                <div class="photo-stats-length" id="photo-stats-length" role="radiogroup"
                     aria-label="<?= h(t('Länge')) ?>" hidden>
                    <span><?= h(t('Länge')) ?></span>
                    <button type="button" class="button subtle small" role="radio" data-length="bent"
                            title="<?= h(t('Entlang der gezeichneten Linie gemessen.')) ?>"><?= h(t('gebogen')) ?></button>
                    <button type="button" class="button subtle small" role="radio" data-length="straight"
                            title="<?= h(t('Von Ende zu Ende in gerader Linie — so messen es die meisten Bücher.')) ?>"><?= h(t('klassisch')) ?></button>
                </div>
                <div class="photo-stats-values" id="photo-stats-values"></div>
                <?php /* THE PART MEASURES of the layer — germ pore, ornament,
                     sterigma, a cystidium's head (07.10.2026): one line
                     each under the structure's numbers (statsParts). */ ?>
                <div class="photo-stats-parts" id="photo-stats-parts" hidden></div>
                </div>
                <div class="photo-stats-group photo-stats-plot-group">
                <h3 id="photo-stats-plot-title" hidden><?= h(t('Verteilung')) ?></h3>
                <svg id="photo-stats-plot" viewBox="0 0 220 150" hidden></svg>
                <?php /* The cloud in two numbers under it (measurestats.js,
                     spreadText, 09.11.2026): count, spread, outliers. */ ?>
                <p class="photo-stats-spread" id="photo-stats-spread" hidden></p>
                <?php /* THE DISTRIBUTION'S HEIGHT, dragged here
                     (20.11.2026: „ein Handle, mit dem ich die Höhe der
                     Verteilung schieben kann … so dass ich mir die
                     Reihenausschnitte unten besser anschauen kann").
                     photowindow.js, plotHeightApply; gone with the plot. */ ?>
                <div class="photo-stats-plot-grip" id="photo-stats-plot-grip" role="separator"
                     aria-orientation="horizontal" tabindex="0"
                     title="<?= h(t('Höhe der Verteilung ziehen – Doppelklick: volle Größe')) ?>"
                     aria-label="<?= h(t('Höhe der Verteilung')) ?>"></div>
                </div>
                <div class="photo-stats-group">
                <?php /* HOW MUCH AIR around each cut-out
                     (28.08.2026): an ascomycete's ornament reaches well
                     past the measured rectangle, and a fixed margin cut
                     it off. The thumbnails are drawn live from the
                     source picture, so the slider only changes a factor
                     and redraws — nothing is stored per spore.

                     THE SLIDER STAYS WITH THE THUMBNAILS, even after
                     numbers and distribution moved up
                     (10.09.2026: „der Randschieberegler bleibt dabei aber
                     bei den Bildchen, weil da gehört es dazu"). It sets
                     nothing about the statistics, only the cropping of
                     the cut-outs below it — a control belongs at what it
                     changes. */ ?>
                <label class="photo-stats-air">
                    <span><?= h(t('Rand')) ?></span>
                    <input type="range" id="photo-stats-air"
                           min="100" max="300" step="1" value="118">
                </label>
                <?php /* THE TILES' SIZE. First in a cutout name's tab, where the
                     margin has nothing to do (07.10.2026: „auf den
                     kleinen Bildern sehe ich das nicht gut … dass die alle
                     ungefähr auf einer Höhe liegen"); since 15.11.2026 under
                     the margin for the measured cut-outs too („um sie etwas zu
                     vergrößern"). Per device, like the pane's width, and each
                     kind of tile its own number (photowindow.js,
                     cropTileHeight, sporeTileHeight). */ ?>
                <label class="photo-stats-air" id="photo-stats-size-row" hidden>
                    <span><?= h(t('Größe')) ?></span>
                    <input type="range" id="photo-stats-size"
                           min="64" max="320" step="8" value="96">
                </label>
                <?php /* THE ORDER OF THE TILES (04.10.2026): by length
                     unless chosen otherwise — the outermost tiles are then the
                     values in brackets above, the smallest and the largest
                     spore, where a mismeasure or a stranger hides; by Q the
                     round and the long ones part, two species in one sample
                     show. NO „AS MEASURED": „da blicke ich heute schon nicht
                     durch, welche Spore zu welchem Bild gehört" — the mark
                     and the jump to the picture say that, the order need not.
                     A few options in sight, not a dropdown; the device
                     remembers the choice (photowindow.js, sporeSortKey). */ ?>
                <div class="photo-stats-sort" id="photo-stats-sort" role="radiogroup"
                     aria-label="<?= h(t('Reihenfolge')) ?>" hidden>
                    <span><?= h(t('Reihenfolge')) ?></span>
                    <button type="button" class="button subtle small" role="radio" data-sort="L"><?= h(t('Länge')) ?></button>
                    <button type="button" class="button subtle small" role="radio" data-sort="B"><?= h(t('Breite')) ?></button>
                    <button type="button" class="button subtle small" role="radio" data-sort="Q">Q</button>
                </div>
                <?php /* A PART'S TILES TURN TOGETHER (07.10.2026): a quarter turn
                     clockwise per press, for every tile of the part on view —
                     its line points where one wants it (photowindow.js,
                     PART_TURNS). Only in a part's tab. */ ?>
                <div class="photo-stats-sort" id="photo-stats-turn" hidden>
                    <button type="button" class="button subtle small" id="photo-stats-turn-button"
                            title="<?= h(t('Alle Kacheln um 90° drehen')) ?>"><?= icon('refresh') ?> <?= h(t('Alle drehen')) ?></button>
                </div>
                <div class="photo-stats-thumbs" id="photo-stats-thumbs"></div>
                </div>
            </div>
        </aside>
        <?php /* „BEARBEITEN", in the statistics' place (06.11.2026:
             „rechts eine Sidebar, wie sie in Lightroom ist … dass wir auch
             die Regler etwas größer haben … je länger die Reglerstrecke
             ist, desto feiner können wir verstellen"). Wide, so a slider is
             long; filled by photowindow.js from develop.js's groups. */ ?>
        <aside class="photo-develop" id="photo-develop" data-scroll-remember="photo:develop" hidden>
            <p class="hint-text small" id="photo-develop-readonly" hidden><?= h(t('Nur zum Anschauen: Dieses Bild darfst du nicht bearbeiten.')) ?></p>
            <?php /* FIVE TABS, AS SIGNS (07.11.2026; „Ausbessern" and
                 „Info" 10.11.2026: „mit den ausgeschriebenen Wörtern jetzt
                 langsam zu breit. Lightroom hat da auch eigene Symbole"): the
                 sliders of the whole picture, the tools that turn and cut it,
                 the masks that change a part of it, the spots that mend one,
                 and what the camera wrote into the file. One at a time, so
                 each has the sidebar's length; the word stands as the tooltip.
                 The tab is kept on the device (settings developTab). */ ?>
            <div class="photo-develop-tabs" id="photo-develop-tabs" role="tablist">
<?php /* „EBENEN" IS THE SIXTH (11.11.2026, assets/js/blend.js): several pictures of the find laid over each other.
         It stands hidden and is shown by photowindow.js where the page offers the tool — the Fundbrowser, on one's
         own find with a cloud. */
      foreach ([['adjust', 'sliders', t('Einstellungen')], ['tools', 'crop', t('Werkzeuge')], ['masks', 'mask', t('Masken')],
                ['spots', 'bandage', t('Ausbessern')], ['layers', 'stack', t('Ebenen')], ['info', 'info', t('Info')]] as $i => [$tab, $sign, $word]): ?>
                <button type="button" role="tab" data-tab="<?= h($tab) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" title="<?= h($word) ?>" aria-label="<?= h($word) ?>"<?= $tab === 'layers' ? ' hidden' : '' ?>><?= icon($sign) ?></button>
<?php endforeach; ?>
            </div>
            <div class="photo-develop-sliders" id="photo-develop-sliders"></div>
            <?php /* THE NEW MASK'S BUTTONS STAND ON TOP (13.11.2026, the
                 same evening they had moved onto the rail: „Bei den Masken
                 gefällt mir das nicht. Da müssten die drei Buttons doch wieder
                 hoch. Und ganz dezent … die Shortcuts … an den Buttons
                 anzeigen. Also 1, 2, 3"). THE RAIL CHOOSES THE CHOSEN MASK'S
                 GROUP instead — „für jede Maske quasi die gleichen
                 Einstellungen. Farbe, Licht, Details … ähnlich per
                 Schnellauswahl öffnen" —, the brush's own settings stand
                 always („die Pinseleinstellungen … immer anzeigen")
                 (photowindow.js, maskDraw). No sentence explaining what a
                 mask is (09.11.2026: „Ich möchte keine
                 Erklärtexte in der Sidebar … Das ist keine App für
                 Dummköpfe"). No plus in front of the words either („Das Plus an
                 den Buttons gefällt mir auch nicht", the same evening). */ ?>
            <?php /* THE CHOSEN MASK'S SETTINGS BEFORE THE LIST (13.11.2026:
                 „ein paar Masken und schon wird das Menü ziemlich lang und ich
                 komme nicht mehr an meine Schieberegler ohne zu scrollen"):
                 the buttons on top over the whole width, then the sliders of
                 the mask in hand with the rail beside THEM („die
                 Schnellauswahl-Buttons müssen natürlich unten neben die
                 Schieberegler"), the masks' rows last. */ ?>
            <section class="photo-develop-radials" id="photo-develop-masks" hidden>
                <div class="button-row photo-mask-add">
                    <button type="button" class="button subtle small" id="photo-develop-add" title="<?= h(t('Neue Ellipse (Taste 1)')) ?>"><?= h(t('Ellipse')) ?><span class="photo-key-hint" aria-hidden="true">1</span></button>
                    <button type="button" class="button subtle small" id="photo-develop-add-linear" title="<?= h(t('Neuer Verlauf (Taste 2)')) ?>"><?= h(t('Verlauf')) ?><span class="photo-key-hint" aria-hidden="true">2</span></button>
                    <button type="button" class="button subtle small" id="photo-develop-add-brush" title="<?= h(t('Neuer Pinsel (Taste 3)')) ?>"><?= h(t('Pinsel')) ?><span class="photo-key-hint" aria-hidden="true">3</span></button>
                </div>
                <?php /* THE MASKS AGAIN ON TOP, AS A FOLD (13.11.2026: „die
                     Masken doch gerne wieder ganz oben, allerdings als
                     Kollapsmenü … nur die aktive … wenn ich auf die Maske gehe,
                     klappen unten drunter alle Masken auf … Es klappt erst zu …
                     wenn ich mit der Maus den Maskenbereich verlasse"). One row
                     shut, all rows while the pointer is over it (photowindow.js,
                     masksPanelDraw). */ ?>
                <div class="photo-develop-radial-list" id="photo-develop-radial-list"></div>
                <div class="photo-develop-adjust photo-mask-settings" id="photo-mask-settings" hidden>
                    <div class="photo-develop-adjust-body">
                        <div class="photo-develop-radial" id="photo-develop-radial" hidden></div>
                    </div>
                    <div class="photo-develop-rail" id="photo-mask-rail">
                        <div class="photo-develop-rail-signs" role="tablist" aria-orientation="vertical" aria-label="<?= h(t('Masken')) ?>">
                            <?php /* The brush's own settings as a group of their own, first, and
                                 only on a brush (13.11.2026: „die Pinseleinstellung
                                 nehmen wir doch mit in die Schnellauswahl. Dann wandern wir
                                 noch ein Stückchen höher") — before, they stood over the whole
                                 width above the rail. */ ?>
                            <button type="button" class="photo-develop-rail-sign" role="tab" data-mask-group="brush" aria-label="<?= h(t('Pinsel')) ?>" title="<?= h(t('Pinsel')) ?>" hidden><?= icon('brush') ?><span class="photo-develop-rail-word"><?= h(t('Pinsel')) ?></span></button>
                            <button type="button" class="photo-develop-rail-sign" role="tab" data-mask-group="color" aria-label="<?= h(t('Farbe')) ?>" title="<?= h(t('Farbe')) ?>"><?= icon('palette') ?><span class="photo-develop-rail-word"><?= h(t('Farbe')) ?></span></button>
                            <button type="button" class="photo-develop-rail-sign" role="tab" data-mask-group="light" aria-label="<?= h(t('Licht')) ?>" title="<?= h(t('Licht')) ?>"><?= icon('sun') ?><span class="photo-develop-rail-word"><?= h(t('Licht')) ?></span></button>
                            <button type="button" class="photo-develop-rail-sign" role="tab" data-mask-group="details" aria-label="<?= h(t('Details')) ?>" title="<?= h(t('Details')) ?>"><?= icon('details') ?><span class="photo-develop-rail-word"><?= h(t('Details')) ?></span></button>
                        </div>
                    </div>
                </div>
            </section>
        </aside>
        </div>
        <?php /* ON THE PHONE THIS ROW IS THE BOTTOM BAR, permanently and no
             longer as a flap (09.09.2026: „drei Punkten sind ja
             eigentlich ziemlich unnütze Schaltflächen für die
             Touchbedienung … Das Einzige, was da noch Sinn macht, ist Foto
             löschen").

             HE IS RIGHT, AND THE GESTURES ARE THE REASON: −/+ is done by
             the two-finger gesture, „Einpassen" and „1:1" by the double
             tap (imagezoom.js has always known it — whole or threefold).
             Four buttons that a gesture does better are four buttons too
             many; that is why they no longer stand on the phone. What
             STAYS has no gesture: deleting, the cloud routes and the
             farewell.

             With that the „⋯" flap goes too, and with it the second
             „Schließen" — this one here is it again. Deleting stands at
             the far left, far away from the farewell on the right. */ ?>
        <?php /* THE MOTIVNAME SUGGESTIONS STAND HERE and not beside their
             field — they have to get out of the measuring row. On the
             phone that row carries max-height: 72% and overflow-y: auto,
             and a scroll box cuts off whatever reaches past its edge:
             measured, a strip of three pixels of the opened list was
             left standing, the rest lay behind the edge.

             As a child of the window and with position: fixed it hangs
             on nothing that scrolls or clips; photowindow.js works out its
             position from the field when it opens — below the field if
             there is room, above it otherwise. */ ?>
        <ul class="species-suggestions" id="photo-title-list" hidden></ul>

        <div class="photo-window-footer">
            <p class="hint-text small" id="photo-details"></p>
            <div class="photo-window-buttons">
                <?php /* Its own button and not the one beside it: the two delete
                     different things in different places, and a handler that
                     first has to work out which case it is in is one that
                     will get it wrong once. */ ?>
                <?php /* QUIET UNTIL THE POINTER IS ON IT (29.10.2026:
                     the red button „zieht ziemlich viel Aufmerksamkeit auf
                     sich"). Labelled, at the far left of the footer, red only
                     under the pointer — like the trash in the Fundbrowser's
                     bar. Two bare buckets at the top were weighed and
                     dropped: „zu gefährlich". The question before deleting
                     still says what goes. */ ?>
                <?php /* „BILD EXPORTIEREN" (stage 3, 07.11.2026, „Bild erzeugen"
                     until 08.11.2026) — in „Bearbeiten" only, at the far left
                     beside the delete buttons (07.11.2026: „nur in
                     die Fußzeile … ohne Akzentfarbe. Nur beim Überfahren
                     Akzentfarbe"). It opens the export window below, and the
                     file is downloaded (photowindow.js, developExport). */ ?>
                <button type="button" class="button subtle" id="photo-develop-export" hidden
                        title="<?= h(t('Lädt das Bild so herunter, wie du es hier siehst. Das Original bleibt, wie es ist.')) ?>"><?= icon('import') ?><span><?= h(t('Bild exportieren')) ?></span></button>
                <button type="button" class="button subtle photo-delete-button" id="photo-cloud-delete" hidden><?= h(t('Aufnahme löschen')) ?></button>
                <button type="button" class="button subtle photo-delete-button" id="photo-delete"><?= h(t('Foto löschen')) ?></button>

                <?php /* THE CLOUD BUTTON OPENS THE FOLDER, straight away
                     (29.10.2026). It used to fold out two buttons,
                     „Original laden" and „Ordner in der Cloud"; the original
                     moved onto the quality badge over the picture, and what
                     was left needs no fold. Into the folder the recording
                     lies in, at its provider, through pages/cloud_folder.php
                     — pCloud, Google Drive and OneDrive; S3 has no page a
                     browser could open, and a co-owner's recording lies in
                     the photographer's cloud, so there it stays away. */ ?>
                <a class="button subtle" id="photo-external" target="_blank" rel="noopener noreferrer" hidden
                   title="<?= h(t('Ordner in der Cloud öffnen')) ?>"
                   aria-label="<?= h(t('Ordner in der Cloud öffnen')) ?>"><?= icon('cloud') ?></a>

                <button type="button" class="button subtle" id="photo-close"><?= h(t('Schließen')) ?></button>
            </div>
        </div>
    </dialog>

    <?php /* „Bild exportieren" — its own file since 13.11.2026, so the picture
             archives' photo stage asks the same window (export_window.php). */
    require_once __DIR__ . '/export_window.php';
    export_window_render(); ?>

    <?php /* The calibration's one question: how long is the drawn line really?
         Asked AFTER the two clicks, so the eye stays on the image while
         aiming. Entfernen shows only while a calibration exists — a button
         that removes nothing is furniture. */ ?>
    <dialog class="form-window" id="photo-scale-window">
        <form id="photo-scale-form">
            <h2><?= h(t('Maßstab setzen')) ?></h2>
            <p class="hint-text" id="photo-scale-hint"><?= h(t('Wie lang ist die gezogene Strecke wirklich?')) ?></p>
            <?php /* BUTTONS INSTEAD OF TYPING (07.10.2026: „ein paar Knöpfe
                 mit üblichen Zahlen. Eins, zwei, fünf, zehn, zwanzig,
                 fünfzig, hundert … und dann die Einheiten nicht im
                 Dropdown-Menü, sondern als Knopf zum Auswählen. Das spart
                 Zeit"). The units stand in sight beside the number, as
                 three buttons of the field's height; the usual lengths a
                 ruler or a micrometer slide is marked with sit under it
                 and fill the number in. The hidden field keeps the factor
                 the form has always read. */ ?>
            <div class="field">
                <label for="photo-scale-value"><?= h(t('Länge')) ?></label>
                <div class="photo-scale-entry">
                    <?php /* TEXT, NOT A NUMBER FIELD (07.10.2026: „das Komma
                         bitte sicher annehmen"): a number field leaves its
                         value empty when the browser's language reads „8,1"
                         differently, and the form then refuses a length it was
                         shown. As text it takes a comma or a point alike; the
                         phone still offers its number keys (inputmode), and
                         the reader turns the comma round (photowindow.js). */ ?>
                    <input type="text" id="photo-scale-value" inputmode="decimal"
                           pattern="\s*[0-9]+([,.][0-9]+)?\s*" autocomplete="off" required>
                    <div class="photo-scale-units" role="radiogroup" aria-label="<?= h(t('Einheit')) ?>">
                        <button type="button" class="button subtle" role="radio" data-unit="10000">cm</button>
                        <button type="button" class="button subtle" role="radio" data-unit="1000">mm</button>
                        <button type="button" class="button subtle" role="radio" data-unit="1">µm</button>
                    </div>
                    <input type="hidden" id="photo-scale-unit" value="10000">
                </div>
                <div class="photo-scale-quick" role="group" aria-label="<?= h(t('Übliche Längen')) ?>">
                    <?php foreach ([1, 2, 5, 10, 20, 50, 100] as $n): ?>
                        <button type="button" class="button subtle small" data-value="<?= $n ?>"><?= $n ?></button>
                    <?php endforeach; ?>
                </div>
                <?php /* A COIN IN THE PICTURE (the same day: „wenn man
                     sonst nichts hat, legt man manchmal ein Euro-Stück ins Bild
                     und macht dann ein Belegfoto mit dem Handy"). Every euro
                     coin by its diameter as the European Central Bank gives
                     it; a press fills in the millimetres. The line is drawn
                     across the whole coin before this window opens. */ ?>
                <p class="photo-scale-caption"><?= h(t('Euro-Münze im Bild – die Strecke quer über die ganze Münze ziehen:')) ?></p>
                <div class="photo-scale-quick" role="group" aria-label="<?= h(t('Euro-Münzen')) ?>">
                    <?php foreach ([['1 ct', 16.25], ['2 ct', 18.75], ['5 ct', 21.25], ['10 ct', 19.75], ['20 ct', 22.25],
                                    ['50 ct', 24.25], ['1 €', 23.25], ['2 €', 25.75]] as [$coin, $mm]): ?>
                        <button type="button" class="button subtle small" data-coin="<?= $mm ?>"
                                title="<?= h(t('%s mm Durchmesser', number_format($mm, 2, ',', ''))) ?>"><?= h($coin) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php /* ONE'S OWN THINGS (the same day: „Feuerzeug,
                     oder was man gerade dabei hat"): a button each, drawn by
                     photowindow.js (scaleObjectsDraw) from the account's
                     style; „Merken" keeps the length now in the field under
                     a name, the bucket beside a thing forgets it. */ ?>
                <p class="photo-scale-caption"><?= h(t('Eigene Gegenstände:')) ?></p>
                <div class="photo-scale-quick" id="photo-scale-own" role="group" aria-label="<?= h(t('Eigene Gegenstände')) ?>"></div>
            </div>
            <?php /* A SCALE FOR THIS PHOTO ONLY — no „Als Vorlage merken" any
                 more (26.09.2026: „Ein Maßstab im Foto
                 individuell für das Foto setzen macht Sinn. Bei
                 Makroaufnahmen kann man aber nicht eine Vorlage draus
                 erstellen, weil die Abstände zum Motiv immer
                 unterschiedlich sind … Bei den Mikrofotos geht das eben
                 schon. Und deswegen machen wir … die Vorlagen auch nur über
                 die Verwaltung"). A preset comes from a calibration image
                 in Einstellungen → Fotos → Maßstab-Vorlagen; here one only
                 PICKS one, in the chooser above the picture. */ ?>
            <div class="dialog-footer">
                <button type="button" class="button danger" id="photo-scale-remove" hidden><?= h(t('Entfernen')) ?></button>
                <button type="button" class="button subtle" id="photo-scale-cancel"><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button"><?= h(t('Übernehmen')) ?></button>
            </div>
        </form>
    </dialog>

    <?php
}
