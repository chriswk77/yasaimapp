<?php
/**
 * pCloud, played in memory — for the tests that move, rename and delete in
 * somebody's cloud (tests/cloud.php, tests/cloud_names.php).
 *
 * THE https STREAM WRAPPER answers pcloud_call() and the upload as the
 * provider would: register it for the length of a test, restore the real
 * one afterwards. Folder 100 is the app folder and is called 0, exactly as
 * inside a private pCloud app (cloud_root_id). The refusals are the
 * provider's own numbers — 2005 folder gone, 2006 folder not empty, 2009
 * file gone — and RENAMEFILE REPLACES A NAMESAKE, as pCloud's
 * documentation says it does („replaced atomically", read 04.10.2026):
 * the trap the renaming has to step around, so the fake must set it.
 */

declare(strict_types=1);

final class FakePcloud
{
    /** @var resource */
    public $context;
    /** @var array<int, array{0: string, 1: ?int}> id => [name, parent] */
    public static array $folders = [];
    /** @var array<int, array{0: string, 1: int}> */
    public static array $files = [];
    /** @var array<int, string> the bytes of uploaded files, by id */
    public static array $bytes = [];
    public static array $calls = [];
    /** What a file's PREVIEW link answers, by id, where it is not the file itself — as pCloud's own previews are. */
    public static array $thumbs = [];
    /** Files replaced by a rename — what must never happen to a picture. */
    public static array $replaced = [];
    /** When a file was last written, by id, where a test cares (seconds; 0 otherwise). */
    public static array $modified = [];
    public static int $next = 5000;
    private string $body = '';
    private int $at = 0;

    public static function reset(array $folders, array $files): void
    {
        self::$folders = $folders;
        self::$files = $files;
        self::$bytes = [];
        self::$thumbs = [];
        self::$calls = [];
        self::$replaced = [];
        self::$modified = [];
    }

    /** The name of a file by id, or null. */
    public static function name(int $id): ?string
    {
        return self::$files[$id][0] ?? null;
    }

    /** The names in a folder, sorted. */
    public static function names(int $folder): array
    {
        $out = [];
        foreach (self::$files as [$name, $parent]) {
            if ($parent === $folder) {
                $out[] = $name;
            }
        }
        sort($out);
        return $out;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened): bool
    {
        // A file's bytes, behind the link getfilelink handed out — the
        // download is no API call, it answers the file itself.
        if (parse_url($path, PHP_URL_HOST) === 'fake-dl.pcloud') {
            $id = (int) basename((string) parse_url($path, PHP_URL_PATH));
            self::$calls[] = 'download ' . $id;
            if (str_starts_with((string) parse_url($path, PHP_URL_PATH), '/thumb/') && isset(self::$thumbs[$id])) {
                $this->body = self::$thumbs[$id];
                return true;
            }
            $this->body = self::$bytes[$id] ?? '';
            return isset(self::$bytes[$id]);
        }
        $method = basename((string) parse_url($path, PHP_URL_PATH));
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);
        $http = stream_context_get_options($this->context)['http'] ?? [];
        $content = (string) ($http['content'] ?? '');
        $p = $query;
        if ($method !== 'uploadfile') {
            parse_str($content, $form);
            $p += $form;
        } else {
            $p['_body'] = $content;
        }
        self::$calls[] = $method . ' ' . ($p['folderid'] ?? $p['fileid'] ?? '') . (isset($p['nofiles']) ? ' nofiles' : '');
        $this->body = (string) json_encode(self::answer($method, $p));
        return true;
    }

    public function stream_read(int $n): string
    {
        $out = substr($this->body, $this->at, $n);
        $this->at += strlen($out);
        return $out;
    }

    public function stream_eof(): bool { return $this->at >= strlen($this->body); }
    public function stream_stat(): array { return []; }

    private static function answer(string $method, array $p): array
    {
        $file   = (int) ($p['fileid'] ?? 0);
        $folder = (int) ($p['folderid'] ?? -1);
        if ($folder === 0) {
            $folder = 100;   // inside the private app, 0 IS the app folder
        }
        switch ($method) {
            case 'stat':
                if (!isset(self::$files[$file])) {
                    return ['result' => 2009, 'error' => 'File not found.'];
                }
                return ['result' => 0, 'metadata' => ['name' => self::$files[$file][0],
                        'size' => strlen(self::$bytes[$file] ?? 'x'),
                        'parentfolderid' => self::$files[$file][1], 'contenttype' => 'image/jpeg']];
            case 'getfilelink':
                if (!isset(self::$files[$file])) {
                    return ['result' => 2009, 'error' => 'File not found.'];
                }
                return ['result' => 0, 'hosts' => ['fake-dl.pcloud'], 'path' => '/dl/' . $file];
            // The preview's link — behind it the file's own bytes: what the
            // thumbnail cache does with a picture larger than a preview is
            // part of what tests/thumbs.php wants to see.
            case 'getthumblink':
                if (!isset(self::$files[$file])) {
                    return ['result' => 2009, 'error' => 'File not found.'];
                }
                return ['result' => 0, 'hosts' => ['fake-dl.pcloud'], 'path' => '/thumb/' . $file];
            case 'deletefile':
                if (!isset(self::$files[$file])) {
                    return ['result' => 2009, 'error' => 'File not found.'];
                }
                unset(self::$files[$file]);
                return ['result' => 0];
            case 'renamefile':
                if (!isset(self::$files[$file])) {
                    return ['result' => 2009, 'error' => 'File not found.'];
                }
                $to   = isset($p['tofolderid']) ? (int) $p['tofolderid'] : self::$files[$file][1];
                $to   = $to === 0 ? 100 : $to;
                $name = (string) ($p['toname'] ?? self::$files[$file][0]);
                if (!isset(self::$folders[$to])) {
                    return ['result' => 2005, 'error' => 'Directory does not exist.'];
                }
                foreach (self::$files as $id => [$n, $parent]) {
                    if ($id !== $file && $parent === $to && $n === $name) {
                        self::$replaced[] = $id;   // pCloud's silent replace
                        unset(self::$files[$id]);
                    }
                }
                self::$files[$file] = [$name, $to];
                return ['result' => 0, 'metadata' => ['fileid' => $file, 'name' => $name, 'parentfolderid' => $to]];
            case 'renamefolder':
                if (!isset(self::$folders[$folder])) {
                    return ['result' => 2005, 'error' => 'Directory does not exist.'];
                }
                $to = isset($p['tofolderid']) ? (int) $p['tofolderid'] : (int) self::$folders[$folder][1];
                $to = $to === 0 ? 100 : $to;
                if (!isset(self::$folders[$to])) {
                    return ['result' => 2005, 'error' => 'Directory does not exist.'];
                }
                self::$folders[$folder] = [(string) ($p['toname'] ?? self::$folders[$folder][0]), $to];
                return ['result' => 0, 'metadata' => ['folderid' => $folder, 'parentfolderid' => $to]];
            case 'createfolderifnotexists':
                if (!isset(self::$folders[$folder])) {
                    return ['result' => 2005, 'error' => 'Directory does not exist.'];
                }
                foreach (self::$folders as $id => [$n, $parent]) {
                    if ($parent === $folder && $n === (string) $p['name']) {
                        return ['result' => 0, 'created' => false, 'metadata' => ['folderid' => $id]];
                    }
                }
                $id = self::$next++;
                self::$folders[$id] = [(string) $p['name'], $folder];
                return ['result' => 0, 'created' => true, 'metadata' => ['folderid' => $id]];
            case 'uploadfile':
                preg_match('/filename="([^"]*)"\r\n[^\r]*\r\n\r\n(.*)\r\n--/s', (string) $p['_body'], $m);
                $name = $m[1] ?? 'x';
                $bytes = $m[2] ?? '';
                $id = null;
                foreach (self::$files as $fid => [$n, $parent]) {
                    if ($parent === $folder && $n === $name) {
                        if (!empty($p['renameifexists'])) {
                            $name = pathinfo($name, PATHINFO_FILENAME) . ' (1).' . pathinfo($name, PATHINFO_EXTENSION);
                        } else {
                            $id = $fid;   // replace in place
                        }
                    }
                }
                $id ??= self::$next++;
                self::$files[$id] = [$name, $folder];
                self::$bytes[$id] = $bytes;
                return ['result' => 0, 'fileids' => [$id],
                        'metadata' => [['fileid' => $id, 'name' => $name, 'size' => strlen($bytes)]]];
            case 'listfolder':
                if (!isset(self::$folders[$folder])) {
                    return ['result' => 2005, 'error' => 'Directory does not exist.'];
                }
                $meta = ['folderid' => $folder, 'name' => self::$folders[$folder][0],
                         'contents' => self::contents($folder, !empty($p['nofiles']), !empty($p['recursive']))];
                if (self::$folders[$folder][1] !== null) {
                    $meta['parentfolderid'] = self::$folders[$folder][1];
                }
                return ['result' => 0, 'metadata' => $meta];
            case 'deletefolder':
                if ($folder === 100) {
                    return ['result' => 2003, 'error' => 'Access denied.'];
                }
                if (!isset(self::$folders[$folder])) {
                    return ['result' => 2005, 'error' => 'Directory does not exist.'];
                }
                foreach ([...self::$folders, ...self::$files] as [, $parent]) {
                    if ($parent === $folder) {
                        return ['result' => 2006, 'error' => 'Folder is not empty.'];
                    }
                }
                unset(self::$folders[$folder]);
                return ['result' => 0];
        }
        return ['result' => 2000, 'error' => 'Unknown method in the test: ' . $method];
    }

    /** $recursive: every folder carries its own `contents`, as pCloud's recursive=1 answers (the survey and cloud_spelling.php read the whole tree in one call). */
    private static function contents(int $folder, bool $nofiles, bool $recursive = false): array
    {
        $out = [];
        foreach (self::$folders as $id => [$name, $parent]) {
            if ($parent === $folder) {
                $out[] = ['isfolder' => true, 'folderid' => $id, 'name' => $name, 'parentfolderid' => $folder]
                    + ($recursive ? ['contents' => self::contents($id, $nofiles, true)] : []);
            }
        }
        foreach ($nofiles ? [] : self::$files as $id => [$name, $parent]) {
            if ($parent === $folder) {
                $out[] = ['isfolder' => false, 'fileid' => $id, 'name' => $name, 'parentfolderid' => $folder,
                          'size' => strlen(self::$bytes[$id] ?? 'x'), 'modified' => self::$modified[$id] ?? 0];
            }
        }
        return $out;
    }
}
