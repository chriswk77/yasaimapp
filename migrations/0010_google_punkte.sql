-- Bring the Google points to the live server.
--
-- A dump of the find spots from the local database: eight lists,
-- 150 points, 145 assignments. There are no photos, map_photos is empty.
--
-- Why as a migration and not via admin/takeout_import.php: that script runs
-- exclusively on the command line ("if (PHP_SAPI !== 'cli')"), and the live
-- server has none. The migration route is the same way out there as for
-- schema changes.
--
-- About the data in the repository: the .gitignore excludes exports with
-- find spots, and for good reason. Here it is a deliberate exception — the
-- repository is private and not shared, and migrations/ is locked on the
-- server (checked: the .sql returns 403, the directory 404).
--
-- @benutzer instead of a fixed number, because the user id differs:
-- locally it is 12, on the set-up server 1.
--
-- INSERT IGNORE so that the file runs through without consequence where the
-- points already exist. The ids were taken over as well, so afterwards they
-- match in both databases.
--
-- IMPORTANT — "WHERE @benutzer IS NOT NULL":
-- map_lists.user_id and map_points.user_id are NOT NULL and reference
-- map_users via foreign key. But setup.php first applies the migrations and
-- creates the account afterwards — on a fresh installation there is thus no
-- user yet at this point. Without the condition, the migration would fail
-- there on the foreign key, and because setup.php locks the button for the
-- account while migrations are pending, there would be no way out of the
-- bind. That is why the file inserts nothing when the user table is empty
-- and still runs through.
--
-- The consequence one has to know: on a fresh installation on an empty
-- database the points do NOT come along — the migration counts as applied
-- afterwards. They then have to be pulled in by hand from this file once
-- the account exists. On the current live server, where the account already
-- exists, the route works as intended.
--
-- Deleted entries are included, with geloescht_am. The trash looks the same
-- on both sides afterwards; whoever wants to empty it does so in the
-- application.

SET @benutzer := (SELECT id FROM map_users WHERE ist_admin = 1 ORDER BY id LIMIT 1);

INSERT IGNORE INTO map_lists (id, user_id, name, farbe, symbol, ist_hauptliste, sortierung, erstellt_am, geaendert_am, geloescht_am)
    SELECT z.* FROM (
        SELECT 9 AS id, @benutzer AS user_id, 'Meine Funde' AS name, '#c0392b' AS farbe, 'kreis' AS symbol, 0 AS ist_hauptliste, 0 AS sortierung, '2026-08-07 13:20:34' AS erstellt_am, '2026-08-07 13:20:34' AS geaendert_am, '2026-08-07 23:35:48' AS geloescht_am
        UNION ALL
        SELECT 18, @benutzer, 'Homberg', '#3a86c8', 'kreis', 0, 0, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 19, @benutzer, 'Fotospots', '#d98b3a', 'kreis', 0, 0, '2026-08-07 22:53:28', '2026-08-07 22:53:28', '2026-08-08 09:09:03'
        UNION ALL
        SELECT 20, @benutzer, 'Urwälder Europa', '#a05fc8', 'kreis', 0, 0, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 21, @benutzer, 'Seltene & schützenswerte Pilze', '#6aa84f', 'pilz', 0, 0, '2026-08-07 22:53:28', '2026-08-07 23:38:44', NULL
        UNION ALL
        SELECT 22, @benutzer, 'Urlaub 2023', '#3aa8a0', 'kreis', 0, 0, '2026-08-07 22:53:28', '2026-08-07 22:53:28', '2026-08-08 09:16:55'
        UNION ALL
        SELECT 23, @benutzer, 'Reiseziele', '#8a8f3a', 'kreis', 0, 0, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 40, @benutzer, 'Campingplätze', '#c0392b', 'dreieck', 0, 0, '2026-08-08 09:17:49', '2026-08-08 09:17:49', NULL
    ) AS z
    WHERE @benutzer IS NOT NULL;

INSERT IGNORE INTO map_points (id, user_id, lat, lon, genauigkeit_m, hoehe_m, titel, notiz, gefunden_am, schutzgebiet, erstellt_am, geaendert_am, geloescht_am)
    SELECT z.* FROM (
        SELECT 14 AS id, @benutzer AS user_id, 49.655895 AS lat, 7.502983 AS lon, NULL AS genauigkeit_m, NULL AS hoehe_m, 'Gesetzte Markierung' AS titel, 'Fomes fomentarius' AS notiz, NULL AS gefunden_am, NULL AS schutzgebiet, '2026-08-07 22:53:28' AS erstellt_am, '2026-08-07 22:53:28' AS geaendert_am, NULL AS geloescht_am
        UNION ALL
        SELECT 15, @benutzer, 49.993989, 9.483397, NULL, NULL, '49°59\'38.4\"N 9°29\'00.2\"E', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 16, @benutzer, 50.097209, 8.106885, NULL, NULL, 'Gesetzte Markierung', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', '2026-08-07 23:03:43'
        UNION ALL
        SELECT 17, @benutzer, 50.100117, 8.231322, NULL, NULL, '50°06\'00.4\"N 8°13\'52.8\"E', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', '2026-08-07 23:03:43'
        UNION ALL
        SELECT 18, @benutzer, 50.074469, 8.233618, NULL, NULL, 'Conocybe aurea', 'Conocybe aurea - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 19, @benutzer, 50.073127, 8.236026, NULL, NULL, 'Lepiota lilacea', 'Lepiota lilacea - RL V', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 20, @benutzer, 50.147154, 8.345764, NULL, NULL, 'Leccinellum crocipodium', 'Leccinellum crocipodium - RL 3\nbei Quercus spec.', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 21, @benutzer, 50.116564, 8.251441, NULL, NULL, 'Leccinellum crocipodium', 'Leccinellum crocipodium - RL 3\nbei Quercus spec.', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 22, @benutzer, 50.114175, 8.251132, NULL, NULL, 'Leccinellum crocipodium', 'Leccinellum crocipodium - RL 3\nbei Quercus spec.', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 23, @benutzer, 50.130399, 8.245908, NULL, NULL, 'Leccinum aurantiacum', 'Leccinum aurantiacum - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 24, @benutzer, 50.115212, 8.220787, NULL, NULL, 'Leccinum aurantiacum', 'Leccinum aurantiacum - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 25, @benutzer, 50.115444, 8.219491, NULL, NULL, 'Leccinum aurantiacum', 'Leccinum aurantiacum - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 26, @benutzer, 50.092190, 8.173107, NULL, NULL, 'Leccinum aurantiacum', 'Leccinum aurantiacum - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 27, @benutzer, 50.026571, 8.340905, NULL, NULL, 'Lepiota echinella', 'Lepiota echinella - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 28, @benutzer, 50.029749, 8.342218, NULL, NULL, 'Lepiota echinella', 'Lepiota echinella - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 29, @benutzer, 50.112174, 8.199176, NULL, NULL, 'Lactarius lilacinus', 'Lactarius lilacinus - RL 3\nbei Alnus', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 30, @benutzer, 50.112226, 8.198990, NULL, NULL, 'Cortinarius bibulus', 'Cortinarius bibulus', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 31, @benutzer, 50.067749, 8.175515, NULL, NULL, 'Aureoboletus gentilis', 'Aureoboletus gentilis - RL 3\nbei Quercus', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 32, @benutzer, 50.064006, 8.180870, NULL, NULL, 'Tubaria dispersa', 'Tubaria dispersa - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 33, @benutzer, 50.030999, 8.341318, NULL, NULL, 'Tubaria dispersa', 'Tubaria dispersa - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 34, @benutzer, 50.026648, 8.341076, NULL, NULL, 'Pseudoclitopilus rhodoleucus', 'Pseudoclitopilus rhodoleucus - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 35, @benutzer, 50.067688, 8.175433, NULL, NULL, 'Boletus aereus', 'Boletus aereus - RL V nach BArtSchV sammelverbot\nmit Quercus spec. vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 36, @benutzer, 50.065256, 8.174996, NULL, NULL, 'Leccinum duriusculum', 'Leccinum duriusculum - RL 3\nbei Populus spec.', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 37, @benutzer, 50.148092, 8.327603, NULL, NULL, 'Leccinum duriusculum', 'Leccinum duriusculum - RL 3\nbei Populus spec.', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 38, @benutzer, 50.108542, 8.253574, NULL, NULL, 'Leccinum duriusculum', 'Leccinum duriusculum - RL 3\nbei Populus spec.', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 39, @benutzer, 50.025773, 8.341093, NULL, NULL, 'Lactarius subumbonatus', 'Lactarius subumbonatus - RL V', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 40, @benutzer, 50.108450, 8.399744, NULL, NULL, 'Macrolepiota excoriata', 'Macrolepiota excoriata - RL 3', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 41, @benutzer, 50.147668, 8.327525, NULL, NULL, 'Tricholoma frondosae o. equestre', 'Tricholoma frondosae o. equestre\nvmtl. stark beeinträchtigt wegen Baumfällung (Pinus / Populus)', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 42, @benutzer, 50.147324, 8.327599, NULL, NULL, 'Tricholoma portentosum', 'Tricholoma portentosum - RL 3\nvmtl. erloschen wegen Baumfällung (Pinus)', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 43, @benutzer, 50.155694, 8.118953, NULL, NULL, 'Gyromitra infula', 'Gyromitra infula - RL V', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 44, @benutzer, 50.101699, 8.279554, NULL, NULL, 'Gyromitra infula', 'Gyromitra infula - RL V', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 45, @benutzer, 50.088406, 8.181576, NULL, NULL, 'Gyromitra infula', 'Gyromitra infula - RL V', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 46, @benutzer, 50.152547, 8.220504, NULL, NULL, 'Tricholoma imbricatum', 'Tricholoma imbricatum - RL V', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 47, @benutzer, 50.064101, 8.180550, NULL, NULL, 'Hypholoma tuberosum', 'Hypholoma tuberosum', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 48, @benutzer, 50.130235, 8.246717, NULL, NULL, 'Russula medullata', 'Russula medullata - RL 2', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 49, @benutzer, 50.105873, 8.254920, NULL, NULL, 'Russula medullata', 'Russula medullata - RL 2', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 50, @benutzer, 50.092229, 8.173049, NULL, NULL, 'Hygrophorus nemoreus', 'Hygrophorus nemoreus - RL V', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 51, @benutzer, 50.095168, 8.217392, NULL, NULL, 'Geoglossum cookeanum', 'Geoglossum cookeanum - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 52, @benutzer, 50.096989, 8.220933, NULL, NULL, 'Geoglossum cookeanum', 'Geoglossum cookeanum - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 53, @benutzer, 50.031120, 8.355686, NULL, NULL, 'Verpa conica', 'Verpa conica - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 54, @benutzer, 50.106630, 8.254094, NULL, NULL, 'Verpa bohemica', 'Verpa bohemica - RL 3', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 55, @benutzer, 50.106403, 8.253600, NULL, NULL, 'Verpa bohemica', 'Verpa bohemica - RL 3', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 56, @benutzer, 50.102011, 8.282618, NULL, NULL, 'Verpa bohemica', 'Verpa bohemica - RL 3', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 57, @benutzer, 50.128201, 8.305416, NULL, NULL, 'Verpa bohemica', 'Verpa bohemica - RL 3', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 58, @benutzer, 50.152264, 8.279411, NULL, NULL, 'Mniaecia jungermanniae', 'Mniaecia jungermanniae - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 59, @benutzer, 50.199903, 8.440136, NULL, NULL, 'Rhizocybe pruinosa', 'Rhizocybe pruinosa - RL V', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 60, @benutzer, 50.144323, 8.218930, NULL, NULL, 'Vibrissea truncorum', 'Vibrissea truncorum - RL 3', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 61, @benutzer, 50.200339, 8.429268, NULL, NULL, 'Vibrissea truncorum', 'Vibrissea truncorum - RL 3', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 62, @benutzer, 50.200325, 8.429528, NULL, NULL, 'Cudoniella tenuispora', 'Cudoniella tenuispora - RL 3', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 63, @benutzer, 50.144086, 8.220876, NULL, NULL, 'Cudoniella tenuispora', 'Cudoniella tenuispora - RL 3', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 64, @benutzer, 50.127946, 8.251757, NULL, NULL, 'Vibrissea flavovirens', 'Vibrissea flavovirens - RL V', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 65, @benutzer, 50.112342, 8.197768, NULL, NULL, 'Vibrissea flavovirens', 'Vibrissea flavovirens - RL V', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 66, @benutzer, 50.112026, 8.199158, NULL, NULL, 'Vibrissea flavovirens', 'Vibrissea flavovirens - RL V', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 67, @benutzer, 50.112062, 8.199006, NULL, NULL, 'Graddonia coracina', 'Graddonia coracina - RL 3', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 68, @benutzer, 50.085820, 7.907619, NULL, NULL, 'Miladina lecithina', 'Miladina lecithina - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 69, @benutzer, 50.114878, 8.223260, NULL, NULL, 'Russula insignis', 'Russula insignis - RL V\nbei Quercus', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 70, @benutzer, 50.075208, 7.863462, NULL, NULL, 'Entoloma sinuatum', 'Entoloma sinuatum - RL V', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 71, @benutzer, 50.138272, 8.215247, NULL, NULL, 'Fayodia bisphaerigera', 'Fayodia bisphaerigera\nvmtl. wegen Fällarbeiten erloschen', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 72, @benutzer, 50.218643, 8.432066, NULL, NULL, 'Fayodia bisphaerigera', 'Fayodia bisphaerigera', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 73, @benutzer, 50.267071, 8.416635, NULL, NULL, 'Clavaria fumosa', 'Clavaria fumosa - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 74, @benutzer, 50.267091, 8.416678, NULL, NULL, 'Neohygrocybe ingrata', 'Neohygrocybe ingrata - RL 1', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 75, @benutzer, 50.218657, 8.432054, NULL, NULL, 'Microglossum viride', 'Microglossum viride - RL 1', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 76, @benutzer, 50.218801, 8.430792, NULL, NULL, 'Microglossum viride', 'Microglossum viride - RL 1', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 77, @benutzer, 50.218809, 8.430874, NULL, NULL, 'Peziza limnaea', 'Peziza limnaea - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 78, @benutzer, 50.112584, 8.198642, NULL, NULL, 'Russula pumila', 'Russula pumila  - RL 1\nbei Alnus', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 79, @benutzer, 50.092411, 8.173123, NULL, NULL, 'Psathyrella maculata', 'Psathyrella maculata - RL 2\nsaprobiont - alte Baumstümpfe', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 80, @benutzer, 50.128507, 8.191862, NULL, NULL, 'Psathyrella maculata', 'Psathyrella maculata - RL 2\nsaprobiont - alte Baumstümpfe', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 81, @benutzer, 50.146028, 8.233023, NULL, NULL, 'Lactarius flexuosus', 'Lactarius flexuosus - RL G\nbei Betula', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 82, @benutzer, 50.113723, 8.193784, NULL, NULL, 'Ramariopsis crocea', 'Ramariopsis crocea - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 83, @benutzer, 50.113739, 8.193764, NULL, NULL, 'Ramariopsis cf. luteo-ochracea', 'Ramariopsis cf. luteo-ochracea', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 84, @benutzer, 50.113725, 8.193823, NULL, NULL, 'Clavulinopsis helveola', 'Clavulinopsis helveola - RL 3', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 85, @benutzer, 50.135872, 8.218061, NULL, NULL, 'Leucoagaricus americanus', 'Leucoagaricus americanus', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 86, @benutzer, 50.267424, 8.417758, NULL, NULL, 'Hygrocybe citrinovirens', 'Hygrocybe citrinovirens - RL 2', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 87, @benutzer, 50.115425, 8.220862, NULL, NULL, 'Hygrocybe citrinovirens', 'Hygrocybe citrinovirens - RL 2', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 88, @benutzer, 50.112730, 8.199429, NULL, NULL, 'Hygrocybe reidii', 'Hygrocybe reidii - RL 2', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 89, @benutzer, 50.115410, 8.220889, NULL, NULL, 'Hygrocybe reidii', 'Hygrocybe reidii - RL 2', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 90, @benutzer, 50.169372, 8.234091, NULL, NULL, 'Geastrum quadrifidum', 'Geastrum quadrifidum - RL G\nVmtl. bei Picea', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 91, @benutzer, 50.029992, 8.342135, NULL, NULL, 'Lepiota pseudolilacea', 'Lepiota pseudolilacea - RL 3', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 92, @benutzer, 50.101162, 8.212913, NULL, NULL, 'Ossicaulis lignatilis', 'Ossicaulis lignatilis - RL G\nAn alter Eiche', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 93, @benutzer, 50.125375, 8.250638, NULL, NULL, 'Entoloma cyanulum', 'Entoloma cyanulum - RL R', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 94, @benutzer, 50.127234, 8.250663, NULL, NULL, 'Clavaria messapica', 'Clavaria messapica', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 95, @benutzer, 50.131698, 8.250278, NULL, NULL, 'Pseudotricholoma metapodium', 'Pseudotricholoma metapodium - RL 1', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 96, @benutzer, 50.115300, 8.221017, NULL, NULL, 'Clavulinopsis dichotoma', 'Clavulinopsis dichotoma', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 97, @benutzer, 50.115305, 8.221012, NULL, NULL, 'Microglossum tenebrosum', 'Microglossum tenebrosum', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 98, @benutzer, 50.104356, 8.279599, NULL, NULL, 'Mycena olida', 'Mycena olida - RL V', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 99, @benutzer, 50.090368, 8.182032, NULL, NULL, 'Spongipellis pachyodon', 'Spongipellis pachyodon - RL G', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 100, @benutzer, 50.097419, 8.216579, NULL, NULL, 'Parmotrema perlatum', 'Parmotrema perlatum - RL V', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 101, @benutzer, 50.104272, 8.280761, NULL, NULL, 'Parmotrema perlatum', 'Parmotrema perlatum - RL V', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 102, @benutzer, 50.180392, 8.202910, NULL, NULL, 'Physconia distorta', 'Physconia distorta - RL 3', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 103, @benutzer, 50.176945, 8.204720, NULL, NULL, 'Ramalina fastigiata', 'Ramalina fastigiata - RL 2', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 104, @benutzer, 50.090976, 8.152209, NULL, NULL, 'Chaenotheca chlorella', 'Chaenotheca chlorella - RL 2\nAn Fraxinus', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 105, @benutzer, 50.177586, 8.204766, NULL, NULL, 'Hypotrachyna revoluta', 'Hypotrachyna revoluta - RL 1', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 106, @benutzer, 50.099715, 8.194391, NULL, NULL, 'Hypotrachyna revoluta', 'Hypotrachyna revoluta - RL 1', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 107, @benutzer, 50.094988, 8.218592, NULL, NULL, 'Sullellus queletii', 'Sullellus queletii - RL 3\nmit Quercus spec. vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 108, @benutzer, 50.094725, 8.218648, NULL, NULL, 'Sullellus queletii', 'Sullellus queletii - RL 3\nmit Quercus spec. vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 109, @benutzer, 50.095340, 8.218364, NULL, NULL, 'Rubroboletus legaliae', 'Rubroboletus legaliae - RL 1\nmit Quercus spec. vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 110, @benutzer, 50.094542, 8.217435, NULL, NULL, 'Rubroboletus legaliae', 'Rubroboletus legaliae - RL 1\nmit Quercus spec. vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 111, @benutzer, 50.095883, 8.217746, NULL, NULL, 'Rubroboletus satanas', 'Rubroboletus satanas - RL V\nmit Quercus spec. vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 112, @benutzer, 50.094754, 8.218057, NULL, NULL, 'Rubroboletus satanas', 'Rubroboletus satanas - RL V\nmit Quercus spec. vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 113, @benutzer, 50.096144, 8.218764, NULL, NULL, 'Rubroboletus satanas', 'Rubroboletus satanas - RL V\nmit Quercus spec. vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 114, @benutzer, 50.113526, 8.250695, NULL, NULL, 'Boletus aereus', 'Boletus aereus - RL V nach BArtSchV sammelverbot\nmit Quercus spec. vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 115, @benutzer, 50.098479, 8.278741, NULL, NULL, 'Boletus aereus', 'Boletus aereus - RL V nach BArtSchV sammelverbot\nmit Quercus spec. vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 116, @benutzer, 50.094089, 8.218178, NULL, NULL, 'Boletus aereus', 'Boletus aereus - RL V nach BArtSchV sammelverbot\nmit Quercus spec. vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 117, @benutzer, 50.095600, 8.218321, NULL, NULL, 'Boletus aereus', 'Boletus aereus - RL V nach BArtSchV sammelverbot\nmit Quercus spec. vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 118, @benutzer, 50.095868, 8.218059, NULL, NULL, 'Boletus aereus', 'Boletus aereus - RL V nach BArtSchV sammelverbot\nmit Quercus spec. vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 119, @benutzer, 50.148047, 8.327452, NULL, NULL, 'Xerocomus silwoodensis', 'Xerocomus silwoodensis - unbekannt\nmit Populus spec. vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 120, @benutzer, 50.079531, 8.167927, NULL, NULL, 'Xerocomus silwoodensis', 'Xerocomus silwoodensis - unbekannt\nmit Populus spec. vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 121, @benutzer, 50.067346, 8.189577, NULL, NULL, 'Hemileccinum depilatum', 'Hemileccinum depilatum RL G\nvmtl. mit Tilia spec.', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 122, @benutzer, 50.103274, 8.281714, NULL, NULL, 'Hemileccinum depilatum', 'Hemileccinum depilatum RL G\nvmtl. mit Tilia spec.', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 123, @benutzer, 50.086024, 8.176507, NULL, NULL, 'Butyriboletus appendiculatus', 'Butyriboletus appendiculatus RL3 nach BArtSchV sammelverbot\nvmtl. dort mit den alten Quercus sp. vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 124, @benutzer, 50.082090, 8.166285, NULL, NULL, 'Butyriboletus appendiculatus', 'Butyriboletus appendiculatus RL3 nach BArtSchV sammelverbot\nvmtl. dort mit den alten Castanea sativa vergesellschaftet', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 23:03:43', NULL
        UNION ALL
        SELECT 125, @benutzer, 46.621594, 12.187028, NULL, NULL, '46°37\'17.7\"N 12°11\'13.3\"E', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-08 09:12:13', '2026-08-08 09:12:13'
        UNION ALL
        SELECT 126, @benutzer, 46.001278, 11.183982, NULL, NULL, '46°00\'04.6\"N 11°11\'02.3\"E', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-08 09:12:43', '2026-08-08 09:12:43'
        UNION ALL
        SELECT 127, @benutzer, 47.634164, 11.746468, NULL, NULL, 'Gesetzte Markierung', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-08 09:13:11', '2026-08-08 09:13:11'
        UNION ALL
        SELECT 128, @benutzer, 47.547847, 11.684033, NULL, NULL, 'Gesetzte Markierung', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-08 09:10:57', '2026-08-08 09:10:57'
        UNION ALL
        SELECT 129, @benutzer, 47.982500, 13.095833, NULL, NULL, '47°58\'57.0\"N 13°05\'45.0\"E', 'Amanita friabilis (Fundbericht von 2000)', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 130, @benutzer, 48.166565, 8.181566, NULL, NULL, '48°09\'59.6\"N 8°10\'53.6\"E', 'Suillus flavidus nur Gebietsmarkierung', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 131, @benutzer, 46.195795, 11.865502, NULL, NULL, '46°11\'44.9\"N 11°51\'55.8\"E', 'Urlaub 2019', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 132, @benutzer, 38.261301, 15.632360, NULL, NULL, '38°15\'40.7\"N 15°37\'56.5\"E', 'Campingplatz Dschini?', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 133, @benutzer, 38.339833, 15.854167, NULL, NULL, '38°20\'23.4\"N 15°51\'15.0\"E', 'Dschini?', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 134, @benutzer, 51.803019, 11.760672, NULL, NULL, '51°48\'10.9\"N 11°45\'38.4\"E', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 135, @benutzer, 51.568738, 11.788483, NULL, NULL, '51°34\'07.5\"N 11°47\'18.5\"E', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 136, @benutzer, 42.866979, 1.832455, NULL, NULL, '42°52\'01.1\"N 1°49\'56.8\"E', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 137, @benutzer, 50.140163, 8.266828, NULL, NULL, '47R8+3P8', 'Entoloma euchroum', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 138, @benutzer, 50.215815, 6.819104, NULL, NULL, '50°12\'56.9\"N 6°49\'08.8\"E', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 139, @benutzer, 50.225750, 6.811778, NULL, NULL, '50°13\'32.7\"N 6°48\'42.4\"E', 'Hygrophorus marzuolus', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 140, @benutzer, 49.993369, 9.487690, NULL, NULL, '49°59\'36.1\"N 9°29\'15.7\"E', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 141, @benutzer, 49.742213, 8.528516, NULL, NULL, '49°44\'32.0\"N 8°31\'42.7\"E', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 142, @benutzer, 50.128953, 7.954128, NULL, NULL, '50°07\'44.2\"N 7°57\'14.9\"E', 'Allee', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 143, @benutzer, 50.150486, 7.939780, NULL, NULL, '50°09\'01.8\"N 7°56\'23.2\"E', 'Schiefergrube', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 144, @benutzer, 50.069837, 8.008081, NULL, NULL, '50°04\'11.4\"N 8°00\'29.1\"E', 'Interessante Allee für Flechten', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 145, @benutzer, 50.195992, 8.101388, NULL, NULL, '50°11\'45.6\"N 8°06\'05.0\"E', 'Flechtenfelsen', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 146, @benutzer, 50.219528, 8.420660, NULL, NULL, '50°13\'10.3\"N 8°25\'14.4\"E', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 147, @benutzer, 50.211938, 8.478688, NULL, NULL, '6F6H+QF', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 148, @benutzer, 50.134426, 8.988894, NULL, NULL, '50°08\'03.9\"N 8°59\'20.0\"E', 'Gyroporus cyanescens', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 149, @benutzer, 50.322118, 8.607601, NULL, NULL, '50°19\'19.6\"N 8°36\'27.4\"E', 'Porpolomopsis calyptriformis', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 150, @benutzer, 50.163009, 7.937312, NULL, NULL, '50°09\'46.8\"N 7°56\'14.3\"E', 'Schöner Fichtenforst', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 151, @benutzer, 49.939575, 9.176925, NULL, NULL, 'Gesetzte Markierung', 'Boletus pinicola', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 152, @benutzer, 47.440489, 11.678063, NULL, NULL, 'Gesetzte Markierung', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 153, @benutzer, 41.335102, 13.051447, NULL, NULL, 'Gesetzte Markierung', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 154, @benutzer, 44.071612, 11.002225, NULL, NULL, '44°04\'17.8\"N 11°00\'08.0\"E', 'Elves', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 155, @benutzer, 49.742209, 8.528517, NULL, NULL, '49°44\'32.0\"N 8°31\'42.7\"E', 'Catinella olivacea', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 156, @benutzer, 50.102527, 8.978150, NULL, NULL, '50°06\'09.1\"N 8°58\'41.3\"E', 'Waldbrandfläche', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 157, @benutzer, 50.082044, 8.898649, NULL, NULL, '50°04\'55.4\"N 8°53\'55.1\"E', 'Waldbrandfläche', NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 158, @benutzer, 49.954745, 9.279846, NULL, NULL, '49°57\'17.1\"N 9°16\'47.5\"E', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 159, @benutzer, 49.815754, 9.102057, NULL, NULL, '49°48\'56.7\"N 9°06\'07.4\"E', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 160, @benutzer, 49.785327, 9.199570, NULL, NULL, '49°47\'07.2\"N 9°11\'58.5\"E', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 161, @benutzer, 49.605077, 9.086776, NULL, NULL, '49°36\'18.3\"N 9°05\'12.4\"E', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 162, @benutzer, 49.616138, 9.627396, NULL, NULL, '49°36\'58.1\"N 9°37\'38.6\"E', NULL, NULL, NULL, '2026-08-07 22:53:28', '2026-08-07 22:53:28', NULL
        UNION ALL
        SELECT 191, @benutzer, 47.688398, 11.748082, NULL, NULL, '', NULL, '2026-08-08', NULL, '2026-08-08 09:18:24', '2026-08-08 09:18:24', NULL
    ) AS z
    WHERE @benutzer IS NOT NULL;

INSERT IGNORE INTO map_point_lists (point_id, list_id)
    SELECT z.* FROM (
        SELECT 14 AS point_id, 18 AS list_id
        UNION ALL
        SELECT 15, 23
        UNION ALL
        SELECT 16, 21
        UNION ALL
        SELECT 17, 21
        UNION ALL
        SELECT 18, 21
        UNION ALL
        SELECT 19, 21
        UNION ALL
        SELECT 20, 21
        UNION ALL
        SELECT 21, 21
        UNION ALL
        SELECT 22, 21
        UNION ALL
        SELECT 23, 21
        UNION ALL
        SELECT 24, 21
        UNION ALL
        SELECT 25, 21
        UNION ALL
        SELECT 26, 21
        UNION ALL
        SELECT 27, 21
        UNION ALL
        SELECT 28, 21
        UNION ALL
        SELECT 29, 21
        UNION ALL
        SELECT 30, 21
        UNION ALL
        SELECT 31, 21
        UNION ALL
        SELECT 32, 21
        UNION ALL
        SELECT 33, 21
        UNION ALL
        SELECT 34, 21
        UNION ALL
        SELECT 35, 21
        UNION ALL
        SELECT 36, 21
        UNION ALL
        SELECT 37, 21
        UNION ALL
        SELECT 38, 21
        UNION ALL
        SELECT 39, 21
        UNION ALL
        SELECT 40, 21
        UNION ALL
        SELECT 41, 21
        UNION ALL
        SELECT 42, 21
        UNION ALL
        SELECT 43, 21
        UNION ALL
        SELECT 44, 21
        UNION ALL
        SELECT 45, 21
        UNION ALL
        SELECT 46, 21
        UNION ALL
        SELECT 47, 21
        UNION ALL
        SELECT 48, 21
        UNION ALL
        SELECT 49, 21
        UNION ALL
        SELECT 50, 21
        UNION ALL
        SELECT 51, 21
        UNION ALL
        SELECT 52, 21
        UNION ALL
        SELECT 53, 21
        UNION ALL
        SELECT 54, 21
        UNION ALL
        SELECT 55, 21
        UNION ALL
        SELECT 56, 21
        UNION ALL
        SELECT 57, 21
        UNION ALL
        SELECT 58, 21
        UNION ALL
        SELECT 59, 21
        UNION ALL
        SELECT 60, 21
        UNION ALL
        SELECT 61, 21
        UNION ALL
        SELECT 62, 21
        UNION ALL
        SELECT 63, 21
        UNION ALL
        SELECT 64, 21
        UNION ALL
        SELECT 65, 21
        UNION ALL
        SELECT 66, 21
        UNION ALL
        SELECT 67, 21
        UNION ALL
        SELECT 68, 21
        UNION ALL
        SELECT 69, 21
        UNION ALL
        SELECT 70, 21
        UNION ALL
        SELECT 71, 21
        UNION ALL
        SELECT 72, 21
        UNION ALL
        SELECT 73, 21
        UNION ALL
        SELECT 74, 21
        UNION ALL
        SELECT 75, 21
        UNION ALL
        SELECT 76, 21
        UNION ALL
        SELECT 77, 21
        UNION ALL
        SELECT 78, 21
        UNION ALL
        SELECT 79, 21
        UNION ALL
        SELECT 80, 21
        UNION ALL
        SELECT 81, 21
        UNION ALL
        SELECT 82, 21
        UNION ALL
        SELECT 83, 21
        UNION ALL
        SELECT 84, 21
        UNION ALL
        SELECT 85, 21
        UNION ALL
        SELECT 86, 21
        UNION ALL
        SELECT 87, 21
        UNION ALL
        SELECT 88, 21
        UNION ALL
        SELECT 89, 21
        UNION ALL
        SELECT 90, 21
        UNION ALL
        SELECT 91, 21
        UNION ALL
        SELECT 92, 21
        UNION ALL
        SELECT 93, 21
        UNION ALL
        SELECT 94, 21
        UNION ALL
        SELECT 95, 21
        UNION ALL
        SELECT 96, 21
        UNION ALL
        SELECT 97, 21
        UNION ALL
        SELECT 98, 21
        UNION ALL
        SELECT 99, 21
        UNION ALL
        SELECT 100, 21
        UNION ALL
        SELECT 101, 21
        UNION ALL
        SELECT 102, 21
        UNION ALL
        SELECT 103, 21
        UNION ALL
        SELECT 104, 21
        UNION ALL
        SELECT 105, 21
        UNION ALL
        SELECT 106, 21
        UNION ALL
        SELECT 107, 21
        UNION ALL
        SELECT 108, 21
        UNION ALL
        SELECT 109, 21
        UNION ALL
        SELECT 110, 21
        UNION ALL
        SELECT 111, 21
        UNION ALL
        SELECT 112, 21
        UNION ALL
        SELECT 113, 21
        UNION ALL
        SELECT 114, 21
        UNION ALL
        SELECT 115, 21
        UNION ALL
        SELECT 116, 21
        UNION ALL
        SELECT 117, 21
        UNION ALL
        SELECT 118, 21
        UNION ALL
        SELECT 119, 21
        UNION ALL
        SELECT 120, 21
        UNION ALL
        SELECT 121, 21
        UNION ALL
        SELECT 122, 21
        UNION ALL
        SELECT 123, 21
        UNION ALL
        SELECT 124, 21
        UNION ALL
        SELECT 129, 23
        UNION ALL
        SELECT 130, 23
        UNION ALL
        SELECT 131, 23
        UNION ALL
        SELECT 132, 23
        UNION ALL
        SELECT 133, 23
        UNION ALL
        SELECT 134, 23
        UNION ALL
        SELECT 135, 23
        UNION ALL
        SELECT 136, 23
        UNION ALL
        SELECT 137, 23
        UNION ALL
        SELECT 138, 23
        UNION ALL
        SELECT 139, 23
        UNION ALL
        SELECT 140, 23
        UNION ALL
        SELECT 141, 23
        UNION ALL
        SELECT 142, 23
        UNION ALL
        SELECT 143, 23
        UNION ALL
        SELECT 144, 23
        UNION ALL
        SELECT 145, 23
        UNION ALL
        SELECT 146, 23
        UNION ALL
        SELECT 147, 23
        UNION ALL
        SELECT 148, 23
        UNION ALL
        SELECT 149, 23
        UNION ALL
        SELECT 150, 23
        UNION ALL
        SELECT 151, 23
        UNION ALL
        SELECT 152, 23
        UNION ALL
        SELECT 153, 23
        UNION ALL
        SELECT 154, 23
        UNION ALL
        SELECT 155, 23
        UNION ALL
        SELECT 156, 23
        UNION ALL
        SELECT 157, 23
        UNION ALL
        SELECT 158, 23
        UNION ALL
        SELECT 159, 23
        UNION ALL
        SELECT 160, 23
        UNION ALL
        SELECT 161, 23
        UNION ALL
        SELECT 162, 23
    ) AS z
    WHERE @benutzer IS NOT NULL;

