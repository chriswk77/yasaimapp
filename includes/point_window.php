<?php
/**
 * The point window's markup — the <aside id="detail"> with its tabs, fields
 * and footer.
 *
 * OUT OF index.php ON 11.10.2026, as the second step of the point window
 * becoming one window for two pages (assets/js/pointwindow.js wires it up;
 * the Fundbrowser prints it too). The way photo_window.php and
 * plate_window.php went: every page that mounts the window prints the
 * markup first, and this is the one place it is written. Moved as it
 * stood, the comments in it are the ones it had.
 *
 * Styles in assets/css/pointwindow.css, which a page loads with this.
 */

declare(strict_types=1);

require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/find_fields.php';
require_once __DIR__ . '/species_packages.php';
require_once __DIR__ . '/map_layers.php';
require_once __DIR__ . '/container.php';
require_once __DIR__ . '/folders.php';
require_once __DIR__ . '/friends.php';
require_once __DIR__ . '/find_number.php';
require_once __DIR__ . '/field_times.php';
require_once __DIR__ . '/person_names.php';
require_once __DIR__ . '/point_hints.php';
require_once __DIR__ . '/cloud.php';

/** Print the point window — before the page's scripts. */
function point_window_render(): void
{
    ?>
    <aside class="detail" id="detail" hidden>
        <?php /* THE GRIP, as on the left (10.09.2026: „in der
             Desktopversion können wir diese rechte Seitbahn auch so
             gestalten, dass man deren Größe verändern kann … also wie die
             Linke mit einem Handle zum Verschieben und zum Größe ändern").
             He himself thought folding away questionable („ist, glaub ich,
             nicht sinnvoll, aber doch machen wir auch") — it costs nothing
             extra, because dragging and clicking share the same grip.

             IT STANDS AS THE FIRST CHILD and not as a floating bar: the
             window is a row of grip and content, so it takes up room
             instead of covering something — the same decision as on the
             left. It needs no divider of its own; the window's edge
             toward the map already is one. */ ?>
        <button type="button" class="detail-grip" id="detail-grip"></button>

        <div class="detail-body">
        <?php /* NO X HERE ANYMORE — closing happens only via the Schließen
             button in the footer, see there. The trash can sits directly
             behind the name instead: deleting is an action ON THE POINT,
             not a tool of the window chrome. Pulled up here from the
             footer at the request, 14.08.2026. */ ?>
        <div class="detail-header">
            <?php /* THE FACTS LINE STANDS UNDER THE NAME, IN THE HEADER
                 (11.09.2026: „die Info mit der Fundnummer, der
                 Genauigkeit et cetera kannst Du oben unter den Namen direkt
                 packen, noch in die Kopfzeile"). It used to sit under the
                 coordinates in the Text tab — but find number,
                 accuracy, elevation, map sheet, municipality and red list
                 are facts of the FIND, not of the coordinate, and they
                 belong where every tab shows them. Name and line in one
                 box so the bucket keeps standing beside the name. */ ?>
            <div class="detail-heading">
                <h2 id="detail-title"><?= h(t('Punkt')) ?></h2>
                <p class="hint-text small detail-facts" id="d-accuracy"></p>
            </div>
            <?php /* BACK INTO THE KEY, where one left it (03.11.2026:
                 „wenn ich mitten in einem Schlüssel bin und gehe zurück zum
                 Fund, zum Beispiel um mir ein Mikrobild anzuschauen, will ich
                 wieder genau zurück zu diesem Schlüssel kommen … ähnlich wie
                 zurück zum Fund"). The twin of the key page's „Zurück zum
                 Fund", in the header so every tab shows it — the picture one
                 went to look at is under Belege. Only while one's own way at
                 this find stands open (pointwindow.js, drawKeyings). */ ?>
            <a class="button small subtle detail-key-back" id="d-key-back" hidden><?= icon('key') ?><?= h(t('Zurück zum Schlüssel')) ?></a>
            <button type="button" class="symbol-button small" id="d-facts-toggle"
                    aria-expanded="false" aria-controls="d-facts-more"
                    title="<?= h(t('Angaben zum Fund')) ?>" aria-label="<?= h(t('Angaben zum Fund')) ?>"><?= icon('info') ?></button>
            <button class="symbol-button small delete" id="d-delete"
                    title="<?= h(t('Punkt löschen')) ?>" aria-label="<?= h(t('Punkt löschen')) ?>"><?= icon('delete') ?></button>
                <?php /* ALL THE FACTS, WITH WHAT THEY MEAN (30.09.2026:
                     „In der Titelzeile soll eigentlich eine verkürzte
                     Version stehen. Man könnte ja einen Info-Knopf machen,
                     wo man diese Information ausklappen kann. Und dann
                     kommen da genau die ganzen Daten mit
                     Zusatzinformationen"). The line above says what one
                     reads at a glance; this table says everything, one row
                     a fact, the explanation beside it. Filled by map.js
                     (fillDetailUneditable), opened by the ⓘ beside the
                     bucket or by a tap on the protected-area signs. A row
                     of the header of its own, UNDER name and buttons: inside
                     the name's box it pushed the ⓘ and the bucket down to
                     the middle of the table, and it gets the whole width. */ ?>
                <dl class="detail-facts-more" id="d-facts-more" hidden></dl>
        </div>

        <?php /* WHOSE FIND, when it is not your own: "von Tobias Lenz ·
             Kartierung Nordhang → Pilze". Only shown on foreign points —
             on your own it would be the same name in every window and say
             nothing. Above the content and not inside it, because it is
             the notice under which everything that follows is to be read:
             what stands grayed out here is not broken, it belongs to
             someone else. */ ?>
        <p class="detail-origin" id="d-origin" hidden></p>

        <?php /* THREE TABS INSTEAD OF ONE LONG STRIP (10.09.2026:
             „im Punkte-Bearbeiten-Fenster hätt ich auch gerne Tabs — einen
             Tab, wo man die ganzen Texte bearbeiten kann bis Notiz, dann
             einen Tab für die Fotos und einen Tab für den Rest: Listen,
             Koordinaten, Fundzeit").

             It is THE SAME BAR as above the sidebar, class for class —
             two windows in one scene, and a second pattern for the same
             thing would be the twin that drifts apart. It carries only
             the one exception: here it does NOT sit on the phone's
             selection layer (tabs.js), because three short words fit side
             by side under a finger, and a fold-out menu would be one more
             move for the same thing.

             The tabs read Text · Belege · Karte since the sequences came
             (22.09.2026); „Fotos" was the second one's first name.

             THE NUMBER ON „BELEGE" is not decoration. Behind a tab one
             cannot see whether anything lies there — the same reasoning
             as for the number on the sidebar's points tab. */ ?>
        <div class="sidebar-tabs detail-tabs" role="tablist"
             aria-label="<?= h(t('Text, Belege oder Karte')) ?>">
            <button type="button" role="tab" id="d-tab-text" aria-controls="d-panel-text"
                    aria-selected="true"><?= h(t('Text')) ?></button>
            <button type="button" role="tab" id="d-tab-photos" aria-controls="d-panel-photos"
                    aria-selected="false" tabindex="-1"><?= h(t('Belege')) ?> <span class="tab-count" id="d-photo-count" hidden>0</span></button>
            <button type="button" role="tab" id="d-tab-place" aria-controls="d-panel-place"
                    aria-selected="false" tabindex="-1"><?= h(t('Karte')) ?></button>
        </div>

        <div class="detail-content">
            <div class="detail-panel" id="d-panel-text" role="tabpanel"
                 aria-labelledby="d-tab-text">
            <?php /* AUFSAMMLUNGEN (0174, collectionui.js): on an Aufsammlung its
                 number and whether the culture runs; on a species grown
                 from one „Aus Kultur" and the way back to it. */ ?>
            <p class="detail-collection-line" id="d-collection-line" hidden>
                <span id="d-collection-text"></span>
                <button type="button" class="button subtle small" id="d-collection-open" hidden></button>
            </p>
            <div class="field">
                <label for="d-title"><?= h(t('Art')) ?></label>
                <?php /* autocomplete off: the browser would otherwise lay its
                     own suggestion list over ours. */ ?>
                <input type="text" id="d-title" maxlength="200" placeholder="<?= h(t('Gefundene Art')) ?>"
                       autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list">
                <ul class="species-suggestions" id="d-species" hidden></ul>
                <?php /* THE KEY AND THE WAYS (14.10.2026: „Art und
                     Schlüssel verbinden"). The key opens the identification
                     tool for this find — where the start data names one
                     (keyTool: a module offers it to this account) and the
                     species may be written; the ways walked for the find stand
                     under the field for everyone who sees it, the phone too.
                     pointwindow.js, drawKeyings. */ ?>
                <?php /* What the button says is what its page holds: a
                     key where a module offers one, else the Internet-Links
                     and the finds (includes/modules.php, identify_tool) — a
                     title promising keys over a page without any would be
                     the first thing a beginner trips over. */
                $keyTitle = (identify_tool(user_current())['plain'] ?? false)
                    ? t('Bestimmung — Internet-Links und Funde zum Namen')
                    : t('Bestimmen — mit den Schlüsseln zur Art'); ?>
                <a class="symbol-button small detail-key" id="d-key" hidden
                   title="<?= h($keyTitle) ?>"
                   aria-label="<?= h($keyTitle) ?>"><?= icon('key') ?></a>
                <div class="detail-keyings" id="d-keyings" hidden></div>
            </div>

            <?php /* THE WAY TO THE NAME, TYPED BY HAND (20.11.2026, migration
                 0198: „ein Text-Area-Feld, falls man in zwei Büchern
                 geschlüsselt hat, dass man eine neue Zeile machen kann …
                 einzeilig darstellen und nur bei entsprechendem Platzbedarf
                 vergrößern"). For whoever keys in the book itself: one line,
                 and autoheight.js lets it grow with what is typed. It is
                 one's OWN way — every person has one per find, the others'
                 stand read under „Art" —, so it is open on a friend's find
                 too and saves itself when it is left (pointwindow.js,
                 drawKeyings and saveWay). Hidden until the server has said
                 what stands there: an empty field shown too early would
                 invite typing over a way that only had not arrived yet. */ ?>
            <div class="field" id="d-key-way-field" hidden>
                <label for="d-key-way"><?= h(t('Schlüsselweg')) ?></label>
                <textarea id="d-key-way" rows="1" maxlength="4000"></textarea>
            </div>

            <?php /* The custom fields of the lists this point lies in. If it
                 lies in several, all appear — merged by name, so the same
                 field does not stand there twice.

                 DIRECTLY UNDER THE TITLE, and the note after them. These
                 fields are what gets filled in at the find — substrate,
                 accompanying plants, whatever the list provides. The note
                 is the collecting place for everything else and can wait.
                 When the custom fields stood behind the note, you had to
                 scroll past a multi-line text field to get to the actual
                 recording. */ ?>
            <div id="d-extra-fields"></div>

            <?php /* WHAT GREW FROM THE AUFSAMMLUNG — each species an ordinary
                 find; the same species may stand twice, one find per
                 fruiting (15.10.2026). „Art hinzufügen" makes a
                 find at the Aufsammlung's place and day. */ ?>
            <div class="field" id="d-collection-species-field" hidden>
                <label><?= h(t('Arten aus dieser Aufsammlung')) ?></label>
                <ul class="person-list detail-collection-species" id="d-collection-species"></ul>
                <div class="button-row">
                    <button type="button" class="button subtle small" id="d-collection-add"><?= icon('plus') ?><?= h(t('Art hinzufügen')) ?></button>
                    <?php /* Only for one's own Aufsammlung without species
                         (collectionui.js, collection_to_find): a mushroom
                         mapped as an Aufsammlung by mistake. */ ?>
                    <button type="button" class="button subtle small" id="d-collection-to-find" hidden><?= h(t('In Fund umwandeln')) ?></button>
                </div>
            </div>

            <div class="field">
                <label for="d-note"><?= h(t('Notiz')) ?></label>
                <?php /* No placeholder. The field is labeled "Notiz", and with
                     that everything is said; a suggestion of what belongs
                     in it only narrows things down. */ ?>
                <textarea id="d-note"></textarea>
            </div>

            <?php /* THE FIND NUMBER BY HAND (25./26.09.2026, point 3
                 of the numbers): for one's own finds that came without their
                 number — an import from another app, a voucher label older
                 than the app — and to mend a typo. Only on one's OWN finds:
                 the number belongs to the holder's series, and on anybody
                 else's find the facts line above shows it. Empty means no
                 number of one's own; the find then wears YASAIMAPP's „Y-"
                 mark, which the placeholder shows. The line under it says at
                 once when another find carries the number already, or when
                 it begins with that mark (map.js, drawNumberHint). */ ?>
            <div class="field" id="d-number-field" hidden>
                <label for="d-number"><?= h(t('Fundnummer')) ?></label>
                <input type="text" id="d-number" maxlength="80" autocomplete="off" spellcheck="false">
                <p class="hint-text small" id="d-number-hint" hidden></p>
            </div>
            <?php /* The find fields that belong beside the find number — the
                 voucher kept and its number (find_fields.php, 'after'). */ ?>
            <div id="d-extra-after-number"></div>

            <?php /* Time of the find.
                 IN TEXT SINCE 22.09.2026 („die Fundzeit ist Daten,
                 das ist irgendwie auch Text") — with the coordinates right
                 after it: what the find says, and when and where it was. On creation the device's clock fills
                 it in correctly — the field is for the case that someone
                 back-enters a find from last week.

                 WITH TIME OF DAY, not just the date. It is stored that way
                 in the database and decides which tour a point belongs to;
                 a field that silently set it to midnight on save would
                 shift the point into another tour without anyone seeing
                 it. */ ?>
            <div class="field">
                <?php /* "Fundzeit" and not "Gefunden am" (23.08.2026: the same
     word everywhere): the filter says „ohne Fundzeit", the table column,
     the CSV role and every import text say Fundzeit — this field was the
     one place that said something else, and the conflict list a third
     thing ("Fundzeitpunkt"). One datum, one word. */ ?>
<label for="d-found"><?= h(t('Fundzeit')) ?></label>
                <input type="datetime-local" id="d-found">
            </div>

            <?php /* The culture's two days (an Aufsammlung) and the day of the
                 determination (a species from one) — collectionui.js. */ ?>
            <div class="field-row detail-culture" id="d-culture" hidden>
                <div class="field">
                    <label for="d-cultured"><?= h(t('In Kultur seit')) ?></label>
                    <input type="date" id="d-cultured">
                </div>
                <div class="field">
                    <label for="d-culture-ended"><?= h(t('Kultur beendet')) ?></label>
                    <input type="date" id="d-culture-ended">
                </div>
            </div>
            <div class="field" id="d-identified-field" hidden>
                <label for="d-identified"><?= h(t('Bestimmt am')) ?></label>
                <input type="date" id="d-identified">
                <p class="hint-text small" id="d-identified-hint" hidden></p>
            </div>

            <div class="field">
                <label for="d-coord"><?= h(t('Koordinaten')) ?></label>
                <?php /* THE FIELD ONLY SHOWS (22.09.2026: „mein eigenen
                     Punkt über Texteingabe verfeinern? Ausgeschlossen — das
                     mache ich über Ziehen in der Karte"). Read-only, so no
                     clear cross grows on it (clearable.js) and no thumb can
                     wipe a position by accident. The one case that remains —
                     a coordinate from somewhere else, a friend's Google Maps
                     pin — goes through the pencil: it opens the field for
                     this one entry, and the next find closes it again. */ ?>
                <div class="coord-row">
                    <input type="text" id="d-coord" placeholder="51.123456, 7.654321" spellcheck="false" readonly>
                    <button type="button" class="symbol-button" id="d-coord-edit" title="<?= h(t('Koordinate eingeben — für eine Position von woanders')) ?>" aria-label="<?= h(t('Koordinate eingeben')) ?>"><?= icon('pencil') ?></button>
                    <button type="button" class="symbol-button" id="d-coord-copy" title="<?= h(t('Koordinaten kopieren')) ?>" aria-label="<?= h(t('Koordinaten kopieren')) ?>"><?= icon('copy') ?></button>
                </div>
            </div>


            </div>

            <div class="detail-panel" id="d-panel-photos" role="tabpanel"
                 aria-labelledby="d-tab-photos" hidden>
            <?php /* Recordings of the find — ONE section, and deliberately so.
                 Two buttons, two intentions, and since the call of
                 18.08.2026 two DESTINATIONS as well:

                 "Aufnehmen" opens the phone's camera and with it everything
                 it can do — macro, lens choice, exposure brackets. What
                 comes out of it is a note: shrunk to 2048 px, kept on this
                 server, counted against the user's quota.

                 "Auswählen" takes a finished file — a stack, a micrograph,
                 a raw file from the proper camera — and hands it straight
                 to the user's OWN cloud, in full resolution. Not through
                 this server, and that is the whole point: several users'
                 originals would fill it up, and upload_max_filesize is 2 MB
                 here anyway. Without a connected cloud there is nowhere to
                 put them, so the button is off and says why.

                 There is no accept= on the second input on purpose: raw
                 files are not image/* to every browser, and they are
                 exactly what belongs up there.

                 capture="environment" preselects the rear camera. It is a
                 request, not an order: whoever uses a camera app that
                 decides differently simply gets its choice. */ ?>
            <?php /* TWO SUB-TABS, FOTOS AND SEQUENZEN (02.10.2026:
                 „Bei zu vielen Fotos verschwinden sonst die Sequenzen aus
                 dem Blickfeld"). The sequences stood under the gallery,
                 and a find with forty pictures pushed them three screens
                 down. The bar shows only where BOTH can be there — on a
                 waypoint there are no sequences (applyDetailRights), and a
                 bar over a single block names it twice; then that block
                 stands alone. Like the main tabs, every point starts at the
                 first (detailVisible). The numbers say what waits behind
                 each, as on „Belege" itself. map.js, detailSubTab. */ ?>
            <div class="detail-tabs detail-subtabs" role="tablist" id="d-subtabs"
                 aria-label="<?= h(t('Fotos, Sequenzen oder Dateien')) ?>">
                <button type="button" role="tab" id="d-subtab-photos" aria-controls="d-subpanel-photos"
                        aria-selected="true"><?= h(t('Fotos')) ?> <span class="tab-count" id="d-subcount-photos" hidden>0</span></button>
                <button type="button" role="tab" id="d-subtab-sequences" aria-controls="d-subpanel-sequences"
                        aria-selected="false" tabindex="-1"><?= h(t('Sequenzen')) ?> <span class="tab-count" id="d-subcount-sequences" hidden>0</span></button>
                <button type="button" role="tab" id="d-subtab-files" aria-controls="d-subpanel-files"
                        aria-selected="false" tabindex="-1" hidden><?= h(t('Dateien')) ?> <span class="tab-count" id="d-subcount-files" hidden>0</span></button>
            </div>
            <?php /* The sub-panels WRAP the two fields instead of being them:
                 the fields' own `hidden` says „cannot be here at all" (no
                 photos for this viewer, no sequences on a waypoint), the
                 panels' says „the other sub-tab is chosen" — one attribute
                 for two questions would answer neither. */ ?>
            <div id="d-subpanel-photos" role="tabpanel" aria-labelledby="d-subtab-photos">
            <div class="field">
                <?php /* HERE „Fotos" USED TO STAND AS A LABEL
                     (10.09.2026: „oberhalb der Buttons steht das Label
                     Fotos — das kann auch weg, der Tab an sich heißt
                     Fotos"). A word that stands two centimetres above
                     itself sorts nothing in; since the window has tabs,
                     the tab carries the name — today „Belege". */ ?>
                <?php /* THE TWO BUTTONS STAND AT THE TOP (10.09.2026:
                     „die beiden Buttons Aufnehmen und Auswählen sind
                     immer ganz oben"). They stood under the gallery, and
                     that worked as long as it was short — since the
                     pictures stand in groups by Motivname, it can be
                     several screens long, and then one hunted for the
                     shutter behind the vouchers. What one DOES belongs
                     before what is already there. */ ?>
                <div class="photo-buttons" id="d-photo-buttons">
                    <?php /* Two buttons, two sources, two signs
                         (24.08.2026): the camera takes a NEW picture right
                         here, the cloud picks one that already lies in
                         the user's own storage. The icons say exactly
                         that — and „Auswählen" without its cloud would be
                         the one word in this window that does not reveal
                         where it leads. */ ?>
                    <button type="button" class="button subtle small" id="d-photo-capture"><?= icon('photo') ?><?= h(t('Aufnehmen')) ?></button>
                    <button type="button" class="button subtle small" id="d-photo-select"><?= icon('cloud') ?><?= h(t('Auswählen')) ?></button>
                    <?php /* THE THIRD SOURCE, the microscope camera on the desk
                         (24.10.2026): the live picture of the
                         camera the MicroCam app serves on this Mac, and
                         its stills whole into the cloud. Hidden on a
                         phone — the camera hangs on a computer's USB, and
                         a phone has no MicroCam to ask (microscope.js). */ ?>
                    <button type="button" class="button subtle small" id="d-photo-microscope" hidden><?= icon('microscope') ?><?= h(t('Mikroskopieren')) ?></button>
                    <span class="hint-text small" id="d-photo-hint"></span>
                </div>
                <input type="file" id="d-photo-camera" accept="image/*" capture="environment" hidden>
                <input type="file" id="d-photo-file" multiple hidden>
                <?php /* THE PLATE STANDS ABOVE THE HAUPTMOTIV
                     (06.10.2026: „im Punktefenster noch über dem Hauptmotiv
                     diese Kacheln anbieten") — what was made of the
                     pictures before the pictures themselves: its heading,
                     and under it the plate or the offer to make one
                     (plates.js, drawRow). */ ?>
                <div class="plate-row" id="d-plates" hidden></div>
                <?php /* THE GROUPS AS A COLUMN OF SYMBOLS beside the pictures
                     (13.11.2026: „die erste Ansicht, wo alles
                     untereinander ist, kannst du komplett streichen. Auch
                     auf dem Handy" — then „ja, dort auch" for this window):
                     the chosen group's pictures alone, a tap or the
                     pointer resting on a symbol switches (photogallery.js,
                     groupMenuDraw). The Fundbrowser moves #d-photos out of
                     here into its rail, which has its own menu — this one
                     then stays empty and hidden. */ ?>
                <div class="photo-groups">
                    <p class="list-label photo-group-now" id="d-photo-now" hidden></p>
                    <nav class="photo-group-menu" id="d-photo-menu" aria-label="<?= h(t('Motivnamen')) ?>" hidden></nav>
                    <div class="photo-gallery" id="d-photos"></div>
                </div>
            </div>
            </div>

            <?php /* SEQUENCES (22.09.2026): the second voucher, text
                 not picture — includes/sequences.php says why. Under
                 „Belege" with the pictures: the tab where one adds files
                 and becomes a co-owner. Not a main tab of its own — three
                 tabs are what the phone can carry, a fourth wraps; a
                 sub-tab since 02.10.2026, see above. No „Sequenzen" label
                 any more: the sub-tab carries the name, as the main tab
                 took „Fotos" off the gallery. */ ?>
            <div id="d-subpanel-sequences" role="tabpanel" aria-labelledby="d-subtab-sequences" hidden>
            <?php /* THE TYPE'S SEQUENCES FROM GENBANK (15.10.2026): what
                 the type material of the find's species was sequenced at —
                 holotype, epitype, neotype … —, shown even before a sequence of
                 one's own was attached, each to save as FASTA. Only where
                 GenBank has records flagged as type of exactly this name
                 (includes/type_sequences.php); otherwise the block stays
                 away. pointwindow.js, drawTypeSequences. */ ?>
            <div class="field type-sequences" id="d-type-sequences" hidden></div>
            <div class="field" id="d-sequences-field">
                <div class="sequence-list" id="d-sequences"></div>
                <?php /* THE BUTTON STAYS UNDER THE LIST, unlike the photos'
                     (02.10.2026: „man hat normalerweise nur eine
                     Sequenz pro Art, maximal zwei, wenn man unterschiedliche
                     Bereiche sequenziert"). The photos moved their buttons
                     to the top because forty pictures bury them; one or two
                     sequences never push this one out of sight. */ ?>
                <div class="button-row">
                    <button type="button" class="button subtle small" id="d-sequence-add"><?= h(t('Sequenz hinzufügen')) ?></button>
                </div>
            </div>
            </div>

            <?php /* THE FIND'S OTHER FILES (05.10.2026: „im Tab Belege
                 neben dem Untertab Fotos und Sequenzen noch einen Tab … Ziehen
                 und Droppen … Multi-Upload", and a file browser „mit Download
                 … oder da wo es geht, mit einer Vorschau", folders kept as
                 dropped). Notes, PDFs, a stack's raw files — in the person's
                 cloud, in a folder „Dateien" inside the find's, and they move
                 with the find. Only the uploader sees them. Drawn by
                 assets/js/pointfiles.js; offered on one's own finds with a
                 cloud connected and migration 0153 applied. */ ?>
            <div id="d-subpanel-files" role="tabpanel" aria-labelledby="d-subtab-files" hidden>
            <div class="field" id="d-files-field">
                <p class="file-intro hint-text"><?= h(t('Weitere Dateien zum Fund, zum Beispiel Notizen, PDFs oder RAW-Bilder. Sie liegen in deiner Cloud beim Fund und ziehen mit ihm um. Nur du siehst sie.')) ?></p>
                <div class="file-drop" id="d-files-drop">
                    <span class="file-drop-text"><?= h(t('Dateien oder Ordner hierher ziehen')) ?></span>
                    <div class="button-row">
                        <button type="button" class="button subtle small" id="d-files-pick"><?= icon('file') ?><?= h(t('Dateien hochladen')) ?></button>
                        <button type="button" class="button subtle small" id="d-files-pick-folder"><?= icon('folder') ?><?= h(t('Ordner hochladen')) ?></button>
                    </div>
                    <input type="file" id="d-files-input" multiple hidden>
                    <input type="file" id="d-files-folder" webkitdirectory multiple hidden>
                </div>
                <div class="file-progress" id="d-files-progress" hidden>
                    <span id="d-files-progress-text"></span>
                    <progress id="d-files-progress-bar" max="1" value="0"></progress>
                </div>
                <div class="file-tree" id="d-files"></div>
            </div>
            </div>

            </div>

            <div class="detail-panel" id="d-panel-place" role="tabpanel"
                 aria-labelledby="d-tab-place" hidden>
            <?php /* THE ORDER: RICHTUNG, FLÄCHE, LISTEN (02.10.2026:
                 „als erstes den Knopf Richtung zeigen, dann die Fläche und
                 dann als letztes die Listen"). What one does out in the
                 field comes first — walk towards the find —, then its shape
                 on the map, and the filing, which one sets once and rarely
                 touches again, last. */ ?>
            <?php /* „RICHTUNG ZEIGEN" (29.09.2026) — the „navigation in
                 quotation marks": an arrow at the screen's edge towards the
                 find, the distance beside it (assets/js/direction.js). Here,
                 in the tab about the find's place on the map, for every find
                 the window shows — a friend's too. The sentence says what it
                 does NOT do, so nobody waits for a route. */ ?>
            <div class="field" id="d-direction-field">
                <label><?= h(t('Richtung')) ?></label>
                <p class="hint-text small"><?= h(t('Ein Pfeil am Bildschirmrand zeigt dir, in welcher Richtung der Fund liegt und wie weit er entfernt ist. Einen Weg sucht die Karte nicht.')) ?></p>
                <div class="photo-buttons">
                    <button type="button" class="button subtle small" id="d-direction"><?= icon('arrow-right') ?><?= h(t('Richtung zeigen')) ?></button>
                </div>
            </div>

            <?php /* Areas.
                 SINCE 22.09.2026 IN THE „KARTE" TAB, with the list: the
                 list decides what the find looks like on the map (color
                 and shape come from it), the outline how far it extends —
                 both are its appearance on the map, and none of it is
                 text or a voucher („Text · Belege · Karte").
                 Coordinates and find time have stood under Text since.
                 The history below explains where it stood before.
                 BETWEEN COORDINATE AND FIND TIME, and that is where
                 the maintainer sent it twice on 10.09.2026: first „die
                 Fläche würd ich noch mit in den ersten Tab nehmen", an
                 hour later „mach mir die Umrandung doch in den Tab
                 Einordnung, und zwar unter Koordinaten und über Fundzeit".
                 The second instruction stands, and it agrees with the old
                 reason: the outline is the same datum writ large — for
                 an area the coordinate above it points at a spot INSIDE,
                 and one only understands that when both stand together.
                 Before the find time, so that the where stays together
                 and the when comes after it.

                 The whole block disappears while the server does not yet
                 have the column from migration 0034 — a button that can
                 only produce an error message is worse than none. */ ?>
            <div class="field" id="d-polygon-field">
                <label><?= h(t('Fläche')) ?></label>
                <p class="hint-text small" id="d-polygon"></p>
                <div class="photo-buttons">
                    <button type="button" class="button subtle small" id="d-outline-draw"><?= h(t('Umriss ziehen')) ?></button>
                    <button type="button" class="button subtle small" id="d-outline-remove" hidden><?= h(t('Umriss entfernen')) ?></button>
                </div>
            </div>

            <?php /* A FIND SHOWN TO ME (migration 0149): who showed it, and the
                 door that takes the hint away for me alone. Only on such a
                 find; the list block below is hidden on a foreign point. */ ?>
            <div class="field" id="d-hint-received" hidden>
                <label><?= h(t('Direkt geteilt')) ?></label>
                <p class="hint-text small" id="d-hint-received-text"></p>
                <div class="photo-buttons">
                    <button type="button" class="button subtle small danger" id="d-hint-leave"><?= icon('sign-out') ?><?= h(t('Entfernen')) ?></button>
                </div>
            </div>

            <?php /* „DIREKT TEILEN" — A FIELD OF ITS OWN ABOVE THE LISTS
                 (06.10.2026: „trennen und oberhalb der Listen anzeigen … dann
                 könnte man auch Punkte mit anderen teilen, die einem nicht
                 selbst gehören … außerdem passt das besser in eine einzelne
                 Kategorie"). It began as a tile UNDER the lists (02.10.2026)
                 and went with them wherever they went; now it stands on its
                 own, on my finds and on those I co-own (point_hint_may_show).
                 NOTHING TO FOLD since the same day („ich würde direkt alles
                 immer anzeigen … und direkt teilen dann als Überschrift
                 nehmen"): the label is the heading, every friend stands
                 under it with his box. A tick shows this one find to that
                 friend, saved at once — a hint is no field of the find.
                 map.js, drawHintTile. */ ?>
            <div class="field" id="d-hints-field" hidden>
                <label><?= h(t('Direkt teilen')) ?></label>
                <div class="list-selection is-tiled detail-hints" id="d-hints"></div>
            </div>

            <div class="field">
                <label><?= h(t('Listen')) ?></label>
                <div class="list-selection" id="d-lists"></div>
            </div>

            </div>


        </div>

        <?php /*
            Order following the habit from the operating system: the main
            function on the far right, where the thumb goes first.
            Schließen stands to its left, like Abbrechen next to a main
            function everywhere else in the application — the delete is
            pulled into the header (see there) and no longer needs its
            own gap here.
        */ ?>
        <div class="detail-footer">
            <button class="button subtle small" id="d-close"><?= h(t('Schließen')) ?></button>
            <button class="button show small" id="d-center"><?= icon('crosshair') ?><?= h(t('Zeigen')) ?></button>
            <button class="button small" id="d-save"><?= icon('save') ?><?= h(t('Speichern')) ?></button>
        </div>
        </div><?php /* /detail-body */ ?>
    </aside>
    <?php
}

/**
 * WHAT THE WINDOW READS OF THE START DATA — for the pages that are not the
 * map (the Fundbrowser), as the map's index.php builds the same keys: the
 * account's lists and Mappen, the lists others share with me with the
 * rights each recipient row carries, the fixed find fields, the number
 * series, the species packages' origins and shapes, the layers' kinds for
 * the protected areas' short names, who is looking.
 *
 * NOT THE MAP'S WHOLE DOCUMENT: no points (the page brings its own), no
 * buckets, no tracks, no offline files. The shared lists come without
 * their buckets and their chat — the window needs the lists and their
 * sharing rows, nothing else of a container.
 *
 * ONE PLACE FOR THE SHAPE would be index.php itself; it builds its array in
 * one piece with the map's other keys, and taking those apart is a change
 * of its own. So these lines repeat what index.php does for the same keys —
 * a key added there that the window reads has to be added here too.
 */
function point_window_start(PDO $pdo, array $me): array
{
    $userId = (int) $me['id'];

    $shared = [];
    if (container_ready($pdo)) {
        foreach (container_mine($pdo, $userId) as $c) {
            $shared[] = [
                'id'       => $c['id'],
                'name'     => $c['name'],
                'owner'    => $c['owner_name'],
                'owner_id' => (int) $c['owner_user_id'],
                'shares'   => container_shares($pdo, $c['id'], $userId),
            ];
        }
    }

    $friends = [];
    if (friends_ready($pdo)) {
        foreach (friends_overview($pdo, $userId)['friends'] as $f) {
            $friends[] = ['id' => (int) $f['user_id'], 'name' => $f['display_name']];
        }
    }

    $finder = user_full_name($me) !== '' ? user_full_name($me) : user_display_name($me);

    return [
        'user'    => ['id' => $userId, 'name' => user_display_name($me), 'isAdmin' => is_admin($me), 'finder' => $finder],
        // The names under Finder and Bestimmer (person_names.php, pointwindow.js peopleOffers).
        'people'  => person_names_written($pdo, $userId),
        'lists'   => lists_load($pdo, $userId),
        'folders' => folders_load($pdo, $userId),
        'shared'  => $shared,
        'friends' => $friends,
        'photos'  => photos_ready($pdo),
        // Whether the Belege may choose files for the cloud: a connected cloud that takes pictures,
        // and one the browser may upload into (index.php says why those are two questions).
        'cloudConnected' => cloud_ready($pdo) && cloud_pictures_ready($pdo, $userId),
        'cloudUpload'    => cloud_ready($pdo) && cloud_uploads_ready($pdo, $userId),
        // Whether „Entfernen" may offer its strong model: only where its files lie on this server (helpers.php).
        'strongModel'    => inpaint_strong_offered(),
        // Whether a find can carry an outline — the Fundbrowser draws it on its own map (findmap.js).
        'outlines' => column_exists($pdo, 'map_points', 'outline'),
        /* A tool that walks an identification key for the find, where a
           module offers one to this account: {url} — the address the key
           behind „Art", the ways' lines and the microscope window lead to
           (includes/modules.php, hook key_tool, lists the parameters the
           browser hangs on it). Null where there is none. */
        'keyTool' => module_first('key_tool', $me),
        /* Where the find's „Bestimmung" leads — for EVERY account: that
           tool where there is one, else the core's page with the
           Internet-Links and the finds of the species; {url, plain}
           (includes/modules.php, identify_tool). The button and the
           Fundbrowser's frame go by this one; keyTool above stays the
           question „is there a KEY" — a walk to go on with, a way to open. */
        'identifyTool' => identify_tool($me),
        /* „Direkt teilen": to whom my finds and the ones I co-own are shown
           — the tile's ticks (index.php builds the same two keys among the
           map's others). Objects even when empty: the tile looks a find up
           by its id as a key, and JSON would make [] of an empty map. null
           while the table is missing: then the tile does not appear. */
        'hints' => point_hints_ready($pdo) ? (static function () use ($pdo, $userId): array {
            $maps = point_hints_given_maps($pdo, $userId);
            return ['given' => (object) $maps['all'], 'givenOthers' => (object) $maps['others']];
        })() : null,
        'fieldTimesReady' => field_times_ready($pdo),
        'find_fields' => find_fields(),
        'findNumber' => find_number_ready($pdo) ? find_number_series($pdo, $userId) : null,
        'findNumberTrash' => find_number_ready($pdo) ? find_number_trash($pdo, $userId) : [],
        'groupShapes' => species_group_shapes(),
        'speciesOrigins' => species_origins(),
        // Species no list knows yet (working_names.php) — the title field's first offers.
        'workingNames' => (static function () use ($pdo, $userId): array {
            require_once __DIR__ . '/working_names.php';
            return working_names_for_start($pdo, $userId);
        })(),
        // The protected areas' kinds (protectedAreasOf reads them) — that layer only.
        'layers' => array_values(array_filter(map_layers_for_js(),
            static fn(array $e): bool => ($e['id'] ?? '') === 'protected_areas')),
    ];
}
