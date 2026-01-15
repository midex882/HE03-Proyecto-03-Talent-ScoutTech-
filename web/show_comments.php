<?php
require_once dirname(__FILE__) . '/private/conf.php';
require_once dirname(__FILE__) . '/private/auth.php';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="css/style.css">
    <title>Práctica RA3 - Comments</title>
</head>
<body>
<header>
    <h1>Comments</h1>
</header>
<section>
    <?php
    if (isset($_GET['id'])) {
        $playerId = $_GET['id'];
        
        // Consultas preparadas para obtener info del jugador
        $stmtPlayer = $db->prepare("SELECT name, team FROM players WHERE playerId = :id");
        $stmtPlayer->bindValue(':id', $playerId, SQLITE3_INTEGER);
        $playerResult = $stmtPlayer->execute();
        $player = $playerResult->fetchArray();

        if ($player) {
            echo "<h2>Player: " . htmlspecialchars($player['name'], ENT_QUOTES, 'UTF-8') . "</h2>";
            echo "<h3>Team: " . htmlspecialchars($player['team'], ENT_QUOTES, 'UTF-8') . "</h3>";
        }

        echo "<h3>Comments</h3>";
        echo "<ul>";
        
        // Consultas preparadas para obtener comentarios
        $stmtComments = $db->prepare("SELECT username, body FROM comments C, users U WHERE C.userId = U.userId AND playerId = :id");
        $stmtComments->bindValue(':id', $playerId, SQLITE3_INTEGER);
        $result = $stmtComments->execute();

        while ($row = $result->fetchArray()) {
            echo "<li>
                    <strong>" . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . ":</strong> 
                    " . htmlspecialchars($row['body'], ENT_QUOTES, 'UTF-8') . "
                  </li>";
        }
        echo "</ul>";
    ?>
    
    <div>
        <h3>Add comment</h3>
        <form action="add_comment.php" method="post">
            <input type="hidden" name="player_id" value="<?= htmlspecialchars($playerId, ENT_QUOTES, 'UTF-8') ?>">
            <textarea name="body"></textarea><br>
            <input type="submit" value="Add comment">
        </form>
    </div>
    <?php
    }
    ?>
    <a href="list_players.php">Back to list</a>
</section>
</body>
</html>
