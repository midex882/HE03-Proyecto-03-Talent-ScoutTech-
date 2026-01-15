<?php
require_once dirname(__FILE__) . '/private/conf.php';
require_once dirname(__FILE__) . '/private/auth.php';

if (isset($_POST['name']) && isset($_POST['team'])) {
    $name = $_POST['name'];
    $team = $_POST['team'];

    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt = $db->prepare("UPDATE players SET name = :name, team = :team WHERE playerId = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    } else {
        $stmt = $db->prepare("INSERT INTO players (name, team) VALUES (:name, :team)");
    }
    
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':team', $team, SQLITE3_TEXT);
    
    $stmt->execute();
    header("Location: list_players.php");
}

$name = "";
$team = "";

if (isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT name, team FROM players WHERE playerId = :id");
    $stmt->bindValue(':id', $_GET['id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray();
    if ($row) {
        $name = $row['name'];
        $team = $row['team'];
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="css/style.css">
    <title>Práctica RA3 - Insert Player</title>
</head>
<body>
<header>
    <h1>Insert Player</h1>
</header>
<section>
    <form action="#" method="post">
        <label>Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"><br>
        <label>Team</label>
        <input type="text" name="team" value="<?= htmlspecialchars($team, ENT_QUOTES, 'UTF-8') ?>"><br>
        <input type="submit" value="Save">
    </form>
    <a href="list_players.php">Back to list</a>
</section>
</body>
</html>
