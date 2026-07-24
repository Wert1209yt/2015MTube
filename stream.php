<?php
if (!isset($_GET['v'])) {
    header("HTTP/1.1 400 Bad Request");
    die("No video ID provided");
}

$command = "streamlink --stream-url \"https://youtube.com/watch?v=" . $_GET['v'] . "\" 360p 2>&1";
$streamUrl = trim(exec($command));

if (empty($streamUrl) || strpos($streamUrl, 'http') !== 0) {
    header("HTTP/1.1 500 Internal Server Error");
    die("Streamlink Error: " . $streamUrl);
}

header("Location: " . $streamUrl, true, 302);
?>