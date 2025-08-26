<?php
header("Content-Type: application/json");

$mysqli = new mysqli("127.0.0.1", "root", "example", "skripsi");

if ($mysqli->connect_error) {
    echo json_encode(["status" => "error", "message" => $mysqli->connect_error]);
    exit();
}

$result = $mysqli->query("SELECT 'API Connected to DB' as msg");
$row = $result->fetch_assoc();

echo json_encode(["status" => "success", "message" => $row["msg"]]);
