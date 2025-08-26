<?php
$host = "127.0.0.1";   // kalau 1 task definition (php + mysql)
$user = "root";
$pass = "example";
$db   = "skripsi";

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $mysqli->connect_error]);
    exit();
}
?>
