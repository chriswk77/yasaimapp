# Bildschirmfotos der Hilfe

Hierher gehören die Bilder, die `pages/help.php` über `help_shot()` einbindet.

**Die Zahlen sind NICHT in den Bildern.** Sie liegen als Elemente darüber,
in Prozent auf dem Bild platziert (siehe `help_shot()` und `.help-mark` in
`app.css`). Wer eine Zahl verschieben will, ändert die Prozentwerte im
Aufruf — nie das Bild. Damit bleiben sie bei jeder Vergrößerung scharf,
und eine umformulierte Legende kostet keinen Bildeditor.

**Fehlt ein Bild, druckt `help_shot()` nichts** und der Abschnitt steht
trotzdem samt Legende. Die Seite ist also nie kaputt, nur manchmal noch
ohne Bilder.

## Maße

Breite **1200 px**, PNG. Das reicht, um einen Knopf zu lesen, und niemand
druckt diese Seite. Diese Bilder sind die einzigen im Projekt, die
ausgeliefert werden — sie bleiben deshalb klein.

## Aufnehmen

    node admin/help_shots.mjs            alle sieben
    node admin/help_shots.mjs karte      eines davon

Das Werkzeug fährt das Chrome auf dem Rechner headless über das
DevTools-Protokoll (wie `tests/neighbours.mjs`), meldet sich als anna aus
den Demodaten an (Sitzung gesetzt, kein Passwort), stellt 1200 px Breite
und die OpenStreetMap-Grundkarte ein, klickt sich in jede Ansicht und
schneidet das Stück aus, um das der Abschnitt geht. Es braucht den lokalen
Server, Chrome und `php admin/demodata.php --new`.

Von Hand geschossene Bilder gab es nie: die Hilfe stand vom 26.08.2026
bis zum 15.09.2026 ohne — „die Bilder folgen" folgten nicht, weil ein
Handbild nach jedem verschobenen Knopf noch einmal zu schießen wäre.

Nach jedem Lauf die **Prozentwerte der Nummern** in `pages/help.php`
gegen das neue Bild prüfen — sie sind die Mitte der Marke.

Die Dateinamen stehen in `pages/help.php`: `karte.png`, `listen.png`,
`punkte.png`, `filter.png`, `punkt.png`, `verwaltung.png`,
`organisieren.png`, `offline.png`, `import.png`, `tour.png`, `layer.png`, `legende.png`, `leiste.png`, `leiste-handy.png`, `kopfzeile.png`, `suche.png`, `gefunden.png`, `listen.png`, `liste.png`, `geteilt.png`, `freigabe.png`, `freigabe-fremd.png`, `fremde-liste.png`, `exkursionen.png`, `punkte-handy.png`, `filter.png`, `filter-text.png`, `filter-time.png`, `filter-place.png`, `filter-elevation.png`, `filter-identification.png`, `filter-taxonomy.png`, `filter-red-list.png`, `filter-edibility.png`, `filter-area-drawing.png`, `filter-area-done.png`, `punkt-fotos.png`, `punkt-einordnung.png`, `punkt-fremd.png`, `punkt-handy-peek.png`, `punkt-handy.png`, `punkt-handy-move.png`, `punkt-rahmen.png`.
