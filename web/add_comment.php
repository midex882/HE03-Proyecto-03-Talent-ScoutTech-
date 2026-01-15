<?php
require_once dirname(__FILE__) . '/private/conf.php';

session_start();

if (isset($_POST['player_id']) && isset($_POST['body'])) {
    if (!isset($_SESSION['userId'])) {
        die("You must be logged in to comment.");
    }

    $playerId = $_POST['player_id'];
    $body = $_POST['body'];
    $userId = $_SESSION['userId'];

    if (!is_numeric($playerId)) {
        die("Invalid player ID.");
    }

    $stmt = $db->prepare("INSERT INTO comments (playerId, userId, body) VALUES (:playerId, :userId, :body)");
    $stmt->bindValue(':playerId', $playerId, SQLITE3_INTEGER);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':body', $body, SQLITE3_TEXT);

    $stmt->execute();

    header("Location: list_players.php");
}
?>
