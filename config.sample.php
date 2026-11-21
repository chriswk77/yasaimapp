<?php
/**
 * Template for the real configuration.
 *
 * Setup:
 *   cp config.sample.php config.php
 *
 * Where the file may live, includes/bootstrap.php searches in this order:
 *   1. path from the environment variable MAP_CONFIG
 *   2. ../map-config.php  — one level above the project, i.e. outside the
 *      webroot when the subdomain points directly at this directory.
 *      That is the preferred location on the live server.
 *   3. config.php in the project directory — convenient for local
 *      development, blocked from delivery via .htaccess.
 *
 * The real config.php contains passwords and belongs nowhere it would be
 * backed up or versioned along with the rest.
 */

return [
    // Database. Locally MAMP with root/root, live the hoster's credentials.
    //
    // 'socket' takes precedence over host/port. Locally the entry is needed
    // because MAMP puts its socket somewhere other than where command-line PHP
    // looks for it — otherwise the application runs in the browser, but no
    // maintenance script does. Live, 'socket' stays empty and host/port apply.
    'db' => [
        'socket' => '/Applications/MAMP/tmp/mysql/mysql.sock',
        'host'   => 'localhost',
        'port'   => 3306,
        'name'   => 'karte',
        'user'   => 'root',
        'pass'   => 'root',
    ],

    // Directory under which the application is reachable in the browser.
    // Locally in a subdirectory: '/map/'. On its own subdomain: '/'.
    // (An existing config may still say 'basis_url' — that keeps working.)
    'base_url' => '/map/',

    // Photos no longer need an entry here: they live under
    // data/users/<identifier>/photos/ and are delivered by photo.php, which
    // checks permissions first. The location is not configurable — the
    // per-user quota counts exactly this directory.

    // Key for the cron jobs (cronjobs/), ONLY needed when the hoster's cron
    // calls them as a URL — the call must carry it as ?key=…:
    //
    //   https://…/cronjobs/daily.php?key=<value>
    //
    // Generate once with:
    //
    //   php -r 'echo bin2hex(random_bytes(24)), "\n";'
    //
    // Without this entry every HTTP call is refused (403); calls from the
    // command line need no key. It sits in the URL and thus in the hoster's
    // logs — that is acceptable, because all it guards is "may trigger a
    // backfill", not any data.
    // Sender of the application's mails (e-mail confirmation, migration
    // 0116). Empty: noreply@ the site's host. The host's mail() sends it,
    // so the address should belong to the domain, or SPF will mark it.
    'mail_from' => '',

    'cron_key' => '',

    // The vector map's offline data in pCloud (includes/offline_map.php):
    // a share link („Link teilen") per file. 'europe' is the OpenStreetMap
    // base map of Europe as PMTiles (admin/europe_extract.py says how it is
    // made). Empty or missing: the vector map offers no base-map download.
    // Not a password — the file is public map data — but the traffic runs
    // on the account that shares it, so it stays here and out of git.
    'offline_map' => [
        'europe' => '',
    ],

    // WHAT A MODULE NEEDS stands in the same array (modules/<name>/ brings
    // its own config.sample.php saying what); a module that is not
    // installed asks for nothing.

    // Set to 'live' as soon as the application is publicly reachable.
    // In 'live' mode PHP errors are logged instead of displayed and
    // session cookies are only set over HTTPS.
    // (An existing config may still say 'umgebung' => 'lokal' — that keeps
    // working; anything but 'live' counts as local.)
    'environment' => 'local',

    // The users' own cloud storage (Mein Bereich → Cloud).
    //
    // THESE BELONG TO THE INSTALLATION, NOT TO THE USER: an app is registered
    // once at the provider, after that every user connects THEIR OWN account.
    // That is why they sit here and not in the database.
    //
    // If the block is missing or empty, the interface does not offer the
    // provider at all — a connect button that fails on missing configuration
    // is worse than none.
    //
    // pCloud: create an app at https://docs.pcloud.com/my_apps/
    // Enter as the redirect URI there EXACTLY what this installation produces:
    //   <base_url>pages/cloud_return.php
    // live for example https://wkpilz.de/map/pages/cloud_return.php
    //
    // ONE APP TAKES SEVERAL RETURN ADDRESSES — measured on 18.08.2026, and
    // the documentation says nothing about it. So a second app for local
    // development is unnecessary: the live address and
    //   http://localhost:8888/map/pages/cloud_return.php
    // sit in the same app side by side, and both accounts stay connected.
    // The port belongs in it; whoever leaves it out gets
    // "This 'redirect_uri' is not authorized" and looks for the mistake
    // everywhere but in those four characters.
    'cloud' => [
        // KEY FOR THE STORED TOKENS. 32 bytes, base64. Generate once with:
        //
        //   php -r 'echo base64_encode(random_bytes(32)), "\n";'
        //
        // WHAT IT PROTECTS AND WHAT IT DOES NOT: it protects against a stolen
        // database dump, against a backup at the host, against SQL injection.
        // It does NOT protect against whoever operates this installation —
        // they hold database and this file. There is no cryptography against
        // the operator in an application the operator runs.
        //
        // IF IT IS MISSING, no token is stored: connecting then fails with a
        // clear message instead of quietly writing plaintext.
        //
        // CHANGING IT INVALIDATES every stored connection — the tokens can no
        // longer be read, and everyone reconnects. Two clicks each, but they
        // have to know.
        'key' => '',

        'pcloud' => [
            'client_id'     => '',
            'client_secret' => '',
        ],
        // Google Drive and OneDrive take the same two values. ONEDRIVE'S
        // SECRET EXPIRES (24 months at most): add
        //   'secret_expires' => '2028-10-02',
        // and administrators are reminded a month before, on the map and in
        // Administration → Cloud-Anbieter. Usually all of this is entered
        // there instead and kept in data/cloud.php.
    ],
];
