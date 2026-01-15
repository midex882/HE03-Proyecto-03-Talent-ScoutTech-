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
    <title>Práctica RA3 - Players list</title>
</head>
<body>
<header>
    <h1>Players list</h1>
</header>
<section>
    <ul>
        <?php
        $query = "SELECT playerId, name, team FROM players ORDER BY playerId DESC";
        $result = $db->query($query);
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
        ?>
    </ul>
    <form action="#" method="post" class="menu-form">
        <input type="submit" name="Logout" value="Logout" class="logout">
    </form>
    <a href="insert_player.php">Add a new player</a>
    <a href="buscador.html">Search a player</a>
</section>
</body>
</html>
