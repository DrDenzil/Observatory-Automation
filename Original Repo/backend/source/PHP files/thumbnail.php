<?php

if (!isset($_GET['id'])) {
    http_response_code(400);
    exit('No image given');
}

$dbid = preg_replace('/[^0-9]/', '', $_GET['id']);
if ($dbid === '') {
    http_response_code(400);
    exit('DBID must be numeric');
}

$jpeg = __DIR__ . '/jpeg/' . floor($dbid / 1000) . '/' . $dbid . '.jpg';
if (!file_exists($jpeg) || !is_readable($jpeg)) {
    http_response_code(404);
    exit('Thumbnail not found');
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($jpeg));
header('Cache-Control: public, max-age=86400');
readfile($jpeg);

?>
