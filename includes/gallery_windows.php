<?php
/**
 * The windows the Belege of a find use — the file preview (the find's other
 * files), the window that hangs a sequence on a find, the window that asks
 * the subject of each picture, the warning before a micrograph is shrunk
 * and the microscope's live window (24.10.2026, assets/js/microscope.js).
 *
 * OUT OF index.php ON 12.10.2026, with the gallery code (assets/js/
 * photogallery.js): the Fundbrowser's window has the Belege tab too, and
 * the elements those scripts look up must stand in the page before they
 * are mounted. The way photo_window.php and plate_window.php went; every
 * page that mounts the gallery prints this first. Moved as it stood, the
 * comments are the ones the windows had.
 */

declare(strict_types=1);

require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/sequences.php';   // SEQUENCE_MARKERS, for the marker field's suggestions

/** Print the Belege's windows — before the page's scripts. */
function gallery_windows_render(): void
{
    ?>
    <?php /* A sequence at the find (migration 0112) ----------------------------*/ ?>
    <?php /* A FILE LOOKED AT — a text read, a picture seen larger — without
         leaving the find (pointfiles.js). A PDF opens in a tab of its own:
         the browser shows it better than any window here could. */ ?>
    <dialog class="form-window file-preview-window" id="file-preview">
        <form method="dialog">
            <h2 id="file-preview-name"></h2>
            <pre class="file-preview-text" id="file-preview-text" hidden></pre>
            <img class="file-preview-image" id="file-preview-image" alt="" hidden>
            <div class="dialog-footer">
                <button type="submit" class="button"><?= h(t('Schließen')) ?></button>
            </div>
        </form>
    </dialog>

    <dialog class="form-window sequence-window pinned-footer" id="sequence-window">
        <form method="dialog" id="sequence-form">
            <h2><?= h(t('Sequenz hinzufügen')) ?></h2>
            <p class="hint-text small" id="sequence-point"></p>

            <div class="field">
                <label for="sequence-marker"><?= h(t('Marker')) ?></label>
                <input type="text" id="sequence-marker" maxlength="40" list="sequence-markers" placeholder="ITS">
                <datalist id="sequence-markers">
                    <?php foreach (SEQUENCE_MARKERS as $m): ?><option value="<?= h($m) ?>"></option><?php endforeach; ?>
                </datalist>
            </div>

            <div class="field">
                <label for="sequence-text"><?= h(t('Sequenz')) ?></label>
                <textarea id="sequence-text" rows="5" spellcheck="false" autocapitalize="off"
                          placeholder="<?= h(t('FASTA oder die nackten Basen — mehrere FASTA-Einträge ergeben mehrere Sequenzen')) ?>"></textarea>
                <div class="button-row">
                    <label class="button subtle small sequence-file-button">
                        <?= h(t('Datei wählen')) ?>
                        <input type="file" id="sequence-file" accept=".fasta,.fa,.fas,.fna,.seq,.txt" hidden>
                    </label>
                </div>
            </div>

            <div class="field two-columns">
                <div>
                    <label for="sequence-accession"><?= h(t('Zugangsnummer')) ?></label>
                    <input type="text" id="sequence-accession" maxlength="80" placeholder="<?= h(t('GenBank oder UNITE')) ?>">
                </div>
                <div>
                    <label for="sequence-sample"><?= h(t('Probennummer')) ?></label>
                    <input type="text" id="sequence-sample" maxlength="80">
                </div>
            </div>

            <div class="field two-columns">
                <div>
                    <label for="sequence-lab"><?= h(t('Labor')) ?></label>
                    <input type="text" id="sequence-lab" maxlength="120">
                </div>
                <div>
                    <label for="sequence-primers"><?= h(t('Primer')) ?></label>
                    <input type="text" id="sequence-primers" maxlength="120" placeholder="ITS1F / ITS4">
                </div>
            </div>

            <div class="field">
                <label for="sequence-note"><?= h(t('Notiz')) ?></label>
                <textarea id="sequence-note" rows="2"></textarea>
            </div>

            <div class="dialog-footer">
                <button type="button" class="button subtle" id="sequence-cancel"><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button" id="sequence-save"><?= h(t('Speichern')) ?></button>
            </div>
        </form>
    </dialog>

    <?php /* THE MOTIVNAME, RIGHT WHEN THE PICTURE IS TAKEN
         (10.09.2026: „Aufnahmen mit der Handykamera sollten, wenn die Cloud
         angeschlossen ist, immer eine Abfrage bekommen, was der Motivname
         ist, dass dieser gleich feststeht … ebenso für jedes Foto, was wir
         auswählen, also in die Cloud laden").

         ONE TAP AND ON: the suggestions stand as real buttons, and any one
         of them closes the window as well. In the woods every second
         gesture is one too many — whoever had to tap first and then
         confirm would leave the name out and never add it later.

         The field below is for everything that is not in the list.
         THE WAY OUT IS „ABBRECHEN" (15.11.2026: every picture
         has a name): nothing of the choice goes up. Until then it was
         „Ohne Namen", and the photo went up unnamed. */ ?>
    <?php /* WHO TOOK THE PHOTOS — asked once for all picked files, before their
         names, on the „Auswählen" road only (08.11.2026: a photo
         from a friend uploaded into one's own account would otherwise carry
         one's own name as its author). „Aufnehmen" and the microscope make
         one's own pictures and do not ask. „Ich" stands chosen every time —
         a remembered „jemand anderes" would file the next own photos under
         a stranger's name. A photographer the file names itself is offered
         in the field, not chosen (photogallery.js, askAuthor). */ ?>
    <dialog class="form-window" id="photo-author-window">
        <form id="photo-author-form">
            <h2><?= h(t('Urheber')) ?></h2>
            <fieldset class="field question">
                <legend id="photo-author-question"><?= h(t('Wer hat die Fotos gemacht?')) ?></legend>
                <label class="choice-row">
                    <input type="radio" name="photo-author" value="me" checked>
                    <span id="photo-author-me"><?= h(t('Ich')) ?></span>
                </label>
                <label class="choice-row">
                    <input type="radio" name="photo-author" value="other">
                    <span><?= h(t('Jemand anderes:')) ?></span>
                </label>
                <input type="text" id="photo-author-name" maxlength="120" autocomplete="off"
                       placeholder="<?= h(t('Vor- und Nachname')) ?>">
                <p class="hint-text small" id="photo-author-camera" hidden></p>
            </fieldset>
            <p class="hint-text small"><?= h(t('Der Urheber steht später in jedem Bild, das du exportierst. Ändern kannst du ihn im Bildbetrachter.')) ?></p>
            <div class="dialog-footer">
                <button type="button" class="button subtle" id="photo-author-cancel"><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button"><?= h(t('Weiter')) ?></button>
            </div>
        </form>
    </dialog>

    <dialog class="form-window pinned-footer" id="photo-subject-window">
        <form id="photo-subject-form">
            <?php /* WHICH PHOTO IS ASKED FOR, IN SIGHT (28.09.2026:
                 two photos picked, „Hauptmotiv" chosen, and only the first
                 got it). The second window came at once and looked exactly
                 like the first — only the small file name changed — so the
                 tap seemed to have done nothing, and the next tap went to
                 „Übernehmen", which with an empty field meant „without a
                 name". Now the heading counts („Motivname für Foto 2 von
                 2") and the picture itself stands in the window. */ ?>
            <h2 id="photo-subject-title"><?= h(t('Motivname')) ?></h2>
            <p class="hint-text small" id="photo-subject-file"></p>
            <?php /* The frame holds the zoom — from the microscope the window is
                 the whole screen, and the shot is checked up close: did the
                 spores drift, are there streaks (photogallery.js). */ ?>
            <div class="photo-subject-frame" id="photo-subject-frame">
                <img class="photo-subject-preview" id="photo-subject-preview" alt="" hidden>
            </div>
            <?php /* THE KIND FIRST (10.09.2026: „sodass wir zuerst
                 auswählen, ist das ein makroskopisches Foto oder ein
                 mikroskopisches"). It picks the list below — and decides
                 whether the file is shrunk before uploading
                 (Einstellungen → Fotos → Maßstab-Vorlagen). Preset with
                 the previous photo's answer: a batch of the same kind
                 then costs only the one tap on the name per picture. */ ?>
            <div class="photo-subject-kind" id="photo-subject-kind" role="group"
                 aria-label="<?= h(t('Art des Fotos')) ?>">
                <button type="button" class="button subtle small" data-kind="macro"><?= h(t('Makro')) ?></button>
                <button type="button" class="button subtle small" data-kind="micro"><?= h(t('Mikro')) ?></button>
            </div>
            <?php /* FROM THE MICROSCOPE: ASCO OR BASIDIO in place of the kind,
                 which is Mikro there anyway (09.11.2026). The two
                 tabs only sort the names, and within a tab heads gather
                 them by part (photos.php, PHOTO_TITLES_MICRO_GROUPS). */ ?>
            <div class="photo-subject-tabs" id="photo-subject-group" role="tablist"
                 aria-label="<?= h(t('Pilzgruppe')) ?>" hidden>
                <button type="button" role="tab" data-group="asco" aria-selected="false"><?= h(t('Ascomyceten')) ?></button>
                <button type="button" role="tab" data-group="basidio" aria-selected="false"><?= h(t('Basidiomyceten')) ?></button>
            </div>
            <?php /* ONE NAME FOR THE WHOLE BATCH, ON REQUEST: ten spore pictures
                 want „Sporenpulver" once, not ten times. It stands ABOVE the
                 names because a name finishes the window at the first tap —
                 set below them, the tick would be read after the choice. Off
                 each time: after a tour the pictures of one find are mostly
                 different subjects. Only while more photos follow. */ ?>
            <label class="checkbox" id="photo-subject-all-row" hidden>
                <input type="checkbox" id="photo-subject-all">
                <span id="photo-subject-all-text"></span>
            </label>
            <div class="photo-subject-choices" id="photo-subject-choices"></div>
            <div class="field">
                <label for="photo-subject-free"><?= h(t('Eigener Name')) ?></label>
                <input type="text" id="photo-subject-free" maxlength="120" autocomplete="off">
            </div>
            <?php /* „ÜBERNEHMEN" TAKES OVER WHAT WAS TYPED, and with nothing typed
                 there is nothing to take: quiet and not pressable until the
                 field holds a name. It used to glow on an empty field and
                 meant „Ohne Namen" there — the trap of 28.09.2026 (above).
                 Not pressable rather than merely quiet, unlike the save
                 buttons of the administration: pressed empty it would
                 answer a question its label never asked. */ ?>
            <div class="dialog-footer">
                <button type="button" class="button subtle small" id="photo-subject-skip"><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button small subtle" id="photo-subject-take" disabled><?= icon('save') ?><?= h(t('Übernehmen')) ?></button>
            </div>
        </form>
    </dialog>

    <?php /* MIKROSKOPIEREN (24.10.2026): the microscope camera's live
         picture, its stills whole into the find's cloud folder, and the
         objective — so the scale travels with every picture. The camera is
         no webcam the browser could see; the MicroCam app on the Mac it
         hangs on serves it on 127.0.0.1 (assets/js/microscope.js says the
         whole of it).

         THE PICTURE FIRST AND LARGEST — it is what the window is opened
         for. Beside it the objective, then the light: the objective changes
         with every slide, the exposure rarely. On the phone width all of it
         stands under the picture.

         „WELCHES OBJEKTIV?" LIES OVER THE PICTURE, not in a window of its
         own: turning the revolver blacks the picture out, and the question
         stands exactly where one is looking at that moment. One tap on an
         objective answers it — after the lamp was only switched off and on
         again, the one that still stands (a button „Unverändert" did that
         until 20.11.2026). The medium is asked under it. */ ?>
    <dialog class="form-window microscope-window" id="microscope-window">
        <form method="dialog" id="microscope-form">
            <?php /* THE WAY OUT IS THE ARROW IN THE CORNER, and there is no
                 footer (26.10.2026: „schließen, machen wir wieder
                 einen Pfeil oben links hin … eine extra Fußzeile für einen
                 Aufnehmen-Knopf brauchen wir auch nicht"). Full screen, the
                 window is a page of its own — and a page is left by the
                 arrow back. A submit of the dialog form, so Escape and the
                 arrow are the same close. */ ?>
            <div class="microscope-head">
                <button type="submit" value="close" class="button subtle small window-x" id="microscope-close"
                        title="<?= h(t('Schließen')) ?>" aria-label="<?= h(t('Schließen')) ?>"><?= icon('arrow-left') ?></button>
                <?php /* FUND · MIKROSKOP · SCHLÜSSEL — THE SAME ROW AS IN THE
                     FIND'S BAR, and like there BEFORE THE SPECIES' NAME, right after the
                     arrow (27.11.2026; pages/finds.php says why; 24.11.2026: „eine
                     einheitliche Kopfzeile mit den 3 Buttons"). It began
                     here on 05.11.2026 as the window's own three views
                     („ich brauche einen weg zwischen mikroskopieren Bilder
                     und schlüssel ich muß ständig dazwischen wechseln beim
                     schlüsseln"), with the key's page inside the window.
                     Now the key lives in the page behind, where it outlasts
                     the window („den Schlüssel direkt aus dem Mikroskop
                     brauchen wir dann nicht mehr"), and both other buttons
                     do the same thing: the window STEPS ASIDE for the page
                     and the camera runs on — no objective asked again
                     (microscope.js, stepAside). */ ?>
                <div class="area-switch microscope-views" id="microscope-views" role="group" aria-label="<?= h(t('Bereich')) ?>">
                    <button type="button" class="button subtle small" data-area="find" aria-pressed="false"><?= icon('photo') ?><?= h(t('Fund')) ?></button>
                    <button type="button" class="button subtle small square is-active" data-area="microscope" aria-pressed="true"
                            title="<?= h(t('Mikroskop')) ?>" aria-label="<?= h(t('Mikroskop')) ?>"><?= icon('microscope') ?></button>
                    <?php /* The third only where the page behind has a key area
                         for this find (the host's areas.keyOffered) — on the
                         map there is none, and for a guest
                         (includes/modules.php, identify_tool) the button does
                         not exist at all. */ ?>
                    <?php if (identify_tool(user_current()) !== null): ?>
                    <button type="button" class="button subtle small square" data-area="key" aria-pressed="false" hidden
                            title="<?= h(t('Bestimmung')) ?>" aria-label="<?= h(t('Bestimmung')) ?>"><?= icon('key') ?></button>
                    <?php endif; ?>
                </div>
                <?php /* THE SPECIES LARGE, THE WINDOW'S NAME SMALL
                     (15.11.2026: „oben links fett mikroskopieren und ganz klein
                     rechts daneben die Art. Das bitte genau umdrehen und auch
                     mit der Schriftgröße"): at the microscope one wants to
                     see which find the pictures go to; that one is
                     microscoping, the window itself says. */ ?>
                <h2 id="microscope-point"></h2>
                <p class="hint-text small"><?= h(t('Mikroskopieren')) ?></p>
                <?php /* THE DEVICE IN THE HEAD (04.11.2026: „Die
                     Geräteauswahl würde ich in die Kopfzeile legen"): which
                     microscope this camera hangs on is the frame of the whole
                     session, not one setting among the objective and the
                     light. Shown from one device on, so the head always says
                     whose objectives stand beside the picture. */ ?>
                <div class="microscope-head-end">
                    <div class="microscope-head-devices" id="microscope-devices-field" hidden>
                        <span class="hint-text small"><?= h(t('Gerät')) ?></span>
                        <div class="microscope-objective-list" id="microscope-devices"></div>
                    </div>
                    <?php /* ONE GEAR FOR DEVICES AND OBJECTIVES, at the head's very
                         end (04.11.2026: „zusammen in ein Button …
                         ganz rechts ein Zahnrad"). It opens the window below;
                         choosing stays with the buttons beside it. */ ?>
                    <button type="button" class="button subtle small square" id="microscope-manage"
                            title="<?= h(t('Einstellungen')) ?>" aria-label="<?= h(t('Einstellungen')) ?>"><?= icon('gear') ?></button>
                </div>
            </div>
            <div class="microscope-body">
                <?php /* WHAT A MEDIUM IS FOR, beside the sidebar over the picture
                     while the pointer rests on it (04.11.2026:
                     „der links außerhalb der Sidebar, also eigentlich im
                     Mikroskopbild, dann aufgeht. Dass er … die Sidebar nicht
                     überdeckt"). Filled from media.js (MEDIA_INFO). */ ?>
                <div class="microscope-medium-info" id="microscope-medium-info" role="tooltip" hidden></div>
                <div class="microscope-stage" id="microscope-stage">
                    <?php /* crossorigin: MicroCam answers only an Origin it
                         knows, and the browser sends one only with it. */ ?>
                    <img class="microscope-live" id="microscope-live" crossorigin="anonymous" alt="" hidden>
                    <?php /* A WEBCAM'S PICTURE, where the camera is one: the
                         browser sees it itself, no program in between
                         (microscope.js, the webcam source). */ ?>
                    <video class="microscope-live" id="microscope-video" muted playsinline hidden></video>
                    <?php /* THE STACK AS IT GROWS, while the shutter is held — the
                         live frame steps aside into a corner then
                         (microscope.js, the stack's preview). */ ?>
                    <canvas class="microscope-live microscope-stack-view" id="microscope-stack-view" hidden></canvas>
                    <span class="microscope-live-label" id="microscope-live-label" hidden><?= h(t('Live')) ?></span>
                    <?php /* The chosen objective's scale bar over the big picture,
                         dressed like the photo window's (microscope.js, the
                         scale bar). */ ?>
                    <div class="photo-scalebar microscope-scalebar" id="microscope-scalebar" hidden>
                        <span class="photo-scalebar-line"></span>
                        <span id="microscope-scalebar-label"></span>
                    </div>
                    <?php /* QUICK MEASURING ON THE LIVE PICTURE
                         (16.11.2026: „ganz einfach für zwischendurch mal
                         schnell was nachschauen"): a line and length × breadth,
                         kept nowhere, a simple statistic in the upper left
                         corner, the bucket empties it (microscope.js, quick
                         measuring). The statistic's button on top, because
                         the statistic stands at the top beside it. */ ?>
                    <svg class="microscope-measures" id="microscope-measures" aria-hidden="true"></svg>
                    <?php /* THE TWO SWITCHES ON TOP — the statistic and the
                         core area's circle (22.11.2026) both show or hide
                         something —, then the tools, the bucket at the foot.
                         data-needs-scale: what measures needs an objective
                         with a scale; the circle does not, so without one
                         the rail holds its button alone (microscope.js). */ ?>
                    <div class="microscope-measure-rail" id="microscope-measure-rail" hidden>
                        <button type="button" class="button subtle" data-quick="stats" data-needs-scale
                                title="<?= h(t('Statistik zeigen')) ?>" aria-label="<?= h(t('Statistik zeigen')) ?>"><?= icon('info') ?></button>
                        <button type="button" class="button subtle" data-quick="zone"
                                title="<?= h(t('Kernbereich zeigen')) ?>" aria-label="<?= h(t('Kernbereich zeigen')) ?>"><?= icon('core-area') ?></button>
                        <span class="microscope-measure-rail-line" data-needs-scale aria-hidden="true"></span>
                        <button type="button" class="button subtle" data-quick="line" data-needs-scale
                                title="<?= h(t('Strecke')) ?>" aria-label="<?= h(t('Strecke')) ?>"><?= icon('measure-line') ?></button>
                        <button type="button" class="button subtle" data-quick="rect" data-needs-scale
                                title="<?= h(t('Länge × Breite')) ?>" aria-label="<?= h(t('Länge × Breite')) ?>"><?= icon('measure-rect') ?></button>
                        <span class="microscope-measure-rail-line" data-needs-scale aria-hidden="true"></span>
                        <button type="button" class="button subtle is-clear" data-quick="clear" data-needs-scale
                                title="<?= h(t('Messungen löschen')) ?>" aria-label="<?= h(t('Messungen löschen')) ?>"><?= icon('delete') ?></button>
                    </div>
                    <div class="microscope-measure-stats" id="microscope-measure-stats" hidden></div>
                    <div class="microscope-cal-banner" id="microscope-measure-banner" hidden></div>
                    <?php /* The still a calibration is drawn on, and the line
                         over it — a canvas, not a drawing of markup: it is
                         the picture's own pixels it counts. */ ?>
                    <?php /* THE CALIBRATION'S STILL in a frame of its own, ON TOP of
                         the live picture: the wheel zooms it and a drag moves
                         it (imagezoom.js), and whatever the camera does
                         meanwhile — a new frame, a freeze — stays under it.
                         The line's canvas is NOT zoomed along: it draws in
                         screen pixels, so the line stays thin at 8× too. */ ?>
                    <div class="microscope-cal-frame" id="microscope-cal-frame" hidden>
                        <img class="microscope-live" id="microscope-still" alt="">
                        <canvas class="microscope-calibrate-line" id="microscope-calibrate-canvas"></canvas>
                    </div>
                    <?php /* WHAT TO CLICK, ON THE PICTURE where one clicks
                         (03.11.2026: „wo kann ich die Strecke
                         zeichnen und wie?"). A grey sentence in the column
                         beside it was read past; this line follows the two
                         clicks (microscope.js, calClickSay). */ ?>
                    <div class="microscope-cal-banner" id="microscope-cal-banner" hidden></div>
                    <?php /* TOO BRIGHT FOR THE FLICKER GUARD (MicroCam's tooBright,
                         04.11.2026): with 50 Hz anti-flicker the exposure
                         cannot go below 10 ms, and a lamp turned up blows the
                         picture out — only the lamp can help, so it is said
                         where the eye is. Same place and dress as the
                         calibration's line, never at the same time. */ ?>
                    <div class="microscope-cal-banner" id="microscope-too-bright" hidden><?= h(t('Das Bild ist zu hell. Dreh die Lampe etwas zurück.')) ?></div>
                    <?php /* WHILE THE SHUTTER IS HELD: how many frames the stack has
                         so far — that it runs, and that turning the focus
                         brings new ones (microscope.js, the stack). */ ?>
                    <div class="microscope-stack-badge" id="microscope-stack-badge" hidden></div>
                    <div class="microscope-message" id="microscope-message" hidden>
                        <p id="microscope-message-text"></p>
                        <p class="hint-text small" id="microscope-message-detail"></p>
                        <button type="button" class="button subtle small" id="microscope-retry" hidden><?= icon('refresh') ?><?= h(t('Erneut versuchen')) ?></button>
                    </div>
                    <div class="microscope-ask" id="microscope-ask" hidden>
                        <p class="microscope-ask-question" id="microscope-ask-question"><?= h(t('Welches Objektiv?')) ?></p>
                        <div class="microscope-ask-choices" id="microscope-ask-choices"></div>
                        <?php /* THE MEDIUM, ASKED IN THE SAME BREATH
                             (20.11.2026: „Beim Objektivwechsel soll … der
                             Button unverändert soll weg. Und dafür soll unten
                             noch eine Auswahl der Medien sein, auch mit den
                             häufigen und ein Ausklappen für besondere"). A
                             turned revolver often means a new slide, and the
                             medium was forgotten in the sidebar. The same
                             ticks as there — one choice, drawn twice
                             (microscope.js, drawMedia). They are set first;
                             the objective answers the question and closes
                             it. „Unverändert" went: the objective that still
                             stands is one of the buttons above. */ ?>
                        <p class="microscope-ask-question microscope-ask-second"><?= h(t('Welches Medium?')) ?></p>
                        <div class="microscope-ask-choices" id="microscope-ask-media"></div>
                        <button type="button" class="button subtle small" id="microscope-ask-media-toggle"
                                aria-expanded="false" aria-controls="microscope-ask-media-more"><?= h(t('Weitere')) ?></button>
                        <div class="microscope-ask-choices" id="microscope-ask-media-more" hidden></div>
                    </div>
                </div>
                <div class="microscope-side">
                    <?php /* THE COLUMN'S HEAD, ONLY WHERE THE WINDOW IS AN AREA OF
                         THE PAGE (the Fundbrowser; microscope.js, inPage).
                         There the window's own head is not drawn — the
                         find's bar stands above it —, and what that head
                         held besides the name comes here: the device and
                         its gear (microscope.js moves them in). AT THE TOP,
                         as the column's heading (24.11.2026: „das ist eher
                         etwas für die Überschrift der Sidebar. Ganz oben") —
                         it says whose objectives stand beneath it. */ ?>
                    <div class="microscope-side-top" id="microscope-side-top" hidden></div>
                    <?php /* THE SHUTTER AT THE TOP OF THE COLUMN — what the
                         window is for, beside the picture, where the eye
                         is. The space bar releases it as well
                         (microscope.js). Its progress right under it. */ ?>
                    <div class="microscope-shoot">
                        <button type="button" class="button" id="microscope-take" disabled><?= icon('photo') ?><?= h(t('Aufnehmen')) ?></button>
                        <p class="hint-text small microscope-progress" id="microscope-progress"></p>
                    </div>
                    <fieldset class="field question microscope-objectives" id="microscope-objectives-field">
                        <?php /* EICHEN WHERE ONE IS (24.10.2026): the
                             stage micrometer lies under the microscope, the
                             camera is live — the still comes from here, in
                             full size, with no file to look for. A SYMBOL AT
                             THE LABEL'S END, not a button of its own under the
                             list (04.11.2026: „kein Riesen-Button,
                             wo Objektiv Eichen draufsteht … so ein Maßstab. Ja,
                             den können wir nehmen") — it re-calibrates one and
                             adds a new one alike. Quiet: the standing offer. */ ?>
                        <legend class="microscope-side-head">
                            <span><?= h(t('Objektiv')) ?></span>
                            <button type="button" class="button subtle small square" id="microscope-calibrate-start"
                                    title="<?= h(t('Objektiv eichen')) ?>" aria-label="<?= h(t('Objektiv eichen')) ?>"><?= icon('ruler') ?></button>
                        </legend>
                        <div class="microscope-objective-list" id="microscope-objectives"></div>
                        <p class="hint-text small" id="microscope-objectives-none" hidden><?= h(t('Noch kein Objektiv geeicht. Klicke rechts neben „Objektiv" auf das Maßstab-Zeichen. Dann bekommt jedes Foto gleich seinen Maßstab.')) ?></p>
                    </fieldset>
                    <?php /* THE MEDIUM THE SLIDE LIES IN (04.11.2026,
                         migration 0187): ticks, several at once — H₂O and
                         KOH 3 %, H₂O and BWB —, the full name under the
                         pointer. The common ones in sight, the rest under
                         „Weitere"; a ticked one from there stays in sight
                         when it folds, so nothing is ticked out of view.
                         Every photo and stack carries them (microscope.js,
                         assets/js/media.js). „Weitere" / „Weniger" AT THE
                         LABEL'S END, in both states (07.11.2026):
                         under the list it jumped down and up with every
                         fold, and the hand had to look for it again. */ ?>
                    <fieldset class="field question microscope-media" id="microscope-media-field">
                        <legend class="microscope-side-head">
                            <span><?= h(t('Medium')) ?></span>
                            <button type="button" class="button subtle small" id="microscope-media-toggle"
                                    aria-expanded="false" aria-controls="microscope-media-more"><?= h(t('Weitere')) ?></button>
                        </legend>
                        <div class="microscope-media-list" id="microscope-media"></div>
                        <div class="microscope-media-list" id="microscope-media-more" hidden></div>
                    </fieldset>
                    <?php /* THE CALIBRATION, in place of objective and light
                         while it runs. First the live picture to set the
                         micrometer sharp, then the still to draw on.
                         IN THE ORDER IT IS DONE (03.11.2026): the
                         photo button first, name and length only once the
                         still is there — standing above the button, they
                         read as „fill in, then photograph", and the line
                         they need can only be drawn after the photo. */ ?>
                    <div class="microscope-calibrate" id="microscope-calibrate" hidden>
                        <?php /* FOR WHICH DEVICE, SAID FIRST (04.11.2026:
                             „beim Eichen soll dann explizit nochmal ein Text
                             erscheinen, für welches Gerät man eicht … oder ob
                             man ein neues Gerät anlegen möchte"). The head's
                             device stands ticked; a new one is named here and
                             made with the objective (calibration.php,
                             remember). */ ?>
                        <fieldset class="field question" id="microscope-cal-device" hidden>
                            <legend><?= h(t('Für welches Gerät eichst du?')) ?></legend>
                            <div class="microscope-cal-device-list" id="microscope-cal-devices"></div>
                            <div class="field" id="microscope-cal-newdevice" hidden>
                                <label for="microscope-cal-newdevice-name"><?= h(t('Name des neuen Geräts')) ?></label>
                                <input type="text" id="microscope-cal-newdevice-name" maxlength="100" autocomplete="off">
                            </div>
                        </fieldset>
                        <p class="hint-text" id="microscope-cal-step"></p>
                        <div class="button-row">
                            <button type="button" class="button small" id="microscope-cal-shoot"><?= icon('photo') ?><?= h(t('Foto für die Eichung')) ?></button>
                            <button type="button" class="button subtle small" id="microscope-cal-again" hidden><?= icon('refresh') ?><?= h(t('Neues Foto')) ?></button>
                        </div>
                        <div class="microscope-cal-fields" id="microscope-cal-fields" hidden>
                        <div class="field">
                            <label for="microscope-cal-name"><?= h(t('Name des Objektivs')) ?></label>
                            <input type="text" id="microscope-cal-name" maxlength="100" autocomplete="off" list="microscope-cal-names">
                            <datalist id="microscope-cal-names"></datalist>
                        </div>
                        <?php /* No clear cross on the length (03.11.2026):
                             a short number field, and the X took half of it. */ ?>
                        <div class="field microscope-cal-length">
                            <label for="microscope-cal-length"><?= h(t('Länge der Strecke')) ?></label>
                            <div class="microscope-cal-length-row">
                                <input type="text" id="microscope-cal-length" inputmode="decimal" autocomplete="off" data-no-clear>
                                <label class="choice-row"><input type="radio" name="microscope-cal-unit" value="1" checked><span>µm</span></label>
                                <label class="choice-row"><input type="radio" name="microscope-cal-unit" value="1000"><span>mm</span></label>
                            </div>
                        </div>
                        <p class="hint-text small" id="microscope-cal-pixels"></p>
                        </div>
                        <?php /* Its two concluding buttons at the panel's end:
                             saving stays the last thing. */ ?>
                        <div class="button-row right">
                            <button type="button" class="button subtle" id="microscope-cal-cancel"><?= h(t('Abbrechen')) ?></button>
                            <button type="button" class="button" id="microscope-cal-save"><?= icon('save') ?><?= h(t('Vorlage speichern')) ?></button>
                        </div>
                    </div>
                    <fieldset class="field microscope-light" id="microscope-light" disabled>
                        <legend><?= h(t('Belichtung')) ?></legend>
                        <label class="checkbox">
                            <input type="checkbox" id="microscope-auto">
                            <span><?= h(t('Automatisch')) ?></span>
                        </label>
                        <label class="microscope-slider" id="microscope-target-row">
                            <span><?= h(t('Helligkeit')) ?></span>
                            <input type="range" id="microscope-target" min="16" max="220" step="1">
                            <output id="microscope-target-value"></output>
                        </label>
                        <label class="microscope-slider" id="microscope-exposure-row">
                            <span><?= h(t('Zeit')) ?></span>
                            <input type="range" id="microscope-exposure" min="0" max="1000" step="1">
                            <output id="microscope-exposure-value"></output>
                        </label>
                        <label class="microscope-slider" id="microscope-gain-row">
                            <span><?= h(t('Verstärkung')) ?></span>
                            <input type="range" id="microscope-gain" min="100" max="300" step="1">
                            <output id="microscope-gain-value"></output>
                        </label>
                        <div class="button-row">
                            <button type="button" class="button subtle small" id="microscope-whitebalance"><?= h(t('Weißabgleich')) ?></button>
                        </div>
                    </fieldset>
                </div>
            </div>
        </form>
    </dialog>
    <?php /* BACK FROM THE PAGE: the window steps aside for what lies
         underneath — the find's own pictures, with everything the page does
         with them — and the camera runs on. This bar is the way back WHERE
         NO SWITCH ROW SHOWS: on the map. In the Fundbrowser the row's
         „Mikroskop" leads back and wears the sign that the camera runs,
         and the camera ends with the find (app.css hides the bar there). */ ?>
    <div class="microscope-return" id="microscope-return" hidden>
        <span class="hint-text small"><?= h(t('Die Kamera läuft weiter.')) ?></span>
        <button type="button" class="button" id="microscope-return-button"><?= icon('microscope') ?><?= h(t('Zurück zum Mikroskop')) ?></button>
    </div>

    <?php /* Step 1: the outline -----------------------------------------------
         Only name and polygon. Layers, zoom level and expiry do not come
         up here: whoever outlines a patch of forest at the desk does not
         yet know on which device they will need it later. */ ?>
    <?php /* THE ONE MOMENT A PICTURE LOSES PIXELS (04.10.2026): a
         micrograph goes up shrunk to 2048 px when the switch under
         Einstellungen → Fotos → Maßstab-Vorlagen is on, and the full size
         never reaches the cloud. Renaming a picture later never shrinks it
         — so this is the only place a warning belongs. It comes when
         „Mikro" is tapped, or, with the window already on Mikro from the
         last photo, before the first name is taken (map.js,
         microShrinkWarning). „Abbrechen" goes back to Makro. The tick
         waves it off on the account, for every device. */ ?>
    <?php /* DEVICES AND OBJECTIVES, renamed and deleted where one works
         (04.11.2026: „wo ich meine Geräte sehe, wo ich sie
         umbenennen kann, wo ich darunter unter jedem Gerät das jeweilige
         Objektiv sehe, wo ich das Objektiv umbenennen kann oder löschen
         kann"). One table per device, dressed like the settings'
         Maßstab-Vorlagen: the device's name in the head with its pencil and
         bucket, its objectives as rows (microscope.js, drawManage). */ ?>
    <dialog class="form-window microscope-manage-window pinned-footer" id="microscope-manage-window">
        <form method="dialog">
            <h2><?= h(t('Einstellungen')) ?></h2>
            <?php /* THE MIRRORING, set once and then forgotten
                 (09.11.2026: „das stelle ich einmal ein, danach ist das
                 fertig … muss uns nicht die ganze Zeit vor der Nase
                 rumflimmern"). Takes effect at once, like the media ticks:
                 nothing to save. microscope.js, the picture's orientation. */ ?>
            <fieldset class="field question">
                <legend><?= h(t('Kamerabild spiegeln')) ?></legend>
                <p class="hint-text"><?= h(t('Schiebst du den Objekttisch, soll das Bild in dieselbe Richtung wandern wie im Okular. Wandert es falsch herum, setze hier einen Haken.')) ?></p>
                <label class="checkbox">
                    <input type="checkbox" id="microscope-flip-h">
                    <span><?= h(t('Links und rechts tauschen')) ?></span>
                </label>
                <label class="checkbox">
                    <input type="checkbox" id="microscope-flip-v">
                    <span><?= h(t('Oben und unten tauschen')) ?></span>
                </label>
            </fieldset>
            <?php /* THE SERIES A STACK WAS MADE OF, as a file (13.11.2026). A
                 tool for getting the stacking right on REAL slides: the
                 thinness measure of that evening was tuned on drawn
                 pictures and failed on the maintainer's first tissue. With the
                 tick, every stack also hands the browser one file holding
                 its single frames and the result (microscope.js,
                 seriesSave). Off by default; per computer. */ ?>
            <fieldset class="field question">
                <legend><?= h(t('Stapeln prüfen')) ?></legend>
                <label class="checkbox">
                    <input type="checkbox" id="microscope-keep-series">
                    <span><?= h(t('Einzelbilder jedes Stapels als Datei speichern')) ?></span>
                </label>
                <p class="hint-text small"><?= h(t('Nach jedem Stapel lädt der Browser eine Datei „Fokusserie …" herunter. Sie enthält alle Einzelbilder und das Ergebnis.')) ?></p>
            </fieldset>
            <h3 class="microscope-manage-head"><?= h(t('Geräte und Objektive')) ?></h3>
            <p class="hint-text"><?= h(t('Ein neues Gerät oder Objektiv legst du mit dem Maßstab-Zeichen neben „Objektiv" an.')) ?></p>
            <p class="microscope-manage-error" id="microscope-manage-error" role="alert" hidden></p>
            <div class="microscope-manage-list" id="microscope-manage-list"></div>
            <div class="dialog-footer">
                <button type="submit" class="button"><?= h(t('Schließen')) ?></button>
            </div>
        </form>
    </dialog>

    <dialog class="dialog" id="micro-shrink-warning">
        <form method="dialog">
            <h2 class="dialog-title"><?= h(t('Mikrofotos werden verkleinert')) ?></h2>
            <p class="dialog-text"><?= h(t('Ein Mikrofoto geht auf 2048 Pixel verkleinert in die Cloud. Die volle Größe des hochgeladenen Bildes ist damit verloren, außer du hast noch eine Sicherheitskopie auf der Festplatte und behältst diese. YASAIMAPP speichert sie nicht.')) ?></p>
            <p class="dialog-text"><?= t('Abschalten kannst du das unter <b>Einstellungen → Fotos → Maßstab-Vorlagen</b>.') ?></p>
            <label class="checkbox">
                <input type="checkbox" id="micro-shrink-warning-off">
                <span><?= h(t('Nicht mehr anzeigen')) ?></span>
            </label>
            <div class="dialog-footer">
                <button type="submit" class="button subtle" value="cancel"><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button" value="ok"><?= h(t('Verstanden')) ?></button>
            </div>
        </form>
    </dialog>
    <?php
}
