<?php
header("Content-Type: application/json");
require "db.php";

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $result = $mysqli->query("SELECT * FROM notes ORDER BY created_at DESC");
        $notes = [];
        while ($row = $result->fetch_assoc()) {
            $notes[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $notes]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents("php://input"), true);
        $title = $mysqli->real_escape_string($input["title"]);
        $content = $mysqli->real_escape_string($input["content"]);

        $mysqli->query("INSERT INTO notes (title, content) VALUES ('$title', '$content')");
        echo json_encode(["status" => "success", "message" => "Note added"]);
        break;

    case 'PUT':
        $input = json_decode(file_get_contents("php://input"), true);
        $id = (int)$input["id"];
        $title = $mysqli->real_escape_string($input["title"]);
        $content = $mysqli->real_escape_string($input["content"]);

        $mysqli->query("UPDATE notes SET title='$title', content='$content' WHERE id=$id");
        echo json_encode(["status" => "success", "message" => "Note updated"]);
        break;

    case 'DELETE':
        $input = json_decode(file_get_contents("php://input"), true);
        $id = (int)$input["id"];

        $mysqli->query("DELETE FROM notes WHERE id=$id");
        echo json_encode(["status" => "success", "message" => "Note deleted"]);
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        break;
}
?>
