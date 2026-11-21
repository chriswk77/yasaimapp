<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/* ONLY A POST WITH THE FORM'S TOKEN LOGS OUT (security review,
   23.09.2026). As a GET, any foreign page could log a user out — an image
   tag pointing here was enough. A GET now just goes back to the map. */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect_to('index.php');
}
csrf_check();
logout();
redirect_to('login.php');
