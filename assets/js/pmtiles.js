/**
 * Read single tiles out of a PMTiles archive — without fetching the archive.
 *
 * FOR THE VECTOR MAP'S OFFLINE AREAS (30.09.2026). The map data comes as
 * PMTiles: Mapterhorn's terrain archives on their download server, and
 * Europe's OpenStreetMap tiles in the maintainer's pCloud. A PMTiles file is one
 * big file with a directory at its head; whoever knows where a tile lies
 * reads exactly those bytes with an HTTP range request. That is what an
 * area download does: a few requests for the directory, then one per tile —
 * a forest, not the 40 GB around it.
 *
 * OUR OWN READER, NOT THE LIBRARY. The format is small (version 3: a
 * 127-byte header, directories of varints, a Hilbert curve for the tile
 * numbers), and what is needed of it smaller still: find a tile, read it.
 * A vendored library would bring a MapLibre protocol, caches and sources we
 * do not use. tests/pmtiles.mjs holds this reader to a real archive.
 *
 * The spec: https://github.com/protomaps/PMTiles/blob/main/spec/v3/spec.md
 */

const HEADER_BYTES = 127;
// The first read takes the root directory along: the spec keeps it within
// the first 16 KiB, so one request answers both.
const FIRST_READ = 16384;

/** Compression as the header numbers it. */
export const COMPRESSION = { unknown: 0, none: 1, gzip: 2, brotli: 3, zstd: 4 };

/**
 * A reader over range requests.
 *
 * @param {(offset:number, length:number, signal?:AbortSignal) => Promise<ArrayBuffer>} read
 *        the bytes [offset, offset + length) of the archive
 */
export class PMTiles {
    constructor(read) {
        this.read = read;
        this.header = null;
        this._root = null;
        this._leaves = new Map();      // offset → Promise<entries>, see _leaf
    }

    /** A reader over plain HTTP ranges — for a server that allows them. */
    static fromUrl(url, init = {}) {
        return new PMTiles(async (offset, length, signal) => {
            const response = await fetch(url, {
                ...init,
                signal,
                headers: { ...(init.headers ?? {}), Range: `bytes=${offset}-${offset + length - 1}` },
            });
            if (response.status !== 206 && !(response.status === 200 && offset === 0)) {
                throw new Error(`PMTiles: HTTP ${response.status} für ${url}`);
            }
            const bytes = await response.arrayBuffer();
            // A server that ignores Range sends everything — only harmless
            // at the very start, and even then cut to what was asked.
            return bytes.byteLength > length ? bytes.slice(0, length) : bytes;
        });
    }

    /** Header and root directory, once. */
    async open(signal) {
        if (this.header) return this.header;
        this._opening ??= (async () => {
            const head = await this.read(0, FIRST_READ, signal);
            const header = parseHeader(head);
            const { rootOffset, rootLength } = header;
            const rootBytes = rootOffset + rootLength <= head.byteLength
                ? head.slice(rootOffset, rootOffset + rootLength)
                : await this.read(rootOffset, rootLength, signal);
            this._root = parseDirectory(await decompress(rootBytes, header.internalCompression));
            this.header = header;
            return header;
        })();
        try {
            return await this._opening;
        } catch (e) {
            this._opening = null;
            throw e;
        }
    }

    /**
     * Where a tile lies: { offset, length } in the archive, or null when the
     * archive has none there.
     */
    async locate(z, x, y, signal) {
        const header = await this.open(signal);
        if (z < header.minZoom || z > header.maxZoom) return null;
        const id = tileId(z, x, y);
        let entries = this._root;
        // Leaves may point at leaves; the spec allows depth, archives use
        // one or two levels. Four is room enough, and a guard.
        for (let depth = 0; depth < 4; depth++) {
            const entry = findEntry(entries, id);
            if (!entry) return null;
            if (entry.runLength > 0) {
                return { offset: header.tileDataOffset + entry.offset, length: entry.length };
            }
            entries = await this._leaf(header.leafOffset + entry.offset, entry.length, signal);
        }
        return null;
    }

    /**
     * A tile's bytes as they lie in the archive (still compressed as the
     * header says — tileCompression), or null.
     */
    async tile(z, x, y, signal) {
        const place = await this.locate(z, x, y, signal);
        if (!place) return null;
        return this.read(place.offset, place.length, signal);
    }

    /* Leaf directories are shared by thousands of tiles: fetched once each,
       kept as the promise so that parallel lookups wait for the same read. */
    _leaf(offset, length, signal) {
        let leaf = this._leaves.get(offset);
        if (!leaf) {
            leaf = this.read(offset, length, signal)
                .then((bytes) => decompress(bytes, this.header.internalCompression))
                .then(parseDirectory);
            leaf.catch(() => this._leaves.delete(offset));
            this._leaves.set(offset, leaf);
        }
        return leaf;
    }
}

function parseHeader(buffer) {
    const view = new DataView(buffer);
    const magic = String.fromCharCode(...new Uint8Array(buffer, 0, 7));
    if (magic !== 'PMTiles') throw new Error('PMTiles: keine PMTiles-Datei');
    const version = view.getUint8(7);
    if (version !== 3) throw new Error(`PMTiles: Version ${version} wird nicht gelesen`);
    const u64 = (at) => view.getUint32(at, true) + view.getUint32(at + 4, true) * 2 ** 32;
    return {
        rootOffset: u64(8),
        rootLength: u64(16),
        metadataOffset: u64(24),
        metadataLength: u64(32),
        leafOffset: u64(40),
        leafLength: u64(48),
        tileDataOffset: u64(56),
        tileDataLength: u64(64),
        internalCompression: view.getUint8(97),
        tileCompression: view.getUint8(98),
        tileType: view.getUint8(99),
        minZoom: view.getUint8(100),
        maxZoom: view.getUint8(101),
        bounds: [view.getInt32(102, true) / 1e7, view.getInt32(106, true) / 1e7,
                 view.getInt32(110, true) / 1e7, view.getInt32(114, true) / 1e7],
    };
}

/** A varint reader over bytes — numbers past 32 bits included, archives run to terabytes. */
function varints(bytes) {
    let at = 0;
    return () => {
        let value = 0;
        let scale = 1;
        for (;;) {
            const byte = bytes[at++];
            if (byte === undefined) throw new Error('PMTiles: Verzeichnis abgeschnitten');
            value += (byte & 0x7f) * scale;
            if (byte < 0x80) return value;
            scale *= 128;
        }
    };
}

function parseDirectory(buffer) {
    const next = varints(new Uint8Array(buffer));
    const count = next();
    const entries = new Array(count);
    let last = 0;
    for (let i = 0; i < count; i++) {
        last += next();
        entries[i] = { tileId: last, runLength: 0, length: 0, offset: 0 };
    }
    for (let i = 0; i < count; i++) entries[i].runLength = next();
    for (let i = 0; i < count; i++) entries[i].length = next();
    for (let i = 0; i < count; i++) {
        const value = next();
        // 0 means: right behind the one before.
        entries[i].offset = value === 0 && i > 0
            ? entries[i - 1].offset + entries[i - 1].length
            : value - 1;
    }
    return entries;
}

/** The entry holding a tile number: the last one starting at or before it. */
function findEntry(entries, id) {
    let low = 0;
    let high = entries.length - 1;
    while (low <= high) {
        const mid = (low + high) >> 1;
        const at = entries[mid].tileId;
        if (at === id) return entries[mid];
        if (at < id) low = mid + 1;
        else high = mid - 1;
    }
    // high is now the last entry below id.
    if (high >= 0) {
        const entry = entries[high];
        if (entry.runLength === 0) return entry;                   // a leaf covers the range
        if (id - entry.tileId < entry.runLength) return entry;     // a run of the same tile
    }
    return null;
}

/**
 * The tile number of z/x/y: every tile of the lower levels counted before
 * it, then its place on a Hilbert curve through its own level.
 */
export function tileId(z, x, y) {
    if (z > 26) throw new Error('PMTiles: Zoomstufe über 26');
    let id = (4 ** z - 1) / 3;
    let d = 0;
    const n = 2 ** z;
    for (let s = n / 2; s >= 1; s /= 2) {
        const rx = (x & s) > 0 ? 1 : 0;
        const ry = (y & s) > 0 ? 1 : 0;
        d += s * s * ((3 * rx) ^ ry);
        // Rotate the quadrant so the curve runs on in the next one.
        if (ry === 0) {
            if (rx === 1) {
                x = s - 1 - x;
                y = s - 1 - y;
            }
            [x, y] = [y, x];
        }
    }
    return id + d;
}

/** Undo the archive's own compression of directories or tiles. */
export async function decompress(buffer, compression) {
    if (compression === COMPRESSION.none || compression === COMPRESSION.unknown) return buffer;
    if (compression !== COMPRESSION.gzip) {
        throw new Error(`PMTiles: Kompression ${compression} wird nicht gelesen`);
    }
    const stream = new Blob([buffer]).stream().pipeThrough(new DecompressionStream('gzip'));
    return new Response(stream).arrayBuffer();
}
