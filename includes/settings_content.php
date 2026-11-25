<?php
/**
 * Settings — processing and view (Mein Bereich → Einstellungen).
 *
 * Two sub-tabs:
 *
 *   KONTO       Name and e-mail address in one form, password in a second.
 *               The rules are the same as at registration (first name is
 *               required, at least 10 characters of password), so that two
 *               doors of the same house are not differently strict. Both
 *               forms demand the current password — the name form via a
 *               modal (see the script further down), not as an open field
 *               on the page, because even a pure name change now runs
 *               through the same form as the e-mail address. The logic
 *               lives in auth.php (konto_*) — only forms here.
 *
 *   OBERFLÄCHE  Settings for the device, today only the theme (dark/light).
 *               It lives in the browser's settings bundle
 *               (settings.js), not on the account — whoever wants
 *               light on the phone and dark on the desktop should be
 *               allowed to.
 */

declare(strict_types=1);

// The theme and accent tiles — shared with the guest's window on the map.
require_once __DIR__ . '/appearance.php';

/**
 * Must run BEFORE any output. 'section' tells the admin area's forms apart
 * — see invitations_content.php.
 *
 * @return array{message: ?string, error: string[]}
 */
function settings_process(PDO $pdo, int $userId): array
{
    // 'token' and 'device' stay null except right after a pairing — see
    // there for why the value cannot travel any other way.
    // 'mykis_typed' holds a rule name the DGfM list did not know, so its
    // field shows it again instead of swallowing what somebody typed.
    // 'mykis_welcome' is set when a save has just granted the DGfM consent:
    // the page then opens a window about the new tab (see the render).
    $state = ['message' => null, 'error' => [], 'token' => null, 'device' => null, 'mykis_typed' => [],
              'mykis_welcome' => null];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'settings') {
        return $state;
    }
    csrf_check();

    try {
        switch ((string) ($_POST['action'] ?? '')) {
            case 'person_email':
                $pending = account_person_email_update($pdo, $userId,
                    (string) ($_POST['first_name'] ?? ''),
                    (string) ($_POST['last_name'] ?? ''),
                    (string) ($_POST['email'] ?? ''),
                    (string) ($_POST['password'] ?? ''));
                $state['message'] = $pending === null
                    ? t('Gespeichert.')
                    : t('Gespeichert. Wir haben eine Mail an %s geschickt. Klicke auf den Link darin. Bis dahin meldest du dich weiter mit deiner alten Adresse an.', $pending);
                break;

            // Send the confirmation link again — for the account's own
            // address, or for the one waiting to take its place.
            case 'email_resend':
                require_once __DIR__ . '/email_confirmation.php';
                $sent = email_confirmation_send($pdo, $userId);
                $state['message'] = t('Wir haben eine Mail an %s geschickt. Klicke auf den Link darin.', $sent['to']);
                break;

            case 'password':
                account_password_update($pdo, $userId,
                    (string) ($_POST['current'] ?? ''),
                    (string) ($_POST['new'] ?? ''),
                    (string) ($_POST['new2'] ?? ''));
                /* THE SENTENCE NAMES BOTH KINDS SINCE THERE ARE TWO
                   (25.08.2026). „Abgemeldet" is true for a browser, which
                   simply asks again next time; a paired app is GONE and
                   has to be paired anew — with a fresh key, because the
                   old one cannot be shown a second time. Whoever reads
                   only „abgemeldet" wonders in the woods why nothing
                   uploads any more. */
                $state['message'] = t('Dein Passwort ist geändert. Hier bleibst du angemeldet. Auf deinen anderen Geräten musst du dich neu anmelden. Gekoppelte Geräte (unter „Geräte") musst du neu koppeln.');
                break;

            /* PAIRING A DEVICE mints a token and hands it out ONCE — only
               its hash is stored (token_create), so nobody, this page
               included, can show it a second time. That is why the fresh
               value travels in $state and not through a redirect: after a
               reload it would be irretrievably gone, and the user would
               have paired a device they can never reach. */
            case 'device_pair':
                $label = trim((string) ($_POST['device'] ?? ''));
                if ($label === '') {
                    throw new InvalidArgumentException(t('Gib dem Gerät einen Namen, damit du es später wiedererkennst.'));
                }
                $state['token']  = token_create($userId, 'App: ' . $label);
                $state['device'] = $label;
                // No toast: the window with the key opens at once, and a
                // toast would only lie behind it.
                break;

            case 'device_remove':
                // Read first: a browser is signed out, an app is unpaired,
                // and the toast says which.
                $which = $pdo->prepare('SELECT device FROM map_tokens WHERE id = ? AND user_id = ?');
                $which->execute([(int) ($_POST['id'] ?? 0), $userId]);
                $isBrowser = str_starts_with((string) $which->fetchColumn(), 'Browser: ');
                $stmt = $pdo->prepare('DELETE FROM map_tokens WHERE id = ? AND user_id = ?');
                $stmt->execute([(int) ($_POST['id'] ?? 0), $userId]);
                $state['message'] = $stmt->rowCount() === 0
                    ? t('Dieses Gerät gibt es nicht mehr.')
                    : ($isBrowser
                        ? t('Abgemeldet. Der Browser kommt nicht mehr an deine Funde.')
                        : t('Getrennt. Das Gerät kommt nicht mehr an deine Funde.'));
                break;

            /* A NAME OF ONE'S OWN (28.09.2026: which Android is
               the tablet, which the phone?). An app's name lives in its
               device column since pairing, so renaming rewrites „App:
               <name>" and needs no migration. A browser's device column is
               its user agent — the source of the automatic label — so its
               name goes into a column of its own (0145), and an empty field
               gives the automatic label back. An app always has a name. */
            case 'device_rename':
                $id   = (int) ($_POST['id'] ?? 0);
                $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 60);
                $which = $pdo->prepare('SELECT device FROM map_tokens WHERE id = ? AND user_id = ?');
                $which->execute([$id, $userId]);
                $device = $which->fetchColumn();
                if ($device === false) {
                    throw new InvalidArgumentException(t('Dieses Gerät gibt es nicht mehr.'));
                }
                if (str_starts_with((string) $device, 'Browser: ')) {
                    if (!column_exists($pdo, 'map_tokens', 'name')) {
                        throw new InvalidArgumentException(t('Das braucht die Migration 0145 — sie ist noch nicht eingespielt.'));
                    }
                    $pdo->prepare('UPDATE map_tokens SET name = ? WHERE id = ? AND user_id = ?')
                        ->execute([$name === '' ? null : $name, $id, $userId]);
                } else {
                    if ($name === '') {
                        throw new InvalidArgumentException(t('Gib dem Gerät einen Namen, damit du es später wiedererkennst.'));
                    }
                    $pdo->prepare('UPDATE map_tokens SET device = ? WHERE id = ? AND user_id = ?')
                        ->execute(['App: ' . $name, $id, $userId]);
                }
                $state['message'] = t('Gespeichert.');
                break;

            case 'find_number':
                require_once __DIR__ . '/find_number.php';
                // No next number any more: the pattern states it, written
                // out, or leaves the counter where it is.
                find_number_settings_save($pdo, $userId,
                    (string) ($_POST['pattern'] ?? ''), null,
                    ($_POST['restart'] ?? '') === '1');
                $state['message'] = t('Gespeichert. Das gilt für alle Funde, die du ab jetzt anlegst.');
                break;

            /* THE MEASURING TOOL'S HOUSEHOLD (28.08.2026:
               „Diese Einstellungen würde ich gerne auch in die Verwaltung
               bei Einstellungen legen"). A preset is minted HERE ONLY,
               from a calibration image (26.09.2026: „bei den
               Maßstab-Vorlagen bitte nur noch die Eichung mit einem
               Eichbild — von Hand entfernen wir", and the same evening
               the photo window's „Als Vorlage merken" went too — a macro
               photo's distance and lens change from shot to shot, so
               „die Vorlagen auch nur über die Verwaltung"). Presets
               made the other ways before stay, and the pencil can still
               rename them or measure them anew on a picture. And the
               picture must reach the cloud, or there is no preset at all
               ('scale_calibrate' below). */
            case 'measure_style':
                require_once __DIR__ . '/photos.php';
                if (!column_exists($pdo, 'map_users', 'measure_style')) {
                    throw new InvalidArgumentException(t('Das braucht die Migration 0091 — sie ist noch nicht eingespielt.'));
                }
                /* The per-layer margins are NOT this form's — their
                   editor is the pane's slider (api/v1/measure_style.php).
                   Carried through from the stored style, so saving a
                   colour cannot wipe them. */
                $stmt = $pdo->prepare('SELECT measure_style FROM map_users WHERE id = ?');
                $stmt->execute([$userId]);
                $stored = measure_style_read($stmt->fetchColumn() ?: null);
                $data = $_POST;
                $data['spore_air'] = $stored['spore_air'];
                // The same for the layers read straight (the pane's switch)
                // and the parts' directions in their tiles.
                $data['straight_length'] = $stored['straight_length'];
                $data['part_direction'] = $stored['part_direction'];
                /* THREE FORMS SINCE THE SUB-TABS (26.09.2026) — the look,
                   the names, the micro switch — and each sends only its
                   own fields. What a form does not carry comes from the
                   stored style: measure_style_write() refuses a missing
                   colour outright, so saving the names alone would
                   otherwise fail, not merely reset. */
                foreach (['scale_color', 'scale_seam_color', 'line_color', 'seam_color', 'font_px'] as $key) {
                    if (!array_key_exists($key, $_POST)) {
                        $data[$key] = $stored[$key];
                    }
                }
                /* The name suggestions arrive as a textarea, one word a
                   line; a form cached from before the field simply
                   carries the stored list through instead of wiping it. */
                foreach (['photo_titles_macro', 'photo_titles_micro'] as $key) {
                    $data[$key] = array_key_exists($key, $_POST)
                        ? preg_split('/\R/u', (string) $_POST[$key])
                        : $stored[$key];
                    /* Which stock names the form showed — its own hidden
                       field, or, for a list it did not send, what the
                       stored style handed out. A form from before the
                       field sends neither, and the baseline decides. */
                    $known = $key . '_known';
                    $data[$known] = array_key_exists($key, $_POST)
                        ? json_decode((string) ($_POST[$known] ?? 'null'), true)
                        : $stored[$known];
                }
                /* The switch comes as a tick box — and a tick box that is
                   not ticked does not come at all. Whether the form knew
                   it in the first place is said by the field beside it; an
                   old, cached form carries the value through. */
                $data['micro_shrink'] = array_key_exists('has_micro_shrink', $_POST)
                    ? !empty($_POST['micro_shrink'])
                    : $stored['micro_shrink'];
                // The same witness for the full-size tick (it lives in the Cloud tab since
                // 14.11.2026, cloud_process; an old cached form may still send it).
                $data['full_size_mobile'] = array_key_exists('has_full_size_mobile', $_POST)
                    ? !empty($_POST['full_size_mobile'])
                    : $stored['full_size_mobile'];
                // Set on the map, not here — carried through, or saving a
                // colour would bring the waved-off warning back.
                $data['micro_shrink_warned'] = $stored['micro_shrink_warned'];
                // The plates' hand is the plate editor's — carried through as well.
                $data['plate'] = $stored['plate'];
                // So are one's own things of a known length (the scale window's).
                $data['scale_objects'] = $stored['scale_objects'];
                $pdo->prepare('UPDATE map_users SET measure_style = ? WHERE id = ?')
                    ->execute([measure_style_write($data), $userId]);
                $state['message'] = t('Gespeichert. Auf der Karte gilt es, sobald du sie neu lädst.');
                break;

            /* MINTED FROM A CALIBRATION IMAGE (28.08.2026): the
               browser measured the photographed stage micrometer BEFORE
               anything was uploaded — the picture was still local, and
               upload_max_filesize (2 MB here) never gets a say. What
               arrives is only numbers: the drawn line's length in image
               pixels, the true length, and the image's dimensions — the
               yardstick the stamping warning compares against, because a
               preset is µm per pixel of ONE camera at ONE resolution.
               The picture itself reaches the „Kalibrierung" folder in the
               cloud BEFORE this form is sent, and its id comes along —
               since 26.09.2026 a preset needs it (below); until then the
               preset was the essential part and never waited on the
               archive. */
            case 'scale_calibrate':
                require_once __DIR__ . '/photos.php';
                if (!column_exists($pdo, 'map_photo_scales', 'width')) {
                    throw new InvalidArgumentException(t('Das braucht die Migration 0093 — sie ist noch nicht eingespielt.'));
                }
                /* One form, two lives (28.08.2026): without a
                   preset id it MINTS; with one it EDITS — rename alone,
                   or with a fresh measurement riding along re-calibrate.
                   The measurement block below therefore only runs when
                   there is one to validate. */
                $presetId = (int) ($_POST['preset'] ?? 0);
                $linePx = (float) str_replace(',', '.', (string) ($_POST['line_px'] ?? ''));
                $renameOnly = $presetId > 0 && $linePx < 1;

                $umPerPx = null;
                $width = $height = null;
                if (!$renameOnly) {
                    $value = str_replace(',', '.', trim((string) ($_POST['length'] ?? '')));
                    $unit  = (string) ($_POST['unit'] ?? '');
                    if (!is_numeric($value) || (float) $value <= 0
                        || !in_array($unit, ['1', '1000'], true)) {
                        throw new InvalidArgumentException(t('Die wahre Länge der Strecke fehlt, oder sie ist keine Zahl.'));
                    }
                    if (!is_finite($linePx) || $linePx < 1) {
                        throw new InvalidArgumentException(t('Klicke zuerst Anfang und Ende der Strecke im Bild an.'));
                    }
                    $width  = (int) ($_POST['width'] ?? 0);
                    $height = (int) ($_POST['height'] ?? 0);
                    if ($width <= 0 || $width > 100000 || $height <= 0 || $height > 100000) {
                        throw new InvalidArgumentException(t('Die Größe des Bildes fehlt. Wähle das Bild bitte noch einmal.'));
                    }
                    $umPerPx = (float) $value * (float) $unit / $linePx;
                }
                // The provider's id of a freshly archived image (0094) —
                // absent when nothing new went up. A string since 0138, and
                // the cloud it went to beside it (the target named it).
                $fileid = trim((string) ($_POST['fileid'] ?? ''));
                $fileid = $fileid === '' || $fileid === '0' || mb_strlen($fileid) > 512 ? null : $fileid;
                $provider = in_array((string) ($_POST['provider'] ?? ''), ['pcloud', 'gdrive', 'onedrive', 's3'], true)
                    ? (string) $_POST['provider'] : 'pcloud';

                /* NO PICTURE KEPT, NO PRESET (26.09.2026: „Ein
                   fehlgeschlagener Upload legt keine Vorlage an"). The
                   browser uploads first and submits only once the cloud
                   has the picture; a mint without its id did not get
                   there. Measuring anew on the STORED picture sends none
                   and keeps the one it has (photo_scale_update). */
                if ($presetId === 0 && $fileid === null) {
                    throw new InvalidArgumentException(t('Das Eichbild ist nicht in der Cloud angekommen. Die Vorlage ist nicht gespeichert.'));
                }
                // „deiner Cloud": since 26.09.2026 the picture may lie in any.
                $archiveWord = t('Das Eichbild liegt in deiner Cloud im Ordner „Kalibrierung".');
                if ($presetId > 0) {
                    photo_scale_update($pdo, $userId, $presetId,
                        (string) ($_POST['name'] ?? ''), $umPerPx, $width, $height, $fileid, $provider);
                    $state['message'] = $renameOnly
                        ? t('Vorlage umbenannt.')
                        // Re-measured on the STORED image: nothing was
                        // uploaded, so the archive sentence stays out.
                        : t('Vorlage neu geeicht.') . ($fileid !== null ? ' ' . $archiveWord : '');
                } else {
                    // Into the device whose „Neue Vorlage …" was pressed (0183).
                    $deviceId = (int) ($_POST['device'] ?? 0);
                    photo_scale_remember($pdo, $userId,
                        (string) ($_POST['name'] ?? ''), $umPerPx, $width, $height, $fileid, $provider,
                        $deviceId > 0 ? $deviceId : null);
                    $state['message'] = t('Vorlage gespeichert.') . ' ' . $archiveWord;
                }
                break;

            /* The account's language (migration 0096). Empty means
               automatic — the column goes back to NULL and the browser's
               Accept-Language decides again. */
            case 'language':
                if (!column_exists($pdo, 'map_users', 'lang')) {
                    throw new InvalidArgumentException(t('Das braucht die Migration 0096 — sie ist noch nicht eingespielt.'));
                }
                $chosen = (string) ($_POST['lang'] ?? '');
                if ($chosen !== '' && !isset(LANGUAGES[$chosen])) {
                    throw new InvalidArgumentException(t('Diese Sprache gibt es hier nicht.'));
                }
                $pdo->prepare('UPDATE map_users SET lang = ? WHERE id = ?')
                    ->execute([$chosen === '' ? null : $chosen, $userId]);
                /* This very answer already speaks the new language —
                   user_current() was cached before the row changed. */
                lang_force($chosen === '' ? 'de' : $chosen);
                $state['message'] = t('Gespeichert. Ab jetzt siehst du YASAIMAPP in dieser Sprache.');
                break;

            /* THE FINDER'S CONSENT TO THE DGfM DELIVERY (migration 0127) —
               only with a confirmed address, because the address goes to
               the coordinator along with the finds (25.09.2026:
               „man kann erst einwilligen, wenn die E-Mail-Adresse bestätigt
               ist"). The date of the first consent stays while it holds.
               The step went to „Genauigkeit" on 27.09.2026 and travels with
               that form (mykis_rules below). */
            case 'mykis_consent':
                require_once __DIR__ . '/mykis.php';
                if (!mykis_ready($pdo)) {
                    throw new InvalidArgumentException(t('Das braucht die Migration 0127 — sie ist noch nicht eingespielt.'));
                }
                $on = ($_POST['consent'] ?? '0') === '1';
                $was = $pdo->prepare('SELECT mykis_consent_at IS NOT NULL FROM map_users WHERE id = ?');
                $was->execute([$userId]);
                $was = (bool) $was->fetchColumn();
                if ($on) {
                    $confirmed = $pdo->prepare('SELECT email_confirmed_at IS NOT NULL FROM map_users WHERE id = ?');
                    $confirmed->execute([$userId]);
                    if (!(bool) $confirmed->fetchColumn()) {
                        throw new InvalidArgumentException(t('Das geht erst, wenn deine E-Mail-Adresse bestätigt ist. Den Link dafür bekommst du unter Konto.'));
                    }
                }
                $pdo->prepare('UPDATE map_users SET mykis_consent_at = '
                    . ($on ? 'COALESCE(mykis_consent_at, NOW())' : 'NULL') . ' WHERE id = ?')
                    ->execute([$userId]);
                // The copy (0131): the hidden 0 stands before the box, as
                // for the consent — an unticked box sends nothing at all.
                if (isset($_POST['copy']) && mykis_copy_ready($pdo)) {
                    $pdo->prepare('UPDATE map_users SET mykis_copy = ? WHERE id = ?')
                        ->execute([$_POST['copy'] === '1' ? 1 : 0, $userId]);
                }
                // Other finders' finds: the rest (0133) and, since 0144, the
                // ones this account determined.
                if (isset($_POST['foreign']) && mykis_foreign_ready($pdo)) {
                    $pdo->prepare('UPDATE map_users SET mykis_foreign = ? WHERE id = ?')
                        ->execute([$_POST['foreign'] === '1' ? 1 : 0, $userId]);
                }
                if (isset($_POST['determined']) && mykis_determined_ready($pdo)) {
                    $pdo->prepare('UPDATE map_users SET mykis_determined = ? WHERE id = ?')
                        ->execute([$_POST['determined'] === '1' ? 1 : 0, $userId]);
                }
                /* A CONSENT JUST GRANTED OPENS A WINDOW, NOT A TOAST
                   (27.09.2026). The save turns the tab into two
                   — „Meldung" and „Genauigkeit" — and the selection of what
                   goes out lives in the new one. „Du baust hier nach einem
                   Speichern komplett die Oberfläche um und denkst, Menschen
                   würden das erkennen. So funktioniert der menschliche
                   Geist nicht." A line in a toast or under the ticks was
                   read past; the window says what is new and takes one
                   there with a button. It carries the deed's confirmation
                   too — a toast would only lie behind it. Every new
                   consent, not only the first: whoever withdrew and agrees
                   again meets the same rebuilt tab. */
                if ($on && !$was) {
                    $state['mykis_welcome'] = t(mykis_next_delivery(new DateTimeImmutable()));
                } else {
                    // The toast speaks of the consent only where it changed
                    // — a new tick alone is no „ab jetzt".
                    $state['message'] = $on === $was ? t('Gespeichert.')
                        : t('Gespeichert. Deine Funde gehen nicht mehr an die Landeskoordinatoren.');
                }
                break;

            /* THE PRECISION RULES (0143, 27.09.2026: „einen
               kompletten Bereich Genauigkeit, wo wir filtern können"). ONE
               FORM WITHOUT A SCRIPT: the lists travel as hidden fields, and
               every cross and every „Hinzufügen" is a submit button of that
               same form — so each press saves everything else with it, and
               nothing ticked on the way is lost. A typed name must be in the
               DGfM's list and is kept in ITS spelling (mykis_rule_name);
               what the list does not know comes back into its field with a
               word why, and the rest is saved all the same.

               The step for „alle übrigen Funde" is the old mykis_precision
               (0129) and saves even before 0143 is applied — it stood in the
               consent form until today, and a deployment must not take it
               away while the migration waits. */
            case 'mykis_rules':
                require_once __DIR__ . '/mykis.php';
                if (!column_exists($pdo, 'map_users', 'mykis_precision')) {
                    throw new InvalidArgumentException(t('Das braucht die Migration 0129 — sie ist noch nicht eingespielt.'));
                }
                $precision = (string) ($_POST['precision'] ?? '');
                if (isset(MYKIS_PRECISIONS[$precision])) {
                    $pdo->prepare('UPDATE map_users SET mykis_precision = ? WHERE id = ?')
                        ->execute([$precision, $userId]);
                }
                if (mykis_rules_ready($pdo)) {
                    $default = mykis_precision($precision);
                    $stepOf = static fn(mixed $v): ?string => is_string($v) && isset(MYKIS_PRECISIONS[$v]) ? $v : null;
                    // What a cross took out — its value is the row's name.
                    $dropped = static fn(string $button): string =>
                        is_string($_POST[$button] ?? null) ? mb_strtolower(trim($_POST[$button])) : '';
                    // A plain list, and a list whose rows carry a step each
                    // (genera[3] with genus_step[3] — by index, so a name's
                    // spaces never have to live in a field's name).
                    $names = static function (string $field, string $button) use ($dropped): array {
                        $out = [];
                        foreach (array_slice((array) ($_POST[$field] ?? []), 0, 500) as $name) {
                            if (is_string($name) && trim($name) !== '' && mb_strtolower(trim($name)) !== $dropped($button)) {
                                $out[] = mb_substr(trim($name), 0, 120);
                            }
                        }
                        return $out;
                    };
                    $stepped = static function (string $field, string $steps, string $button) use ($dropped, $stepOf, $default): array {
                        $out = [];
                        foreach (array_slice((array) ($_POST[$field] ?? []), 0, 500, true) as $i => $name) {
                            if (is_string($name) && trim($name) !== '' && mb_strtolower(trim($name)) !== $dropped($button)) {
                                $out[mb_substr(trim($name), 0, 120)] = $stepOf($_POST[$steps][$i] ?? null) ?? $default;
                            }
                        }
                        return $out;
                    };
                    $rules = [
                        'exclude' => ['genera'  => $names('exclude_genera', 'drop_exclude_genus'),
                                      'species' => $names('exclude_species', 'drop_exclude_species')],
                        // One step per category since 28.09.2026 (rl_steps[<category>]);
                        // a page loaded before still sends the ticked categories
                        // with their one step, and mykis_rules_read turns that
                        // shape round instead of wiping the rule.
                        'redlist' => isset($_POST['rl_steps'])
                            ? array_filter((array) $_POST['rl_steps'], 'is_string')
                            : ['categories' => array_filter((array) ($_POST['rl'] ?? []), 'is_string'),
                               'step'       => $stepOf($_POST['rl_step'] ?? null)],
                        'edible'  => $stepOf($_POST['edible_step'] ?? null),
                        'genera'  => $stepped('genera', 'genus_step', 'drop_genus'),
                        'species' => $stepped('species', 'species_step', 'drop_species'),
                    ];
                    // The four fields for a new name. Every press takes all
                    // of them: whoever typed a genus and then pressed
                    // „Speichern" meant it too. A new row starts at the step
                    // for all other finds — it changes nothing until chosen.
                    foreach (['add_exclude_genus' => ['exclude', 'genera', false],
                              'add_exclude_species' => ['exclude', 'species', true],
                              'add_genus' => ['genera', null, false],
                              'add_species' => ['species', null, true]] as $field => [$group, $list, $species]) {
                        $typed = is_string($_POST[$field] ?? null) ? trim((string) preg_replace('/\s+/u', ' ', $_POST[$field])) : '';
                        if ($typed === '') {
                            continue;
                        }
                        $name = mykis_rule_name($pdo, $typed, $species);
                        if ($name === null) {
                            $state['mykis_typed'][$field] = $typed;
                            $state['error'][] = $species
                                ? t('„%s" steht nicht als Art in der Artenliste der DGfM. Eine Art schreibst du mit zwei Wörtern, zum Beispiel „Boletus edulis".', $typed)
                                : t('„%s" steht nicht als Gattung in der Artenliste der DGfM. Eine Gattung ist ein Wort, zum Beispiel „Boletus".', $typed);
                        } elseif ($list !== null) {
                            $rules[$group][$list][] = $name;
                        } else {
                            $rules[$group][$name] ??= $default;
                        }
                    }
                    $rules = mykis_rules_read($rules);
                    $pdo->prepare('UPDATE map_users SET mykis_rules = ? WHERE id = ?')
                        ->execute([$rules === mykis_rules_read(null) ? null : json_encode($rules, JSON_UNESCAPED_UNICODE), $userId]);
                }
                $state['message'] = t('Gespeichert.');
                break;

            /* A DEVICE, made or renamed (migration 0183) — „Neues Gerät …"
               and the pencil in its head send the name a prompt asked
               for (calibrate.js). */
            case 'scale_device_save':
                require_once __DIR__ . '/photos.php';
                $deviceId = (int) ($_POST['id'] ?? 0);
                scale_device_save($pdo, $userId, $deviceId, (string) ($_POST['name'] ?? ''));
                $state['message'] = $deviceId > 0 ? t('Gerät umbenannt.') : t('Gerät angelegt.');
                break;

            case 'scale_device_remove':
                require_once __DIR__ . '/photos.php';
                $state['message'] = scale_device_forget($pdo, $userId, (int) ($_POST['id'] ?? 0))
                    ? t('Gerät gelöscht. Fotos, die eine seiner Vorlagen schon bekommen haben, behalten ihren Maßstab.')
                    : t('Dieses Gerät gibt es nicht mehr.');
                break;

            case 'scale_preset_remove':
                require_once __DIR__ . '/photos.php';
                $state['message'] = photo_scale_forget($pdo, $userId, (int) ($_POST['id'] ?? 0))
                    ? t('Vorlage gelöscht. Fotos, die sie schon bekommen haben, behalten ihren Maßstab.')
                    : t('Diese Vorlage gibt es nicht mehr.');
                break;

            // Einstellungen → Arten → Arbeitsnamen (working_names_content.php).
            case 'working_name_save':
            case 'working_name_delete':
            case 'working_name_publish':
                require_once __DIR__ . '/working_names_content.php';
                $state['message'] = working_names_process($pdo, $userId, (string) $_POST['action']);
                break;
        }
    } catch (InvalidArgumentException $e) {
        $state['error'][] = error_for_user($e);
    }

    return $state;
}

/**
 * A device name one can read.
 *
 * TWO KINDS LIVE IN map_tokens. An app names itself when pairing („App:
 * Pixel im Rucksack") and is fine as it stands. A browser gets its label
 * from the „stay logged in" checkbox, and that writes the whole user
 * agent — „Browser: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)
 * AppleWebKit/537.36 (KHTML, like Gecko) Chrome/…". Seventy characters in
 * which the two that matter are buried.
 *
 * The list has to answer ONE question: which of these do I recognize, and
 * which do I throw out? For that, browser and system suffice. Everything
 * else is version bookkeeping that says nothing about the device in one's
 * hand.
 *
 * READ, NOT REWRITTEN. The stored value stays as it is — it is evidence,
 * and shortening it in the database would lose what a support question
 * might need. Only the eye gets the short form. (Since 28.09.2026 the
 * login drops the three phrases every browser sends before storing —
 * browser_agent_short() in auth.php — so the column's 100 characters
 * reach „Mobile"; nothing a question could need goes with them.)
 *
 * PHONE OR TABLET ON ANDROID: Chrome, Samsung Internet and Edge write
 * „Mobile" on a phone and leave it out on a tablet; Firefox writes
 * „Mobile" or „Tablet". The MISSING word means „tablet" only in a row the
 * login shortened — recognisable because it no longer starts with
 * „Mozilla/" — and only below the 100 characters. A row from before was
 * cut at 100 for phone and tablet alike, in front of the word, and stays
 * plain „Android". Not the length alone: measured on the seeded rows, the
 * cut fell on a space, a trim made it 99, and the old phone read as a
 * tablet.
 */
function device_label(string $raw): string
{
    if (!str_starts_with($raw, 'Browser: ')) {
        return $raw;                       // an app named itself, and did it well
    }
    $agent = substr($raw, 9);

    /* ORDER MATTERS, and that is the whole trick of user agents: Edge also
       says „Chrome", Chrome also says „Safari", and almost everything says
       „Mozilla". Whoever asks in the wrong order gets the wrong answer —
       so the most specific goes first. */
    $browser = t('Browser');
    foreach (['Edg/' => 'Edge', 'EdgA/' => 'Edge', 'OPR/' => 'Opera', 'SamsungBrowser/' => 'Samsung Internet',
              'Firefox/' => 'Firefox', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'] as $needle => $name) {
        if (str_contains($agent, $needle)) { $browser = $name; break; }
    }

    $system = '';
    foreach (['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad',
              'Macintosh' => 'macOS', 'Windows' => 'Windows', 'Linux' => 'Linux'] as $needle => $name) {
        if (str_contains($agent, $needle)) { $system = $name; break; }
    }
    if ($system === 'Android') {
        if (str_contains($agent, 'Mobile')) {
            $system = t('Android-Handy');
        } elseif (str_contains($agent, 'Tablet')
                  || (!str_starts_with($agent, 'Mozilla/') && mb_strlen($raw) < 100)) {
            $system = t('Android-Tablet');
        }
    }

    return $system !== '' ? t('%s auf %s', $browser, $system) : $browser;
}

/** The view. $state comes from settings_process(). */
function settings_render(PDO $pdo, int $userId, array $state): void
{
    // All account forms return to the account sub-tab — the anchor opens
    // the whole tab chain (tabs.js picks, per bar, the tab that contains
    // the target).
    $target = url('pages/administration.php') . '#account';

    notice('error', $state['error'], true);
    if ($state['message'] !== null) {
        notice('success', $state['message'], true);
    }

    // Read fresh, not from the session: after saving, the new state should
    // stand here — the session was loaded before the processing.
    $stmt = $pdo->prepare('SELECT email, first_name, last_name FROM map_users WHERE id = ?');
    $stmt->execute([$userId]);
    $ich = $stmt->fetch();

    // The DGfM tab only where its tables are (migration 0127).
    require_once __DIR__ . '/mykis.php';
    $mkReady = mykis_ready($pdo);
    ?>

<div class="tab" role="tablist" aria-label="<?= h(t('Einstellungen')) ?>">
    <button type="button" role="tab" id="tab-account" aria-controls="section-account"
            data-anchor="account"><?= icon('key') ?><?= h(t('Konto')) ?></button>
    <button type="button" role="tab" id="tab-appearance" aria-controls="section-appearance"
            data-anchor="appearance"><?= icon('contrast') ?><?= h(t('Oberfläche')) ?></button>
    <?php /* 'species-groups' AND NOT 'species': that name is taken. The
         administration's backfill bar has an "Arten" tab of its own
         (pages/administration.php), and both live in the SAME document —
         the settings are included into it. With two section-species ids,
         getElementById always found the first one (this one), so the
         backfill tab unhid a panel inside a hidden ancestor and its own
         never — it looked simply dead (23.08.2026). Same
         label for the user is fine; same id and anchor is not. */ ?>
    <button type="button" role="tab" id="tab-species-groups" aria-controls="section-species-groups"
            data-anchor="species-groups"><?= icon('label') ?><?= h(t('Arten')) ?></button>
    <?php /* A tab of its own, not a fold under Konto (23.08.2026):
         the number belongs to the COLLECTION, not to the login — and next
         to name and password it looked like account plumbing. */ ?>
    <button type="button" role="tab" id="tab-find-number" aria-controls="section-find-number"
            data-anchor="find-number"><?= icon('badge') ?><?= h(t('Fundnummer')) ?></button>
    <?php /* The measuring tool's household (28.08.2026): the presets minted in
         the photo window are MANAGED here, and the drawing style lives
         here from the start — per account, like the presets: the phone in
         the woods shall draw like the desk. „FOTOS" SINCE 26.09.2026
         („Der kann aber mittlerweile schon mehr … weil man ja
         auch die Motivnamen auswählt, Mikrobilder automatisch verkleinern
         lassen kann"); measuring became one of its sub-tabs. The anchor
         keeps its old name, so bookmarks and the bars' memory hold. */ ?>
    <button type="button" role="tab" id="tab-measure" aria-controls="section-measure"
            data-anchor="measure"><?= icon('photo') ?><?= h(t('Fotos')) ?></button>
    <?php if ($mkReady): ?>
    <?php /* The finder's side of the DGfM delivery (25.09.2026):
         about the FINDS, like Arten and Fundnummer, not about the login.
         „DGfM-Kartierung" and never „Kartierung" alone — in an app that
         maps, the bare word means everything („sonst kommt man
         durcheinander"). 'dgfm' and not 'mapping': that anchor is the
         administrator's tab in the same document — the trap of
         'species-groups' above. */ ?>
    <button type="button" role="tab" id="tab-dgfm" aria-controls="section-dgfm"
            data-anchor="dgfm"><?= icon('map') ?><?= h(t('DGfM-Kartierung')) ?></button>
    <?php endif; ?>
    <?php /* Under Einstellungen and not under Offline (25.08.2026): Offline is
         about what lies ON this device, this is about which devices may
         reach the ACCOUNT. That is the same question as password and
         e-mail, one tab to the left. */ ?>
    <button type="button" role="tab" id="tab-devices" aria-controls="section-devices"
            data-anchor="devices"><?= icon('device') ?><?= h(t('Geräte')) ?></button>
    <?php /* ONE'S FINDS ON ONE'S OWN HOMEPAGE (publication_content.php,
         25.11.2026). Last in the bar: it is set up once and then left
         alone. Only where it is offered — see publication_offered. */
    require_once __DIR__ . '/publication_content.php';
    $fundbuch = publication_offered($pdo, user_current());
    if ($fundbuch): ?>
    <button type="button" role="tab" id="tab-fundbuch" aria-controls="section-fundbuch"
            data-anchor="fundbuch"><?= icon('globe') ?><?= h(t('Fundbuch')) ?></button>
    <?php endif; ?>
</div>

<section id="section-account" role="tabpanel" aria-labelledby="tab-account" hidden>
    <?php /* THE GROUPS IN SIGHT, NO FOLDS (25.09.2026: „Entfernen
         wir bei Konto auch die Akkordeon-Klappmenüs … aber unterteile das
         schön optisch in drei Gruppen"). Until then the blocks lay folded
         and closed (22.08.2026: one comes to look, not to change one's
         name) — but a closed fold hides what it holds just as a dropdown
         hides its options. The third group, the DGfM delivery, went to a
         tab of its own the same evening (Kartierung). Each group wears the
         head every group of this project wears (.list-label, app.css). */ ?>
    <div class="settings-group">
        <h3 class="list-label"><?= h(t('Name und E-Mail-Adresse')) ?></h3>
    <?php /* One form, one save button — name and e-mail address used to stand
         apart, with separate confirmation: only the address demanded the
         password, the name did not. Merged, a modal now ALWAYS asks for the
         password before submitting (see script below), instead of a field
         standing openly on the page — the password is never in the DOM
         longer than for the one moment of submission. */ ?>
    <?php
    /* THE STATE OF THE ADDRESS (migration 0116): confirmed with its date,
       waiting for its link, or a new one pending — said right under the
       address, with the button beside it in the same row
       (23.09.2026: „Bestätigungslink-Knopf direkt hinter die
       E-Mail-Adresse, in die gleiche Zeile, nicht ganz weit unten").
       ONCE CONFIRMED THE BUTTON GOES (25.09.2026: „wenn die
       E-Mail-Adresse bestätigt ist, braucht dieser Button nicht mehr da
       sein … durch den Text bestätigt am ersetzen, mit einem grünen
       Haken"): the state takes its place in the row, and the line under
       the field falls silent, or it would say the same twice. A new
       address waiting for its link brings the button back. */
    require_once __DIR__ . '/email_confirmation.php';
    require_once __DIR__ . '/password_reset.php';
    $mailState = email_confirmation_state($pdo, $userId);
    $mailDone  = $mailState['ready'] && $mailState['confirmed'] && $mailState['pending'] === null;
    ?>
    <form method="post" action="<?= h($target) ?>" id="settings-person-form">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="settings">
        <input type="hidden" name="action" value="person_email">
        <input type="hidden" name="password" id="settings-person-password">
        <div class="field">
            <label for="settings-first-name"><?= h(t('Vorname')) ?></label>
            <input type="text" id="settings-first-name" name="first_name" required maxlength="60"
                   autocomplete="given-name" value="<?= h($ich['first_name']) ?>">
        </div>
        <div class="field">
            <label for="settings-last-name"><?= h(t('Nachname')) ?></label>
            <input type="text" id="settings-last-name" name="last_name" maxlength="80"
                   autocomplete="family-name" value="<?= h($ich['last_name']) ?>">
            <?php /* EACH SENTENCE WHERE IT MATTERS (25.09.2026: „nicht
                 so einen großen Satz irgendwo zentral zeigen, sondern
                 aufteilen und an die Stellen packen, wo es wichtig ist"):
                 what the name is for under the name, what a new address
                 costs under the address, and that one signs in with it in
                 its label. */ ?>
            <p class="hint-text small"><?= h(t('Vor- und Nachname stehen bei deinen Funden als Finder.')) ?></p>
        </div>
        <div class="field">
            <label for="settings-email"><?= h(t('E-Mail-Adresse (damit meldest du dich an)')) ?></label>
            <div class="mail-row">
                <input type="email" id="settings-email" name="email" required
                       autocomplete="email" value="<?= h($ich['email']) ?>">
                <?php if ($mailDone): ?>
                    <span class="mail-confirmed"><?= icon('check') ?><?= h(t('Bestätigt am %s',
                        date('d.m.Y', strtotime((string) $mailState['confirmed_at'])))) ?></span>
                <?php elseif ($mailState['ready']): ?>
                    <button class="button subtle" type="submit" form="settings-mail-send"><?= h($mailState['pending'] === null
                        ? t('Link zum Bestätigen schicken')
                        : t('Link noch einmal schicken')) ?></button>
                <?php endif; ?>
            </div>
            <?php if ($mailState['ready'] && !$mailDone): ?>
                <p class="hint-text small mail-state is-open">
                    <?= h($mailState['pending'] !== null
                        ? t('Die neue Adresse %s ist noch nicht bestätigt. Dorthin ist eine Mail mit einem Link unterwegs. Bitte klicke auf den Link.', $mailState['pending'])
                        /* WHAT CONFIRMING IS FOR, said where one decides
                           (23.09.2026): it stays voluntary, and
                           whoever skips it should know what they give up.
                           Since the DGfM delivery it gives up two things —
                           the report needs a reachable address too
                           (25.09.2026: „das sollte hier eben
                           auch stehen"). The guest finds are left out —
                           they concern the few who mapped as guests. */
                        : (password_reset_ready($pdo) && $mkReady
                            ? t('Deine Adresse ist noch nicht bestätigt. Das ist freiwillig. Aber nur mit bestätigter Adresse kannst du ein vergessenes Passwort selbst neu setzen und deine Funde an die DGfM-Kartierung melden. So geht es: Klicke auf „Link zum Bestätigen schicken". Dann klicke auf den Link in der Mail.')
                            : (password_reset_ready($pdo)
                                ? t('Deine Adresse ist noch nicht bestätigt. Das ist freiwillig. Aber nur mit bestätigter Adresse kannst du ein vergessenes Passwort selbst neu setzen. So geht es: Klicke auf „Link zum Bestätigen schicken". Dann klicke auf den Link in der Mail.')
                                : ($mkReady
                                    ? t('Deine Adresse ist noch nicht bestätigt. Das ist freiwillig. Aber nur mit bestätigter Adresse kannst du deine Funde an die DGfM-Kartierung melden. So geht es: Klicke auf „Link zum Bestätigen schicken". Dann klicke auf den Link in der Mail.')
                                    : t('Deine Adresse ist noch nicht bestätigt. So geht es: Klicke auf „Link zum Bestätigen schicken". Dann klicke auf den Link in der Mail.'))))) ?>
                </p>
            <?php endif; ?>
            <?php if (($mailState['pending'] ?? null) === null): ?>
                <?php /* Not while a new address waits: the line above says it
                     already, with the address. */ ?>
                <p class="hint-text small"><?= h(t('Änderst du die Adresse, bekommt die neue Adresse eine Mail mit einem Link. Erst wenn du auf den Link klickst, gilt die neue Adresse.')) ?></p>
            <?php endif; ?>
        </div>
        <div class="button-row right">
            <button class="button subtle" type="submit" id="settings-person-save" data-quiet-until-changed><?= icon('save') ?><?= h(t('Speichern')) ?></button>
        </div>
    </form>
    <?php if ($mailState['ready'] && !$mailDone): ?>
        <?php /* The resend form stands apart — HTML knows no nested forms —, its
             button lives in the address row above and reaches it by form=. */ ?>
        <form method="post" action="<?= h($target) ?>" id="settings-mail-send" hidden>
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="settings">
            <input type="hidden" name="action" value="email_resend">
        </form>
    <?php endif; ?>
    </div>

    <div class="settings-group">
        <h3 class="list-label"><?= h(t('Passwort')) ?></h3>
    <form method="post" action="<?= h($target) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="settings">
        <input type="hidden" name="action" value="password">
        <div class="field">
            <label for="settings-current"><?= h(t('Dein jetziges Passwort')) ?></label>
            <?php /* Not a change of its own (quietsave.js): a password manager
                 may fill it while nobody means to change anything. */ ?>
            <input type="password" id="settings-current" name="current" required
                   autocomplete="current-password" data-not-a-change>
        </div>
        <div class="field">
            <label for="settings-new"><?= h(t('Neues Passwort (mindestens 10 Zeichen)')) ?></label>
            <input type="password" id="settings-new" name="new" required minlength="10"
                   autocomplete="new-password">
        </div>
        <div class="field">
            <label for="settings-new2"><?= h(t('Neues Passwort noch einmal')) ?></label>
            <input type="password" id="settings-new2" name="new2" required minlength="10"
                   autocomplete="new-password">
        </div>
        <div class="button-row right">
            <button class="button subtle" type="submit" data-quiet-until-changed><?= h(t('Passwort ändern')) ?></button>
        </div>
    </form>
    </div>
    <?php /* The privacy statement, as a footnote of Konto: it is where one
         looks for what the account keeps (pages/privacy.php, 20.11.2026).
         Meta, so smaller and fainter than the page (CLAUDE.md). */ ?>
    <p class="hint-text small provider-currency"><a href="<?= h(url('pages/privacy.php')) ?>"><?= h(t('Datenschutzerklärung')) ?></a></p>
</section>

<section id="section-find-number" role="tabpanel" aria-labelledby="tab-find-number" hidden>
    <?php
    require_once __DIR__ . '/find_number.php';
    // The form returns to ITS tab, not to Konto — $target points there.
    $findTarget = url('pages/administration.php') . '#find-number';
    if (!find_number_ready($pdo)) {
        // A state, not a deed — it stays in the flow until somebody acts.
        notice('hint', t('Die Fundnummern brauchen die Migration 0070 — sie ist noch nicht eingespielt.'));
    } else {
        $findPattern = find_number_pattern($pdo, $userId) ?? '';
        ?>

    <?php /* TWO GROUPS, BOTH OPEN (25.09.2026, in the plain-words
         pass: „es ist nicht genau erkennbar, was ist der Bereich, wie geht
         das, und was ist der Bereich, hier stelle ich ein"). What one comes
         for stands on top — the pattern, the counter, the answer line —
         and the instructions below it under a head of their own, with the
         restart shown in examples, which were missing. The fold „Wie geht
         das?" went: a fold hides what it holds (CLAUDE.md, „What there is
         to choose stands in sight"). EVERY EXAMPLE IS COMPUTED by the SAME
         renderer that mints the numbers; hand-written outputs would drift
         the day the arithmetic changes. */ ?>
    <div class="settings-group">
        <h3 class="list-label"><?= h(t('Deine Fundnummer')) ?></h3>
        <p class="hint-text"><?= h(t('Jeder neue Fund in einer Artenliste bekommt eine Fundnummer. Wie sie aussieht, bestimmst du mit einem Muster. Wie du ein Muster baust, steht weiter unten.')) ?></p>
        <p class="hint-text"><?= str_replace('%s', '<b>' . h(find_number_fallback(123)) . '</b>',
            h(t('Lässt du das Muster leer, bekommt der Fund eine Nummer von YASAIMAPP, zum Beispiel %s.'))) ?></p>
        <?php /* What happens to numbers given already, said simply
             (26.09.2026) — the details, free or taken, next free, a jump
             of the counter, stand where they happen: under the point
             window's number field (map.js, drawNumberHint). */ ?>
        <p class="hint-text"><?= h(t('Das Muster gilt nur für neue Funde. Funde, die schon eine Nummer haben, behalten sie. Eine Nummer von Hand trägst du im Fundfenster ein. Dort steht auch, ob sie frei ist.')) ?></p>
        <?php /* The two ways to a number that is NOT minted at birth
             (26.09.2026, B with C): a point laid into a species list later
             stays without one („Dann lass offen" — a find put into the
             wrong list by mistake must not use up a number), and the old
             finds after a change of pattern go through the numbering
             window. „Jeder neue Fund" above invites expecting both to
             happen by themselves; without these lines whoever switches
             the pattern learns only that the old numbers stay. */ ?>
        <p class="hint-text"><?= h(t('Legst du einen Punkt erst später in eine Artenliste, bekommt er keine Nummer nach deinem Muster. Im Fundfenster übernimmst du die nächste freie Nummer mit einem Klick.')) ?></p>
        <p class="hint-text"><?= h(t('Alte Funde nummerierst du unter Organisieren um. Markiere dort die Funde und klicke auf „Fundnummern …". Das Fenster zeigt dir jede neue Nummer, bevor etwas gespeichert wird.')) ?></p>

        <form method="post" action="<?= h($findTarget) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="settings">
            <input type="hidden" name="action" value="find_number">
            <div class="field">
                <label for="settings-find-pattern"><?= h(t('Muster')) ?></label>
                <input type="text" id="settings-find-pattern" name="pattern" maxlength="60"
                       autocomplete="off" spellcheck="false"
                       placeholder="<?= h(t('z. B. CW-JJJJ-NNNN')) ?>" value="<?= h($findPattern) ?>">
            </div>
            <?php /* NO FIELD FOR THE NEXT NUMBER (25.09.2026: „das
                 Feld ist einfach doppelt gemoppelt, kann weg"). One's last
                 number is written into the pattern — „wk-JJJJ-00000543" —
                 and the preview below says what comes next. */ ?>
            <?php
            /* THE RESTART CHOICE, only offered where it can mean something: a
               pattern with a date part is unique per its finest unit — day,
               month or year (find_number_period) —, so the counter may begin
               at 1 there anew. The row hides without a date part (and the
               browser twin shows/hides it live while typing); the unit word
               in the label follows the pattern the same way. */
            $findPeriod  = find_number_period($findPattern === '' ? null : $findPattern, null);
            $findRestart = find_number_restart($pdo, $userId);
            ?>
            <label class="checkbox" id="find-number-restart-row" style="margin-top:.8rem"
                   <?= $findPeriod === null ? 'hidden' : '' ?>>
                <input type="checkbox" name="restart" value="1" id="find-number-restart"
                       <?= $findRestart ? 'checked' : '' ?>>
                <?php /* The unit word rides as %s so a language may move it —
                         escaped first, the <b> spliced in afterwards. The unit
                         values themselves ('Tag'/'Monat'/'Jahr') stay German in
                         find_number_period: 'key' is STORED, and the JS twin
                         (findnumber.js) speaks the same vocabulary — only the
                         eye gets the translation, here. */ ?>
                <span><?= str_replace('%s',
                    '<b id="find-number-restart-unit">' . h(t($findPeriod['unit'] ?? 'Jahr')) . '</b>',
                    h(t('Mit jedem neuen %s wieder bei 1 anfangen. Weil das Datum im Muster steht, gibt es trotzdem keine Nummer doppelt.'))) ?></span>
            </label>
            <?php /* The preview line: the server renders the saved state, the
                 browser twin (assets/js/findnumber.js, wired in
                 administration.js) keeps it live while somebody types. Both
                 compute with the same rules — tests hold them together.
                 NOT hint-text: this line is the ANSWER of the tab, not a
                 footnote (23.08.2026). */ ?>
            <?php /* WITHOUT A PATTERN the words stay text and only „Y-123" is
                 the big number: the whole sentence in the preview's large
                 bold type read like a code. The sentence keeps its %s, so
                 a language may move the number; the script swaps the two
                 spans while somebody types. */ ?>
            <?php /* The counter as it stands rides along for the browser twin:
                 the next number, and whether a restart is on in which
                 section — so the line promises 1 on the first morning of a
                 new section exactly where the mint will begin at 1
                 (find_number_upcoming). */ ?>
            <?php
            /* And the highest number already given in the saved pattern's
               shape — once for the whole series, once for today's section
               — since the mint goes on above it (find_number_highest). The
               twin uses them while the typed pattern keeps the saved
               shape; a new shape has no numbers yet. */
            $findSection = $findPattern !== '' ? find_number_period($findPattern, null) : null;
            $findHighestAll = $findPattern !== '' ? find_number_highest($pdo, $userId, $findPattern, null, null) : 0;
            $findHighestSection = $findSection !== null
                ? find_number_highest($pdo, $userId, $findPattern, $findSection, null) : $findHighestAll;
            ?>
            <p class="find-number-preview-row"
               data-counter="<?= (int) find_number_next($pdo, $userId) ?>"
               data-restart="<?= $findRestart ? '1' : '0' ?>"
               data-period="<?= h((string) find_number_counter_period($pdo, $userId)) ?>"
               data-highest-all="<?= (int) $findHighestAll ?>"
               data-highest-section="<?= (int) $findHighestSection ?>">
                <?= h(t('Dein nächster Fund bekommt diese Nummer:')) ?>
                <code id="find-number-preview"<?= $findPattern === '' ? ' hidden' : '' ?>><?= h($findPattern !== ''
                    ? find_number_render($findPattern, null, find_number_upcoming($pdo, $userId, $findPattern)) : '') ?></code>
                <span id="find-number-preview-fallback"<?= $findPattern !== '' ? ' hidden' : '' ?>><?= str_replace('%s',
                    '<code>' . h(find_number_fallback(123)) . '</code>',
                    h(t('eine Nummer von YASAIMAPP, zum Beispiel %s'))) ?></span>
                <span id="find-number-preview-note" class="hint-text small"></span>
            </p>
            <div class="button-row right">
                <button class="button subtle" type="submit" data-quiet-until-changed><?= icon('save') ?><?= h(t('Speichern')) ?></button>
            </div>
        </form>
    </div>

    <?php
    /* The restart in examples: today's number and the first one after the
       section turns — next year, tomorrow — minted by the renderer with
       those days as find days. */
    $findToday    = new DateTimeImmutable('today 12:00');
    $findNextYear = $findToday->modify('first day of january next year');
    $findTomorrow = $findToday->modify('+1 day');
    ?>
    <div class="settings-group">
        <h3 class="list-label"><?= h(t('So baust du das Muster')) ?></h3>
        <?php /* no-stack: at phone width table.list stacks its cells — for
             point tables rightly, but these three live off the side-by-side
             reading, and both columns are short enough for any phone. */ ?>
        <div class="table-frame">
            <table class="list no-stack">
                <thead><tr><th><?= h(t('Baustein')) ?></th><th><?= h(t('Bedeutung')) ?></th></tr></thead>
                <tbody>
                <tr><td><code>JJJJ</code> / <code>JJ</code></td>
                    <td><?= h(t('das Jahr des Funds, mit vier oder zwei Ziffern: %s oder %s', $findToday->format('Y'), $findToday->format('y'))) ?></td></tr>
                <tr><td><code>MM</code> / <code>TT</code></td>
                    <td><?= h(t('Monat und Tag des Funds, mit zwei Ziffern: %s und %s', $findToday->format('m'), $findToday->format('d'))) ?></td></tr>
                <tr><td><code>NNNN</code></td>
                    <td><?= h(t('die laufende Nummer. So viele N du schreibst, so viele Stellen hat sie: aus NNNN wird %s.', find_number_render('NNNN', null, 7))) ?></td></tr>
                <tr><td><code>00543</code></td>
                    <td><?= h(t('Statt der N kannst du deine letzte Nummer schreiben. Der nächste Fund zählt dann von dort weiter.')) ?></td></tr>
                <tr><td><?= h(t('alles andere')) ?></td>
                    <td><?= h(t('bleibt so, wie du es schreibst: Buchstaben, Striche, Leerzeichen')) ?></td></tr>
                </tbody>
            </table>
        </div>

        <div class="table-frame">
            <table class="list no-stack">
                <thead><tr><th><?= h(t('Muster')) ?></th><th><?= h(t('wird heute zu')) ?></th></tr></thead>
                <tbody>
                <?php foreach (['NNNN', 'CW-NNNN', 'CW-JJJJ-NNNN', 'JJMMTT-NNN',
                                t('Fund NNNNN (JJJJ)')] as $example): ?>
                    <tr>
                        <td><code><?= h($example) ?></code></td>
                        <td><code><?= h(find_number_render($example, null, 7)) ?></code></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td><code>wk-JJJJ-00000543</code></td>
                    <td><code><?= h(find_number_render('wk-JJJJ-NNNNNNNN', null, 544)) ?></code> — <?= h(t('der nächste nach deiner Nummer 543')) ?></td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="table-frame">
            <table class="list no-stack">
                <thead><tr><th><?= h(t('Mit Haken „wieder bei 1 anfangen"')) ?></th><th><?= h(t('heute · danach')) ?></th></tr></thead>
                <tbody>
                <tr>
                    <td><code>CW-JJJJ-NNN</code>, <?= h(t('jedes Jahr neu')) ?></td>
                    <td><code><?= h(find_number_render('CW-JJJJ-NNN', null, 7)) ?></code> · <?= h(t('der erste Fund %s:', $findNextYear->format('Y'))) ?>
                        <code><?= h(find_number_render('CW-JJJJ-NNN', $findNextYear->format('Y-m-d H:i:s'), 1)) ?></code></td>
                </tr>
                <tr>
                    <td><code>JJMMTT-NN</code>, <?= h(t('jeden Tag neu')) ?></td>
                    <td><code><?= h(find_number_render('JJMMTT-NN', null, 3)) ?></code> · <?= h(t('der erste Fund am nächsten Tag:')) ?>
                        <code><?= h(find_number_render('JJMMTT-NN', $findTomorrow->format('Y-m-d H:i:s'), 1)) ?></code></td>
                </tr>
                </tbody>
            </table>
        </div>
        <p class="hint-text small"><?= h(t('Neu anfangen geht nur, wenn ein Jahr im Muster steht. Für jeden Monat braucht es JJ und MM, für jeden Tag JJ, MM und TT.')) ?></p>
        <p class="hint-text small"><?= h(t('Ein Muster darf nicht mit „Y-" anfangen. So beginnen die Nummern, die YASAIMAPP selbst vergibt — deshalb kann keine deiner Nummern je gleich lauten wie eine davon.')) ?></p>
    </div>
    <?php } ?>
</section>

<section id="section-measure" role="tabpanel" aria-labelledby="tab-measure" hidden>
    <?php
    require_once __DIR__ . '/photos.php';
    /* The forms return to THEIR sub-tab. $target carries „#account"
       already, and appending to it gave „#account#measure" — an anchor
       no tab has, so the way back hung on the bar's memory alone (found
       26.09.2026, when the sub-tabs came). Fundnummer's $findTarget does
       the same. */
    $measureTarget = url('pages/administration.php');
    $presets = photo_scales($pdo, $userId);
    // Without the column (before 0091) the defaults ARE the style — one
    // source, where a written-out copy here had drifted: its lines were
    // still white when the default had long been orange.
    $style = MEASURE_STYLE_DEFAULTS;
    if (column_exists($pdo, 'map_users', 'measure_style')) {
        $stmt = $pdo->prepare('SELECT measure_style FROM map_users WHERE id = ?');
        $stmt->execute([$userId]);
        $style = measure_style_read($stmt->fetchColumn() ?: null);
    }
    ?>

    <?php /* THREE SUB-TABS (26.09.2026, in the plain-words pass —
         named Messen, Maßstab-Vorlagen, Motivnamen since the main tab
         became „Fotos" the same evening:
         „Hier würde ich gleich drei Untertabs machen. Der erste
         Darstellung, der zweite Maßstab Vorlagen und der dritte
         Motivnamen") — a fourth tab level like Oberfläche's. The ONE
         style form of before is three now, one per sub-tab and each with
         its own save; the processing carries through whatever a form
         does not send ('measure_style' in settings_process). The switch
         for shrinking micro photos went to the presets — his choice
         among three homes, because it touches the scale. The anchors are
         unique in the document, like 'colours' and 'language'. */ ?>
    <?php /* „Messen" and not „Darstellung" (26.09.2026, once the
         main tab was „Fotos": under it, „Darstellung" read like the
         display of the photos). The camera went to the main tab, so the
         names wear the label. */ ?>
    <?php /* NO „MOTIVNAMEN" TAB ANY MORE (14.11.2026). It stood
         first since 27.09.2026 and let every account rewrite the two
         suggestion lists — „zu gefährlich": the names become file names in
         the cloud, sort the gallery and carry the 32 subject symbols, and
         a deleted or renamed stock word breaks all three. A name of one's
         own is typed straight into the upload window („Eigener Name"); a
         name everybody should have goes into PHOTO_TITLES_MACRO / _MICRO in
         photos.php, on his word. The full-size tick that stood at the head
         of the tab went to Mein Bereich → Cloud (cloud_content.php), the
         only place it means anything. Lists saved before stay honoured by
         measure_style_read — on 14.11.2026 the local copy of the live data
         had none — and the processing below carries them through. */ ?>
    <div class="tab" role="tablist" aria-label="<?= h(t('Fotos')) ?>">
        <button type="button" role="tab" id="tab-measure-look" aria-controls="section-measure-look"
                data-anchor="measure-look"><?= icon('ruler') ?><?= h(t('Messen')) ?></button>
        <button type="button" role="tab" id="tab-scale-presets" aria-controls="section-scale-presets"
                data-anchor="scale-presets"><?= icon('microscope') ?><?= h(t('Maßstab-Vorlagen')) ?></button>
        <?php /* THE CAMERA AT THE MICROSCOPE (24.10.2026): beside the
             presets it is calibrated for, not a main tab of its own — one
             working step, camera, calibrating, measuring, stays under one
             roof, and the main bar carries enough. Last, because few have
             a microscope camera on the computer. */ ?>
        <button type="button" role="tab" id="tab-microscope-camera" aria-controls="section-microscope-camera"
                data-anchor="microscope-camera"><?= icon('device') ?><?= h(t('Mikroskop-Kamera')) ?></button>
        <?php /* HOW A PICTURE LEAVES THE APP (08.11.2026: „bei
             Einstellungen, Fotos, und hier bei einem neuen Tab Copyright
             oder Export"): author, file name, watermark templates. */ ?>
        <button type="button" role="tab" id="tab-export-marks" aria-controls="section-export-marks"
                data-anchor="export"><?= icon('import') ?><?= h(t('Export')) ?></button>
    </div>


    <?php /* One block, so no heading over it — the tab already says
         „Messen". ONE SENTENCE (26.09.2026: „Was von Haus
         aus ist, sieht man, und wie sich eine Farbänderung auf dem Bild
         verhält, das weiß man"): the pickers show the colours, and the
         defaults and their reasons live in MEASURE_STYLE_DEFAULTS
         (photos.php). */ ?>
    <section id="section-measure-look" role="tabpanel" aria-labelledby="tab-measure-look" hidden>
    <p class="hint-text"><?= h(t('Hier stellst du ein, wie der Maßstab und deine Messlinien auf einem Foto aussehen.')) ?></p>
    <form method="post" action="<?= h($measureTarget) ?>#measure-look" id="measure-look-form">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="settings">
        <input type="hidden" name="action" value="measure_style">
        <div class="measure-look">
            <div>
                <?php /* COLOUR AND EDGE AS A PAIR, for the bar and for the lines
                     each (26.09.2026: „Dadurch muss der Rand immer
                     zur Hauptfarbe passen. Wenn wir einheitlich die Randfarbe
                     für beide Farben nehmen, muss man immer einen Kompromiss
                     schließen"). */ ?>
                <fieldset class="field">
                    <legend><?= h(t('Maßstab')) ?></legend>
                    <div class="swatch-pair">
                        <label class="swatch-choice"><input type="color" id="measure-scale-color" name="scale_color"
                               value="<?= h($style['scale_color']) ?>"><?= h(t('Farbe')) ?></label>
                        <label class="swatch-choice"><input type="color" id="measure-scale-seam" name="scale_seam_color"
                               value="<?= h($style['scale_seam_color']) ?>"><?= h(t('Randfarbe')) ?></label>
                    </div>
                </fieldset>
                <fieldset class="field">
                    <legend><?= h(t('Messlinien')) ?></legend>
                    <div class="swatch-pair">
                        <label class="swatch-choice"><input type="color" id="measure-line-color" name="line_color"
                               value="<?= h($style['line_color']) ?>"><?= h(t('Farbe')) ?></label>
                        <label class="swatch-choice"><input type="color" id="measure-seam-color" name="seam_color"
                               value="<?= h($style['seam_color']) ?>"><?= h(t('Randfarbe')) ?></label>
                    </div>
                </fieldset>
                <div class="field">
                    <label for="measure-font"><?= h(t('Schriftgröße der Beschriftung, in Pixeln')) ?></label>
                    <input type="number" id="measure-font" name="font_px" min="8" max="36"
                           value="<?= (int) $style['font_px'] ?>">
                </div>
            </div>
            <?php /* THE LIVE PREVIEW (26.09.2026: „wo man schon mal
                 die Ergebnisse sieht, wenn man Farbe, Rand etc. und
                 Schriftgröße einstellt"): a measuring line with its length
                 and the scale bar, on a light, a grey and a dark ground.
                 measurepreview.js draws it with the photo window's own
                 builder (measuredraw.js) and the bar's own stylesheet rule,
                 so it shows what the photo will — redrawn on every change,
                 before anything is saved. For the eye only: the fields
                 say everything it shows. */ ?>
            <div aria-hidden="true">
                <p class="hint-text small" style="margin-top:0"><?= h(t('So sieht es auf einem Foto aus:')) ?></p>
                <div class="measure-preview">
                    <div class="measure-tile light"></div>
                    <div class="measure-tile grey"></div>
                    <div class="measure-tile dark"></div>
                </div>
                <div class="measure-tile-names hint-text small">
                    <span><?= h(t('hell')) ?></span><span><?= h(t('grau')) ?></span><span><?= h(t('dunkel')) ?></span>
                </div>
            </div>
        </div>
        <div class="button-row right">
            <button class="button subtle" type="submit" data-quiet-until-changed><?= icon('save') ?><?= h(t('Speichern')) ?></button>
        </div>
    </form>
    </section>

    <section id="section-scale-presets" role="tabpanel" aria-labelledby="tab-scale-presets" hidden>
    <?php
    /* NOTHING HERE WITHOUT A CLOUD (26.09.2026: „dass man
       diesen Tab erst nutzen kann, wenn man eine Cloud hat … Denn was in
       diesem Tab passiert, braucht die Cloud"). A preset is minted from a
       calibration image, and the image is kept in the cloud — no image
       kept, no preset (a failed upload makes none either). The micro
       switch below goes with it: it only governs what goes up to the
       cloud. Presets made before stay pickable in the photo window.
       WHICH cloud counts is the upload list's call (CLOUD_UPLOAD_PROVIDERS)
       — the clouds the browser may upload into — and the gate names them
       while not all four are proven: a Drive user reading only „mit einer
       verbundenen Cloud" would say „I have one" and wait. */
    require_once __DIR__ . '/cloud.php';
    // The calibration image goes up from the BROWSER — the upload list.
    $calibrateCloud = cloud_released_provider($pdo, $userId, CLOUD_UPLOAD_PROVIDERS);
    ?>
    <?php if ($calibrateCloud === null): ?>
    <p class="hint-text"><?= h(count(CLOUD_UPLOAD_PROVIDERS) < count(CLOUD_PROVIDERS)
        ? t('Maßstab-Vorlagen kannst du nur mit einer verbundenen Cloud nutzen. Im Moment geht das mit %s. Verbinden kannst du sie im Reiter Cloud.', cloud_names_or(CLOUD_UPLOAD_PROVIDERS))
        : t('Maßstab-Vorlagen kannst du nur mit einer verbundenen Cloud nutzen. Verbinden kannst du sie im Reiter Cloud.')) ?></p>
    <?php else: ?>
    <?php /* FIRST, BEFORE THE PRESETS (26.09.2026: „den Bereich
         Mikrofotos ganz oben hinsetzen, noch vor den eigentlichen
         Maßstäben") — it decides what goes up small, the calibration
         images included, so it is decided before a preset is made. Its
         explanation stands before the switch in body size, not as small
         print under it (CLAUDE.md, „A page is sorted by weight"). */ ?>
    <div class="settings-group">
        <h3 class="list-label"><?= h(t('Mikrofotos')) ?></h3>
        <p class="hint-text"><?= h(t('Ein Mikrofoto braucht nicht die volle Größe deiner Kamera. YASAIMAPP verkleinert es auf 2048 Pixel an der langen Seite, auch das Eichbild einer neuen Vorlage. Das spart Platz und Zeit beim Hochladen. RAW-Dateien kann der Browser nicht verkleinern. Sie gehen so hoch, wie sie sind.')) ?></p>
        <p class="hint-text"><?= h(t('Deine Vorlagen messen trotzdem richtig, denn YASAIMAPP merkt sich die volle Größe und rechnet um. Du kannst den Haken also jederzeit setzen oder herausnehmen.')) ?></p>
        <form method="post" action="<?= h($measureTarget) ?>#scale-presets">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="settings">
            <input type="hidden" name="action" value="measure_style">
            <?php /* The tick box and its witness: a tick box that is not ticked
                 is not transmitted at all, and without the witness the
                 processing could not tell "off" from "the form did not
                 know the field yet". */ ?>
            <input type="hidden" name="has_micro_shrink" value="1">
            <div class="field">
                <label class="checkbox-row">
                    <input type="checkbox" name="micro_shrink" value="1"<?= !empty($style['micro_shrink']) ? ' checked' : '' ?>>
                    <?= h(t('Mikrofotos vor dem Hochladen verkleinern')) ?>
                </label>
            </div>
            <div class="button-row right">
                <button class="button subtle" type="submit" data-quiet-until-changed><?= icon('save') ?><?= h(t('Speichern')) ?></button>
            </div>
        </form>
    </div>

    <div class="settings-group">
        <h3 class="list-label"><?= h(t('Deine Vorlagen')) ?></h3>
        <?php /* The presets' explanation under their own head since the
                 micro group went first (26.09.2026): standing loose between
                 the two groups it read as the micro switch's small print. */ ?>
        <p class="hint-text"><?= h(t('Eine Vorlage merkt sich, wie viele Mikrometer ein Pixel auf deinen Mikrofotos misst. Du legst eine für jedes Objektiv an, zum Beispiel „10x" und „40x". Gibst du einem Foto die passende Vorlage, misst du darin sofort in Mikrometern.')) ?></p>
        <?php /* The cloud BY NAME: whoever has two connected must know which one
             to look in — the image goes where the browser uploads
             (api/v1/calibration.php), and a bare „Cloud" once let a Google
             Drive user wait for a folder that never came. */ ?>
        <p class="hint-text"><?= h(t('Für eine neue Vorlage fotografierst du dein Objektmikrometer mit dem Objektiv, für das sie gelten soll. YASAIMAPP legt das Foto in deiner Cloud (%s) im Ordner „Kalibrierung" ab. Dann kannst du die Vorlage später am selben Bild nachmessen.', CLOUD_PROVIDERS[$calibrateCloud]['name'] ?? $calibrateCloud)) ?></p>
        <p class="hint-text"><?= h(t('Speicherst du bei einem Gerät eine Vorlage unter einem Namen, den es dort schon gibt, ersetzt sie die alte.')) ?></p>
        <p class="hint-text"><?= h(t('Hast du mehrere Mikroskope oder eine Stereolupe, legst du für jedes ein eigenes Gerät an. Jedes Gerät bekommt seine eigenen Vorlagen. Beim Messen wählst du dann zuerst das Gerät und danach das Objektiv.')) ?></p>
        <?php if ($presets !== []): ?>
        <?php /* The column explained only where there is one: the stamping
             warning compares a photo's width with the calibration image's
             (map.js), and „6000 × 4000 px" says nothing to whoever does not
             know that. */ ?>
        <p class="hint-text"><?= h(t('Bei „Eichbild" steht die Größe des Fotos, mit dem du geeicht hast. Hat ein Foto eine andere Größe, warnt dich YASAIMAPP. Es stammt dann vielleicht von einer anderen Kamera, und die Vorlage misst falsch.')) ?></p>
        <?php endif; ?>
        <?php
        /* ONE TABLE PER DEVICE (02.11.2026, migration 0183),
           the device's name in the place of the „Name" column's head and
           its own pencil and bucket at the head's right end, in line with
           the rows' — one head bar per device instead of a device bar
           stacked over a column bar. Before 0183 there is one table with
           no device, as it always was. An empty device stands too: it
           waits for its first objective. */
        $devices = scale_devices($pdo, $userId);
        $groups = $devices === []
            ? ($presets === [] ? [] : [['id' => 0, 'name' => null]])
            : $devices;
        ?>
        <?php foreach ($groups as $device):
            $rows = $device['id'] === 0 ? $presets
                : array_values(array_filter($presets, static fn(array $p): bool => $p['device_id'] === $device['id'])); ?>
        <div class="table-frame scale-device">
            <table class="list">
                <?php /* „Mikrometer" written out: the header row sets
                         capitals, and Unicode turns µ into a Greek capital
                         Mu — „µm je Pixel" stood there as „MM JE PIXEL",
                         and everybody reads MM as millimetres. */ ?>
                <thead><tr>
                    <th><?= h($device['name'] ?? t('Name')) ?></th>
                    <th><?= h(t('Mikrometer je Pixel')) ?></th>
                    <th><?= h(t('Eichbild')) ?></th>
                    <th class="button-cell">
                        <?php if ($device['id'] > 0): ?>
                        <button type="button" class="button subtle small"
                                data-device-rename data-id="<?= (int) $device['id'] ?>"
                                data-name="<?= h($device['name']) ?>"
                                aria-label="<?= h(t('Gerät umbenennen')) ?>"><?= icon('pencil') ?></button>
                        <?php /* Bucket: the device and its presets cease to exist.
                             The pictures keep their scale all the same. */ ?>
                        <form method="post" action="<?= h($measureTarget) ?>#scale-presets"
                              data-confirm="<?= h($rows === []
                                  ? t('Das Gerät „%s" wird gelöscht.', $device['name'])
                                  : t('Das Gerät „%s" wird mit seinen %d Vorlagen gelöscht. Fotos, die eine davon schon bekommen haben, behalten ihren Maßstab.', $device['name'], count($rows))) ?>"
                              data-confirm-title="<?= h(t('Gerät löschen?')) ?>"
                              data-confirm-button="<?= h(t('Löschen')) ?>"
                              data-confirm-danger>
                            <?= csrf_field() ?>
                            <input type="hidden" name="section" value="settings">
                            <input type="hidden" name="action" value="scale_device_remove">
                            <input type="hidden" name="id" value="<?= (int) $device['id'] ?>">
                            <button type="submit" class="button danger small"
                                    aria-label="<?= h(t('Gerät löschen')) ?>"><?= icon('delete') ?></button>
                        </form>
                        <?php endif; ?>
                    </th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $p): ?>
                    <tr>
                        <td><?= h($p['name']) ?></td>
                        <td><?= h(number_format($p['um_per_px'], 4, ',', '')) ?></td>
                        <?php /* The calibration image's pixel grid (0093) — the
                                 yardstick of the stamping warning. Every preset
                                 is minted from a picture now; the dash only
                                 guards a row from before. */ ?>
                        <td><?= $p['width'] !== null && $p['height'] !== null
                            ? h($p['width'] . ' × ' . $p['height'] . ' px') : '—' ?></td>
                        <td class="button-cell">
                            <?php /* Rename, or re-measure on the STORED calibration
                                 image (28.08.2026: „mit dem gleichen
                                 Bild natürlich") — the pencil opens the
                                 calibration window on it (calibrate.js). */ ?>
                            <button type="button" class="button subtle small"
                                    data-preset-edit data-id="<?= (int) $p['id'] ?>"
                                    data-name="<?= h($p['name']) ?>"
                                    data-image="<?= ($p['fileid'] ?? null) !== null ? '1' : '' ?>"
                                    data-width="<?= (int) ($p['width'] ?? 0) ?>"
                                    data-height="<?= (int) ($p['height'] ?? 0) ?>"
                                    aria-label="<?= h(t('Vorlage bearbeiten')) ?>"><?= icon('pencil') ?></button>
                            <?php /* Bucket and not cross: the preset ceases to exist
                                 for good. Photos already stamped keep their
                                 scale — the stamp materialised the factor, so
                                 no photo ever depends on a preset's survival. */ ?>
                            <form method="post" action="<?= h($measureTarget) ?>#scale-presets"
                                  data-confirm="<?= h(t('Die Vorlage „%s" wird gelöscht. Fotos, die sie schon bekommen haben, behalten ihren Maßstab.', $p['name'])) ?>"
                                  data-confirm-title="<?= h(t('Vorlage löschen?')) ?>"
                                  data-confirm-button="<?= h(t('Löschen')) ?>"
                                  data-confirm-danger>
                                <?= csrf_field() ?>
                                <input type="hidden" name="section" value="settings">
                                <input type="hidden" name="action" value="scale_preset_remove">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="button danger small"
                                        aria-label="<?= h(t('Vorlage löschen')) ?>"><?= icon('delete') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="4" class="hint-text small"><?= h(t('Noch keine Vorlagen für dieses Gerät.')) ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php /* Quiet: a standing offer, not the one action that is due. It
             asks for the picture first — on the phone camera or gallery —
             and the window opens once there is one (calibrate.js), for
             THIS device. */ ?>
        <div class="button-row">
            <button type="button" class="button subtle" data-calibrate-new
                    data-device="<?= (int) $device['id'] ?>"
                    data-device-name="<?= h($device['name'] ?? '') ?>"><?= icon('plus') ?><?= h(t('Neue Vorlage …')) ?></button>
        </div>
        <?php endforeach; ?>
        <?php if ($groups === []): ?>
            <p class="hint-text small"><?= h(t('Noch keine Vorlagen.')) ?></p>
            <div class="button-row">
                <button type="button" class="button subtle" data-calibrate-new data-device="0" data-device-name=""><?= icon('plus') ?><?= h(t('Neue Vorlage …')) ?></button>
            </div>
        <?php endif; ?>
        <?php if (scale_devices_ready($pdo)): ?>
        <?php /* A new device asks only for its name (calibrate.js), and this
             form carries it. */ ?>
        <form method="post" action="<?= h($measureTarget) ?>#scale-presets" id="scale-device-form">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="settings">
            <input type="hidden" name="action" value="scale_device_save">
            <input type="hidden" name="id" value="0">
            <input type="hidden" name="name" value="">
        </form>
        <div class="button-row scale-device-new">
            <button type="button" class="button subtle" data-device-new><?= icon('plus') ?><?= h(t('Neues Gerät …')) ?></button>
        </div>
        <?php endif; ?>
    </div>

    <?php /* THE CALIBRATION IS A STEP OF ITS OWN, IN A WINDOW OF ITS OWN
         (26.09.2026: „Neue Vorlage würde ich ähnlich bauen wie
         die Fotovorschau, und zwar in einem Fullscreen-Fenster, wo das
         Bild angezeigt wird, wo ich dann messe, wo ich oben … den Namen
         der Vorlage, dann die Länge eingeben kann … Das muss ein eigener
         Schritt sein mit einem eigenen Fenster"). Built in the photo
         window's grammar: the row of entries on top, the picture on black
         in the middle, zoom on the left and the concluding buttons on the
         right below. „Neue Vorlage …" above asks for the picture first,
         the pencil opens the same window on the preset's stored picture.
         The data attributes are calibrate.js's. */ ?>
    <dialog class="calibrate-window" id="calibrate-window" tabindex="-1"
            aria-labelledby="calibrate-heading"
            data-target="<?= h(url('api/v1/calibration.php')) ?>"
            data-image-url="<?= h(url('pages/calibration_image.php')) ?>"
            data-micro-shrink="<?= !empty($style['micro_shrink']) ? '1' : '0' ?>"
            data-csrf="<?= h(csrf_token()) ?>">
        <form method="post" action="<?= h($measureTarget) ?>#scale-presets" id="calibrate-form">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="settings">
            <input type="hidden" name="action" value="scale_calibrate">
            <input type="hidden" name="line_px" id="calibrate-line-px">
            <input type="hidden" name="width" id="calibrate-width">
            <input type="hidden" name="height" id="calibrate-height">
            <?php /* Set by the pencil: the row being EDITED — rename, or
                 re-calibrate on its stored image. Empty means minting. */ ?>
            <input type="hidden" name="preset" id="calibrate-preset">
            <?php /* Set by „Neue Vorlage …": the device the new preset joins
                 (0183). Empty, or before the migration, means the first. */ ?>
            <input type="hidden" name="device" id="calibrate-device">
            <?php /* The provider's id of a freshly archived image (0094), and
                 which cloud it went to (0138) — the target names it. */ ?>
            <input type="hidden" name="fileid" id="calibrate-fileid">
            <input type="hidden" name="provider" id="calibrate-provider">
            <?php /* DELIBERATELY WITHOUT name: an unnamed field never submits,
                 and the picture must never ride this form —
                 upload_max_filesize is 2 MB, a full-resolution micrograph is
                 many times that. The browser measures locally and uploads
                 to the cloud directly (calibrate.js), like every recording.
                 Hidden: „Neue Vorlage …", „Anderes Bild …" and the empty
                 stage's button reach it. */ ?>
            <input type="file" id="calibrate-file" accept="image/*" hidden>
            <?php /* NAME FIRST, THEN THE LENGTH (his order). The length is NOT
                 required in markup: renaming a preset submits this form
                 without a measurement, and the browser must not block
                 that — calibrate.js demands it exactly when points were
                 clicked. */ ?>
            <div class="calibrate-window-top">
                <?php /* THE DOOR IN THE TOP LEFT CORNER (30.09.2026),
                     as in the map's picture viewer (.window-x in app.css).
                     It does what „Abbrechen" below does, and says so: the
                     measuring begun here is not kept. type=button — inside
                     this form anything else would submit it. */ ?>
                <button type="button" class="button subtle small window-x" id="calibrate-close-top"
                        title="<?= h(t('Abbrechen')) ?>" aria-label="<?= h(t('Abbrechen')) ?>"><?= icon('close') ?></button>
                <span class="calibrate-window-title" id="calibrate-heading"><?= h(t('Neue Vorlage')) ?></span>
                <label class="calibrate-entry">
                    <span><?= h(t('Name der Vorlage')) ?></span>
                    <input type="text" id="calibrate-name" name="name" maxlength="100"
                           placeholder="<?= h(t('z. B. 10x')) ?>" required autocomplete="off">
                </label>
                <label class="calibrate-entry">
                    <span><?= h(t('Wahre Länge der Strecke')) ?></span>
                    <input type="text" id="calibrate-length" name="length" class="calibrate-length"
                           inputmode="decimal" placeholder="<?= h(t('z. B. 100')) ?>" autocomplete="off">
                    <select name="unit" aria-label="<?= h(t('Einheit')) ?>">
                        <option value="1">µm</option>
                        <option value="1000">mm</option>
                    </select>
                </label>
            </div>
            <?php /* The measuring stage: the picture with an SVG plane over it,
                 zoom and pan from imagezoom.js — the same machinery as the
                 photo window, because aiming happens zoomed in. The SVG is
                 a canvas like the map's #photo-overlay (tests/icons.php
                 keeps it on record). The hint FLOATS over the picture, as
                 the photo window's aiming hint does; without a picture the
                 stage says so and offers one. */ ?>
            <div class="calibrate-stage" id="calibrate-stage">
                <img id="calibrate-image" alt="" hidden>
                <svg class="calibrate-overlay" id="calibrate-overlay" aria-hidden="true"></svg>
                <p class="calibrate-hint" id="calibrate-hint" hidden></p>
                <div class="calibrate-empty" id="calibrate-empty" hidden>
                    <p><?= h(t('Zu dieser Vorlage gibt es kein Eichbild. Du kannst nur den Namen ändern. Oder du wählst ein Eichbild und misst neu.')) ?></p>
                    <button type="button" class="button subtle" id="calibrate-choose"><?= h(t('Eichbild wählen …')) ?></button>
                </div>
            </div>
            <div class="calibrate-window-footer">
                <?php /* Zoom stands apart from the concluding buttons, as in the
                     photo window: it works on what is above it. */ ?>
                <div class="calibrate-window-zoom">
                    <button type="button" class="button subtle small" id="calibrate-zoom-out"
                            aria-label="<?= h(t('Verkleinern')) ?>">−</button>
                    <button type="button" class="button subtle small" id="calibrate-zoom-in"
                            aria-label="<?= h(t('Vergrößern')) ?>">+</button>
                    <button type="button" class="button subtle small" id="calibrate-zoom-full">1:1</button>
                    <button type="button" class="button subtle small" id="calibrate-zoom-fit"><?= h(t('Einpassen')) ?></button>
                </div>
                <div class="calibrate-window-buttons">
                    <button type="button" class="button subtle" id="calibrate-other" hidden><?= h(t('Anderes Bild …')) ?></button>
                    <?php /* The CROSS gesture: dismissing something pending, nothing
                         is lost that was stored. */ ?>
                    <button type="button" class="button subtle" id="calibrate-cancel"><?= h(t('Abbrechen')) ?></button>
                    <?php /* Loud: in this window saving IS the one thing that is
                         due, as „Übernehmen" is in the photo window's scale
                         question. */ ?>
                    <button type="submit" class="button" id="calibrate-save"><?= icon('save') ?><?= h(t('Vorlage speichern')) ?></button>
                </div>
            </div>
        </form>
    </dialog>

    <?php endif; ?>
    </section>

    <?php /* MIKROSKOP-KAMERA (24.10.2026). What the tab is opened
         for decides the order: is THIS computer set up — three points, each
         with its state and the step that answers it; then the way to a
         camera nothing reaches yet, the guide for an AI.

         THE SETUP IS THE DOOR TO THE BUTTON. „Mikroskopieren" stands at a
         find only on a computer set up with a camera and with a cloud the
         browser uploads into (microscope.js, mountMicroscope). The presets
         are no condition — a picture without a scale is still a picture,
         and the window calibrates. The camera is the browser's to find
         out, not the server's: it hangs on the computer the page runs on
         (assets/js/microscopesettings.js). */ ?>
    <?php
    $microscopeCloud = cloud_released_provider($pdo, $userId, CLOUD_UPLOAD_PROVIDERS);
    $microscopePresets = photo_scales($pdo, $userId);
    ?>
    <section id="section-microscope-camera" role="tabpanel" aria-labelledby="tab-microscope-camera"
             data-cloud="<?= $microscopeCloud !== null ? '1' : '0' ?>" hidden>
        <p class="hint-text"><?= h(t('Hängt eine Mikroskop-Kamera an diesem Computer, kannst du direkt in YASAIMAPP mikroskopieren. Öffne einen Fund, dann Belege und „Mikroskopieren". Du siehst das Livebild. Jedes Foto geht mit dem Maßstab deines Objektivs in deine Cloud.')) ?></p>
        <p class="hint-text"><?= h(t('Manche Mikroskop-Kameras arbeiten wie eine Webcam, die findet der Browser selbst. Andere brauchen ein kleines Programm auf dem Computer, für die BMS-Kamera am Mac ist das MicroCam.')) ?></p>

        <div class="settings-group">
            <h3 class="list-label"><?= h(t('Auf diesem Computer einrichten')) ?></h3>
            <ol class="microscope-setup">
                <li class="microscope-setup-step" id="microscope-setup-camera">
                    <span class="microscope-setup-mark"><?= icon('check') ?></span>
                    <div>
                        <p class="microscope-camera-state" id="microscope-camera-state"><?= h(t('Kamera: noch nicht eingerichtet.')) ?></p>
                        <p class="hint-text" id="microscope-camera-detail" hidden></p>
                        <div class="button-row">
                            <button type="button" class="button subtle" id="microscope-camera-check"><?= icon('refresh') ?><?= h(t('Kamera-Programm suchen')) ?></button>
                            <button type="button" class="button subtle" id="microscope-webcam-start"><?= icon('photo') ?><?= h(t('Als Webcam einrichten')) ?></button>
                            <button type="button" class="button subtle" id="microscope-camera-forget" hidden><?= icon('close') ?><?= h(t('Auf diesem Computer vergessen')) ?></button>
                        </div>
                        <?php /* THE WEBCAM CHOICE: every camera the browser knows,
                             and the picture of the one chosen — a laptop has
                             its own camera beside the microscope's, and only
                             the picture tells them apart. */ ?>
                        <div class="microscope-webcam-choice" id="microscope-webcam-choice" hidden>
                            <fieldset class="field question">
                                <legend><?= h(t('Welche Kamera ist am Mikroskop?')) ?></legend>
                                <div id="microscope-webcam-list"></div>
                            </fieldset>
                            <video class="microscope-webcam-preview" id="microscope-webcam-preview" muted playsinline></video>
                            <div class="button-row">
                                <button type="button" class="button" id="microscope-webcam-take"><?= icon('check') ?><?= h(t('Diese Kamera nehmen')) ?></button>
                            </div>
                        </div>
                    </div>
                </li>
                <li class="microscope-setup-step<?= $microscopeCloud !== null ? ' is-done' : '' ?>">
                    <span class="microscope-setup-mark"><?= icon('check') ?></span>
                    <div>
                        <?php if ($microscopeCloud !== null): ?>
                        <p class="microscope-camera-state"><?= h(t('Cloud: verbunden mit %s.', CLOUD_PROVIDERS[$microscopeCloud]['name'] ?? $microscopeCloud)) ?></p>
                        <?php else: ?>
                        <p class="microscope-camera-state"><?= h(t('Cloud: noch keine verbunden.')) ?></p>
                        <p class="hint-text"><?= h(t('Die Fotos vom Mikroskop gehen in voller Größe in deine Cloud. Verbinden kannst du sie im Reiter Cloud.')) ?></p>
                        <?php endif; ?>
                    </div>
                </li>
                <li class="microscope-setup-step<?= $microscopePresets !== [] ? ' is-done' : ' is-optional' ?>">
                    <span class="microscope-setup-mark"><?= icon('check') ?></span>
                    <div>
                        <?php if ($microscopePresets !== []): ?>
                        <p class="microscope-camera-state"><?= h(t('Maßstab: %s geeicht.', implode(', ', array_map(static fn(array $p): string => $p['name'], $microscopePresets)))) ?></p>
                        <?php else: ?>
                        <p class="microscope-camera-state"><?= h(t('Maßstab: noch kein Objektiv geeicht.')) ?></p>
                        <?php endif; ?>
                        <p class="hint-text"><?= h(t('Das brauchst du nicht zum Fotografieren, nur zum Messen. Eichen kannst du im Mikroskop-Fenster mit dem Maßstab-Zeichen neben „Objektiv": Objektmikrometer unterlegen, Foto machen, eine Strecke der Skala anklicken.')) ?></p>
                    </div>
                </li>
            </ol>
            <p class="microscope-setup-result" id="microscope-setup-result"></p>
        </div>

        <div class="settings-group">
            <h3 class="list-label"><?= h(t('Eine andere Kamera')) ?></h3>
            <p class="hint-text"><?= h(t('Findet YASAIMAPP deine Kamera weder als Webcam noch über ein Programm, kann eine KI das Programm mit dir zusammen bauen, zum Beispiel Claude oder ChatGPT Codex, auch unter Windows. Lade dazu die Anleitung herunter und gib sie der KI. Sie führt dich Schritt für Schritt, auch wenn du dich mit Computern wenig auskennst.')) ?></p>
            <div class="button-row">
                <a class="button subtle" href="<?= h(url('pages/microscope_guide.php')) ?>" download><?= icon('file') ?><?= h(t('Anleitung herunterladen')) ?></a>
            </div>
            <p class="hint-text small provider-currency"><?= h(t('Erprobt mit: BMS U3CMOS10000KPA am Mac.')) ?></p>
        </div>
    </section>

    <?php
    /* EXPORT — drawn by assets/js/exportsettings.js from the account's style
       (START_DATA.measure_style.export) and saved through
       api/v1/measure_style.php. The account's own name stands ready as the
       author's until one is written. */
    $exportMe = $pdo->prepare('SELECT first_name, last_name, email FROM map_users WHERE id = ?');
    $exportMe->execute([$userId]);
    $exportName = user_full_name($exportMe->fetch() ?: null);
    ?>
    <section id="section-export-marks" role="tabpanel" aria-labelledby="tab-export-marks"
             data-default-name="<?= h($exportName) ?>" hidden>
        <p class="hint-text"><?= h(t('Hier legst du fest, wie ein bearbeitetes Bild beim Exportieren aussieht: wer der Urheber ist, wie die Datei heißt, und welche Wasserzeichen du zur Wahl hast. Exportieren kannst du ein Bild im Bildbetrachter unter „Bearbeiten“ mit „Bild exportieren“.')) ?></p>
        <div id="export-settings"><p class="hint-text"><?= h(t('Wird geladen …')) ?></p></div>
    </section>

</section>

<?php
/* THE DGfM DELIVERY, A TAB OF ITS OWN (25.09.2026: „Lass uns
   doch aus der DGfM-Kartierung einen eigenen Reiter bauen") — for an hour
   the third group under Konto, but it is about the finds, not the login.
   The finder's own tick (migration 0127, includes/mykis.php) and their
   step (0129, 0130). What goes out and what never does stands BEFORE the
   tick — one decides with it, not after it. One block, so no group head:
   over a single one it would name the tab twice. */
if ($mkReady):
    $mkTarget = url('pages/administration.php') . '#dgfm';
    $mkHasPrecision = column_exists($pdo, 'map_users', 'mykis_precision');
    $mkHasCopy = mykis_copy_ready($pdo);
    $mkHasForeign = mykis_foreign_ready($pdo);
    $mkHasDetermined = mykis_determined_ready($pdo);
    $mkHasRules = mykis_rules_ready($pdo);
    $mk = $pdo->prepare('SELECT u.mykis_consent_at, u.email_confirmed_at, u.first_name, u.last_name,'
        . ($mkHasPrecision ? ' u.mykis_precision,' : '')
        . ($mkHasCopy ? ' u.mykis_copy,' : '')
        . ($mkHasForeign ? ' u.mykis_foreign,' : '')
        . ($mkHasDetermined ? ' u.mykis_determined,' : '')
        . ($mkHasRules ? ' u.mykis_rules,' : '') . '
            (SELECT COUNT(*) FROM map_mykis_deliveries d JOIN map_points p ON p.id = d.point_id
              WHERE p.user_id = u.id) AS delivered
          FROM map_users u WHERE u.id = ?');
    $mk->execute([$userId]);
    $mk = $mk->fetch();
    $mkConfirmed = $mk['email_confirmed_at'] !== null;
    $mkConsented = $mk['mykis_consent_at'] !== null;
    $mkPrecision = mykis_precision($mk['mykis_precision'] ?? null);
    $mkRules = mykis_rules_read($mk['mykis_rules'] ?? null);
    $mkNext = t(mykis_next_delivery(new DateTimeImmutable()));
?>
<section id="section-dgfm" role="tabpanel" aria-labelledby="tab-dgfm" hidden>
<?php if ($mkConsented && ($state['mykis_welcome'] ?? null) !== null): ?>
    <?php /* THE NEW TAB, ANNOUNCED (see 'mykis_consent' in the processing):
         administration.js opens a dialog[data-open-at-load] by itself, and
         a button whose value names a tab's anchor opens that tab as it
         closes the window. */ ?>
    <dialog class="dialog" id="dgfm-welcome" data-open-at-load>
        <form method="dialog">
            <h2 class="dialog-title"><?= h(t('Neuer Reiter: Genauigkeit')) ?></h2>
            <p class="dialog-text"><?= h(t('Gespeichert. Deine Funde gehen ab jetzt zweimal im Jahr an die Landeskoordinatoren. Die nächste Meldung geht %s raus.', (string) $state['mykis_welcome'])) ?></p>
            <p class="dialog-text"><?= h(t('Neben „Meldung" gibt es jetzt den Reiter „Genauigkeit". Dort stellst du ein und filterst, was und wie genau du an die DGfM schickst.')) ?></p>
            <div class="dialog-footer">
                <button type="submit" class="button subtle" value=""><?= h(t('Später')) ?></button>
                <button type="submit" class="button" value="dgfm-precision"><?= icon('crosshair') ?><?= h(t('Genauigkeit öffnen')) ?></button>
            </div>
        </form>
    </dialog>
<?php endif; ?>
<?php if ($mkConsented): ?>
    <?php /* TWO SUB-TABS ONCE AGREED (27.09.2026): „Meldung" for
         the consent and what goes along, „Genauigkeit" for the rules of how
         precisely („einen kompletten Bereich Genauigkeit, wo wir filtern
         können"). Before the consent neither exists — one decides WHETHER
         first, then HOW (his remark: all options stood open and settable
         before anybody had agreed to anything). */ ?>
    <div class="tab" role="tablist" aria-label="<?= h(t('DGfM-Kartierung')) ?>">
        <button type="button" role="tab" id="tab-dgfm-report" aria-controls="section-dgfm-report"
                data-anchor="dgfm-report"><?= icon('arrow-right') ?><?= h(t('Meldung')) ?></button>
        <button type="button" role="tab" id="tab-dgfm-precision" aria-controls="section-dgfm-precision"
                data-anchor="dgfm-precision"><?= icon('crosshair') ?><?= h(t('Genauigkeit')) ?></button>
    </div>
    <section id="section-dgfm-report" role="tabpanel" aria-labelledby="tab-dgfm-report" hidden>
<?php endif; ?>
    <?php /* ONLY WHAT DECIDES THE TICK, AND THE TICK RIGHT UNDER IT
         (27.09.2026: „hier bitte nur das Wichtigste erzählen. Der Text ist
         viel zu lang. Und der Haken viel zu weit unten."): what happens, to
         whom, when, that only sure finds go, and that name and address go
         along — his sentence about the coordinator stays. The conditions
         wait behind a question mark („einfach so hinschreiben ist zu viel
         Text. Das liest keiner."), open to whoever has not agreed yet too. */ ?>
    <p class="hint-text"><?= h(t('Deine Pilzfunde können an die Pilzkartierung der DGfM gehen, der Deutschen Gesellschaft für Mykologie. YASAIMAPP schickt sie zweimal im Jahr, Anfang Juni und Anfang Dezember. Jeder Fund geht automatisch an den entsprechenden Landeskoordinator.')) ?></p>
    <p class="hint-text"><?= h(t('Es gehen nur sicher bestimmte Funde. Dein Name und deine E-Mail-Adresse stehen dabei, damit man dich bei Fragen erreicht.')) ?></p>
    <details class="info-fold">
        <summary><?= icon('help') ?><?= h(t('Welche Funde gehen mit?')) ?></summary>
        <p class="hint-text"><?= h(t('Ein Fund geht nur mit, wenn alles zutrifft:')) ?></p>
        <ul class="hint-text">
            <li><?= h(t('gefunden in Deutschland,')) ?></li>
            <li><?= h(t('mit Fundtag,')) ?></li>
            <li><?= h(t('mit einem Namen aus der Artenliste der DGfM,')) ?></li>
            <li><?= h(t('sicher bestimmt: makroskopisch, mikroskopisch oder genetisch.')) ?></li>
        </ul>
        <p class="hint-text"><?= h(t('Hast du nichts angegeben, gilt ein Fund als sicher — außer im Namen steht cf., aff. oder sp. Sammelarten mit agg. und Namen mit s. l. gehen mit.')) ?></p>
    </details>

    <?php /* THE OPTIONS WAIT FOR THE TICK (D1): .dgfm-after shows only while
         the consent box is ticked (app.css, :has) — at once, before any
         saving, and gone again when it is taken out. */ ?>
    <form method="post" action="<?= h($mkTarget) ?>" class="dgfm-form">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="settings">
        <input type="hidden" name="action" value="mykis_consent">
        <input type="hidden" name="consent" value="0">
        <div class="field">
            <label class="checkbox-row">
                <input type="checkbox" name="consent" value="1"<?= $mkConsented ? ' checked' : '' ?><?= $mkConfirmed ? '' : ' disabled' ?>>
                <?= h(t('Meine Funde an die DGfM-Kartierung melden')) ?>
            </label>
            <?php if (!$mkConfirmed): ?>
                <p class="hint-text small"><?= h(t('Das geht erst, wenn deine E-Mail-Adresse bestätigt ist. Den Link dafür bekommst du unter Konto.')) ?></p>
            <?php elseif ($mkConsented): ?>
                <p class="hint-text small dgfm-after"><?= h(t('Zugestimmt am %s. Bisher %d Funde gemeldet. Die nächste Meldung geht %s raus.',
                    date('d.m.Y', strtotime((string) $mk['mykis_consent_at'])), (int) $mk['delivered'], $mkNext)) ?></p>
                <?php
                /* WHAT STAYS OUT FOR ITS NAME (05.11.2026, with the working
                   names): it went without a word before. A state, so it
                   stands in the flow, not as a toast. */
                $mkUnlisted = mykis_unlisted(mykis_points($pdo, $userId), mykis_self($mk));
                ?>
                <?php if ($mkUnlisted > 0): ?>
                    <p class="hint-text small dgfm-after"><?= h($mkUnlisted === 1
                        ? t('1 Fund geht nicht mit, weil sein Name in keiner Liste der DGfM steht. Das ist zum Beispiel bei Arbeitsnamen so.')
                        : t('%d Funde gehen nicht mit, weil ihr Name in keiner Liste der DGfM steht. Das ist zum Beispiel bei Arbeitsnamen so.', $mkUnlisted)) ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="dgfm-after">
        <?php if ($mkHasForeign): ?>
        <?php /* OTHER FINDERS' FINDS ONLY ON ONE'S OWN WORD, AND IN TWO KINDS
             (0133, 0144; 27.09.2026: „Ich würde da zwei Felder
             draus machen"). The finds one determined for somebody else, and
             the rest — the lists one was given to enter, a deceased
             member's finds. Off to begin with — nothing goes out in somebody
             else's name unasked. The labels say it themselves, so no
             sentence stands under them. Before 0144 the one old tick is
             both (mykis_self). The MykIS sheet made by hand asks the same
             in its own window (Organisieren → Export). */ ?>
        <?php if ($mkHasDetermined): ?>
        <input type="hidden" name="determined" value="0">
        <div class="field">
            <label class="checkbox-row">
                <input type="checkbox" name="determined" value="1"<?= (int) ($mk['mykis_determined'] ?? 0) === 1 ? ' checked' : '' ?>>
                <?= h(t('Auch Funde melden, bei denen ich nicht Finder, aber Bestimmer bin')) ?>
            </label>
        </div>
        <?php endif; ?>
        <input type="hidden" name="foreign" value="0">
        <div class="field">
            <label class="checkbox-row">
                <input type="checkbox" name="foreign" value="1"<?= (int) ($mk['mykis_foreign'] ?? 0) === 1 ? ' checked' : '' ?>>
                <?= h($mkHasDetermined
                    ? t('Auch Funde melden, bei denen ich weder Finder noch Bestimmer bin')
                    : t('Auch Funde melden, bei denen jemand anderes Finder ist')) ?>
            </label>
        </div>
        <?php endif; ?>
        <?php if ($mkHasCopy): ?>
        <?php /* THE COPY IS ONE'S OWN CHOICE (0131, 25.09.2026:
             „wenn man eine Kopie möchte, hier auch ein Haken"). Worth it to
             whoever wants to send on by hand what did not go — a name the
             TaxRef lacked, which Frank Dämmrich then adds to it. The label
             says what comes, not why: „ich schreibe das nicht so
             ausführlich". */ ?>
        <input type="hidden" name="copy" value="0">
        <div class="field">
            <label class="checkbox-row">
                <input type="checkbox" name="copy" value="1"<?= (int) ($mk['mykis_copy'] ?? 0) === 1 ? ' checked' : '' ?><?= $mkConfirmed ? '' : ' disabled' ?>>
                <?= h(t('Schick mir eine Kopie der Funde, die an die Landeskoordinatoren gingen')) ?>
            </label>
        </div>
        <?php endif; ?>
        </div>
        <div class="button-row right">
            <button class="button subtle" type="submit" data-quiet-until-changed<?= $mkConfirmed ? '' : ' disabled' ?>><?= icon('save') ?><?= h(t('Speichern')) ?></button>
        </div>
    </form>
<?php if ($mkConsented): ?>
    </section>

    <section id="section-dgfm-precision" role="tabpanel" aria-labelledby="tab-dgfm-precision" hidden>
    <?php
    require_once __DIR__ . '/species.php';
    $mkTyped = $state['mykis_typed'] ?? [];
    // The short names in the rows; the long ones, with the sizes, stand
    // once above the rules (see „So groß sind die Stufen").
    $mkShort = ['q4' => t('Viertelquadrant'), 'q16' => t('16tel'), 'q64' => t('64stel'), 'exact' => t('genau')];
    /* ONE LINE OF STEPS BEHIND A NAME (28.09.2026: „mach mir
       diese einzeilige Auswahl bei den restlichen auch") — the Red List's
       categories, the edible mushrooms, all other finds: the same row as a
       genus's, without the cross. */
    $mkStepRow = static function (string $label, string $field, ?string $chosen, bool $withNone) use ($mkShort): string {
        $out = '<div class="item-row rule-row"><span class="rule-name">' . h($label) . '</span>'
            . '<span class="inline-choices" role="radiogroup" aria-label="' . h(t('Genauigkeit für %s', $label)) . '">';
        if ($withNone) {
            $out .= '<label class="choice-row"><input type="radio" name="' . h($field) . '" value=""'
                . ($chosen === null ? ' checked' : '') . '> ' . h(t('wie alle übrigen')) . '</label>';
        }
        foreach ($mkShort as $key => $text) {
            $out .= '<label class="choice-row"><input type="radio" name="' . h($field) . '" value="' . h($key) . '"'
                . ($chosen === $key ? ' checked' : '') . '> ' . h($text) . '</label>';
        }
        return $out . '</span></div>';
    };
    // One row of a list: the name, its step where the list has steps, the
    // cross. The cross is a submit button whose value is the name — the
    // processing leaves that one out and saves the rest.
    $mkRow = static function (string $name, string $field, string $drop, ?string $stepField, ?string $step) use ($mkShort): string {
        // A row without steps is an exclusion — it reads as one (app.css).
        $out = '<div class="item-row rule-row' . ($stepField === null ? ' is-excluded' : '') . '"><span class="rule-name">' . h($name) . '</span>'
            . '<input type="hidden" name="' . h($field) . '" value="' . h($name) . '">';
        if ($stepField !== null) {
            $out .= '<span class="inline-choices" role="radiogroup" aria-label="' . h(t('Genauigkeit für %s', $name)) . '">';
            foreach ($mkShort as $key => $label) {
                $out .= '<label class="choice-row"><input type="radio" name="' . h($stepField) . '" value="' . h($key) . '"'
                    . ($step === $key ? ' checked' : '') . '> ' . h($label) . '</label>';
            }
            $out .= '</span>';
        }
        return $out . '<button type="submit" class="symbol-button" name="' . h($drop) . '" value="' . h($name) . '"'
            . ' title="' . h(t('Entfernen')) . '" aria-label="' . h(t('„%s" entfernen', $name)) . '">' . icon('close') . '</button></div>';
    };
    $mkAdder = static fn(string $field, string $label, string $typed): string =>
        '<div class="field-row rule-adder"><input type="text" name="' . h($field) . '" value="' . h($typed) . '"'
        . ' maxlength="120" autocomplete="off" spellcheck="false" aria-label="' . h($label) . '" placeholder="' . h($label) . '">'
        . '<button type="submit" class="button subtle" name="op" value="add">' . icon('plus') . h(t('Hinzufügen')) . '</button></div>';
    ?>
    <?php if (!$mkHasRules): ?>
        <p class="hint-text"><?= h(t('Eigene Regeln für einzelne Arten und Gruppen brauchen die Migration 0143 — sie ist noch nicht eingespielt.')) ?></p>
    <?php else: ?>
    <p class="hint-text"><?= h(t('Hier legst du fest, wie genau der Fundort deiner Funde an die Kartierung geht. Du kannst Gattungen und Arten ganz ausnehmen. Und du kannst Gruppen eine eigene Genauigkeit geben: Rote-Liste-Arten, Speisepilzen, einzelnen Gattungen und Arten. Für alle übrigen Funde gilt die Genauigkeit ganz unten.')) ?></p>
    <?php /* THE COARSEST STEP WINS (27.09.2026: „am gröbsten, am
         vorsichtigsten") — said with the one example that could be read
         the other way, a genus set coarse and one of its species fine. */ ?>
    <p class="hint-text"><?= h(t('Passen auf einen Fund mehrere Regeln, gilt immer die gröbste. Steht zum Beispiel „Boletus" auf Viertelquadrant und „Boletus regius" auf genau, geht auch der Königsröhrling nur auf den Viertelquadranten genau mit.')) ?></p>
    <p class="hint-text"><?= h(t('Genaue Koordinaten sieht nur der Landeskoordinator. Die DGfM veröffentlicht sie nicht.')) ?></p>
    <?php endif; ?>
    <?php /* THE SIZES, ONCE, AT THE TOP (28.09.2026: „schreibe
         dafür oben irgendwo hin, wie groß die Quadrate sind, dass das
         nicht verloren geht"): the rows below carry only the short names
         since they became one line each. */ ?>
    <p class="hint-text"><?= h(t('So groß sind die Stufen:')) ?></p>
    <ul class="hint-text">
        <?php foreach (MYKIS_PRECISIONS as $mkLevel): ?>
            <li><?= h(t($mkLevel['label'])) ?></li>
        <?php endforeach; ?>
    </ul>
    <p class="hint-text small provider-currency"><?= h(t('Die Größen gelten für Hessen; im Norden sind die Blätter etwas schmaler, im Süden etwas breiter.')) ?></p>
    <form method="post" action="<?= h(url('pages/administration.php') . '#dgfm-precision') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="settings">
        <input type="hidden" name="action" value="mykis_rules">
        <?php if ($mkHasRules): ?>
        <?php /* THE ENTER KEY SAVES, and takes along what stands in a field.
             Enter presses the form's FIRST submit button — without this one
             that would be the cross of the first row, and Enter in a field
             below would remove an entry. Moved out of sight rather than
             hidden: a button that is not rendered may not count. */ ?>
        <button type="submit" name="op" value="save" class="offscreen" tabindex="-1" aria-hidden="true"></button>

        <div class="settings-group">
            <?php /* EVERY FILTER STEP NUMBERED, the number first
                 (28.09.2026: „jeden einzelnen Filterschritt durchnummerieren
                 … zuerst die Zahl, dann die Überschrift"). Literal numbers:
                 the six stand together or not at all (0143). */ ?>
            <h3 class="list-label"><span class="step-number">1</span><?= h(t('Nicht melden')) ?></h3>
            <p class="hint-text"><?= h(t('Funde dieser Gattungen und Arten gehen gar nicht an die Kartierung.')) ?></p>
            <p class="sub-label"><?= h(t('Gattungen')) ?></p>
            <?php foreach ($mkRules['exclude']['genera'] as $name): ?>
                <?= $mkRow($name, 'exclude_genera[]', 'drop_exclude_genus', null, null) ?>
            <?php endforeach; ?>
            <?= $mkAdder('add_exclude_genus', t('Gattung eingeben'), $mkTyped['add_exclude_genus'] ?? '') ?>
            <p class="sub-label"><?= h(t('Arten')) ?></p>
            <?php foreach ($mkRules['exclude']['species'] as $name): ?>
                <?= $mkRow($name, 'exclude_species[]', 'drop_exclude_species', null, null) ?>
            <?php endforeach; ?>
            <?= $mkAdder('add_exclude_species', t('Art eingeben'), $mkTyped['add_exclude_species'] ?? '') ?>
        </div>

        <?php /* EACH CATEGORY ITS OWN STEP (28.09.2026: „so wie du es
             bei den einzelnen Gattungen und Arten machst … pro Stufe hinten
             dran eine Auswahl … und wir nicht mehr gemeinsam alles
             verunschärfen"). Until then the ticked categories shared ONE
             step. A row per category, its steps behind it, „wie alle
             übrigen" for none — the categories with a threat or a doubt;
             „*" and „nb" say there is none. */ ?>
        <div class="settings-group">
            <h3 class="list-label"><span class="step-number">2</span><?= h(t('Rote-Liste-Arten')) ?></h3>
            <p class="hint-text"><?= h(t('Wähle für jede Stufe der Roten Liste, wie genau ihre Funde mitgehen.')) ?></p>
            <?php foreach (MYKIS_RED_LIST as $cat): ?>
                <?= $mkStepRow($cat . ' — ' . t(RED_LIST_NAMES[$cat] ?? $cat), 'rl_steps[' . $cat . ']', $mkRules['redlist'][$cat] ?? null, true) ?>
            <?php endforeach; ?>
        </div>

        <div class="settings-group">
            <h3 class="list-label"><span class="step-number">3</span><?= h(t('Speisepilze')) ?></h3>
            <p class="hint-text"><?= h(t('Speisepilz heißt: Die Art steht auf der Speisepilzliste der DGfM. Wer seine Speisepilzstellen nicht verraten möchte, stellt hier eine gröbere Stufe ein.')) ?></p>
            <?= $mkStepRow(t('Alle Speisepilze'), 'edible_step', $mkRules['edible'], true) ?>
        </div>

        <div class="settings-group">
            <h3 class="list-label"><span class="step-number">4</span><?= h(t('Einzelne Gattungen')) ?></h3>
            <p class="hint-text"><?= h(t('Eine eigene Genauigkeit für eine ganze Gattung.')) ?></p>
            <?php $i = 0; foreach ($mkRules['genera'] as $name => $step): ?>
                <?= $mkRow((string) $name, 'genera[' . $i . ']', 'drop_genus', 'genus_step[' . $i++ . ']', $step) ?>
            <?php endforeach; ?>
            <?= $mkAdder('add_genus', t('Gattung eingeben'), $mkTyped['add_genus'] ?? '') ?>
        </div>

        <div class="settings-group">
            <h3 class="list-label"><span class="step-number">5</span><?= h(t('Einzelne Arten')) ?></h3>
            <p class="hint-text"><?= h(t('Eine eigene Genauigkeit für eine Art. Sie gilt auch für ihre Varietäten und Formen.')) ?></p>
            <?php $i = 0; foreach ($mkRules['species'] as $name => $step): ?>
                <?= $mkRow((string) $name, 'species[' . $i . ']', 'drop_species', 'species_step[' . $i++ . ']', $step) ?>
            <?php endforeach; ?>
            <?= $mkAdder('add_species', t('Art eingeben'), $mkTyped['add_species'] ?? '') ?>
        </div>
        <?php endif; ?>

        <?php /* HOW PRECISELY FOR EVERYTHING ELSE (0129, 0130) — the one step
             there was before the rules. The quarter is the default and
             stands first; finer only by one's own hand. ALL STEPS IN SIGHT,
             radios and not a dropdown („sonst sieht man nicht die anderen
             Optionen") — one line since 28.09.2026, like every other step;
             what each measures on the ground stands once at the top. */ ?>
        <div class="settings-group">
            <?php if ($mkHasRules): ?><h3 class="list-label"><span class="step-number">6</span><?= h(t('Alle übrigen Funde')) ?></h3><?php endif; ?>
            <?= $mkStepRow($mkHasRules ? t('Alle übrigen Funde') : t('Alle Funde'), 'precision', $mkPrecision, false) ?>
        </div>

        <div class="button-row right">
            <button class="button subtle" type="submit" name="op" value="save" data-quiet-until-changed><?= icon('save') ?><?= h(t('Speichern')) ?></button>
        </div>
    </form>
    </section>
<?php endif; ?>
</section>
<?php endif; ?>

<section id="section-appearance" role="tabpanel" aria-labelledby="tab-appearance" hidden>
    <?php /* A FOURTH TAB LEVEL (25.09.2026: „ich würde tatsächlich
         noch eine vierte Reiterebene machen bei Oberfläche — Farben und
         Sprache"). Theme and accent fill a page of their own since they
         stand side by side, and the language is a different question.
         tabs.js wires a bar wherever it stands; 'colours' and 'language'
         are unique in the document (the 'species-groups' trap). */ ?>
    <div class="tab" role="tablist" aria-label="<?= h(t('Oberfläche')) ?>">
        <button type="button" role="tab" id="tab-colours" aria-controls="section-colours"
                data-anchor="colours"><?= icon('palette') ?><?= h(t('Farben')) ?></button>
        <button type="button" role="tab" id="tab-language" aria-controls="section-language"
                data-anchor="language"><?= icon('globe') ?><?= h(t('Sprache')) ?></button>
    </div>

    <section id="section-colours" role="tabpanel" aria-labelledby="tab-colours" hidden>
    <?php
    /* THE TILES COME FROM includes/appearance.php — this tab is no
       longer their only home: the excursion guest chooses too, from the
       map (24.08.2026), and two copies of eight themes and
       six accents would drift apart at the first new colour. */
    appearance_picker();
    ?>
    </section>

    <?php /* One block, so no heading over it — the tab already says
         „Sprache". */ ?>
    <section id="section-language" role="tabpanel" aria-labelledby="tab-language" hidden>
    <p class="hint-text">
        <?= h(t('In dieser Sprache zeigt dir YASAIMAPP Knöpfe, Menüs und Hinweise. Sie gilt auf allen deinen Geräten. „Automatisch" nimmt die Sprache deines Geräts.')) ?>
    </p>
    <?php /* A paragraph of its own (25.09.2026): it answers a
         different worry than the sentences above. */ ?>
    <p class="hint-text">
        <?= h(t('Was du selbst einträgst, wird nicht übersetzt: Arten, Substrat, Notizen, die Namen deiner Listen und alles andere. Schreibst du zum Beispiel „Steinpilz" als Art, steht dort auch in der englischen Ansicht „Steinpilz".')) ?>
    </p>
    <?php
    $ownLang = null;
    if (column_exists($pdo, 'map_users', 'lang')) {
        $stmt = $pdo->prepare('SELECT lang FROM map_users WHERE id = ?');
        $stmt->execute([$userId]);
        $ownLang = $stmt->fetchColumn() ?: null;
    }
    ?>
    <form method="post" action="<?= h(url('pages/administration.php')) ?>#language">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="settings">
        <input type="hidden" name="action" value="language">
        <?php /* ALL CHOICES IN SIGHT, radios and not a dropdown
             (25.09.2026: „dass man alle Auswahlmöglichkeiten sieht") — the
             rule from CLAUDE.md. No visible legend: the tab already says
             „Sprache", the group keeps the name for screen readers. */ ?>
        <fieldset class="field" aria-label="<?= h(t('Sprache')) ?>">
            <label class="choice-row">
                <input type="radio" name="lang" value=""<?= $ownLang === null ? ' checked' : '' ?>>
                <?= h(t('Automatisch (wie dein Gerät)')) ?>
            </label>
            <?php foreach (LANGUAGES as $code => $name): ?>
                <label class="choice-row">
                    <input type="radio" name="lang" value="<?= h($code) ?>"<?= $ownLang === $code ? ' checked' : '' ?>>
                    <?= h($name) ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <div class="button-row right">
            <button class="button subtle" type="submit" data-quiet-until-changed><?= icon('save') ?><?= h(t('Speichern')) ?></button>
        </div>
    </form>
    </section>
</section>

<?php /* WHAT ONE RECORDS — and thereby what is suggested and what lies on the
     device. The design of 19.08.2026, and the point of it is that
     one does not choose files but subjects: whoever wants no lichens should
     not have to know which package that is. The consequence follows on its
     own — species.js takes the packages of a switched-off subject off the
     device on the next start and stops fetching them.

     THE CHOICE IS PER DEVICE, like every setting here (settings.js writes
     into local storage). That fits what it does: the offline packages lie
     on this device too. */ ?>
<?php /* The anchor is "species" and not "groups": that one belongs to the
     working groups under "Gemeinsam", and tabs.js finds a target by its
     anchor across the whole page — two of the same name and the wrong
     tab opens, which is exactly what happened on the first try. */ ?>
<section id="section-species-groups" role="tabpanel" aria-labelledby="tab-species-groups" hidden>
    <?php /* ALREADY MAPPED SPECIES FIRST — AT THE TOP, A RULE BELOW
         (19.09.2026: „vielleicht machen wir das einfach als Einschaltoption
         bei den Einstellungen"; begun as an experiment, part of the program
         since 21.09.2026, and the same day: „ganz nach oben, darunter eine
         horizontale Trennlinie zu den eigentlichen Artenlisten" — and not
         „vertraut", which sounded as if one only wanted to map what one
         knows; it is a ranking of what was mapped here and now before).
         THE LABEL SAYS THE ORDER since 25.09.2026 („das trifft
         es mehr wie bereits kartierte Arten zuerst"): „schon gefunden"
         and not „bereits kartiert", because the shared finds count too,
         and tour, season and surroundings as what goes on top — they
         order ONE pool, a species found once far away still comes before
         the reference. A switch of this device, on by default; the
         ranking is familiarSpecies() in map.js, the switch lives in
         species.js. */ ?>
    <label class="checkbox">
        <input type="checkbox" id="familiar-first">
        <span class="group-name"><?= h(t('Schon gefundene Arten zuerst vorschlagen — die von der Tour ganz oben, dann die häufigsten')) ?></span>
    </label>
    <?php /* WHAT THE SWITCH DOES NOT DO, said with examples
         (25.09.2026): the list comes from what is typed, never by itself
         — „man bekommt nicht automatisch eine Liste der Tagesfunde, wenn
         man nichts eingibt". Many in the fungus scene are older and not
         at home with computers, and a sentence that lets them expect a
         list that never comes unsettles them. Body size, not small print:
         this is the explanation the switch is decided by. PLAIN WORDS,
         short sentences (same day: „immer eine ganz einfache
         Sprache"); the exact rule — ±30 days, the find's own day and
         place — stays in familiarSpecies(). The facts are the code's:
         three letters (MIN_CHARS in species.js, the < 3 in map.js),
         every typed word a word start (matches(), familiarSpecies()). */ ?>
    <p class="hint-text"><?= h(t('So kommen die Vorschläge: Du tippst im Titelfeld die ersten Buchstaben eines Namens. Ab dem dritten Buchstaben erscheint eine Liste mit passenden Arten. Solange du nichts tippst, erscheint keine Liste.')) ?></p>
    <p class="hint-text"><?= h(t('Ein Beispiel: Du tippst „bol". Dann siehst du Arten wie Boletus edulis und Boletus reticulatus. Tippst du „bol edu" — mit einem Leerzeichen dazwischen —, bleibt fast nur noch Boletus edulis übrig.')) ?></p>
    <p class="hint-text"><?= h(t('Mit diesem Haken stehen oben die Arten, die du schon gefunden hast oder die andere gefunden und mit dir geteilt haben. Zuerst kommen die Arten von heute und von deiner laufenden Tour. Dann Arten, die zur gleichen Jahreszeit gefunden wurden, egal in welchem Jahr. Dann Arten aus der Umgebung, bis 20 Kilometer um den Fundort. Was oft gefunden wurde, steht vor Seltenem. Darunter folgen alle anderen passenden Arten.')) ?></p>
    <hr class="section-rule">
    <?php /* THE GROUPS ARE CHOSEN AT THE LISTS since 12.11.2026
         (migration 0195): „Was enthält diese Liste?" in the window „Liste
         bearbeiten" says what its „Art" suggests, and the device keeps the
         packages its lists need. What stays here is where the names come
         from — the sources and what each holds, to read, no longer to tick. */ ?>
    <div class="column-header">
        <h3><?= h(t('Woher die Namen kommen')) ?></h3>
    </div>
    <p class="hint-text">
        <?= h(t('Welche Namen du bei „Art" vorgeschlagen bekommst, legst du bei jeder Liste fest: im Fenster „Liste bearbeiten" unter „Was enthält diese Liste?". Die Namen dieser Gruppen kommen dann von selbst aufs Gerät.')) ?>
    </p>

    <?php
    /* THE SWITCHES COME OUT OF THE PACKAGE REGISTRY, not out of this file.
       Three subjects plus the animal groups, and a new one there appears
       here by itself. Written out, the two lists would have drifted apart
       at the first addition — the mistake this project has already made
       eight times in other places. */
    require_once __DIR__ . '/species_packages.php';
    require_once __DIR__ . '/shapes.php';

    /* The shape per subject — the same drawings the lists can carry as
       their marker (shapes.php). No second set of icons: the map shapes
       ARE the picture of these subjects, and two sets of the same motif
       drift apart — which is the very reason the library exists.

       NAME AND SHAPE COME FROM THE REGISTRY, not from here. They stood
       written out for the three non-animal subjects while the eight animal
       ones came from ANIMAL_GROUPS — as long as only this tab needed them,
       that went unnoticed. Since the suggestion list shows the same shape,
       it would be two sets of the same knowledge. What stays here is only
       what really holds only here: where the names come from. */
    $subjects = [];
    foreach (['fungi'    => t('Referenzliste der DGfM und die weltweite GBIF-Liste'),
              'lichens' => t('Rote Liste der Flechten Deutschlands, BfN; deutsche Namen aus GBIF'),
              'plants' => t('Rote Liste der Farn- und Blütenpflanzen Deutschlands, BfN')]
             as $key => $source) {
        $subjects[$key] = [species_group_label($key), t('Quelle: %s', $source), species_group_shape($key)];
    }
    foreach (ANIMAL_GROUPS as $key => $group) {
        // WHAT IS IN IT IS COUNTED, NOT CLAIMED. With the named groups the
        // name says it already; „Übrige Tiere" by contrast is a catch-all,
        // and a switch that does not name itself is one nobody dares to
        // press (19.08.2026).
        // The line says what it is: „Quelle:" where it names where the
        // names come from, „Darin:" where it lists what the catch-all
        // holds — one prefix for both read the contents as a source.
        $content = species_group_contents($pdo, $key);
        $source = $key === 'other_animals' && $content !== []
            ? t('Darin: %s', implode(', ', array_map(
                static fn(array $x): string => $x['name'] . ' ' . number_format($x['total'], 0, ',', '.'),
                $content)))
            : t('Quelle: %s', t('Museum für Naturkunde Stuttgart und Fauna Europaea'));
        $subjects[$key] = [species_group_label($key), $source, species_group_shape($key)];
    }
    ?>
    <?php
    /* AND WHAT EACH ONE HOLDS, IN NUMBERS (13.09.2026: „bitte
       noch eine Anzahl, wie viel wissenschaftliche Namen, wie viel
       Synonyme und wie viel deutsche Namen jeweils in der Liste ist").
       One line per source list, named where a subject has two — the
       lists overlap, and a sum would count a species twice
       (species_subject_counts). */
    $counts = species_subject_counts($pdo);
    ?>
    <div class="group-choice">
        <?php foreach ($subjects as $key => [$name, $source, $form]): ?>
        <div class="checkbox group-source">
            <span class="group-name"><?= shape_point($form, 'currentColor') ?><?= h($name) ?></span>
            <span class="hint-text small"><?= h($source) ?></span>
            <?php
            // Two empty lists say it once, not once each.
            $lines = $counts[$key] ?? [];
            $empty = array_sum(array_map(static fn(array $c): int => $c['names'] + $c['synonyms'], $lines)) === 0;
            if ($lines !== [] && $empty): ?>
            <span class="hint-text small group-count"><?= h(species_counts_line($lines[0])) ?></span>
            <?php else: foreach ($lines as $c): ?>
            <span class="hint-text small group-count">
                <?= count($lines) > 1 ? h($c['source']) . ': ' : '' ?><?= h(species_counts_line($c)) ?>
            </span>
            <?php endforeach; endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php
    // Species no list knows yet — a block of their own below the groups.
    require_once __DIR__ . '/working_names_content.php';
    working_names_render($pdo, $userId);
    ?>

</section>

<?php
/* THE PAIRING ADDRESS, ABSOLUTE. A QR code is read by a camera that knows
   nothing of this page, and a deep link is handed to an app that knows
   nothing of it either — a path without a host leads both nowhere. Same
   construction as the excursion's join address. */
$base = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url();

/* WHICH OF THESE IS THE BROWSER I AM SITTING IN (26.08.2026).
   Three rows saying „Chrome auf macOS" and no way to tell them apart —
   that is a list one does not dare to act on, and a device list exists
   for exactly one purpose: throwing something out.

   THE COMPARISON HAPPENS IN THE DATABASE, and that is the point of doing
   it this way round. The cookie carries the token itself, the table only
   its hash (auth.php says why). Hashing it here and letting SQL compare
   means the stored hashes never travel into this page's memory at all —
   the answer that comes back is a yes or a no, nothing that could be
   printed by accident.

   AN EMPTY STRING WHERE THERE IS NO COOKIE, deliberately not the hash of
   the empty string: token_hash is always 64 hex characters, so '' can
   never match anything, while hash('') is a real value that a row could
   in principle carry. Whoever visits without „Angemeldet bleiben" has no
   token here, and then nothing is marked — which is the truth and is
   said out loud under the table. */
$currentToken = persistent_cookie_value();
$currentHash  = $currentToken !== '' ? hash('sha256', $currentToken) : '';

// The own names of browsers need 0145; before it the pencil stands only
// at the apps, whose names live in the device column.
$hasNames = column_exists($pdo, 'map_tokens', 'name');
$devices = [];
$stmt = $pdo->prepare(
    'SELECT id, device, ' . ($hasNames ? 'name' : 'NULL AS name') . ', created_at, last_used_at, valid_until,
            (token_hash = ?) AS is_current
       FROM map_tokens WHERE user_id = ? ORDER BY created_at DESC'
);
$stmt->execute([$currentHash, $userId]);
$devices = $stmt->fetchAll();

$knowsItself = false;
foreach ($devices as $d) {
    if ((int) $d['is_current'] === 1) { $knowsItself = true; break; }
}
?>

<section id="section-devices" role="tabpanel" aria-labelledby="tab-devices" hidden>
    <?php
    /* APPS AND BROWSERS APART (28.09.2026: „kann man hier
       unterscheiden zwischen wirklich gekoppelten Geräten mit der App und
       einfach nur einem Anmeldungsspeicher über den Browser"). The label
       says which: a pairing writes „App: <name>" (device_pair), „Angemeldet
       bleiben" writes „Browser: <user agent>" (login). Anything else is a
       named device, so it stands with the apps. */
    $mkApps = array_values(array_filter($devices, static fn(array $d): bool => !str_starts_with((string) $d['device'], 'Browser: ')));
    $mkBrowsers = array_values(array_filter($devices, static fn(array $d): bool => str_starts_with((string) $d['device'], 'Browser: ')));
    $appName = static fn(array $d): string => preg_replace('/^App: /', '', (string) ($d['device'] ?: t('Ohne Namen')));
    $deviceRow = static function (array $d, bool $isBrowser) use ($appName, $hasNames): string {
        $here = (int) $d['is_current'] === 1;
        $auto = $isBrowser ? device_label((string) $d['device']) : '';
        $own  = trim((string) ($d['name'] ?? ''));
        $name = $isBrowser ? ($own !== '' ? $own : $auto) : $appName($d);
        $dates = [$isBrowser
            ? t('angemeldet %s', date('d.m.Y', strtotime((string) $d['created_at'])))
            : t('gekoppelt %s', date('d.m.Y', strtotime((string) $d['created_at'])))];
        $dates[] = $d['last_used_at'] !== null
            ? t('zuletzt gesehen %s', date('d.m.Y', strtotime((string) $d['last_used_at'])))
            : t('noch nie benutzt');
        // SAID OUT LOUD, not left out: „läuft nicht ab" is a statement one
        // can act on — the reason this list has a way out at all
        // (auth.php, token_create).
        $dates[] = $d['valid_until'] !== null
            ? t('läuft ab %s', date('d.m.Y', strtotime((string) $d['valid_until'])))
            : t('läuft nicht ab');
        // A name of one's own goes in front; the automatic one moves into
        // the grey line, so one still sees which browser it is.
        if ($isBrowser && $own !== '') {
            array_unshift($dates, $auto);
        }
        /* THE PENCIL hands its window what that window needs to say — the
           name, the field's example, and for a browser the label an empty
           field brings back (administration.js, data-device-rename). */
        $pencil = ($isBrowser && !$hasNames) ? '' :
            '<button type="button" class="button subtle small" data-device-rename'
            . ' data-id="' . (int) $d['id'] . '"'
            . ' data-name="' . h($isBrowser ? $own : $appName($d)) . '"'
            . ' data-placeholder="' . h($isBrowser ? t('z. B. Tablet') : t('z. B. Handy Xiaomi')) . '"'
            . ' data-hint="' . h($isBrowser
                ? t('Den Namen siehst nur du. Lässt du das Feld leer, steht hier wieder „%s".', $auto)
                : t('Den Namen siehst nur du. So findest du das Gerät in der Liste wieder.')) . '"'
            . ($isBrowser ? '' : ' data-required')
            . ' aria-label="' . h(t('Namen ändern')) . '">' . icon('pencil') . '</button>';
        /* THE CURRENT BROWSER GETS ITS OWN SENTENCE, because the general
           one would be a lie here: one is sitting in it and stays logged
           in — the session carries this visit, the token only the next
           one (auth.php). */
        $confirm = $here
            ? t('Du bist gerade in diesem Browser. Du bleibst angemeldet, bis du ihn schließt. Danach musst du dein Passwort wieder eingeben.')
            : ($isBrowser
                ? t('„%s" wird abgemeldet. Dort musst du dein Passwort wieder eingeben.', $name)
                : t('„%s" kommt danach nicht mehr an deine Funde. Was es schon hochgeladen hat, bleibt.', $name));
        $title = $here ? t('Diesen Browser abmelden?') : ($isBrowser ? t('Browser abmelden?') : t('Gerät trennen?'));
        // The window's button says the deed, not „OK" — the same word as
        // the row's button, so the click one confirms is the one one made.
        $verb = $isBrowser ? t('Abmelden') : t('Trennen');
        return '<tr' . ($here ? ' class="is-current"' : '') . '><td>'
            // The mark goes on the name: it answers „which one is me".
            . '<strong class="import-row-name">' . h($name)
            . ($here ? '<span class="badge-here">' . h(t('dieser Browser')) . '</span>' : '') . '</strong>'
            . '<span class="item-data import-row-data">' . h(implode(' · ', $dates)) . '</span></td>'
            . '<td class="button-cell">' . $pencil . '<form method="post" action="' . h(url('pages/administration.php')) . '#devices"'
            . ' data-confirm="' . h($confirm) . '" data-confirm-title="' . h($title) . '"'
            . ' data-confirm-button="' . h($verb) . '" data-confirm-danger>'
            . csrf_field()
            . '<input type="hidden" name="section" value="settings">'
            . '<input type="hidden" name="action" value="device_remove">'
            . '<input type="hidden" name="id" value="' . (int) $d['id'] . '">'
            // The DOOR, not the bin: the device steps out, what it recorded
            // stays (CLAUDE.md). „Abmelden" for a browser — that is what one
            // did there —, „Trennen" for an app.
            . '<button type="submit" class="button subtle small danger">' . icon('sign-out')
            . h($verb) . '</button></form></td></tr>';
    };
    ?>
    <?php /* IN PLAIN WORDS (text pass, 28.09.2026): what the pairing is for, and
         the one thing the app's side taught — it asks for the key at its
         very first start, before the map, and the login to the map is a
         second, separate step (the app session: „Anmeldung für die Karte und
         Kopplung für das Hochladen der Wegstrecken sind zwei getrennte
         Dinge"). */ ?>
    <p class="hint-text"><?= h(t('Hier koppelst du die YASAIMAPP-App auf deinem Handy mit deinem Konto. Erst dann lädt die App deine aufgezeichneten Wege hoch. Sie zeichnet auch auf, wenn der Bildschirm aus ist.')) ?></p>
    <p class="hint-text"><?= h(t('Öffnest du die App zum ersten Mal, fragt sie nach einem Schlüssel. Den bekommst du hier über „Gerät hinzufügen". An der Karte meldest du dich in der App danach ganz normal an.')) ?></p>

    <?php /* ONE BLOCK FOR THE APPS, the adding in its head
         (28.09.2026: „Gerät koppeln und gekoppelte Geräte in einer Überschrift
         zusammenpacken … einfach irgendwo Gerät hinzufügen, und dann kommt
         erst der Name, und über ein eigenes Fenster dann die
         Koppelmöglichkeiten"). The head carries its button like the offline
         areas' „Neu". */ ?>
    <div class="column-header">
        <h3><?= h(t('Gekoppelte Apps')) ?></h3>
        <button type="button" class="button small" data-open-window="device-add-window"><?= icon('plus') ?><?= h(t('Gerät hinzufügen')) ?></button>
    </div>
    <?php if ($mkApps === []): ?>
        <p class="hint-text"><?= h(t('Noch keine App gekoppelt.')) ?></p>
    <?php else: ?>
        <div class="table-frame">
            <table class="list"><tbody>
            <?php foreach ($mkApps as $d): ?><?= $deviceRow($d, false) ?><?php endforeach; ?>
            </tbody></table>
        </div>
    <?php endif; ?>

    <div class="column-header">
        <h3><?= h(t('Angemeldete Browser')) ?></h3>
    </div>
    <p class="hint-text"><?= h(t('Browser, in denen du beim Anmelden „Angemeldet bleiben" gewählt hast.')) ?></p>
    <?php if ($hasNames && $mkBrowsers !== []): ?>
        <?php /* THE WAY TO TELL THEM APART (28.09.2026): a browser
             says too little about itself to be recognised from far away,
             but on the device itself its row is marked — so one names it
             there. Only where there is a row and a pencil to use. */ ?>
        <p class="hint-text"><?= h(t('Öffnest du diese Seite auf einem Gerät, steht bei seinem Eintrag „dieser Browser". Mit dem Stift gibst du ihm dann einen Namen, zum Beispiel „Tablet".')) ?></p>
    <?php endif; ?>
    <?php if ($mkBrowsers === []): ?>
        <p class="hint-text"><?= h(t('Keiner.')) ?></p>
    <?php else: ?>
        <div class="table-frame">
            <table class="list"><tbody>
            <?php foreach ($mkBrowsers as $d): ?><?= $deviceRow($d, true) ?><?php endforeach; ?>
            </tbody></table>
        </div>
    <?php endif; ?>
    <?php if (!$knowsItself): ?>
        <?php /* A STATE, so it stays in the flow (CLAUDE.md): without it a list
             in which nothing is marked looks like the marking is broken. */ ?>
        <p class="hint-text small"><?= h(t('Dieser Browser steht nicht in der Liste, weil du dich ohne „Angemeldet bleiben" angemeldet hast. Schließt du ihn, bist du abgemeldet.')) ?></p>
    <?php endif; ?>

    <?php /* WINDOW 1: THE NAME. A post like before (device_pair); „Abbrechen"
         closes the window without sending — a plain button, because Enter
         in the name field presses the first SUBMIT button of the form
         (administration.js, data-close-window). */ ?>
    <dialog class="dialog pinned-footer" id="device-add-window">
        <form method="post" action="<?= h(url('pages/administration.php')) ?>#devices">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="settings">
            <input type="hidden" name="action" value="device_pair">
            <h2 class="dialog-title"><?= h(t('Gerät hinzufügen')) ?></h2>
            <div class="field">
                <label for="device-name"><?= h(t('Name des Geräts')) ?></label>
                <?php /* „Handy Xiaomi" and not „Pixel im Rucksack"
                     (28.09.2026): nobody writes where a phone lies, and not
                     everybody knows that „Pixel" is Google's phone. */ ?>
                <input type="text" id="device-name" name="device" maxlength="60" required
                       placeholder="<?= h(t('z. B. Handy Xiaomi')) ?>">
                <p class="hint-text small"><?= h(t('Den Namen siehst nur du. So findest du das Gerät in der Liste wieder.')) ?></p>
            </div>
            <div class="dialog-footer">
                <button type="button" class="button subtle" data-close-window><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button"><?= icon('device') ?><?= h(t('Koppeln')) ?></button>
            </div>
        </form>
    </dialog>

    <?php /* THE PENCIL'S WINDOW, one for every row of both lists: the pencil
         fills in id, name, example and the sentence under the field
         (administration.js, data-device-rename), so the save button takes
         its measure when the window opens. */ ?>
    <dialog class="dialog pinned-footer" id="device-rename-window">
        <form method="post" action="<?= h(url('pages/administration.php')) ?>#devices">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="settings">
            <input type="hidden" name="action" value="device_rename">
            <input type="hidden" name="id" value="">
            <h2 class="dialog-title"><?= h(t('Namen ändern')) ?></h2>
            <div class="field">
                <label for="device-rename-name"><?= h(t('Name')) ?></label>
                <input type="text" id="device-rename-name" name="name" maxlength="60">
                <p class="hint-text small" data-rename-hint></p>
            </div>
            <div class="dialog-footer">
                <button type="button" class="button subtle" data-close-window><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button subtle" data-quiet-until-changed="open"><?= h(t('Speichern')) ?></button>
            </div>
        </form>
    </dialog>

    <?php if ($state['token'] !== null): ?>
        <?php
        /* WINDOW 2: THE KEY, SHOWN EXACTLY ONCE, and the window says so.
           Only the hash is stored (token_create), so no page can ever show
           this value again — not even this one after a reload. It opens by
           itself (dialog[data-open-at-load], administration.js); the tab
           stays behind it with the new device already in the list. */
        $pairUrl = 'yasaimapp://pair?url=' . rawurlencode($base)
                 . '&token=' . rawurlencode($state['token']);
        ?>
        <dialog class="dialog pair-window" id="pair-window" data-open-at-load>
            <?php /* method="dialog" only for the window's frame and spacing
                 (.dialog form); nothing in it is sent. */ ?>
            <form method="dialog">
            <h2 class="dialog-title"><?= h(t('„%s" koppeln', (string) $state['device'])) ?></h2>
            <p class="hint-text"><strong><?= h(t('Übertrage den Schlüssel jetzt in die App.')) ?></strong>
                <?= h(t('Er erscheint nur dieses eine Mal. Geht er verloren, fügst du das Gerät einfach neu hinzu.')) ?></p>
            <p class="hint-text"><?= h(t('Wähle einen der drei Wege:')) ?></p>
            <div class="pair-ways">
                <?php /* THE CAMERA DOES NOT OPEN THE LINK on the Xiaomi: its
                     scanner offers only „Text kopieren" for anything but
                     http(s), and the app reads the link from the clipboard —
                     the words are the app's own (the app session,
                     28.09.2026). A scanner that does open custom links leads
                     straight to the filled-in pairing screen, hence the
                     second step's „Öffnet sie die App nicht von selbst". */ ?>
                <div class="pair-way">
                    <h4><?= icon('device') ?><?= h(t('Diese Seite ist am Rechner offen')) ?></h4>
                    <ol class="hint-text small pair-steps">
                        <li><?= h(t('Scanne den QR-Code mit der Kamera deines Handys.')) ?></li>
                        <li><?= h(t('Öffnet sie die App nicht von selbst, wähle „Text kopieren".')) ?></li>
                        <li><?= h(t('Öffne YASAIMAPP und tippe auf „Link aus der Zwischenablage einfügen".')) ?></li>
                    </ol>
                    <div class="excursion-qr-code" id="pair-qr"
                         data-lib="<?= h(url('assets/vendor/qrcode/qrcode.js')) ?>"
                         data-link="<?= h($pairUrl) ?>"></div>
                </div>
                <div class="pair-way">
                    <h4><?= icon('link') ?><?= h(t('Diese Seite ist am Handy offen')) ?></h4>
                    <p class="hint-text small"><?= h(t('Tippe auf „In der App öffnen". Passiert nichts, ist die App noch nicht installiert.')) ?></p>
                    <p><a class="button" href="<?= h($pairUrl) ?>"><?= h(t('In der App öffnen')) ?></a></p>
                </div>
                <div class="pair-way">
                    <h4><?= icon('copy') ?><?= h(t('Von Hand')) ?></h4>
                    <p class="hint-text small"><?= h(t('Tippe Adresse und Schlüssel in die App ein, oder kopiere sie hinüber.')) ?></p>
                    <label class="hint-text small" for="pair-base"><?= h(t('Adresse')) ?></label>
                    <input type="text" id="pair-base" class="pair-value" readonly value="<?= h($base) ?>">
                    <label class="hint-text small" for="pair-token"><?= h(t('Schlüssel')) ?></label>
                    <input type="text" id="pair-token" class="pair-value" readonly value="<?= h((string) $state['token']) ?>">
                    <div class="button-row">
                        <button type="button" class="button subtle small" data-copy="#pair-token"><?= icon('copy') ?><?= h(t('Schlüssel kopieren')) ?></button>
                    </div>
                </div>
            </div>
            <?php /* A DOOR, NOT A CLOSE: the page under the window is the answer
                 to a post, and a reload would pair a second time. „Fertig"
                 leaves it for a plain address — with the parameter, or the
                 browser would answer the click with nothing at all. */ ?>
            <div class="dialog-footer">
                <a class="button" href="<?= h(url('pages/administration.php')) ?>?paired=1#devices"><?= h(t('Fertig')) ?></a>
            </div>
            </form>
        </dialog>
    <?php endif; ?>
</section>

<?php if ($fundbuch): ?>
<section id="section-fundbuch" role="tabpanel" aria-labelledby="tab-fundbuch" hidden>
    <?php publication_render($pdo, $userId); ?>
</section>
<?php endif; ?>

<script type="module">
/* BY THE BARE PATH, DELIBERATELY. Every module imports these files by
   their bare name (tabs.js says './settings.js'), and the same file under
   two URLs is the same module TWICE — each with its own state. The
   settings bundle then lived in two caches whose delayed writes overwrote
   each other: the healing write-back below landed in storage and was
   reverted moments later by the stale twin. Found 22.08.2026 while
   measuring exactly that write-back. Since 30.09.2026 the page's import
   map (asset_import_map) turns the bare path into the versioned one —
   for these lines and for the modules alike, so it is still one address
   and now a fresh one. See trap five in CLAUDE.md. */
import { settings } from '<?= h(url('assets/js/settings.js')) ?>';
import { prompt } from '<?= h(url('assets/js/dialogs.js')) ?>';
import { wireAppearance } from '<?= h(url('assets/js/appearance.js')) ?>';

/* Name and e-mail address now ALWAYS demand the password, via a modal
   instead of an open field on the page (the maintainer wanted both merged and
   the confirmation as a modal, 14.08.2026). 'bestaetigt' prevents the
   requestSubmit() below from opening the confirmation a second time — the
   same trick as wireForms() in dialogs.js. */
const personForm     = document.getElementById('settings-person-form');
const personPassword = document.getElementById('settings-person-password');

personForm.addEventListener('submit', async (e) => {
    if (personForm.dataset.confirmed === '1') {
        personForm.dataset.confirmed = '';
        return;
    }
    e.preventDefault();

    /* The texts come through PHP's t(), not i18n.js — this script is
       rendered by the page, so the language is already decided here. */
    const password = await prompt({
        title: <?= json_encode(t('Bitte dein Passwort')) ?>,
        text: <?= json_encode(t('Zur Sicherheit: Gib dein Passwort ein. Dann werden Name und E-Mail-Adresse gespeichert.')) ?>,
        label: <?= json_encode(t('Passwort')) ?>,
        type: 'password',
        autocomplete: 'current-password',
        button: <?= json_encode(t('Speichern')) ?>,
    });
    if (password === null || password === '') return;

    personPassword.value = password;
    personForm.dataset.confirmed = '1';
    // requestSubmit instead of submit(), so the required fields (first
    // name, e-mail) still get checked — just like wireForms().
    personForm.requestSubmit();
});

/* Theme and accent: the tiles come from includes/appearance.php, the
   wiring from assets/js/appearance.js — both shared with the guest's
   window on the map, so a retired colour name is healed in ONE place. */
wireAppearance({
    themeDefault:  <?= json_encode(THEME_DEFAULT) ?>,
    accentDefault: <?= json_encode(ACCENT_DEFAULT) ?>,
});

/* THE FAMILIAR-FIRST SWITCH, through species.js, so the name of the key
   stands in ONE place. The group ticks that stood here went to the lists
   on 12.11.2026 (migration 0195). Without ?v= for the same reason as the
   imports at the top: species.js itself imports './settings.js' bare, and
   a versioned twin of it would bring a second settings instance in. */
const familiarBox = document.getElementById('familiar-first');
if (familiarBox) {
    const species = await import('<?= h(url('assets/js/species.js')) ?>');
    familiarBox.checked = species.familiarFirst();
    familiarBox.addEventListener('change', () => species.setFamiliarFirst(familiarBox.checked));
}
</script>
    <?php
}
