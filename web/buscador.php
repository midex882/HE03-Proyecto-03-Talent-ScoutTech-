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
    <title>Práctica RA3 - Search Results</title>
</head>
<body>
<header>
    <h1>Search Results</h1>
</header>
<section>
    <ul>
        <?php
        if (isset($_POST['name'])) {
            $stmt = $db->prepare("SELECT playerId, name, team FROM players WHERE name LIKE :name ORDER BY playerId DESC");
            // Concatenamos los % fuera de la consulta SQL
            $searchTerm = '%' . $_POST['name'] . '%';
            $stmt->bindValue(':name', $searchTerm, SQLITE3_TEXT);
            
            $result = $stmt->execute();
            
            while ($row = $result->fetchArray()) {
                echo "<li>
                        <div>
                        <span>Name: " . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "</span>
                        <span>Team: " . htmlspecialchars($row['team'], ENT_QUOTES, 'UTF-8') . "</span>
                        </div>
                        <div>
                        <a href=\"show_comments.php?id=" . $row['playerId'] . "\">(show/add comments)</a> 
                        <a href=\"insert_player.php?id=" . $row['playerId'] . "\">(edit player)</a>
                        </div>
                    </li>";
            }
        }
        ?>
    </ul>
    <a href="list_players.php">Back to list</a>
</section>
</body>
</html>
