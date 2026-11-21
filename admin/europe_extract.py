#!/usr/bin/env python3
"""
Cut Europe out of OpenFreeMap's planet MBTiles — the vector map's offline
data (30.09.2026).

    python3 admin/europe_extract.py ~/Karten/planet.mbtiles ~/Karten/europa.mbtiles

WHY THIS EXISTS. OpenFreeMap publishes its tiles (the OpenMapTiles schema
the vector map's style is written for) only as one planet file, 103 GB as
of 27.09.2026, and as MBTiles — an SQLite database, from which nobody can
read one area over the network. PMTiles can be read that way, and
`pmtiles convert` turns the one into the other; but it holds the original,
a temporary copy and the result on the disk at once, up to ~290 GB for the
planet, and the maintainer's Mac had 188 GB free by the Finder's count (156 by
the system's, see room_left). So Europe comes out first
(estimated 36 GB), the planet file can go, and only Europe is converted:
at most ~140 GB at any moment.

EUROPE IS A BOX, from the Azores to the Urals' edge and from the Canaries
to the North Cape — Iceland, Madeira and Cyprus included; the box takes a
strip of North Africa and the Near East along, which costs little. The
lowest levels (up to FULL_UP_TO) stay whole: the world overview is a few
megabytes, and a map that ends in the void when zoomed out looks broken.

MBTILES COUNTS ITS ROWS FROM THE BOTTOM (TMS), the web from the top; the
box is worked out in web tiles and turned over per level. pmtiles convert
turns them back.

The planet file is only read, never changed. Afterwards:

    rm ~/Karten/planet.mbtiles
    ~/Karten/pmtiles convert ~/Karten/europa.mbtiles ~/Karten/europa.pmtiles --tmpdir ~/Karten
    ~/Karten/pmtiles show ~/Karten/europa.pmtiles

(pmtiles is go-pmtiles from github.com/protomaps/go-pmtiles, a single
program — 1.31.2 made the file of 27.09.2026. The maintainer deleted ~/Karten
with it on 01.10.2026, once the file lay in pCloud; for the next round
fetch the release for macOS from that page again.)

THE NEXT ROUND, about once or twice a year (recommended 01.10.2026): a
fresh planet from OpenFreeMap — they rebuild it about weekly, the list of
versions stands in btrfs.openfreemap.com/dirs.txt under areas/planet/ —,
then the three steps above, then the new europa.pmtiles into pCloud. If
its share link changes, the new one goes into the LIVE config.php
('offline_map' => ['europe' => …]). Areas already on phones keep their
old tiles until they are loaded anew.
"""

import math
import os
import shutil
import sqlite3
import sys
import time

WEST, SOUTH, EAST, NORTH = -32.0, 27.0, 45.0, 72.0
FULL_UP_TO = 6          # levels kept whole, see above
BATCH = 2000            # tiles per insert
COMMIT_EVERY = 100_000  # tiles per commit
LAST_FREE = 3e9         # stop cleanly below this much free space



def tile_x(lon, z):
    return int((lon + 180.0) / 360.0 * 2 ** z)


def tile_y(lat, z):
    """The web tile row (0 at the top) of a latitude."""
    return int((1 - math.asinh(math.tan(math.radians(lat))) / math.pi) / 2 * 2 ** z)


def gigabytes(n):
    return f'{n / 1e9:.1f}'.replace('.', ',') + ' GB'


def count(n):
    return f'{n:,}'.replace(',', '.')


def room_left(target):
    """THE EMERGENCY BRAKE. The Mac this was written for had 156 GB truly
    free (Finder said 188, counting what macOS frees only on demand) —
    after the 103 GB planet, room for Europe's estimated 36 GB and not
    much more. A disk that runs full mid-write leaves SQLite broken and
    macOS unhappy; stopping a few gigabytes early leaves a clean half."""
    free = shutil.disk_usage(os.path.dirname(os.path.abspath(target))).free
    if free < LAST_FREE:
        sys.exit(f'Angehalten: nur noch {gigabytes(free)} frei. {target} ist unvollständig — bitte löschen,'
                 ' Platz schaffen (zum Beispiel den Papierkorb leeren) und neu starten.')


def main(source, target):
    if not os.path.isfile(source):
        sys.exit(f'Die Datei {source} gibt es nicht.')
    if os.path.exists(target):
        sys.exit(f'{target} gibt es schon — bitte erst löschen oder einen anderen Namen wählen.')

    free = shutil.disk_usage(os.path.dirname(os.path.abspath(target))).free
    print(f'Frei auf der Zielplatte: {gigabytes(free)} (gebraucht werden etwa 40 GB).')
    if free < 45e9:
        sys.exit('Zu wenig Platz — mindestens 45 GB sollten frei sein.')

    planet = sqlite3.connect(f'file:{os.path.abspath(source)}?mode=ro', uri=True)
    europe = sqlite3.connect(target)
    europe.executescript('''
        PRAGMA journal_mode = OFF;
        PRAGMA synchronous = OFF;
        CREATE TABLE metadata (name TEXT, value TEXT);
        CREATE TABLE tiles (zoom_level INTEGER, tile_column INTEGER, tile_row INTEGER, tile_data BLOB);
    ''')

    meta = dict(planet.execute('SELECT name, value FROM metadata'))
    meta['bounds'] = f'{WEST},{SOUTH},{EAST},{NORTH}'
    meta['name'] = (meta.get('name') or 'OpenFreeMap') + ' — Europa'
    europe.executemany('INSERT INTO metadata VALUES (?, ?)', meta.items())

    low = int(meta.get('minzoom', 0))
    high = int(meta.get('maxzoom', 14))
    started = time.time()
    total_tiles = 0
    total_bytes = 0

    for z in range(low, high + 1):
        n = 2 ** z
        if z <= FULL_UP_TO:
            rows = planet.execute(
                'SELECT tile_column, tile_row, tile_data FROM tiles WHERE zoom_level = ?', (z,))
        else:
            x0, x1 = tile_x(WEST, z), min(tile_x(EAST, z), n - 1)
            y0, y1 = tile_y(NORTH, z), min(tile_y(SOUTH, z), n - 1)
            # Turned over: the northern edge is the HIGHEST TMS row.
            r0, r1 = n - 1 - y1, n - 1 - y0
            rows = planet.execute(
                'SELECT tile_column, tile_row, tile_data FROM tiles'
                ' WHERE zoom_level = ? AND tile_column BETWEEN ? AND ? AND tile_row BETWEEN ? AND ?',
                (z, x0, x1, r0, r1))

        tiles = 0
        size = 0
        batch = []
        for column, row, data in rows:
            batch.append((z, column, row, data))
            tiles += 1
            size += len(data)
            if len(batch) >= BATCH:
                europe.executemany('INSERT INTO tiles VALUES (?, ?, ?, ?)', batch)
                batch = []
            if tiles % COMMIT_EVERY == 0:
                europe.commit()
                print(f'  Stufe {z}: {count(tiles)} Kacheln, {gigabytes(size)} …', flush=True)
                room_left(target)
        if batch:
            europe.executemany('INSERT INTO tiles VALUES (?, ?, ?, ?)', batch)
        europe.commit()
        total_tiles += tiles
        total_bytes += size
        minutes = (time.time() - started) / 60
        print(f'Stufe {z} fertig: {count(tiles)} Kacheln, {gigabytes(size)} — bisher {gigabytes(total_bytes)},'
              f' {minutes:.0f} Min.', flush=True)

    print('Lege das Verzeichnis an …', flush=True)
    europe.execute('CREATE UNIQUE INDEX tile_index ON tiles (zoom_level, tile_column, tile_row)')
    europe.commit()
    europe.close()
    planet.close()
    print(f'Fertig: {count(total_tiles)} Kacheln, {gigabytes(os.path.getsize(target))} in {target}.')
    print('Jetzt kann die Planetendatei gelöscht und Europa umgewandelt werden (siehe oben im Skript).')


if __name__ == '__main__':
    if len(sys.argv) != 3:
        sys.exit('Aufruf: python3 admin/europe_extract.py <planet.mbtiles> <europa.mbtiles>')
    main(os.path.expanduser(sys.argv[1]), os.path.expanduser(sys.argv[2]))
