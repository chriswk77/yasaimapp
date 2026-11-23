/**
 * The Belege of a find — the gallery of its pictures (the voucher photos
 * and the cloud recordings, in one order), what is done at it (choosing
 * files for the cloud, taking a photo, naming the subject of each, putting
 * the pictures in order by dragging, the originals), and the sequences
 * hung on it (the second voucher, text not picture).
 *
 * OUT OF map.js ON 12.10.2026, because the Fundbrowser's window wants its
 * Belege tab too (on the first round with the cockpit: „was ich
 * bei den Bildern vermisse, ist die Upload-Funktion … dass ich die
 * Reihenfolge der Vorschaubilder umsortieren kann … Mir fehlt die Funktion
 * für die DNS"). The way pointwindow.js left it: moved as it stood, found by
 * the scope analysis of map.js's top level; mountPhotoGallery(host) hands
 * back drawPhotoGallery and drawSequences, which map.js uses under their
 * old names.
 *
 * THE HOST: start, state, GUEST, the element sets (photoFields,
 * sequenceFields), what the point window gives (detailPhotoTab, detailState,
 * mayEditPointPhotos, foreignPoint), what the viewer, the plates and the
 * files block give (showPhoto, showCloudPhoto, cloudPhotoUrl, cloudBytes,
 * measureStyle, cloudFilesShown, plates, pointFiles), and drawList, status
 * and pointReady of the page around it.
 */

import { t } from './i18n.js';
import { api, request } from './api.js';
import { confirm } from './dialogs.js';
import * as photos from './photos.js';
import * as galleryOriginals from './galleryoriginals.js';
import * as formState from './formstate.js';
import { icon } from './icons.js';
import { sortableGallery, subjectGroups } from './gallerysort.js';
import { readableDate } from './dates.js';
import { mountMicroscope } from './microscope.js';
import { developThumb } from './develop.js';
import { settings } from './settings.js';
import * as imageZoom from './imagezoom.js';
import * as exif from './exif.js';
import { subjectIcon } from './subjecticons.js';
import { vocab, vocabStored } from './vocabulary.js';
import { hoverPick } from './hoverpick.js';
import { personName } from './names.js';

/** The sequence as FASTA — the same shape the server writes (sequence_fasta). */
export function sequenceFasta(s) {
    const head = s.label || [s.marker, s.accession].filter(Boolean).join(' ') || t('Sequenz');
    return '>' + head + '\n' + (s.bases.match(/.{1,70}/g) ?? []).join('\n') + '\n';
}

/**
 * The sequence as a FASTA file to save — from the bases the page holds, no
 * request. The Fundbrowser's overview offers the same download
 * (finds.js, drawFace), so it lives out here and not in the gallery.
 */
export function saveSequenceFasta(s) {
    const blob = new Blob([sequenceFasta(s)], { type: 'text/plain' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = ((s.label || s.marker || 'sequenz').replace(/[^\w.-]+/g, '_') || 'sequenz') + '.fasta';
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

export function mountPhotoGallery(host) {
    const {
        GUEST,
        cloudBytes,
        cloudFilesShown,
        cloudPhotoUrl,
        detailPhotoTab,
        detailState,
        foreignPoint,
        mayEditPointPhotos,
        measureStyle,
        photoFields,
        plates,
        pointFiles,
        sequenceFields,
        showCloudPhoto,
        showPhoto,
        start,
        state,
        drawList,
        pointReady,
        status,
    } = host;

    // ---------------------------------------------------------------------------
    // The cloud half of the gallery
    //
    // WHY THIS IS NOT A SECTION OF ITS OWN ANYMORE (18.08.2026). Two
    // blocks under each other read like two systems, and they are not: both are
    // pictures of the same find. What differs is only where they lie, and that
    // follows from which button was pressed — "Aufnehmen" is the phone camera and
    // stays here on the server as a note, "Auswählen" is the finished file and
    // goes into the user's own storage in full resolution.
    //
    // The server decides what can be filed at all, not this file: what counts as
    // a usable name is a rule out of several sources, and a second copy of it in
    // JavaScript would drift apart from the first day.
    // ---------------------------------------------------------------------------

    /** What the server says about this point — or null when there is no cloud. */
    async function cloudStateFor(pointId) {
        if (!(pointId > 0)) return null;
        /* A FOREIGN FIND IS ASKED WHATEVER MY OWN CLOUD SAYS (14.09.2026). The
           recordings on it are the holder's and come with his token through
           cloud_thumb.php; whoever may see the find may see them (the rule
           of 23.08.2026, which until now reached only the voucher photos).
           Without this line a friend without a cloud of his own never even
           asked, and one with a cloud was turned away at the server. An own
           point without a cloud has nothing to list. */
        if (start.cloudConnected !== true && !foreignPoint(pointId)) return null;
        try {
            return await request(`cloud.php?id=${pointId}&action=files`, { method: 'POST' });
        } catch {
            // Not reaching the cloud is not an error of this window: the point
            // stays editable, only the recordings are missing.
            return { files: [], filable: false, unreachable: true };
        }
    }

    /** One recording in the user's cloud, as a tile in the same grid. */
    function cloudTile(f) {
        const box = document.createElement('a');
        box.className = 'cloud-file';
        /* NO TOOLTIP (13.11.2026: „in der Thumbnail-Leiste bekomme
           ich einen Tooltip, wenn ich über einem Bild stehen bleibe. Das will
           ich nicht"). Name and size stood here as a title since 28.08.2026;
           a pause over the strip laid a box over the next picture. The name
           stays for a screen reader only, and the viewer's fact line and
           its Info tab say name and size where one asks for them. */
        box.setAttribute('aria-label', f.name);

        /* IT STAYS A LINK, AND IT LEADS TO THE PICTURE ITSELF — but a click
           opens our own window instead of following it.

           It used to point at the provider's start page. That was one step too
           far for merely wanting to look: whoever taps a picture wants it
           bigger, not a foreign site with a login (19.08.2026). The
           window above the map does that, and the way to the original is still
           there, in its footer.

           The href is not decoration. Middle click and "open in new tab" then
           give the large preview on OUR OWN origin — no detour through the
           provider, and no address that only works for whoever asked for it
           (see pages/cloud_thumb.php). */
        box.href = cloudPhotoUrl(f);
        box.addEventListener('click', (e) => {
            e.preventDefault();
            showCloudPhoto(f);
        });

        // The name is what the tile falls back to, in both directions: when
        // there is no preview link at all, and when the link there is does not
        // load. A broken-image symbol says nothing anybody can act on.
        const name = document.createElement('span');
        name.className = 'cloud-file-name';
        name.textContent = f.name;

        if (f.preview) {
            const img = document.createElement('img');

            // Our own address (pages/cloud_thumb.php) — the provider is asked
            // by the server, never by the browser, so no referer policy is
            // needed any more against its hotlink protection.
            img.alt = f.name;
            img.loading = 'lazy';
            // As „Bearbeiten" left it — the viewer refreshes it after an edit (develop.js).
            developThumb(img, f.preview, f.develop, 'cloud:' + f.id);

            // Thumbnails are generated by the provider, and not necessarily by
            // the time the upload's answer comes back. Whoever looks again in a
            // minute gets the picture; until then the name stands there and
            // says so, instead of a broken frame that looks like a lost file.
            img.addEventListener('error', () => {
                img.remove();
                // THE TWO CASES MUST NOT SOUND ALIKE. The provider handed out an
                // address and the picture behind it did not come — that is a
                // different story from "there is no address", and only the
                // wording tells them apart afterwards.
                name.title = t('Für diese Aufnahme gibt es keine Vorschau — die Datei selbst liegt in der Cloud.');
                box.append(name);

                // The reason stands in the server's error log; from here one
                // cannot see it. This line at least says which recording it was.
                console.warn('Vorschau nicht ladbar:', f.name, f.preview);
            }, { once: true });

            box.append(img);
        } else {
            // The server names an address for every recording these days, so
            // this is the case of a row it cannot serve at all. The name says
            // more than an empty grey square.
            box.append(name);
        }
        return box;
    }

    /**
     * Say whether "Auswählen" can do anything — and if not, what is missing.
     *
     * DISABLED WITH A REASON, not hidden. A button that is simply gone leaves
     * the question of whether the function exists at all; one that says why it
     * cannot be pressed answers it. And every one of these reasons is
     * removable by the user.
     */
    function setChooseState(point, cloud) {
        const button = photoFields.choose;
        if (!button) return '';

        /* THE GUEST GETS NO REASON, because he gets no button (stage 6a hides
           it): the sentence below sends him to „Mein Bereich → Cloud", a
           room he cannot enter — an instruction nobody can follow is worse
           than silence (the maintainer saw it standing there, 24.08.2026). His
           photos lie on the server with his finds; there is no cloud in his
           path at all. */
        if (GUEST) return '';

        /* „Auswählen" uploads from the browser straight into the cloud — a
           second release beside the voucher photos' (cloud.php, the two
           lists): a cloud may already take the photos the server pushes
           before the browser can upload into it. */
        /* THE REASON NAMES THE BUTTON BY ITS OWN WORD (14.11.2026):
           in the map's point window it says „Auswählen", in the Fundbrowser
           „Hochladen" (finds.js renames it), and a hint speaking of a button
           that is not there sends one looking for it. The word is read off
           the button — its title where finds.js put the word into a span
           that may be folded away, else its text — already translated. */
        const word = (button.title || button.textContent).trim();
        if (start.cloudUpload !== true) {
            button.disabled = true;
            return start.cloudConnected === true
                ? t('„%s" geht mit deiner Cloud noch nicht. Fotos über „Aufnehmen" landen schon dort.', word)
                : t('„%s" braucht eine verbundene Cloud — Mein Bereich → Cloud.', word);
        }
        if (!(point?.id > 0)) {
            button.disabled = true;
            return t('„%s" geht, sobald der Punkt übertragen ist.', word);
        }
        if (cloud?.unreachable) {
            button.disabled = true;
            return t('Die Cloud ist gerade nicht erreichbar.');
        }
        // NOTHING ABOUT THE FIND STANDS IN THE WAY ANY MORE. Until 19.08.2026 a
        // point without a genus was refused here, because the folders are
        // taxonomic and a nameless find fits nowhere in them. That was
        // backwards: a picture of something one cannot name is exactly the one
        // worth keeping. It goes to "Unbestimmt" and moves on by itself once
        // the name is there — see includes/photo_cloud.php.

        button.disabled = false;
        return '';
    }

    // As long as the table lacks the columns from migration 0031, the server
    // cannot accept photos. Then only "Aufnehmen" goes — a button that can only
    // produce an error message is worse than none.
    //
    // NOT THE WHOLE SECTION ANYMORE. Since the cloud lives in this block too,
    // hiding it would take away the storage that works perfectly well without
    // migration 0031 — the recordings never touch this server.
    if (start.photos === false) {
        photoFields.capture?.setAttribute('hidden', '');
    }

    /* THE GUEST'S PHOTO WORLD (stage 6a): "Auswählen" never — it takes a
       file from a connected cloud, and a guest has none. "Aufnehmen" only
       while the leader allows pictures; switched off, the hint says WHO
       decided that instead of leaving a bare gap. The measure itself is the
       server's word at every upload — a stale page must not promise room
       that is no longer there. */
    if (GUEST) {
        photoFields.choose?.setAttribute('hidden', '');
        if (!start.guest.photosAllowed) {
            photoFields.capture?.setAttribute('hidden', '');
            if (photoFields.hint) {
                photoFields.hint.textContent = t('Der Leiter hat Fotos für diese Exkursion ausgeschaltet.');
                photoFields.hint.hidden = false;
            }
        }
    }

    /**
     * The URLs of the not-yet-transferred preview images.
     *
     * An object URL keeps the blob in memory until it is given back. Without
     * the cleanup, consumption would grow with every drawing of the gallery —
     * and it is redrawn on every sync.
     */
    let photoUrls = [];

    function releasePhotoUrls() {
        for (const a of photoUrls) URL.revokeObjectURL(a);
        photoUrls = [];
    }

    /** The photographer's name on a picture that is not the viewer's own —
     *  „von Anna", small, in the tile's corner. The set of a co-owned find
     *  has several hands in it, and a gallery that did not say whose was
     *  whose would look like one person's work. */
    function photoBy(node, f) {
        const by = f.photographer_id ?? null;
        // TAKEN BY SOMEBODY ELSE even on my own row: a copy I kept when a
        // shared find split (migration 0161) — the picture stays his work.
        if (!f.taken_by && (by === null || by === start.user.id || f.mine === true)) return node;
        const box = document.createElement('span');
        box.className = 'photo-tile-by';
        const badge = document.createElement('span');
        badge.className = 'photo-by';
        badge.textContent = t('von %s', personName(f.photographer));
        box.append(node, badge);
        return box;
    }

    /* EVERY TILE IN A BOX OF ITS OWN, NAMED BY ITS PICTURE (18.11.2026): the
       viewer marks a tile whose picture holds measures of the layer it
       stands in (photowindow.js, measuredMarks — „bei jedem
       Thumbnail … angezeigt … in dem Layer ist schon was gemessen worden")
       and, after a picture is deleted, opens the one that followed it
       (galleryPlace). An <img> carries no mark of its own, so the tile
       that was the bare picture gets the box the foreign ones always had
       (photoBy); data-picture is the gallery's key — „photo:63",
       „cloud:17" — on every tile, sortable or not. */
    function tileFor(node, f, key) {
        let tile = photoBy(node, f);
        if (tile === node) {
            tile = document.createElement('span');
            tile.className = 'photo-tile';
            tile.append(node);
        }
        tile.dataset.picture = key;
        return tile;
    }

    /**
     * Draw a point's gallery.
     *
     * Two origins, one row: the photos the server knows, and those still
     * waiting in the local store. For the viewer it is the same picture — it
     * only carries a mark as long as it is not up yet. Pulling them apart
     * would mean a day in the woods looks as if half of it had been lost.
     */
    /**
     * Order the gallery by subject names and put it out — the order itself is
     * subjectGroups() in gallerysort.js, shared with the photo window's strip.
     *
     * HEADINGS ONLY when anything is named at all. As long as not a single
     * picture carries a subject name — and that is the normal case for
     * everything that came into being before migration 0097 —, „Ohne
     * Motivnamen" would be a heading over everything and say nothing.
     *
     * THE HEADING TAKES THE WHOLE ROW. The gallery is a grid of
     * automatically filled columns; without grid-column the title would
     * stand in the first cell and the pictures beside it.
     */
    function galleryLayout(entries) {
        photoFields.gallery.innerHTML = '';
        const groups = subjectGroups(entries, measureStyle.photo_titles);
        const withHeadings = groups.some(g => g.name !== null);
        for (const { name, items } of groups) {
            if (withHeadings) {
                const head = document.createElement('h4');
                head.className = 'photo-group-title';
                /* The stored name keys the group and its symbol; the eye reads it
                   international (vocabulary.js) — finds.js reads the key too. */
                head.dataset.name = name ?? t('Ohne Motivnamen');
                head.textContent = vocab(head.dataset.name);
                photoFields.gallery.append(head);
            }
            for (const e of items) photoFields.gallery.append(e.node);
        }
        groupMenuDraw();
        // The viewer marks the tiles measured in its layer and finds its way back after a delete (photowindow.js).
        document.dispatchEvent(new CustomEvent('gallerydrawn'));
    }

    /* THE POINT WINDOW'S GROUPS AS SYMBOLS (13.11.2026), as
       the Fundbrowser's rail has them (finds.js, railGroups): built from
       the headings the gallery just drew, every tile told its group, the
       chosen group alone in sight, the headings hidden while the menu
       stands (pointwindow.css). With fewer than two groups there is
       nothing to switch and the headings stay. The choice outlives a
       redraw and the next find while that find has the group. */
    let photoGroup = null;
    const groupMenu = document.getElementById('d-photo-menu');
    // The chosen group's name over the whole width (pointwindow.css).
    const groupNow = document.getElementById('d-photo-now');
    if (groupMenu) {
        hoverPick(groupMenu, (b) => b.dataset.group === photoGroup, (b) => groupShow(b.dataset.group));
    }
    function groupMenuDraw() {
        const gallery = photoFields.gallery;
        // In the Fundbrowser the gallery lives in its rail, with a menu of its own.
        if (!groupMenu || !gallery || groupMenu.parentElement !== gallery.parentElement) return;
        const names = [];
        let name = '';
        for (const el of gallery.children) {
            if (el.classList.contains('photo-group-title')) {
                name = el.dataset.name ?? el.textContent;
                names.push(name);
            }
            el.dataset.group = name;
        }
        groupMenu.textContent = '';
        groupMenu.hidden = names.length < 2;
        if (groupNow) groupNow.hidden = groupMenu.hidden;
        if (names.length > 0 && !names.includes(photoGroup)) photoGroup = names[0];
        for (const n of names) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'photo-group-sign';
            b.dataset.group = n;
            b.title = vocab(n);
            b.setAttribute('aria-label', b.title);
            b.append(subjectIcon(n, n === t('Ohne Motivnamen') ? 'label' : null));
            b.addEventListener('click', () => groupShow(n));
            groupMenu.append(b);
        }
        groupShow(names.length < 2 ? null : photoGroup);
    }
    function groupShow(name) {
        if (name !== null) photoGroup = name;
        groupNow?.replaceChildren(...(name === null ? [] : [subjectIcon(name, name === t('Ohne Motivnamen') ? 'label' : null), vocab(name)]));
        for (const el of photoFields.gallery.children) el.classList.toggle('is-off', name !== null && el.dataset.group !== name);
        for (const b of groupMenu.children) {
            b.classList.toggle('is-active', b.dataset.group === name);
            b.setAttribute('aria-pressed', String(b.dataset.group === name));
        }
    }

    /* ONLY THE NEWEST DRAWING DRAWS (15.11.2026: on poor
       reception he switched between two species, and „Bilder" showed the
       previous one's pictures under the new one's name). Every drawing
       awaits — the foreign find's list, the waiting photos, the cloud — and
       one begun for the find just left can end after the next one started.
       The id check before the cloud half catches only a different find, and
       it ran after the local half had already been drawn. A number per
       drawing, and every await asks whether it is still the last one. */
    let galleryGen = 0;

    /**
     * The find is left: its pictures go at once, before the next find's
     * answer arrives (the Fundbrowser, cockpit.js close). On poor reception
     * that answer takes seconds, and the old tiles stood under the new
     * find's name the whole time — one click on them opened the previous
     * find's picture. A drawing still under way for it is dropped.
     */
    function clearPhotoGallery() {
        galleryGen++;
        releasePhotoUrls();
        if (photoFields.gallery) photoFields.gallery.innerHTML = '';
        Object.assign(cloudFilesShown, { pointId: 0, files: [] });
    }

    async function drawPhotoGallery(point) {
        if (!photoFields.gallery) return;
        const gen = ++galleryGen;
        const stale = () => gen !== galleryGen;

        let saved = point?.photos ?? [];
        /* A FOREIGN FIND'S PICTURES ARE FETCHED FRESH (11.09.2026): its
           object in the foreign store is the document's snapshot, and what
           I attached a minute ago — or what a co-owner attached — is not in
           it. Own points keep the list that rides with them. THE ANSWER IS
           THE HOLDER'S SET (point_view_scope, 14.09.2026) — for three days
           it was the caller's own pictures on the find, none for a share
           member, and the empty list overrode the snapshot: a friend saw no
           picture on any shared find. */
        if (point && foreignPoint(point.id)) {
            try {
                saved = (await api.get('photos', { point: point.id })).photos ?? saved;
                /* AND THE POINT CARRIES THE FRESH LIST FROM HERE ON (06.10.2026):
                   the photo window opens these objects, while its strip and its
                   statistics read the point's — two lists, and a measure taken
                   on a foreign find's photo stood in the picture but not in the
                   numbers. One list, one truth, until the next sync brings a
                   newer snapshot. */
                point.photos = saved;
            } catch { /* the snapshot, then */ }
            if (stale()) return;
        }
        let pending = [];
        try {
            pending = point ? await photos.pending(point.id) : [];
        } catch (e) {
            console.warn('Wartende Fotos nicht lesbar:', e);
        }
        if (stale()) return;

        // Clean up only now: between releasing and redrawing there should be no
        // moment in which the images are already dead but still visible.
        releasePhotoUrls();
        photoFields.gallery.innerHTML = '';
        // Both origins count: for the tab a waiting photo is as good as an
        // uploaded one — it is there.
        detailPhotoTab(saved.length + pending.length);

        /* COLLECTED INSTEAD OF APPENDED, since the pictures stand in groups
           by subject name: only when all tiles are there does one know which
           headings exist. The tiles themselves come into being as before —
           once built, they are only sorted in, not loaded anew. */
        const tiles = [];

        /* WHO MAY ADD A PICTURE HERE MAY ARRANGE THEM (30.09.2026) — the same
           door as the two buttons above, and the server asks the same one
           (point_attach_scope). The voucher photos and the cloud recordings
           in one row; a waiting photo has no row yet to carry an order, and
           a recording gets its place only with migration 0148 — until then
           it stays where it stood. The guest never — his photo slice of the
           API knows no PATCH. */
        const sortable = !GUEST && mayEditPointPhotos('attach_photos');

        for (const f of saved) {
            const image = document.createElement('img');
            image.className = 'photo-thumbnail';
            image.loading = 'lazy';
            developThumb(image, `${start.baseUrl}pages/photo.php?id=${f.id}&k=1`, f.develop, 'photo:' + f.id);
            image.alt = f.taken_at ? t('Aufnahme vom %s', readableDate(f.taken_at)) : t('Belegfoto');

            // width AND height FROM THE DATABASE ONCE STOOD HERE, to reserve
            // the space in advance. That was wrong, and visibly so: the two
            // attributes act like a style declaration, and because the
            // stylesheet does set a width but no height, height:1536px
            // remained. The thumbnail became a bar eighty pixels narrow and a
            // thousand and a half pixels tall.
            //
            // The space is reserved anyway — .photo-thumbnail has aspect-ratio:1
            // and is square long before the image arrives.
            image.addEventListener('click', () => showPhoto(f));

            // A FILE THAT IS NOT THERE MUST NOT LOOK LIKE A TORN PAGE. The row
            // in the database and the file on disk can come apart — a restored
            // dump brings the rows, the pictures live under data/users/ and are
            // rightly not in it. The browser then draws its broken-image symbol
            // and spills the alt text across the tile, which reads like damage
            // to the find rather than a missing file. Same treatment as the
            // cloud tiles: say it plainly, in the space the picture had.
            image.addEventListener('error', () => {
                const gap = document.createElement('span');
                gap.className = 'cloud-file';
                gap.title = t('Die Bilddatei liegt nicht auf diesem Server.');
                const text = document.createElement('span');
                text.className = 'cloud-file-name';
                text.textContent = t('Bild fehlt');
                gap.append(text);
                // The row is still there and keeps its place in the order — a
                // gap without the id would drop to the end on the next drag.
                if (image.dataset.sortKey) gap.dataset.sortKey = image.dataset.sortKey;
                image.replaceWith(gap);
            }, { once: true });

            const tile = tileFor(image, f, `photo:${f.id}`);
            if (sortable) {
                tile.dataset.sortKey = `photo:${f.id}`;
                // The browser's own image drag would take the gesture over.
                image.draggable = false;
            }
            tiles.push({ title: f.title ?? null, node: tile, order: f.sort_order });
        }

        for (const e of pending) {
            const wrapper = document.createElement('span');
            wrapper.className = 'photo-thumbnail-pending';

            const image = document.createElement('img');
            image.className = 'photo-thumbnail';
            const objectUrl = URL.createObjectURL(e.preview || e.image);
            photoUrls.push(objectUrl);
            image.src = objectUrl;
            image.alt = t('Noch nicht übertragen');

            const badge = document.createElement('span');
            badge.className = 'photo-badge';
            badge.textContent = '⏳';
            badge.title = t('Wird übertragen, sobald Verbindung da ist');

            wrapper.append(image, badge);
            // A waiting photo carries no subject name yet — one gives it that
            // in the picture window, and it only gets there after uploading.
            tiles.push({ title: null, node: wrapper });
        }

        /* THE LOCAL PICTURES STAND AT ONCE, without waiting for the cloud —
           the same decision as before, only now already sorted. If the cloud
           comes afterwards, they are sorted in anew ONCE; the tiles are the
           same nodes, so nothing loads again. */
        galleryLayout(tiles);

        // AND THE CLOUD HALF, into the same grid. The fetch happens after the
        // local pictures are already standing: they are there without a line,
        // and whoever has no reception should not be looking at an empty box
        // for it.
        const cloud = await cloudStateFor(point?.id ?? 0);

        // Between the two awaits the user may have switched points. Then this
        // answer belongs to somebody else's find and must not be drawn.
        //
        // ONLY ASKED WHEN THERE IS A POINT. Without one — a deleted find, a
        // foreign one that is not in the stock — there is nothing to compare
        // against, and returning here would leave the previous point's count
        // standing under an empty gallery.
        if (stale() || (point && state.selectedId !== point.id)) return;

        /* THE SAME RECORDS, NOT NEW ONES, for the same find
           (15.11.2026: on a pCloud picture the statistics did not open and did
           not grow while he measured — after a reload every measure was
           there). The viewer measures into the record it opened
           (cloudInWindow, its `measures` array), and the statistics read this
           list. Each drawing of the gallery fetched the list anew and put
           fresh objects in — in the Fundbrowser it runs right after the find
           opens, so the picture in the viewer and the one in the list were
           two objects, and every new measure went into the one the
           statistics never looked at. A record already known keeps its
           object (as finds.js refreshDetail does); new ones come in, gone
           ones go. */
        const known = cloudFilesShown.pointId === (point?.id ?? 0)
            ? new Map(cloudFilesShown.files.map((f) => [f.id, f])) : new Map();
        const files = (cloud?.files ?? []).map((f) => known.get(f.id) ?? f);
        Object.assign(cloudFilesShown, { pointId: point?.id ?? 0, files });
        // The plates lay out these very pictures — asked once both halves stand.
        plates.show(point?.id ?? 0);

        /* THE FIND'S OTHER FILES came in the same answer (pointfiles.js):
           offered on one's own find when the server keeps them — the answer
           carries the list then, an empty one included. Without a cloud,
           without reception or before migration 0153 it carries none, and
           the sub-tab stays away. */
        host.filesOffered = !GUEST && point != null && !foreignPoint(point.id) && Array.isArray(cloud?.documents);
        pointFiles.show(point?.id ?? 0, host.filesOffered ? cloud.documents : null);

        /* The freshest truth about the „bearbeitet" mark just arrived — the
           row in the sidebar should not wait for the next bucket round to
           learn what this device did itself (upload, removal). */
        if (point) {
            const n = cloud?.files?.length ?? 0;
            if ((point.recordings ?? 0) !== n) {
                point.recordings = n;
                drawList();
            }
        }

        /* THE TILES FROM THE KEPT RECORDS, not from the answer's (15.11.2026):
           a tile hands its record to the viewer, and a fresh object there
           beside the kept one in the list was two lists of measures for one
           picture again. */
        for (const f of files) {
            const link = cloudTile(f);
            const tile = tileFor(link, f, `cloud:${f.id}`);
            if (sortable && f.sort_order != null) {
                tile.dataset.sortKey = `cloud:${f.id}`;
                /* A LINK IS DRAGGED AWAY AS A LINK by the browser, its preview
                   as an image — either ends the drag before it began, and on
                   the desktop that was the whole gallery, since what comes in
                   there through „Auswählen" are cloud recordings. */
                link.draggable = false;
                link.querySelector('img')?.setAttribute('draggable', 'false');
            }
            tiles.push({ title: f.title ?? null, node: tile, order: f.sort_order ?? undefined });
        }
        if (files.length) galleryLayout(tiles);
        /* THE FIELD SHOWS WHAT IT HOLDS (14.09.2026). The window hides the
           gallery of a foreign find one may not tend when the SNAPSHOT
           carries no photo — decided before this fetch, and the snapshot
           knows nothing of the recordings. A find that is all micrographs
           and no phone shot would stay a closed field; now it opens as soon
           as there is a tile. */
        if (tiles.length > 0) {
            const field = photoFields.gallery.closest('.field');
            if (field) field.hidden = false;
        }
        /* AND ONLY NOW IS THE NUMBER ON THE TAB RIGHT
           (10.09.2026: „diese Anzahl zeigt nicht die hochgeladenen Fotos in
           der Cloud, sondern nur die Aufnahmen"). Further up it is already set
           once, so that it says anything at all without reception — there it
           cannot know the cloud yet, the answer only stands here. A tab that
           says 2 and shows 9 pictures is worse than one that says nothing for
           a moment. */
        detailPhotoTab(saved.length + pending.length + (cloud?.files?.length ?? 0));

        /* ONLY THE REASON NOW, NO LONGER THE NUMBER (10.09.2026:
           „hinter den Buttons Aufnehmen und Auswählen steht die Anzahl, diese
           bitte entfernen"). It stood here from the time when the tab did not
           exist yet; now the tab carries it, and the same number twice on one
           screen is one too many — all the more since, until the cloud's
           answer, the one said something other than the other.

           What STAYS is the sentence on why „Auswählen" does not work:
           whoever cannot press a button must learn the reason, and it always
           stood before the number anyway. */
        photoFields.hint.textContent = setChooseState(point, cloud);
        /* The microscope wants a find the server knows, like „Auswählen":
           a still is filed under the find's id. */
        if (photoFields.microscope) photoFields.microscope.disabled = !(point?.id > 0);
    }

    /**
     * Store the order a drag left the gallery in (30.09.2026) —
     * gallerysort.js does the dragging, this the saving.
     *
     * ONLINE ONLY, like the picture's name: both are changes to a row the
     * server holds, and neither goes through the queue. Whoever drags without
     * reception gets told so, and the gallery goes back to the order that is
     * really stored — a row that shows an order nobody saved would be found
     * out only on the next device.
     *
     * REDRAWN EITHER WAY. The server's answer is the whole row, a picture
     * from another device included; the own point's baggage takes it on, so
     * a redraw from there and a reopened window show the same thing. A
     * foreign find fetches its pictures fresh on every drawing anyway, and
     * the cloud recordings are asked fresh on every drawing of any find.
     */
    async function savePhotoOrder(keys) {
        const pointId = state.selectedId;
        const point = state.points.get(pointId) ?? state.sharedPoints.get(pointId);
        try {
            const answer = await request('photos.php', {
                method: 'POST',
                body: JSON.stringify({ point_id: pointId, order: keys, _methode: 'PATCH' }),
            });
            // „photo:63" / „cloud:17" → its new place; both lists take it on.
            const rank = new Map((answer?.order ?? keys).map((key, i) => [key, i]));
            const renumber = (list, kind) => {
                for (const f of list) {
                    const at = rank.get(`${kind}:${f.id}`);
                    if (at !== undefined) f.sort_order = at;
                }
            };
            if (point?.photos) {
                renumber(point.photos, 'photo');
                point.photos.sort((a, b) => (a.sort_order - b.sort_order) || (a.id - b.id));
            }
            if (cloudFilesShown.pointId === pointId) renumber(cloudFilesShown.files, 'cloud');
        } catch {
            status(t('Die neue Reihenfolge der Bilder ließ sich nicht speichern.'), true);
        }
        if (point && state.selectedId === pointId) drawPhotoGallery(point);
    }

    if (photoFields.gallery) sortableGallery(photoFields.gallery, savePhotoOrder);

    /**
     * Open the camera — and secure the form's state beforehand.
     *
     * THAT IS THE PRICE OF THE ROUTE VIA THE CAMERA APP. It comes to the front,
     * this page goes into the background, and Android may clear it away there
     * when memory runs short. On returning, the page reloads — and a
     * half-typed note would be gone. The state then lies in localStorage and
     * is put back in on the next start.
     */
    function preparePhotoCapture() {
        if (state.selectedId == null) return;
        formState.persist(state.selectedId, JSON.parse(detailState()));
    }

    /* The kind of the previous photo — the default for the next one. „Makro"
       to start with: a phone at the find spot takes macro photos, and whoever
       comes from the microscope switches over with one tap and then stays
       there. */
    let subjectKind = 'macro';

    /* Confirmed once for the batch being asked — ten micrographs in a row
       are one decision, not ten (askSubjects resets it). */
    let microWarnedNow = false;

    /**
     * THE WARNING BEFORE A MICROGRAPH GOES UP SHRUNK (04.10.2026:
     * „ich klicke auf Mikrofoto, ich bekomme nochmal eine Warnmeldung … und du
     * klickst auf Verstanden oder Abbrechen, was dich wieder zurück zu den
     * Makrofotos führen würde"). The one moment a picture loses pixels for
     * good; a renamed picture never shrinks, so nothing else warns. Not at all
     * with the shrink switch off, nor once waved off on the account
     * („Nicht mehr anzeigen", measure_style.micro_shrink_warned).
     *
     * @returns {Promise<boolean>} true to go on as micro, false for back to macro
     */
    function microShrinkWarning() {
        const box = document.getElementById('micro-shrink-warning');
        if (!box || measureStyle.micro_shrink === false || measureStyle.micro_shrink_warned === true || microWarnedNow) {
            return Promise.resolve(true);
        }
        const off = document.getElementById('micro-shrink-warning-off');
        off.checked = false;
        box.returnValue = '';
        return new Promise((resolve) => {
            box.onclose = () => {
                // Escape and „Abbrechen" alike: back to macro.
                const ok = box.returnValue === 'ok';
                if (ok) {
                    microWarnedNow = true;
                    if (off.checked) {
                        measureStyle.micro_shrink_warned = true;
                        const body = new FormData();
                        body.append('micro_shrink_warned', '1');
                        // Not stuck is no loss: the window comes once more.
                        request('measure_style.php', { method: 'POST', body }).catch(() => {});
                    }
                }
                resolve(ok);
            };
            box.showModal();
        });
    }

    /**
     * Ask for the subject name — once per photo.
     *
     * 10.09.2026: „Aufnahmen mit der Handykamera sollten, wenn die
     * Cloud angeschlossen ist, immer eine Abfrage bekommen, was der Motivname
     * ist, dass dieser gleich feststeht … ebenso für jedes Foto, was wir
     * auswählen, also in die Cloud laden."
     *
     * BEFORE AND NOT AFTER, and that is the whole gain: adding it later means
     * opening every picture singly and reaching into the measuring line —
     * nobody does that after a tour with thirty shots. In the moment of
     * releasing, on the other hand, one knows exactly what one photographed.
     *
     * ONE TAP SUFFICES: the suggestions are buttons, and one of them answers
     * the question and closes the window in one movement. Whoever had to
     * type first and then confirm would leave the name out.
     *
     * FROM THE MICROSCOPE IT ASKS DIFFERENTLY (26.10.2026: „nur
     * Mikro-Labels … gespeichert werden, was zuletzt gewählt wurde … ohne
     * Name geht nicht … bestätigen mit Space … statt ohne Name brauchen wir
     * ein Abbrechen"). Five spore pictures in a row are five presses of the
     * space bar to shoot and five to confirm: the micro names only, no kind
     * to choose; the last name — a typed one too — stands chosen, on this
     * computer; the space bar or „Übernehmen" takes it; and instead of
     * „Ohne Namen" there is „Abbrechen", which throws the picture away — a
     * space bar pressed by mistake must not leave a picture behind.
     *
     * @param {File} file         the picture asked about — shown in the window
     * @param {number} index      its place in the batch, from 0
     * @param {number} total      how many were picked together
     * @param {{microscope?: boolean}} options
     * @returns {Promise<{kind: string, title: ?string, forAll: boolean, cancelled?: boolean}>}
     *          kind and name — the name null for „ohne" — and whether the
     *          answer goes to every remaining photo of the batch; cancelled
     *          only from the microscope, the picture is not to be kept
     */
    /* The shot's zoom in the subject window — bound the first time the
       microscope asks, reset for every shot. */
    let subjectZoom = null;
    /* The heads over the microscope's name groups (photos.php,
       PHOTO_TITLES_MICRO_GROUPS keys them). */
    const GROUP_HEADS = {
        general:   () => t('Allgemein'),
        hymenium:  () => t('Hymenium'),
        excipulum: () => t('Excipulum und Rand'),
        cap:       () => t('Hut'),
        stem:      () => t('Stiel'),
        hyphae:    () => t('Hyphen'),
        polypores: () => t('Porlinge und Rindenpilze'),
        gasteroid: () => t('Bauchpilze'),
    };
    /* THE GROUPS' SIGNS on the microscope window's rail (below) — each the
       symbol of a name that stands for its part. „Weitere" gathers what no
       group holds, a list. */
    const GROUP_SIGNS = {
        general:   'subject-overview',
        hymenium:  'subject-hymenium',
        excipulum: 'subject-excipulum',
        cap:       'subject-cuticle',
        stem:      'subject-caulocystidia',
        hyphae:    'subject-generative-hyphae',
        polypores: 'subject-skeletal-hyphae',
        gasteroid: 'subject-capillitium',
        rest:      'list',
    };

    /* A NAME OF ONE'S OWN JOINS THE ACCOUNT'S LIST OF ITS KIND
       (18.11.2026: „Kristalle" set in the microscope had no „Medium" in the
       viewer — „der Weg aus dem Mikroskopiermodul ist ja klar, dass das ein
       Mikrofoto wird"). The viewer knows micro and macro only by the
       account's two lists (photowindow.js, pictureIsMicro, pictureScale),
       and until then a typed name stayed on this computer
       (microscopeOwnSubjects) and the account never heard of it. The kind
       is the window's: the microscope's is always Mikro, the camera's the
       tab standing — the same question the viewer's „Eigener Name …" asks
       outright. A name either list holds already changes nothing
       (measure_style.php). Offline, it waits on this computer and goes up
       with the next window (learnPending). */
    async function subjectNameLearn(name, kind) {
        const known = [...(measureStyle.photo_titles_macro ?? []), ...(measureStyle.photo_titles_micro ?? [])]
            .some((w) => String(w).toLowerCase() === name.toLowerCase());
        if (known) return true;
        // An account without the two lists has nowhere to put it — keep waiting.
        if (!(measureStyle.photo_titles_macro || measureStyle.photo_titles_micro)) return false;
        try {
            const answer = await request('measure_style.php', { method: 'POST', body: JSON.stringify({ photo_title: { name, kind } }) });
            for (const key of ['photo_titles', 'photo_titles_macro', 'photo_titles_micro']) {
                if (answer.measure_style?.[key]) measureStyle[key] = answer.measure_style[key];
            }
            return true;
        } catch {
            return false;
        }
    }
    /* The microscope's names kept on this computer before the rule above go
       up to the account once, as micro — and leave this computer's list,
       which from then on holds only what could not be sent. A name deleted
       in the viewer's menu therefore does not come back from here. */
    let learnRunning = false;
    async function learnPending() {
        if (learnRunning) return;
        const pending = (settings.get('microscopeOwnSubjects', []) ?? []).filter((n) => typeof n === 'string' && n !== '');
        if (pending.length === 0) return;
        learnRunning = true;
        const left = [];
        for (const name of pending) if (!await subjectNameLearn(name, 'micro')) left.push(name);
        settings.set('microscopeOwnSubjects', left);
        learnRunning = false;
    }
    // Once per page as well: a name from before waits for no picture to be taken.
    if (!GUEST) learnPending();

    function askSubject(file, index = 0, total = 1, { microscope = false } = {}) {
        const box     = document.getElementById('photo-subject-window');
        const form    = document.getElementById('photo-subject-form');
        const kinds   = document.getElementById('photo-subject-kind');
        const groups  = document.getElementById('photo-subject-group');
        const choices = document.getElementById('photo-subject-choices');
        const free    = document.getElementById('photo-subject-free');
        const label   = document.getElementById('photo-subject-file');
        const title   = document.getElementById('photo-subject-title');
        const preview = document.getElementById('photo-subject-preview');
        const allRow  = document.getElementById('photo-subject-all-row');
        const all     = document.getElementById('photo-subject-all');
        const take    = document.getElementById('photo-subject-take');
        const skip    = document.getElementById('photo-subject-skip');
        if (!box) return Promise.resolve({ kind: subjectKind, title: null, forAll: false });

        /* The microscope's way (above): Mikro, without the choice; the last
           name chosen — one of the list, or typed into the field.

           THE WHOLE SCREEN, the shot as large as it goes and zoomable, the
           names in a column at the edge (26.10.2026: „mit einer
           größeren Vorschau und der Seitenleiste am Rand, sodass man
           wirklich prüfen kann, was man aufgenommen hat … sind die Sporen
           verrückt in der Zeit, gibt es Schlieren … will ich das Bild
           speichern, ja, nein"). The names in that column alphabetical,
           one's OWN typed names among them — kept on this computer like
           the last one, so a name typed once is a button from then on. */
        kinds.hidden = microscope;
        if (groups) groups.hidden = !microscope;
        box.classList.toggle('is-microscope', microscope);
        // NO „OHNE NAMEN" (15.11.2026: every picture has a name) — the way out is „Abbrechen".
        skip.textContent = t('Abbrechen');
        if (microscope) {
            learnPending();
            subjectKind = 'micro';
            subjectZoom ??= imageZoom.bind(document.getElementById('photo-subject-frame'), preview);
            subjectZoom.reset();
        }
        const ownNames = microscope ? (settings.get('microscopeOwnSubjects', []) ?? []).filter((n) => typeof n === 'string' && n !== '') : [];
        const microNames = microscope
            ? [...new Set([...(measureStyle.photo_titles_micro ?? []), ...ownNames])]
                .sort((a, b) => a.localeCompare(b, 'de', { sensitivity: 'base' }))
            : null;
        const remembered = microscope ? String(settings.get('microscopeSubject', '') ?? '') : '';
        const listed = microscope && microNames.includes(remembered);
        /* ASCO OR BASIDIO, AND BY PART WITHIN (09.11.2026;
           photos.php, PHOTO_TITLES_MICRO_GROUPS) — the tab last chosen on
           this computer; a name that stands only in the other tab switches
           to it, so the remembered name is in sight. */
        const microGroups = measureStyle.photo_titles_micro_groups ?? {};
        const tabsOf = (word) => Object.keys(microGroups)
            .filter((tab) => Object.values(microGroups[tab] ?? {}).some((words) => words.includes(word)));
        let group = settings.get('microscopeSubjectGroup', 'basidio') === 'asco' ? 'asco' : 'basidio';
        if (listed && tabsOf(remembered).length > 0 && !tabsOf(remembered).includes(group)) group = tabsOf(remembered)[0];
        let chosen = listed ? remembered : null;

        /* EVERY WINDOW OF A BATCH LOOKS NEW (28.09.2026): the count
           in the heading and the picture itself. Without them the second
           window was the first one to the eye, and the tap on „Hauptmotiv"
           seemed lost (index.php says the rest). */
        title.textContent = total > 1 ? t('Motivname für Foto %d von %d', index + 1, total) : t('Motivname');
        label.textContent = file?.name ?? '';
        label.hidden = !file?.name;
        free.value = microscope && !listed ? vocab(remembered) : '';

        /* THE PICTURE AS THE BROWSER CAN SHOW IT — a JPEG from the phone, a
           PNG; a RAW file or a HEIC in Chrome it cannot, and then the file
           name stands alone, as before. The address lives only as long as
           the window. */
        const address = file ? URL.createObjectURL(file) : null;
        preview.hidden = true;
        preview.onload = () => { preview.hidden = false; };
        preview.onerror = () => { preview.hidden = true; };
        if (address) preview.src = address; else preview.removeAttribute('src');

        /* „For all" only while photos follow; its sentence says whether the
           batch starts here or is already under way. ON THE LAST PHOTO OF A
           BATCH THE ROW KEEPS ITS PLACE, invisible (is-last): gone, it pulled
           the names up by one line, and a quick second tap at the spot of
           „Hauptmotiv" landed on „Sporenpulver" — measured, 28.09.2026. A
           single photo has no batch and no row. */
        const more = index < total - 1;
        allRow.hidden = total === 1;
        allRow.classList.toggle('is-last', !more);
        all.checked = false;
        document.getElementById('photo-subject-all-text').textContent = index === 0
            ? t('Diesen Namen für alle Fotos nehmen')
            : t('Diesen Namen für alle übrigen Fotos nehmen');

        const typed = () => {
            const empty = free.value.trim() === '' && chosen === null;
            take.disabled = empty;
            take.classList.toggle('subtle', empty);
        };
        typed();

        return new Promise((resolve) => {
            let answered = false;
            const done = (name) => {
                if (answered) return;
                answered = true;
                box.close();
                if (address) URL.revokeObjectURL(address);
                if (microscope && name) settings.set('microscopeSubject', name);
                /* A name of one's own joins the account's list of the kind
                   standing (subjectNameLearn) — not awaited, the picture
                   goes on; what fails waits on this computer. */
                if (name) {
                    const kind = microscope ? 'micro' : subjectKind;
                    subjectNameLearn(name, kind).then((ok) => {
                        if (ok || !microscope) return;
                        const kept = settings.get('microscopeOwnSubjects', []) ?? [];
                        if (!kept.includes(name)) settings.set('microscopeOwnSubjects', [...kept, name].slice(-60));
                    });
                }
                resolve({ kind: subjectKind, title: name, forAll: more && all.checked });
            };
            // From the microscope: the picture is not kept.
            const cancel = () => {
                if (answered) return;
                answered = true;
                box.close();
                if (address) URL.revokeObjectURL(address);
                resolve({ kind: subjectKind, title: null, forAll: false, cancelled: true });
            };
            /* Every way out of the window with a name — chosen or typed —
               sends the picture on as the kind standing there. With
               Mikro standing from the last photo, no tap on „Mikro" warned:
               so the warning stands here, in front of every way out. */
            const finish = async (name) => {
                if (subjectKind === 'micro' && !(await microShrinkWarning())) {
                    // From the microscope there is no macro to go back to:
                    // the window simply stays.
                    if (microscope) return;
                    subjectKind = 'macro';
                    render();
                    return;
                }
                done(name);
            };

            /* The names of the chosen kind — rebuilt on every switch, because
               the two lists are the account's (Einstellungen → Messen) and
               can have any length. */
            const render = () => {
                for (const b of kinds.querySelectorAll('[data-kind]')) {
                    b.classList.toggle('is-active', b.dataset.kind === subjectKind);
                    b.setAttribute('aria-pressed', String(b.dataset.kind === subjectKind));
                }
                choices.innerHTML = '';
                for (const b of groups?.querySelectorAll('[data-group]') ?? []) {
                    b.classList.toggle('is-active', b.dataset.group === group);
                    b.setAttribute('aria-selected', String(b.dataset.group === group));
                }
                const nameButton = (word, into = choices) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'button subtle small';
                    button.classList.toggle('is-active', word === chosen);
                    // The micro names international (vocabulary.js); the click keeps the stored word.
                    button.textContent = vocab(word);
                    button.addEventListener('click', () => finish(word));
                    into.append(button);
                };
                const head = (text, into = choices) => {
                    const p = document.createElement('p');
                    p.className = 'list-label photo-subject-head';
                    p.textContent = text;
                    into.append(p);
                };
                if (!microNames) {
                    for (const word of subjectKind === 'micro'
                        ? (measureStyle.photo_titles_micro ?? [])
                        : (measureStyle.photo_titles_macro ?? [])) nameButton(word);
                    return;
                }
                /* THE TAB'S GROUPS ON A RAIL (15.11.2026: „so eine
                   Schnellauswahl, wie wir sie im Bilderbearbeiten haben … Auf
                   der rechten Seite außen die Symbole. Die beim Überfahren
                   dann links die Liste öffnen"). Until then every group stood
                   under its head in one long column that scrolled. Now a
                   column of signs at the window's right edge, one per group
                   the account still has names in — then „Weitere": every name
                   in no group of either tab, the account's and this
                   computer's own —, and beside it to the left the chosen
                   group's names under its head. Resting on a sign shows its
                   group, with the guard of „Bearbeiten" against grazing
                   (hoverpick.js); a click and the keyboard choose too. The
                   group stays chosen per tab on this computer. */
                const railGroups = Object.entries(microGroups[group] ?? {})
                    .map(([key, words]) => ({ key, name: GROUP_HEADS[key]?.() ?? key, words: words.filter((w) => microNames.includes(w)) }))
                    .filter((g) => g.words.length > 0);
                const rest = microNames.filter((w) => tabsOf(w).length === 0);
                if (rest.length > 0) railGroups.push({ key: 'rest', name: t('Weitere'), words: rest });
                if (railGroups.length === 0) return;
                const keptRail = settings.get('microscopeSubjectRail', null) ?? {};
                /* The name standing chosen opens its group, so it is in sight;
                   else the group last looked at. A name in two groups
                   (photos.php: Velum, the hyphae) opens the one last looked
                   at, if it is one of them — the place one came from. */
                const kept = railGroups.find((g) => g.key === keptRail[group]);
                let shown = (chosen !== null && kept?.words.includes(chosen) ? kept : null)
                    ?? railGroups.find((g) => chosen !== null && g.words.includes(chosen))
                    ?? kept ?? railGroups[0];
                const page = document.createElement('div');
                page.className = 'photo-subject-railpage';
                /* THE HEAD OVER THE WHOLE WIDTH, the rail and the names beneath
                   it (15.11.2026: „die Überschrift … in voller
                   Breite, sodass die Symbole und die Listen unten drunter
                   sind") — as „Bearbeiten" has it since 13.11.2026. */
                head('', page);
                const title = page.lastElementChild;
                const body = document.createElement('div');
                body.className = 'photo-subject-railbody';
                const signs = document.createElement('div');
                signs.className = 'photo-subject-rail';
                signs.setAttribute('role', 'tablist');
                signs.setAttribute('aria-orientation', 'vertical');
                signs.setAttribute('aria-label', t('Gruppen'));
                const showGroup = (g) => {
                    shown = g;
                    title.textContent = g.name;
                    body.textContent = '';
                    for (const w of g.words) nameButton(w, body);
                    for (const b of signs.children) {
                        const on = b.dataset.railKey === g.key;
                        b.classList.toggle('is-active', on);
                        b.setAttribute('aria-selected', String(on));
                        b.tabIndex = on ? 0 : -1;
                    }
                };
                const pick = (key) => {
                    const g = railGroups.find((x) => x.key === key);
                    if (!g || g === shown) return;
                    showGroup(g);
                    settings.set('microscopeSubjectRail', { ...(settings.get('microscopeSubjectRail', null) ?? {}), [group]: key });
                };
                for (const g of railGroups) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'photo-subject-sign';
                    b.setAttribute('role', 'tab');
                    b.dataset.railKey = g.key;
                    b.title = g.name;
                    b.setAttribute('aria-label', g.name);
                    b.append(icon(GROUP_SIGNS[g.key] ?? 'list'));
                    b.addEventListener('click', () => pick(g.key));
                    b.addEventListener('focus', () => pick(g.key));
                    b.addEventListener('keydown', (e) => {
                        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
                        e.preventDefault();
                        const all = [...signs.children];
                        const i = all.indexOf(b) + (e.key === 'ArrowDown' ? 1 : -1);
                        all[(i + all.length) % all.length].focus();
                    });
                    signs.append(b);
                }
                hoverPick(signs, (b) => b.dataset.railKey === shown.key, (b) => pick(b.dataset.railKey), '.photo-subject-sign', 'left');
                page.append(body, signs);
                choices.append(page);
                showGroup(shown);
            };
            for (const b of groups?.querySelectorAll('[data-group]') ?? []) {
                b.onclick = () => {
                    group = b.dataset.group;
                    settings.set('microscopeSubjectGroup', group);
                    render();
                };
            }
            for (const b of kinds.querySelectorAll('[data-kind]')) {
                b.onclick = async () => {
                    // „Abbrechen" leaves it on (or puts it back to) Makro.
                    if (b.dataset.kind === 'micro' && subjectKind !== 'micro' && !(await microShrinkWarning())) {
                        subjectKind = 'macro';
                    } else {
                        subjectKind = b.dataset.kind;
                    }
                    render();
                };
            }
            render();

            /* A name typed takes the place of the one chosen — from the
               microscope the highlighted name would otherwise contradict
               the field. */
            free.oninput = () => {
                if (microscope && chosen !== null && free.value.trim() !== '') {
                    chosen = null;
                    render();
                }
                typed();
            };

            form.onsubmit = (e) => {
                e.preventDefault();
                // The typed name first; from the microscope the chosen one.
                // „Spores" typed is the stored „Sporen" (vocabulary.js).
                const name = vocabStored(free.value) || chosen || '';
                // Enter in an empty field takes nothing over — as the button.
                if (name === '') return;
                finish(name.slice(0, 120));
            };
            /* „ABBRECHEN" AND ESCAPE: nothing of this choice goes up
               (15.11.2026: „Motivnamenzwang … jedes Bild braucht
               einen Motivnamen … nur noch Abbrechen"). Until then they meant
               „Ohne Namen" and the picture went up unnamed; from the
               microscope they already cancelled. A batch stops as a whole
               (askSubjects) — as the author's window before it does.
               onX and not addEventListener: a listener per window of a batch
               would pile up on the one dialog. */
            skip.onclick = () => cancel();
            box.oncancel = (e) => { e.preventDefault(); cancel(); };

            /* THE SPACE BAR CONFIRMS, from the microscope — the press that
               took the picture, pressed again. Not in the name field, where
               it is a space; and not in the first moments: a webcam's still
               comes so fast that the window can stand before the shutter's
               key is even released. The window itself holds the focus, so
               no name button catches the press for itself. */
            const opened = performance.now();
            box.onkeydown = (e) => {
                if (!microscope || e.key !== ' ' || e.repeat || e.target === free) return;
                e.preventDefault();
                if (performance.now() - opened > 350) form.requestSubmit();
            };

            box.showModal();
            if (microscope) {
                box.tabIndex = -1;
                box.focus();
            }
        });
    }

    /**
     * Kind and name for every picked photo — one window each, until one of
     * them answers „for all": that answer then goes to every photo still to
     * come, and no further window opens.
     *
     * @param {File[]} files
     * @returns {Promise<{kind: string, title: ?string}[]>} one per file, in order
     */
    /**
     * WHO TOOK THE PHOTOS — once for a batch on the „Auswählen" road (the
     * window in gallery_windows.php says why). Resolves {takenBy: null} for
     * „Ich", {takenBy: '<name>'} for somebody else, null when cancelled.
     * The first photographer a file names itself (EXIF Artist, exif.js)
     * stands in the field as a proposal, with a line saying where it came
     * from; „Ich" stays chosen.
     */
    async function askAuthor(files) {
        const box = document.getElementById('photo-author-window');
        if (!box) return { takenBy: null };
        const form = document.getElementById('photo-author-form');
        const name = document.getElementById('photo-author-name');
        const camera = document.getElementById('photo-author-camera');
        const mine = String(start.user?.finder || start.user?.name || '').trim();
        document.getElementById('photo-author-me').textContent = mine ? t('Ich (%s)', mine) : t('Ich');
        document.getElementById('photo-author-question').textContent = files.length > 1
            ? t('Wer hat die %d Fotos gemacht?', files.length) : t('Wer hat das Foto gemacht?');
        form.querySelector('input[value="me"]').checked = true;
        name.value = '';
        camera.hidden = true;
        let found = null;
        for (const file of files.slice(0, 20)) {
            try {
                const artist = exif.read(await file.slice(0, 256 * 1024).arrayBuffer()).artist;
                if (artist && artist.toLowerCase() !== mine.toLowerCase()) { found = artist; break; }
            } catch { /* a file without EXIF proposes nothing */ }
        }
        if (found) {
            name.value = found;
            camera.textContent = t('In der Bilddatei steht als Fotograf: %s', found);
            camera.hidden = false;
        }
        // Typing a name chooses „jemand anderes".
        name.oninput = () => { form.querySelector('input[value="other"]').checked = true; };
        return new Promise((resolve) => {
            const done = (answer) => {
                form.onsubmit = null;
                document.getElementById('photo-author-cancel').onclick = null;
                box.onclose = null;
                box.close();
                resolve(answer);
            };
            form.onsubmit = (e) => {
                e.preventDefault();
                const other = form.querySelector('input[value="other"]').checked;
                const typed = name.value.trim();
                if (other && typed === '') {
                    name.focus();
                    return;
                }
                done({ takenBy: other && typed.toLowerCase() !== mine.toLowerCase() ? typed.slice(0, 120) : null });
            };
            document.getElementById('photo-author-cancel').onclick = () => done(null);
            box.onclose = () => resolve(null);
            box.showModal();
        });
    }

    /** Kind and name per file — null when one window was cancelled: then none of them goes up. */
    async function askSubjects(files) {
        microWarnedNow = false;
        const answers = [];
        for (const [i, file] of files.entries()) {
            const a = await askSubject(file, i, files.length);
            if (a.cancelled) return null;
            answers.push(a);
            if (a.forAll) {
                while (answers.length < files.length) answers.push({ kind: a.kind, title: a.title });
                break;
            }
        }
        return answers;
    }

    photoFields.capture?.addEventListener('click', () => {
        preparePhotoCapture();
        photoFields.camera.click();
    });

    /* No preparePhotoCapture() here: picking a file does not put a camera app in
       front, so this page does not go into the background and Android has no
       reason to clear it away. The snapshot is for the camera route alone. */
    photoFields.choose?.addEventListener('click', () => photoFields.file.click());

    /* ONLY THE CAMERA COMES THROUGH HERE. What is picked from the device goes
       to the cloud — see further below. Until 18.08.2026 both hung on the same
       handler and both landed on this server; that filled it with originals
       that have no business there. */
    photoFields.camera?.addEventListener('change', async () => {
        {
            const input = photoFields.camera;
            const files = [...(input.files || [])];
            // Reset, otherwise the same file triggers no 'change' the second
            // time — and the button would look broken.
            input.value = '';
            if (files.length === 0) return;

            // The shot belongs to the point that was selected when the camera
            // opened. Fetching it anew here matters: if it still had a
            // provisional id, it may have been swapped in the meantime.
            const point = await pointReady(state.points.get(state.selectedId));
            if (!point) {
                status(t('Kein Punkt ausgewählt.'), true);
                return;
            }

            /* ASKED ONLY WITH A CONNECTED CLOUD, that is how the maintainer set
               it: without one a shot is a quick memo, with one the beginning
               of a filing in which the name names the file. The question
               stands BEFORE the preparing — after it the upload would already
               be running, and the name would come too late. */
            const titles = [];
            const answers = [];
            if (start.cloudConnected === true && !GUEST) {
                // The kind is asked here too (the choice, 10.09.2026)
                // — it files the name, and on the full-size road below it
                // decides about shrinking a micrograph, as with „Auswählen".
                const asked = await askSubjects(files);
                if (asked === null) {
                    status(files.length === 1 ? t('Abgebrochen — das Foto wird nicht übernommen.') : t('Abgebrochen — die Fotos werden nicht übernommen.'));
                    return;
                }
                for (const a of asked) {
                    answers.push(a);
                    titles.push(a.title);
                }
            }

            // IN THE APP the original is in the phone's gallery already.
            if (galleryOriginals.bridge() && start.cloudUpload === true && !GUEST) {
                await captureInApp(point, files, titles, answers);
                formState.forget();
                drawPhotoGallery(state.points.get(state.selectedId) ?? state.sharedPoints.get(state.selectedId));
                return;
            }

            if (fullSizeCapture(point)) {
                await captureFullSize(point, files, titles, answers);
                formState.forget();
                drawPhotoGallery(state.points.get(state.selectedId) ?? state.sharedPoints.get(state.selectedId));
                return;
            }

            status(files.length === 1 ? t('Foto wird aufbereitet …') : t('%d Fotos werden aufbereitet …', files.length));
            const result = await photos.capture(point.id, files, titles);

            if (result.errors.length > 0) {
                status(t('Nicht lesbar: %s', result.errors.join(', ')), true);
            } else if (result.captured > 0) {
                status(navigator.onLine
                    ? (result.captured === 1 ? t('1 Foto übernommen.') : t('%d Fotos übernommen.', result.captured))
                    : t('Gespeichert. Wird übertragen, sobald Verbindung da ist.'));
            }

            // The snapshot has served its purpose — the form is still standing.
            formState.forget();
            drawPhotoGallery(state.points.get(state.selectedId) ?? state.sharedPoints.get(state.selectedId));
        }
    });

    /**
     * Does this shot go up WHOLE, straight into the cloud?
     * (03.10.2026: „mit Cloud und online: volle Größe in die Cloud; offline:
     * verkleinern wie bisher; ohne Cloud: immer 1000 px".)
     *
     * ONLY WHERE „AUSWÄHLEN" COULD CARRY IT: a cloud that takes the browser's
     * own uploads (start.cloudUpload, a second release beside the pictures'),
     * a point the server knows (a provisional one has no id to file under),
     * and reception. Then Wi-Fi always, mobile data only with the tick in
     * Mein Bereich → Cloud. WHERE THE DEVICE DOES NOT SAY what
     * it is on (an iPhone, most desktops), the tick decides — guessing Wi-Fi
     * would cost a stranger's data plan.
     */
    function fullSizeCapture(point) {
        if (GUEST || start.cloudUpload !== true || !(point?.id > 0)) return false;
        return fullSizeNetwork() !== null;
    }

    /**
     * May a whole picture travel on the network the phone is on NOW? 'any'
     * when the tick allows mobile data, 'unmetered' when only Wi-Fi does —
     * the app takes it as its upload's constraint, so a switch to mobile data
     * in the middle waits instead of going on — and null when not at all.
     * Inside the app it asks the app, which knows (NET_CAPABILITY_NOT_METERED);
     * navigator.connection.type is missing in many WebViews and in Safari.
     */
    function fullSizeNetwork() {
        if (!navigator.onLine) return null;
        if (measureStyle.full_size_mobile === true) return 'any';
        const app = galleryOriginals.bridge();
        let unmetered = false;
        try { unmetered = app?.isUnmetered?.() === true; } catch { /* an older app */ }
        unmetered = unmetered || ['wifi', 'ethernet'].includes(navigator.connection?.type);
        return unmetered ? 'unmetered' : null;
    }

    /**
     * What arrived, in words — NEVER „volle Größe" for a picture that went
     * up shrunk (26.10.2026: the message said full size while
     * his micro switch shrank every micrograph to 2048). All shrunk, all
     * whole, or — a batch of both — only that they arrived.
     */
    function arrivedWords(count, shrunk, edge) {
        if (shrunk === 0) {
            return count === 1 ? t('Foto in voller Größe in der Cloud.') : t('%d Fotos in voller Größe in der Cloud.', count);
        }
        if (shrunk === count) {
            return count === 1 ? t('Foto in der Cloud, auf %d Pixel verkleinert.', edge)
                : t('%d Fotos in der Cloud, auf %d Pixel verkleinert.', count, edge);
        }
        return t('%d Fotos in der Cloud.', count);
    }

    /**
     * The full-size road: the shrunk copy is parked first — held, not sent —
     * then the whole shot goes the way „Auswählen" goes (cloudupload.js,
     * browser straight to the cloud; the server's 2 MB never get a say).
     * Arrives it, the copy is thrown away; breaks it off, the copy goes up the
     * usual way (photos.js, HOLD). One file at a time, each with its own copy,
     * so one that fails does not drag the others down.
     *
     * WHAT ARRIVES IS A RECORDING (map_cloud_files), as with „Auswählen" — and
     * that is no difference anybody sees: one gallery, one order, one viewer,
     * and the Motivname says what a picture is (03.10.2026: „das
     * sind alles Fotos, und nur das Label entscheidet").
     */
    async function captureFullSize(point, files, titles, answers) {
        status(files.length === 1 ? t('Foto wird aufbereitet …') : t('%d Fotos werden aufbereitet …', files.length));
        const kept = await photos.capture(point.id, files, titles, { hold: true });
        const { uploadForPoint, SHRINK_EDGE } = await import('./cloudupload.js');

        let whole = 0;
        let small = 0;
        let shrunk = 0;
        for (const [i, file] of files.entries()) {
            const copy = kept.ids[i];
            const answer = answers[i] ?? {};
            try {
                const up = await uploadForPoint(point.id, [file], (text, progress) => {
                    photoFields.hint.textContent = progress === null ? text : `${text} ${Math.round(progress * 100)} %`;
                }, [{ title: answer.title ?? titles[i] ?? null,
                      shrink: answer.kind === 'micro' && measureStyle.micro_shrink !== false }]);
                if (copy) await photos.drop(copy);
                whole++;
                shrunk += up.shrunk ?? 0;
            } catch (e) {
                console.warn('Full-size shot did not reach the cloud, the copy goes instead:', e);
                if (copy) await photos.release(copy);
                small++;
            }
        }
        photoFields.hint.textContent = '';

        if (kept.errors.length > 0) {
            status(t('Nicht lesbar: %s', kept.errors.join(', ')), true);
        } else if (small === 0) {
            status(arrivedWords(whole, shrunk, SHRINK_EDGE));
        } else {
            status(t('In voller Größe ging es nicht in die Cloud — das Foto geht verkleinert hoch.'), true);
        }
    }

    /**
     * The camera's road inside the Android app (galleryoriginals.js says the
     * whole of it; the maintainer's variant A, 03.10.2026). Every shot lies whole in
     * the phone's gallery already, so nothing here can lose it.
     *
     * MAY IT GO NOW (Wi-Fi, or mobile data with the tick, and a point the
     * server knows): handed to the app at once, no shrunk copy — the app
     * carries it on through a dark screen. OTHERWISE the shrunk copy goes the
     * usual way, so the find shows a picture at once, and the original waits
     * as a note until the page runs on the right network again; then it
     * replaces the copy. A MICROGRAPH with the shrink switch on stays a shrunk
     * copy for good — its owner wants 2048 px, and the app sends originals
     * only.
     */
    async function captureInApp(point, files, titles, answers) {
        status(files.length === 1 ? t('Foto wird aufbereitet …') : t('%d Fotos werden aufbereitet …', files.length));
        let whole = 0;
        let copies = 0;
        const errors = [];
        for (const [i, file] of files.entries()) {
            const answer = answers[i] ?? {};
            const micro = answer.kind === 'micro' && measureStyle.micro_shrink !== false;
            const shot = { name: file.name, size: file.size, type: file.type || 'image/jpeg', mtime: file.lastModified,
                           pointId: point.id, title: answer.title ?? titles[i] ?? null };
            const network = point.id > 0 && !micro ? fullSizeNetwork() : null;
            const handed = network ? await galleryOriginals.sendNow(shot, network) : 'failed';
            if (handed === 'sent') {
                whole++;
                continue;
            }
            const kept = await photos.capture(point.id, [file], [shot.title]);
            errors.push(...kept.errors);
            if (kept.ids[0]) {
                copies++;
                // A note only where the app has the original to carry later.
                if (!micro && handed !== 'gone') galleryOriginals.note({ ...shot, entryId: kept.ids[0] });
            }
        }
        if (errors.length > 0) {
            status(t('Nicht lesbar: %s', errors.join(', ')), true);
        } else if (copies === 0) {
            status(whole === 1 ? t('Das Foto geht in voller Größe in die Cloud.') : t('%d Fotos gehen in voller Größe in die Cloud.', whole));
        } else {
            status(measureStyle.full_size_mobile === true
                ? t('Gespeichert. Das Original liegt in der Galerie und geht in die Cloud, sobald du Netz hast.')
                : t('Gespeichert. Das Original liegt in der Galerie und geht im WLAN in die Cloud.'));
        }
    }

    /* What finishes the app's uploads: record, replace the copy, name it. */
    const galleryHooks = {
        dropCopy: (entryId) => photos.drop(entryId),
        title: (pointId, file, title) => request(`cloud.php?id=${pointId}&action=scale`, {
            method: 'POST', body: JSON.stringify({ file: file.id, title }),
        }),
    };

    /** After a recorded original: drop the replaced copy from the stock, redraw. */
    function galleryOriginalSettled(result) {
        if (!result) return;
        const point = state.points.get(result.pointId);
        if (point && result.replaced) {
            point.photos = (point.photos ?? []).filter((p) => p.id !== result.replaced);
        }
        if (state.selectedId === result.pointId) {
            drawPhotoGallery(state.points.get(result.pointId) ?? state.sharedPoints.get(result.pointId));
        }
    }

    /* THE ROUNDS: whatever finished while no page ran, then whatever may go
       now — at load, when the network returns, when the page comes back to
       the front, and when the app says the network changed. */
    async function galleryOriginalsRound() {
        if (!galleryOriginals.bridge() || GUEST || start.cloudUpload !== true || !navigator.onLine) return;
        try {
            for (const result of await galleryOriginals.settleFinished(galleryHooks)) galleryOriginalSettled(result);
            await galleryOriginals.sendWaiting(fullSizeNetwork());
        } catch (e) {
            console.warn('Gallery originals round:', e);
        }
    }

    window.addEventListener('yasaimapp:photo-sent', async (e) => {
        try {
            galleryOriginalSettled(await galleryOriginals.settle(e.detail ?? {}, galleryHooks));
        } catch (err) {
            console.warn('Finished upload not recorded yet:', err);
        }
    });

    window.addEventListener('online', galleryOriginalsRound);

    window.addEventListener('yasaimapp:network', galleryOriginalsRound);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') galleryOriginalsRound();
    });

    setTimeout(galleryOriginalsRound, 4000);

    /**
     * A finished file, straight into the user's own cloud.
     *
     * NOT THROUGH THIS SERVER, and that is the whole reason for the route: a
     * stack, a micrograph, a raw file from the proper camera weigh many times
     * what upload_max_filesize allows here — and several users' originals have
     * no business on a shared host at all.
     */
    photoFields.file?.addEventListener('change', async () => {
        const files = [...(photoFields.file.files || [])];
        photoFields.file.value = '';
        if (files.length === 0 || state.selectedId === null) return;

        const pointId = state.selectedId;

        // Ask first, then upload — once per file. The name is thus set
        // already before the first byte is on its way.
        /* Per file: kind and name. Here the kind decides about shrinking —
           micro with the switch on (Einstellungen → Fotos → Maßstab-Vorlagen)
           means a long edge of 2048 px; cloudupload.js notes the camera's
           dimensions beforehand, so that the scale stays right. */
        // Who took them, before their names — cancelled here, nothing goes up.
        const author = await askAuthor(files);
        if (author === null) return;
        const asked = await askSubjects(files);
        if (asked === null) return;
        const wishes = asked.map((a) => ({ title: a.title, takenBy: author.takenBy,
            shrink: a.kind === 'micro' && measureStyle.micro_shrink !== false }));

        photoFields.choose.disabled = true;

        try {
            const { uploadForPoint } = await import('./cloudupload.js');
            const result = await uploadForPoint(pointId, files, (text, progress) => {
                photoFields.hint.textContent = progress === null
                    ? text
                    : `${text} ${Math.round(progress * 100)} %`;
            }, wishes);

            // The rearranging is worth a word: folders have moved, and whoever
            // looks into the cloud afterwards should know why.
            status(result.moved > 0
                ? t('%d Aufnahme(n) abgelegt — %d Gattungsordner umgeräumt.', result.uploaded, result.moved)
                : t('%d Aufnahme(n) abgelegt.', result.uploaded));

            // Only redraw when the same point is still open: whoever switched on
            // in the meantime must not get someone else's recordings.
            if (state.selectedId === pointId) {
                await drawPhotoGallery(state.points.get(pointId) ?? state.sharedPoints.get(pointId));
            }
        } catch (e) {
            status(e.message || t('Das Hochladen ist fehlgeschlagen.'), true);
            if (state.selectedId === pointId) {
                await drawPhotoGallery(state.points.get(pointId) ?? state.sharedPoints.get(pointId));
            }
        } finally {
            photoFields.choose.disabled = false;
        }
    });

    // ---------------------------------------------------------------------------
    // Mikroskopieren (24.10.2026) — the microscope camera's stills,
    // through the window of microscope.js. What is done with a still is the
    // business of this file, the same as for „Aufnehmen" and „Auswählen": the
    // subject window, whole into the cloud with a held copy as the net, and
    // the scale of the objective it was taken through.
    // ---------------------------------------------------------------------------

    /* The scales of copies that went up instead of their whole still — set
       on the copy as soon as the server knows it (photos.onChange below).
       Only for the life of this page: at the desk with reception the copy
       goes up within seconds, and one lost on a reload is measured again in
       the photo window, as every picture without a scale. */
    const copyScales = new Map();

    /* ONE STILL AFTER THE OTHER into the cloud — the reasoning of
       cloudupload.js (one uplink, the first ones through). The shutter does
       not wait for it: the next slide is taken while the last still is
       still travelling. */
    let microscopeUploads = Promise.resolve();

    /* Not for the guest: his pictures lie on the server with his finds, and
       an excursion has no microscope on the table. */
    const microscope = GUEST ? { open() {} } : mountMicroscope({
        button: photoFields.microscope,
        presets: () => start.photo_scales ?? [],
        devices: () => start.scale_devices ?? [],
        // A device made in the window's calibration joins the page's list.
        setDevices: (list) => { start.scale_devices = list; },
        /* A preset calibrated in the window goes where the photo window
           reads them too — one list for the page. */
        setPresets: (list) => { start.photo_scales = list; },
        cloudReady: () => start.cloudUpload === true,
        // The view „Schlüssel" — the key tool's address, where a module offers one to this account.
        keyTool: () => start.keyTool?.url ?? null,
        /* A species confirmed in the window's key changed the find's name on
           the server; the page behind takes the find in afresh, or its next
           autosave would write the old name back (host.speciesConfirmed). */
        keyConfirmed: (pointId, name) => host.speciesConfirmed?.(pointId, name),
        microShrink: () => measureStyle.micro_shrink !== false,
        lineColours: () => ({ line: measureStyle.line_color ?? '#000000', seam: measureStyle.seam_color ?? '#ffffff' }),
        scaleStyle: () => ({ colour: measureStyle.scale_color ?? '#000000', seam: measureStyle.scale_seam_color ?? '#ffffff',
                             font: measureStyle.font_px ?? 16 }),
        save: async (file, scale, report, media = null) => {
            const pointId = microscopePoint;
            /* MIKRO, WITHOUT A CHOICE — it was taken through a microscope
               (askSubject, the microscope's way). The shrink warning asks
               as for every micrograph, once per opening of the window
               (reset below, where it opens). */
            const answer = await askSubject(file, 0, 1, { microscope: true });
            if (answer.cancelled) {
                report(t('Foto verworfen.'));
                return;
            }
            microscopeUploads = microscopeUploads
                .then(() => microscopeUpload(pointId, file, answer, scale, report, media))
                .catch((e) => console.warn('Microscope still:', e));
        },
    });

    /* The find the window was opened for — a click in the map behind it
       cannot change it, the window is modal; this only keeps the uploads
       of a closed window on the right find. */
    let microscopePoint = 0;

    photoFields.microscope?.addEventListener('click', () => {
        const point = state.points.get(state.selectedId) ?? state.sharedPoints.get(state.selectedId);
        if (!(point?.id > 0)) return;
        microscopePoint = point.id;
        // Five spore pictures in a row are one decision about shrinking.
        microWarnedNow = false;
        microscope.open(point.title, point.id);
    });

    /**
     * One still to its find: whole into the cloud where the cloud takes the
     * browser's uploads, the held copy thrown away on arrival; otherwise, or
     * when it breaks off, the copy goes up the usual way. The road of
     * captureFullSize, with two differences:
     *
     * NO NETWORK TICK. The tick „Volle Größe auch über mobile Daten" guards a
     * phone's data plan; the microscope camera hangs on a computer's USB at a
     * desk, and there a browser seldom says what it is on — Firefox never.
     * Asked here, the tick would shrink every micrograph on the iMac.
     *
     * THE SCALE RIDES WITH IT, in the request that names the picture
     * (cloudupload.js) — and onto the copy once it has a row.
     */
    async function microscopeUpload(pointId, file, answer, scale, report, media = null) {
        const say = (text) => {
            report(text);
            photoFields.hint.textContent = text;
        };
        const title = answer?.title ?? null;
        const whole = start.cloudUpload === true && navigator.onLine;
        const kept = await photos.capture(pointId, [file], [title], { hold: whole });
        const copy = kept.ids[0] ?? null;
        let arrived = null;   // what arrived, in words
        if (whole) {
            try {
                const { uploadForPoint, SHRINK_EDGE } = await import('./cloudupload.js');
                const up = await uploadForPoint(pointId, [file], (text, progress) => {
                    say(progress === null ? text : `${text} ${Math.round(progress * 100)} %`);
                }, [{ title, scale, media,
                      shrink: answer?.kind === 'micro' && measureStyle.micro_shrink !== false }]);
                arrived = arrivedWords(1, up.shrunk ?? 0, SHRINK_EDGE);
                if (copy) await photos.drop(copy);
            } catch (e) {
                console.warn('Microscope still did not reach the cloud, the copy goes instead:', e);
                if (copy && scale) copyScales.set(copy, scale);
                if (copy) await photos.release(copy);
            }
        } else if (copy && scale) {
            copyScales.set(copy, scale);
        }
        say('');
        if (kept.errors.length > 0) {
            say(t('Nicht lesbar: %s', kept.errors.join(', ')));
        } else if (arrived) {
            report(arrived);
            status(arrived);
        } else if (whole) {
            report(t('In voller Größe ging es nicht in die Cloud — das Foto geht verkleinert hoch.'));
            status(t('In voller Größe ging es nicht in die Cloud — das Foto geht verkleinert hoch.'), true);
        } else {
            report(t('1 Foto übernommen.'));
        }
        if (state.selectedId === pointId) {
            await drawPhotoGallery(state.points.get(pointId) ?? state.sharedPoints.get(pointId));
        }
    }

    // ---------------------------------------------------------------------------
    // Sequences at a find (22.09.2026) — the second voucher, text not picture.
    // includes/sequences.php holds the model: the sequencer's, co-owner like a
    // photographer, only he deletes. Here: the list in the Einordnung tab, the
    // window that hangs one on, FASTA to the clipboard or as a file.
    // ---------------------------------------------------------------------------

    function sequenceDay(stamp) {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(stamp ?? '');
        return m ? `${m[3]}.${m[2]}.${m[1]}` : '';
    }

    function drawSequences(point) {
        if (!sequenceFields.list) return;
        const rows = point?.sequences ?? [];
        const me = start.user?.id;
        sequenceFields.list.innerHTML = '';
        // A provisional point (negative id) has not reached the server — the
        // voucher needs the record it hangs on.
        sequenceFields.add.disabled = !(point?.id > 0);

        for (const s of rows) {
            const row = document.createElement('div');
            row.className = 'sequence-row';

            const head = document.createElement('div');
            head.className = 'sequence-head';
            const name = document.createElement('span');
            name.className = 'sequence-name';
            name.textContent = [s.marker || t('Sequenz'), t('%d bp', s.length)].join(' · ');
            head.append(name);
            const meta = [s.accession, s.lab, s.sample_no, s.primers].filter(Boolean).join(' · ');
            if (meta) {
                const m = document.createElement('span');
                m.className = 'sequence-meta';
                m.textContent = meta;
                head.append(m);
            }

            const buttons = document.createElement('span');
            buttons.className = 'sequence-buttons';
            const copy = document.createElement('button');
            copy.type = 'button';
            copy.className = 'symbol-button small';
            copy.title = t('Als FASTA kopieren');
            copy.setAttribute('aria-label', t('Als FASTA kopieren'));
            copy.append(icon('copy'));
            copy.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(sequenceFasta(s));
                    status(t('Sequenz als FASTA kopiert.'));
                } catch {
                    status(t('Kopieren nicht möglich — der Browser gibt die Zwischenablage nicht frei.'), true);
                }
            });
            const save = document.createElement('button');
            save.type = 'button';
            save.className = 'symbol-button small';
            save.title = t('Als FASTA-Datei speichern');
            save.setAttribute('aria-label', t('Als FASTA-Datei speichern'));
            save.append(icon('save'));
            save.addEventListener('click', () => saveSequenceFasta(s));
            buttons.append(copy, save);
            if (s.user_id === me) {
                const del = document.createElement('button');
                del.type = 'button';
                del.className = 'symbol-button small delete';
                del.title = t('Sequenz löschen');
                del.setAttribute('aria-label', t('Sequenz löschen'));
                del.append(icon('delete'));
                del.addEventListener('click', async () => {
                    const yes = await confirm({
                        title: t('Sequenz löschen?'),
                        text: t('Die Sequenz verschwindet vom Fund — für alle, die ihn sehen. Wiederherstellen geht nicht, nur neu hochladen.'),
                        button: t('Sequenz löschen'),
                        danger: true,
                    });
                    if (!yes) return;
                    try {
                        await api.post('sequences', { action: 'delete', id: s.id });
                        point.sequences = (point.sequences ?? []).filter(x => x.id !== s.id);
                        drawSequences(point);
                        status(t('Sequenz gelöscht.'));
                    } catch (error) {
                        status(t('Löschen fehlgeschlagen: %s', error.message), true);
                    }
                });
                buttons.append(del);
            }
            head.append(buttons);
            row.append(head);

            const who = document.createElement('div');
            who.className = 'sequence-who';
            who.textContent = [t('von %s', s.user_id === me ? t('mir') : personName(s.author)), sequenceDay(s.created_at), s.label]
                .filter(Boolean).join(' · ');
            row.append(who);

            const bases = document.createElement('code');
            bases.className = 'sequence-bases';
            bases.textContent = s.bases.length > 72 ? s.bases.slice(0, 72) + '…' : s.bases;
            bases.title = s.bases;
            row.append(bases);

            if (s.note) {
                const note = document.createElement('div');
                note.className = 'sequence-note';
                note.textContent = s.note;
                row.append(note);
            }
            sequenceFields.list.append(row);
        }
        if (rows.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'hint-text small';
            empty.textContent = t('Noch keine Sequenz am Fund.');
            sequenceFields.list.append(empty);
        }
        // The number on the tab counts pictures and sequences alike.
        host.sequenceTabCount = rows.length;
        detailPhotoTab();
    }

    /** The window: hang a sequence on the point in the detail window. */
    function openSequenceWindow() {
        const point = state.points.get(state.selectedId) ?? state.sharedPoints.get(state.selectedId);
        if (!point || !(point.id > 0)) return;
        if (!navigator.onLine) {
            status(t('Ohne Netz — eine Sequenz braucht die Verbindung zum Server.'), true);
            return;
        }
        sequenceFields.point.textContent = t('An „%s"', point.title || t('Ohne Titel'));
        sequenceFields.form.reset();
        sequenceFields.window.showModal();
        sequenceFields.marker.focus();
    }

    if (sequenceFields.window) {
        sequenceFields.add.addEventListener('click', openSequenceWindow);
        sequenceFields.cancel.addEventListener('click', () => sequenceFields.window.close());
        // A chosen file lands in the text field — read here, checked on the server.
        sequenceFields.file.addEventListener('change', () => {
            const f = sequenceFields.file.files?.[0];
            if (!f) return;
            f.text().then(text => { sequenceFields.text.value = text; });
        });
        sequenceFields.form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const point = state.points.get(state.selectedId) ?? state.sharedPoints.get(state.selectedId);
            if (!point) return;
            sequenceFields.save.disabled = true;
            try {
                const response = await api.post('sequences', {
                    point_id:  point.id,
                    text:      sequenceFields.text.value,
                    marker:    sequenceFields.marker.value,
                    accession: sequenceFields.accession.value,
                    sample_no: sequenceFields.sample.value,
                    lab:       sequenceFields.lab.value,
                    primers:   sequenceFields.primers.value,
                    note:      sequenceFields.note.value,
                });
                const fresh = response?.sequences ?? [];
                point.sequences = [...(point.sequences ?? []), ...fresh];
                drawSequences(point);
                sequenceFields.window.close();
                status(fresh.length === 1
                    ? t('Sequenz gespeichert — %d bp.', fresh[0].length)
                    : t('%d Sequenzen gespeichert.', fresh.length));
            } catch (error) {
                status(t('Speichern fehlgeschlagen: %s', error.message), true);
            } finally {
                sequenceFields.save.disabled = false;
            }
        });
    }

    // When the transfer goes through, the gallery should show it — the clock
    // disappears, the image stays.
    photos.onChange((what) => {
        if (what.type === 'rejected') {
            status(t('Ein Foto wurde abgelehnt: %s', what.reason), true);
        }

        // THE TRANSFERRED PHOTO GOES STRAIGHT INTO THE STOCK, otherwise it
        // vanishes before the user's eyes: it is gone from the local store
        // (transferred), and it only stands in state.points after the next
        // sync — up to a minute later. In between the gallery would be empty
        // although "übernommen" was just reported. Whoever sees that takes the
        // photo again.
        // The copy's server id — the original that replaces it later needs it.
        if (what.type === 'transferred' && what.photo?.id && what.entryId) {
            galleryOriginals.copyArrived(what.entryId, what.photo.id);
        }

        // A microscope still's copy: its scale, now that it has a row.
        if (what.type === 'transferred' && what.photo?.id && copyScales.has(what.entryId)) {
            const scale = copyScales.get(what.entryId);
            copyScales.delete(what.entryId);
            what.photo.scale = scale;
            request('photos.php', {
                method: 'POST',
                body: JSON.stringify({ id: what.photo.id, scale, _methode: 'PATCH' }),
            }).catch((e) => console.warn('Scale not set on the copy:', e));
        }

        if (what.type === 'transferred' && what.photo?.id) {
            const point = state.points.get(what.pointId);
            if (point) {
                point.photos = [...(point.photos ?? []).filter(f => f.id !== what.photo.id), what.photo];
            }
        }

        // 'dropped': a held copy went because its full-size shot arrived.
        if (['transferred', 'done', 'rejected', 'dropped'].includes(what.type) && state.selectedId != null) {
            // Look it up in the foreign store too: otherwise the completion of
            // an OWN photo would clear the gallery of a currently open foreign
            // point — drawPhotoGallery(undefined) draws nothing.
            drawPhotoGallery(state.points.get(state.selectedId)
                ?? state.sharedPoints.get(state.selectedId));
        }
    });

    return {
        clearPhotoGallery,
        drawPhotoGallery,
        drawSequences,
    };
}
