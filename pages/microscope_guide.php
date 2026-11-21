<?php
/**
 * The guide for building a camera program — as a Markdown file to save
 * (Einstellungen → Fotos → Mikroskop-Kamera, 24.10.2026).
 *
 * WHO READS IT: an AI (Claude Code, Codex …) that a friend with another
 * microscope camera hands it to, and that friend, led by the AI step by
 * step. It says how to build a program that serves the camera the way
 * MicroCam serves the maintainer's — and, word for word, what YASAIMAPP expects
 * of it (assets/js/microscope.js is the other side; whoever changes one
 * changes the guide in the same step).
 *
 * WHY THROUGH THIS PAGE AND NOT AS A FILE IN assets/: Markdown is neither
 * deployed nor served here (.htaccess, „Markdown is not served"; deploy.yml,
 * AUSSCHLUSS) — CLAUDE.md once answered with 200. So the text lies as .txt
 * under includes/, which the browser cannot reach, and this page hands it
 * out with the name and type of a Markdown file.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

login_required();

$text = (string) file_get_contents(__DIR__ . '/../includes/microscope_guide.txt');

header('Content-Type: text/markdown; charset=utf-8');
header('Content-Disposition: attachment; filename="YASAIMAPP-Mikroskop-Kamera-Anleitung.md"');
header('Content-Length: ' . strlen($text));
header('Cache-Control: no-cache');
echo $text;
