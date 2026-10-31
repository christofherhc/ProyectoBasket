#!/usr/bin/env bash
# Suite de regresión del proyecto Liga de Básquetbol.
# Comprueba que los errores documentados en la auditoría ya no se reproducen.
#
# Uso:  ./tests/regresion.sh [URL_BASE]
# Por defecto: http://localhost/ProyectoBasket-Deploy/
# También acepta la entrada antigua: .../ProyectoBasket-Deploy/public/index.php
#
# Requisitos: la aplicación desplegada en htdocs y MySQL/MariaDB arrancados.
#
# AVISO: esta suite MODIFICA la base de datos (crea registros de prueba) y al
# terminar la reinicia desde database/schema.sql + database/seeds.sql. No la
# ejecutes contra una base con datos reales.
set -u

BASE="${1:-http://localhost/ProyectoBasket-Deploy/}"
JAR="$(mktemp -t ck.XXXXXX)"
PASS=0
FAIL=0
TOKEN=""

codigo() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
cuerpo() { curl -s "$@"; }

# POST que devuelve el CODIGO de estado.
POST() { curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -X POST "$BASE?$1" --data "$2"; }
# POST que devuelve el CUERPO.
POSTB() { curl -s -b "$JAR" -X POST "$BASE?$1" --data "$2"; }
# POST que devuelve el cuerpo siguiendo redirecciones.
POSTL() { curl -sL -b "$JAR" -X POST "$BASE?$1" --data "$2"; }

# check <descripción> <esperado> <obtenido>
check() {
  if [ "$2" = "$3" ]; then
    printf '  OK    %-56s %s\n' "$1" "$3"; PASS=$((PASS + 1))
  else
    printf '  FALLA %-56s esperado=%s obtenido=%s\n' "$1" "$2" "$3"; FAIL=$((FAIL + 1))
  fi
}

# no500 <descripción> <obtenido>  (acepta cualquier cosa menos 500)
no500() {
  if [ "$2" = "500" ]; then
    printf '  FALLA %-56s devolvio 500\n' "$1"; FAIL=$((FAIL + 1))
  else
    printf '  OK    %-56s %s\n' "$1" "$2"; PASS=$((PASS + 1))
  fi
}

contiene() {
  case "$3" in
    *"$2"*) printf '  OK    %-56s presente\n' "$1"; PASS=$((PASS + 1)) ;;
    *)      printf '  FALLA %-56s ausente: %s\n' "$1" "$2"; FAIL=$((FAIL + 1)) ;;
  esac
}

# El bloqueo de intentos de login se guarda en el servidor, en un archivo por
# IP dentro de la carpeta temporal. La suite lo limpia al empezar y al
# terminar, para no dejar bloqueada la IP de quien la ejecuta y para que el
# resultado no dependa de una ejecucion anterior.
LOCKDIRS="$(/opt/lampp/bin/php -r 'echo sys_get_temp_dir();' 2>/dev/null || echo /tmp)/liga_basquet_login /opt/lampp/temp/liga_basquet_login"

limpiar_bloqueo() {
  for d in $LOCKDIRS; do
    rm -f "$d"/*.json 2>/dev/null
  done
}

limpiar_bloqueo

# Lee un valor suelto de la base. Lo usan las comprobaciones que necesitan
# contrastar lo que se guardo con lo que hay realmente en la tabla.
MYSQL_BIN="${MYSQL_BIN:-/opt/lampp/bin/mysql}"
mysql_valor() {
  "$MYSQL_BIN" -u root -N -B liga_basquet -e "$1" 2>/dev/null | head -1
}

# =============================================
# Comprobación previa: sin esto, si falta la base de datos la suite falla
# en casi todo y el mensaje no dice cuál es el problema real.
# =============================================
preflight() {
  echo "════════════════════════════════════════════════════════════"
  echo " 0. Comprobaciones previas"
  echo "════════════════════════════════════════════════════════════"

  if ! command -v "$MYSQL_BIN" >/dev/null 2>&1; then
    printf '  ERROR %-54s no se encuentra el cliente MySQL (%s)\n' "el cliente mysql" "$MYSQL_BIN"
    return 1
  fi

  if ! "$MYSQL_BIN" -u root -N -B -e "SELECT 1" >/dev/null 2>&1; then
    printf '  ERROR %-54s no se puede conectar a MySQL como root\n' "conexion a MySQL"
    return 1
  fi

  if [ "$("$MYSQL_BIN" -u root -N -B -e \
        "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='liga_basquet'" \
        2>/dev/null | head -1)" != "1" ]; then
    printf '  ERROR %-54s la base de datos liga_basquet no existe\n' "base de datos"
    echo
    echo "  Crea la base de datos importando el esquema. Ojo: el comando va"
    echo "  SIN el nombre de la base, porque schema.sql ya hace el CREATE"
    echo "  DATABASE y el USE por dentro:"
    echo
    echo "      mysql -u root < database/schema.sql"
    echo "      mysql -u root < database/seeds.sql   # datos de prueba (opcional)"
    echo
    echo "  Si la base ya existe pero le faltan tablas, vuelve a importar el esquema."
    return 1
  fi

  TABLAS=$("$MYSQL_BIN" -u root -N -B -e \
    "SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA='liga_basquet'
        AND TABLE_NAME IN ('usuarios','equipos','jugadores','partidos')" 2>/dev/null | head -1)
  if [ "$TABLAS" != "4" ]; then
    printf '  ERROR %-54s faltan tablas (hay %s de 4)\n' "tablas del esquema" "${TABLAS:-0}"
    echo
    echo "      mysql -u root < database/schema.sql"
    return 1
  fi

  CODIGO=$(codigo "$BASE?controller=Auth&action=index")
  if [ "$CODIGO" = "000" ] || [ "$CODIGO" = "500" ]; then
    printf '  ERROR %-54s la aplicacion no responde (HTTP %s)\n' "la aplicacion" "$CODIGO"
    echo
    echo "  Comprueba que Apache está arrancado y que la URL es correcta:"
    echo "      $BASE"
    return 1
  fi

  printf '  OK    %-56s %s\n' "MySQL accesible, base de datos y tablas presentes" "4 tablas"
  printf '  OK    %-56s %s\n' "la aplicacion responde" "HTTP $CODIGO"
  echo
  return 0
}

if ! preflight; then
  echo
  echo "════════════════════════════════════════════════════════════"
  echo " La suite no puede continuar sin la base de datos y la aplicacion."
  echo "════════════════════════════════════════════════════════════"
  rm -f "$JAR"
  exit 1
fi

noContiene() {
  case "$3" in
    *"$2"*) printf '  FALLA %-56s presente (no deberia): %s\n' "$1" "$2"; FAIL=$((FAIL + 1)) ;;
    *)      printf '  OK    %-56s ausente\n' "$1"; PASS=$((PASS + 1)) ;;
  esac
}

# token <ruta> -> imprime el token CSRF de la sesion actual
token() {
  # -b y -c juntos: se lee la cookie y se guarda la que devuelve la respuesta.
  curl -s -b "$JAR" -c "$JAR" "$BASE?$1" \
    | grep -o 'name="csrf_token" value="[a-f0-9]\{64\}"' | head -1 \
    | grep -o '[a-f0-9]\{64\}'
}

echo "════════════════════════════════════════════════════════════"
echo " 1. Router: lista blanca, sin reflejos y sin 500"
echo "════════════════════════════════════════════════════════════"
R=$(cuerpo "$BASE?controller=%3Cscript%3Ealert(1)%3C/script%3E")
noContiene "XSS: no refleja <script>" "<script>alert" "$R"
contiene   "XSS: muestra pagina de error" "Petición no válida" "$R"
noContiene "XSS: no imprime la ruta del disco" "../app/controllers" "$R"

check "controlador inexistente -> 404" "404" "$(codigo "$BASE?controller=NoExiste")"
check "controlador fuera de lista -> 404" "404" "$(codigo "$BASE?controller=phpinfo")"
check "controlador con ruta relativa -> 404" "404" "$(codigo "$BASE?controller=../../etc/passwd%00")"
check "accion inexistente -> 404" "404" "$(codigo "$BASE?controller=Equipos&action=volar")"
check "accion protegida (view) -> 404" "404" "$(codigo "$BASE?controller=Auth&action=view")"
check "accion protegida (requiereLogin) -> 404" "404" "$(codigo "$BASE?controller=Auth&action=requiereLogin")"
check "accion __construct -> 404" "404" "$(codigo "$BASE?controller=Auth&action=__construct")"
no500 "controller[]=array no provoca 500" "$(codigo "$BASE?controller[]=x")"
no500 "action[]=array no provoca 500" "$(codigo "$BASE?controller=Auth&action[]=x")"
R=$(cuerpo "$BASE?controller=Auth&action=view")
noContiene "sin 'Fatal error'" "Fatal error" "$R"
noContiene "sin 'private method'" "private method" "$R"

echo
echo "════════════════════════════════════════════════════════════"
echo " 2. Host header no envenena las redirecciones"
echo "════════════════════════════════════════════════════════════"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -H 'Host: evil.example.com' \
  "$BASE?controller=Equipos&action=index")
noContiene "Location sin host ajeno" "evil.example.com" "$LOC"
contiene "Location conserva el host legitimo" "localhost" "$LOC"
contiene "Location lleva a la ruta de la app" "controller=Auth" "$LOC"

echo
echo "════════════════════════════════════════════════════════════"
echo " 3. Cookies de sesion endurecidas"
echo "════════════════════════════════════════════════════════════"
SC=$(curl -sI "$BASE?controller=Auth&action=index" | tr -d '\r' | grep -i '^set-cookie:' | head -1)
contiene "cookie HttpOnly" "httponly" "${SC,,}"
contiene "cookie SameSite" "samesite" "${SC,,}"

echo
echo "════════════════════════════════════════════════════════════"
echo " 4. CSRF: los POST sin token se rechazan con 403"
echo "════════════════════════════════════════════════════════════"
check "login sin token -> 403" "403" "$(codigo -X POST "$BASE?controller=Auth&action=login" -d 'email=demo@liga.com&password=password')"
check "registro sin token -> 403" "403" "$(codigo -X POST "$BASE?controller=Auth&action=guardarRegistro" -d 'nombre=X&email=x@x.com&password=PASSword1&confirmar=PASSword1')"
# bcrypt solo mira los 72 primeros bytes: una contrasena mas larga se
# truncaria en silencio al guardarla.
LONG_PASS="$(printf 'a%.0s' $(seq 1 80))"
JAR4="$(mktemp -t ck4.XXXXXX)"
TOKEN4=$(curl -s -c "$JAR4" -b "$JAR4" "$BASE?controller=Auth&action=register" \
  | grep -o 'name="csrf_token" value="[a-f0-9]\{64\}"' | head -1 | grep -o '[a-f0-9]\{64\}')
contiene "  avisa del limite de 72 bytes" "no puede superar los 72 bytes" "$(curl -sL -c "$JAR4" -b "$JAR4" -X POST "$BASE?controller=Auth&action=guardarRegistro" -d "csrf_token=$TOKEN4&nombre=Largo&email=largo@liga.com&password=$LONG_PASS&confirmar=$LONG_PASS")"
rm -f "$JAR4"
check "login con token erroneo -> 403" "403" "$(codigo -X POST "$BASE?controller=Auth&action=login" -d 'email=demo@liga.com&password=password&csrf_token=0000000000000000000000000000000000000000000000000000000000000000')"

echo
echo "════════════════════════════════════════════════════════════"
echo " 5. Login valido y proteccion de paginas"
echo "════════════════════════════════════════════════════════════"
rm -f "$JAR"
TOKEN=$(token "controller=Auth&action=index")
if [ -n "$TOKEN" ]; then
  printf '  OK    %-56s %.12s…\n' "token CSRF en el formulario" "$TOKEN"; PASS=$((PASS + 1))
else
  printf '  FALLA %-56s no encontrado\n' "token CSRF en el formulario"; FAIL=$((FAIL + 1))
fi
check "login valido -> 302" "302" "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" \
  -X POST "$BASE?controller=Auth&action=login" \
  -d "email=demo@liga.com&password=password&csrf_token=$TOKEN")"

for r in Equipos:index Equipos:crear Jugadores:index Jugadores:crear Partidos:index Partidos:crear Estadisticas:index; do
  c="${r%%:*}"; a="${r##*:}"
  check "pagina $r -> 200" "200" "$(codigo -b "$JAR" "$BASE?controller=$c&action=$a")"
done
check "editar id inexistente -> 302" "302" "$(codigo -b "$JAR" "$BASE?controller=Equipos&action=editar&id=99999")"
R=$(cuerpo -b "$JAR" -L "$BASE?controller=Equipos&action=editar&id=99999")
contiene "editar id inexistente avisa" "no encontrado" "${R,,}"

echo
echo "════════════════════════════════════════════════════════════"
echo " 6. CSRF en paginas autenticadas y borrado por POST"
echo "════════════════════════════════════════════════════════════"
check "equipo sin token -> 403" "403" "$(POST "controller=Equipos&action=guardar" "nombre=X&ciudad=Y")"
# Por GET no se ejecuta nada: el controlador exige POST y redirige (302).
check "borrado por GET redirige (no borra)" "302" "$(codigo -b "$JAR" "$BASE?controller=Equipos&action=eliminar&id=1")"
check "logout por GET redirige (no cierra)" "302" "$(codigo -b "$JAR" "$BASE?controller=Auth&action=logout")"
contiene "el equipo sigue existiendo tras GET" "Los Lakers" "$(cuerpo -b "$JAR" "$BASE?controller=Equipos&action=index")"
TOKEN=$(token "controller=Equipos&action=index")
if [ -n "$TOKEN" ]; then
  printf '  OK    %-56s %.12s…\n' "token CSRF en pagina autenticada" "$TOKEN"; PASS=$((PASS + 1))
else
  printf '  FALLA %-56s no encontrado\n' "token CSRF en pagina autenticada"; FAIL=$((FAIL + 1))
fi

echo
echo "════════════════════════════════════════════════════════════"
echo " 7. Validacion en servidor (antes: error 500 de MySQL)"
echo "════════════════════════════════════════════════════════════"
LONG=$(printf 'A%.0s' $(seq 1 200))
check "nombre de 200 chars -> 302" "302" "$(POST "controller=Equipos&action=guardar" "csrf_token=$TOKEN&nombre=$LONG&ciudad=Valida")"
contiene "  avisa del limite de 100" "100 caracteres" "$(POSTL "controller=Equipos&action=guardar" "csrf_token=$TOKEN&nombre=$LONG&ciudad=Valida")"
check "ciudad de 200 chars -> 302" "302" "$(POST "controller=Equipos&action=guardar" "csrf_token=$TOKEN&nombre=Ok&ciudad=$LONG")"
check "fundacion=abc -> 302" "302" "$(POST "controller=Equipos&action=guardar" "csrf_token=$TOKEN&nombre=Ok&ciudad=Valida&fundacion=abc")"
contiene "  avisa del anio no numerico" "número entero" "$(POSTL "controller=Equipos&action=guardar" "csrf_token=$TOKEN&nombre=Ok&ciudad=Valida&fundacion=abc")"
check "fundacion=1700 -> 302" "302" "$(POST "controller=Equipos&action=guardar" "csrf_token=$TOKEN&nombre=Ok&ciudad=Valida&fundacion=1700")"

check "equipo_id inexistente -> 302" "302" "$(POST "controller=Jugadores&action=guardar" "csrf_token=$TOKEN&nombre=TestEq&numero=1&posicion=Base&equipo_id=9999")"
contiene "  avisa del equipo inexistente" "no existe" "$(POSTL "controller=Jugadores&action=guardar" "csrf_token=$TOKEN&nombre=TestEq&numero=1&posicion=Base&equipo_id=9999")"
check "posicion fuera de catalogo -> 302" "302" "$(POST "controller=Jugadores&action=guardar" "csrf_token=$TOKEN&nombre=TestPos&numero=1&posicion=ADMIN&equipo_id=1")"
contiene "  avisa de la posicion invalida" "Posición no válida" "$(POSTL "controller=Jugadores&action=guardar" "csrf_token=$TOKEN&nombre=TestPos&numero=1&posicion=ADMIN&equipo_id=1")"
check "dorsal 150 -> 302" "302" "$(POST "controller=Jugadores&action=guardar" "csrf_token=$TOKEN&nombre=TestNum&numero=150&posicion=Base&equipo_id=1")"
contiene "  avisa del limite de dorsal" "entre 0 y" "$(POSTL "controller=Jugadores&action=guardar" "csrf_token=$TOKEN&nombre=TestNum&numero=150&posicion=Base&equipo_id=1")"
check "nombre de 200 chars -> 302" "302" "$(POST "controller=Jugadores&action=guardar" "csrf_token=$TOKEN&nombre=$LONG&numero=1&posicion=Base&equipo_id=1")"
no500 "campo como array no provoca 500" "$(codigo -b "$JAR" -X POST "$BASE?controller=Jugadores&action=guardar" -d "nombre[]=array&equipo_id=1&csrf_token=$TOKEN")"

check "fecha invalida (31/02/2026) -> 302" "302" "$(POST "controller=Partidos&action=guardar" "csrf_token=$TOKEN&equipo_local_id=1&equipo_visitante_id=2&puntos_local=1&puntos_visitante=2&fecha=31/02/2026")"
contiene "  avisa de la fecha invalida" "fecha indicada no es válida" "$(POSTL "controller=Partidos&action=guardar" "csrf_token=$TOKEN&equipo_local_id=1&equipo_visitante_id=2&puntos_local=1&puntos_visitante=2&fecha=31/02/2026")"
check "puntos fuera de rango -> 302" "302" "$(POST "controller=Partidos&action=guardar" "csrf_token=$TOKEN&equipo_local_id=1&equipo_visitante_id=2&puntos_local=5000&puntos_visitante=2&fecha=2026-10-12T19:00")"
contiene "  avisa del limite de puntos" "no pueden superar" "$(POSTL "controller=Partidos&action=guardar" "csrf_token=$TOKEN&equipo_local_id=1&equipo_visitante_id=2&puntos_local=5000&puntos_visitante=2&fecha=2026-10-12T19:00")"
check "puntos no numericos -> 302" "302" "$(POST "controller=Partidos&action=guardar" "csrf_token=$TOKEN&equipo_local_id=1&equipo_visitante_id=2&puntos_local=abc&puntos_visitante=2&fecha=2026-10-12T19:00")"
# Si el campo no viene, el marcador se avisa como obligatorio: antes el
# valor por defecto era '0' y se guardaba un 0-0 sin avisar.
contiene "  avisa de los puntos obligatorios" "son obligatorios" "$(POSTL "controller=Partidos&action=guardar" "csrf_token=$TOKEN&equipo_local_id=1&equipo_visitante_id=2&fecha=2026-10-12T19:00")"
# Un id con basura no es un id: (int)'1abc' valía 1 y cargaba el registro 1.
check "id con basura no carga el registro 1" "302" "$(codigo -b "$JAR" "$BASE?controller=Equipos&action=editar&id=1abc")"
contiene "  y avisa que no existe" "Equipo no encontrado" "$(cuerpo -b "$JAR" -L "$BASE?controller=Equipos&action=editar&id=1abc")"
check "equipo local = visitante -> 302" "302" "$(POST "controller=Partidos&action=guardar" "csrf_token=$TOKEN&equipo_local_id=1&equipo_visitante_id=1&puntos_local=1&puntos_visitante=2&fecha=2026-10-12T19:00")"
check "equipos inexistentes -> 302" "302" "$(POST "controller=Partidos&action=guardar" "csrf_token=$TOKEN&equipo_local_id=9998&equipo_visitante_id=9999&puntos_local=1&puntos_visitante=2&fecha=2026-10-12T19:00")"

echo
echo "════════════════════════════════════════════════════════════"
echo " 8. Alta valida y listado coherente"
echo "════════════════════════════════════════════════════════════"
check "crear equipo valido -> 302" "302" "$(POST "controller=Equipos&action=guardar" "csrf_token=$TOKEN&nombre=ZZ%20Sin%20Partidos&ciudad=Prueba&fundacion=1990")"
R=$(cuerpo -b "$JAR" "$BASE?controller=Equipos&action=index")
contiene "  el equipo nuevo aparece" "ZZ Sin Partidos" "$R"
# Nombre de equipo repetido: mensaje claro en vez del error 1062 de MySQL.
contiene "  avisa del nombre de equipo duplicado" "Ya existe un equipo con ese nombre" "$(POSTL "controller=Equipos&action=guardar" "csrf_token=$TOKEN&nombre=Los%20Lakers&ciudad=Otra&fundacion=1990")"
check "  el equipo duplicado NO se crea" "1" "$(mysql_valor "SELECT COUNT(*) FROM equipos WHERE nombre='Los Lakers'")"
check "crear jugador valido -> 302" "302" "$(POST "controller=Jugadores&action=guardar" "csrf_token=$TOKEN&nombre=ZZ%20Jugador&numero=42&posicion=Base&equipo_id=4")"
R=$(cuerpo -b "$JAR" "$BASE?controller=Jugadores&action=index")
contiene "  el jugador nuevo aparece" "ZZ Jugador" "$R"
contiene "  el dorsal no se muestra como 0" "42" "$R"
# El dorsal 0 es valido (no significa "sin dorsal"); debe guardarse como 0.
check "dorsal 0 se acepta -> 302" "302" "$(POST "controller=Jugadores&action=guardar" "csrf_token=$TOKEN&nombre=ZZ%20Cero&numero=0&posicion=Base&equipo_id=4")"
check "  el dorsal 0 se guarda como 0" "0" "$(mysql_valor "SELECT numero FROM jugadores WHERE nombre='ZZ Cero'")"
# Dorsal repetido en el mismo equipo: mensaje claro en vez del error 1062.
contiene "  avisa del dorsal duplicado" "ya está en uso" "$(POSTL "controller=Jugadores&action=guardar" "csrf_token=$TOKEN&nombre=ZZ%20Dup&numero=42&posicion=Base&equipo_id=4")"
check "crear partido valido -> 302" "302" "$(POST "controller=Partidos&action=guardar" "csrf_token=$TOKEN&equipo_local_id=1&equipo_visitante_id=2&puntos_local=95&puntos_visitante=88&fecha=2026-11-01T20:30")"
R=$(cuerpo -b "$JAR" "$BASE?controller=Partidos&action=index")
contiene "  el partido nuevo aparece" "95" "$R"

echo
echo "════════════════════════════════════════════════════════════"
echo " 9. Estadisticas: los equipos sin partidos tambien salen"
echo "════════════════════════════════════════════════════════════"
R=$(cuerpo -b "$JAR" "$BASE?controller=Estadisticas&action=index")
contiene "el equipo sin partidos aparece" "ZZ Sin Partidos" "$R"
contiene "  y con 0 partidos jugados" "ZZ Sin Partidos" "$R"

echo
echo "════════════════════════════════════════════════════════════"
echo "10. Borrado: clave foranea y registros inexistentes"
echo "════════════════════════════════════════════════════════════"
check "borrar equipo con asociados -> 302" "302" "$(POST "controller=Equipos&action=eliminar" "csrf_token=$TOKEN&id=1")"
R=$(POSTL "controller=Equipos&action=eliminar" "csrf_token=$TOKEN&id=1")
contiene "  avisa del bloqueo" "No se puede eliminar" "$R"
noContiene "  sin pantalla en blanco" "Fatal error" "$R"
check "borrar id inexistente -> 302" "302" "$(POST "controller=Equipos&action=eliminar" "csrf_token=$TOKEN&id=99999")"
contiene "  avisa de que no existia" "No se encontró" "$(POSTL "controller=Equipos&action=eliminar" "csrf_token=$TOKEN&id=99999")"
check "borrar jugador inexistente -> 302" "302" "$(POST "controller=Jugadores&action=eliminar" "csrf_token=$TOKEN&id=99999")"
contiene "  avisa de que no existia" "No se encontró" "$(POSTL "controller=Jugadores&action=eliminar" "csrf_token=$TOKEN&id=99999")"
check "borrar partido inexistente -> 302" "302" "$(POST "controller=Partidos&action=eliminar" "csrf_token=$TOKEN&id=99999")"

echo
echo "════════════════════════════════════════════════════════════"
echo "11. Edicion: los errores vuelven al formulario, no al listado"
echo "════════════════════════════════════════════════════════════"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -X POST \
  "$BASE?controller=Equipos&action=actualizar" \
  --data-urlencode "csrf_token=$TOKEN" --data-urlencode "id=1" \
  --data-urlencode "nombre=$LONG" --data-urlencode "ciudad=X")
contiene "redirige al formulario de edicion" "action=editar" "$LOC"
check "actualizar id inexistente -> 302" "302" "$(POST "controller=Equipos&action=actualizar" "csrf_token=$TOKEN&id=99999&nombre=NoExiste&ciudad=X")"
contiene "  el usuario ve que no existe" "no encontrado" "$(POSTL "controller=Equipos&action=actualizar" "csrf_token=$TOKEN&id=99999&nombre=NoExiste&ciudad=X")"
check "editar id inexistente (POST)-> 302" "302" "$(POST "controller=Equipos&action=editar" "csrf_token=$TOKEN&id=99999")"

# MySQL cuenta filas *cambiadas* en un UPDATE, no filas *encontradas*.
# Si se edita un equipo y se guarda sin tocar nada, rowCount() es 0 aunque
# el equipo exista de sobra. Antes eso producia un falso "no se encontro el
# equipo que se quiso modificar". Estas dos comprobaciones lo vigilan.
echo
echo "════════════════════════════════════════════════════════════"
echo "12. Guardar sin cambiar nada: MySQL no debe inventarse un fallo"
echo "════════════════════════════════════════════════════════════"
ORIGINAL="$(mysql_valor 'SELECT nombre FROM equipos WHERE id = 1')"
SIN_CAMBIOS="$(POSTL "controller=Equipos&action=actualizar" \
  "csrf_token=$TOKEN&id=1&nombre=$ORIGINAL&ciudad=Valladolid")"
contiene    "guardar el mismo nombre -> exito"  "actualizado correctamente" "$SIN_CAMBIOS"
noContiene "guardar el mismo nombre -> no error" "No se encontro el equipo"  "$SIN_CAMBIOS"
contiene    "el nombre sigue igual"              "$ORIGINAL" "$(mysql_valor 'SELECT nombre FROM equipos WHERE id = 1')"

echo
echo
echo "════════════════════════════════════════════════════════════"
echo
echo "════════════════════════════════════════════════════════════"
echo
echo "════════════════════════════════════════════════════════════"
echo " 13. Bloqueo de intentos: por IP y por cuenta"
echo "════════════════════════════════════════════════════════════"
# El bloqueo NO está en la sesión (el atacante dejaría de mandar la cookie)
# ni es solo por IP (cinco fallos de una cuenta bloquearían a todas las
# cuentas de esa IP). La clave combina IP y cuenta.
#
# El estado vive en el servidor, en un archivo por clave dentro de la
# carpeta temporal. Se limpia al empezar y al terminar la suite, para no
# dejar bloqueada a la máquina que la ejecuta.
LOCKDIRS="$(/opt/lampp/bin/php -r 'echo sys_get_temp_dir();' 2>/dev/null || echo /tmp)/liga_basquet_login /opt/lampp/temp/liga_basquet_login"

limpiar_bloqueo() {
  for d in $LOCKDIRS; do
    rm -f "$d"/*.json 2>/dev/null
  done
}

limpiar_bloqueo

intento_fallido() {
  # $1 = email. Devuelve el cuerpo de la respuesta.
  local J T
  J="$(mktemp -t ckf.XXXXXX)"
  T=$(curl -s -c "$J" -b "$J" "$BASE?controller=Auth&action=index" \
    | grep -o 'name="csrf_token" value="[a-f0-9]\{64\}"' | head -1 | grep -o '[a-f0-9]\{64\}')
  curl -sL -c "$J" -b "$J" -X POST "$BASE?controller=Auth&action=login" \
    -d "csrf_token=$T&email=$1&password=incorrecta"
  rm -f "$J"
}

intento_correcto() {
  # $1 = email, $2 = password. Devuelve el cuerpo de la respuesta.
  local J T
  J="$(mktemp -t ckc.XXXXXX)"
  T=$(curl -s -c "$J" -b "$J" "$BASE?controller=Auth&action=index" \
    | grep -o 'name="csrf_token" value="[a-f0-9]\{64\}"' | head -1 | grep -o '[a-f0-9]\{64\}')
  curl -sL -c "$J" -b "$J" -X POST "$BASE?controller=Auth&action=login" \
    -d "csrf_token=$T&email=$1&password=$2"
  rm -f "$J"
}

# Crea una cuenta nueva para las pruebas del limitador. Se usa un sufijo
# distinto en cada ejecucion para no chocar con una cuenta que haya quedado
# de una ejecucion anterior.
registrar_usuario() {
  local email="$1" password="$2" J T
  J="$(mktemp -t ckr.XXXXXX)"
  T=$(curl -s -c "$J" -b "$J" "$BASE?controller=Auth&action=register" \
    | grep -o 'name="csrf_token" value="[a-f0-9]\{64\}"' | head -1 | grep -o '[a-f0-9]\{64\}')
  curl -sL -c "$J" -b "$J" -X POST "$BASE?controller=Auth&action=guardarRegistro" \
    -d "csrf_token=$T&nombre=Suite&email=$email&password=$password&confirmar=$password" >/dev/null
  rm -f "$J"
}

SUFIJO="$(date +%s)-$$"
CUENTA_ATACADA="bloqueo1-$SUFIJO@liga.com"
CUENTA_SANA="bloqueo2-$SUFIJO@liga.com"
registrar_usuario "$CUENTA_ATACADA" "Password1"
registrar_usuario "$CUENTA_SANA" "Password1"

# --- 1) Cinco intentos fallidos en PARALELO ---
# Simultaneidad a propósito: el contador se lee y se escribe en disco, y
# si la lectura no estuviera protegida se perderían intentos y el bloqueo
# no llegaría a activarse.
for i in 1 2 3 4 5; do
  intento_fallido "$CUENTA_ATACADA" >/dev/null &
done
wait

R=$(intento_correcto "$CUENTA_ATACADA" "Password1")
contiene "  5 fallos en paralelo bloquean la cuenta" "Demasiados intentos fallidos" "$R"

# --- 2) El bloqueo es POR CUENTA: otra cuenta de la misma IP entra ---
R=$(intento_correcto "$CUENTA_SANA" "Password1")
contiene "  otra cuenta de la misma IP NO se bloquea" "Sesión iniciada" "$R"

# --- 3) Y la cuenta atacada sigue bloqueada ---
R=$(intento_correcto "$CUENTA_ATACADA" "Password1")
contiene "  la cuenta atacada sigue bloqueada" "Demasiados intentos fallidos" "$R"

# --- 4) Cuatro fallos no bloquean y un login correcto reinicia el contador ---
for i in 1 2 3 4; do
  intento_fallido "$CUENTA_SANA" >/dev/null
done
R=$(intento_correcto "$CUENTA_SANA" "Password1")
contiene "  4 fallos no bloquean y el login correcto limpia" "Sesión iniciada" "$R"

echo "14. Restauracion de la base de datos"
echo "════════════════════════════════════════════════════════════"
# La suite crea registros para poder comprobar que se guardan y se listan.
# Al terminar se reinicia la base desde los scripts de seed, de modo que
# siempre queda en el estado inicial documentado (4/12/4/2).
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
MYSQL_BIN="${MYSQL_BIN:-/opt/lampp/bin/mysql}"

if command -v "$MYSQL_BIN" >/dev/null 2>&1; then
  # seeds.sql usa INSERT IGNORE con IDs fijos: solo rellena lo que falta, no
  # borra. Se vacian las tablas (en orden de clave foranea) y luego se importa.
  "$MYSQL_BIN" -u root liga_basquet -e "SET FOREIGN_KEY_CHECKS=0; TRUNCATE TABLE partidos; TRUNCATE TABLE jugadores; TRUNCATE TABLE equipos; TRUNCATE TABLE usuarios; SET FOREIGN_KEY_CHECKS=1;" 2>/dev/null
  # El import va SIN el nombre de la base a proposito: schema.sql hace el
  # CREATE DATABASE y el USE por dentro. Si se le pone el nombre y la base
  # no existe, el cliente falla con "Unknown database" antes de leer el
  # script, y la restauracion se queda a medias.
  "$MYSQL_BIN" -u root < "$RAIZ/database/schema.sql" 2>/dev/null
  "$MYSQL_BIN" -u root < "$RAIZ/database/seeds.sql"  2>/dev/null
  R=$("$MYSQL_BIN" -N -B -u root liga_basquet -e \
       "SELECT CONCAT((SELECT COUNT(*) FROM equipos),'/',(SELECT COUNT(*) FROM jugadores),'/',(SELECT COUNT(*) FROM partidos),'/',(SELECT COUNT(*) FROM usuarios));" 2>/dev/null)
  if [ "$R" = "4/12/4/2" ]; then
    printf '  OK    %-56s %s\n' "base en estado inicial (eq/jug/par/usu)" "$R"; PASS=$((PASS + 1))
  else
    printf '  FALLA %-56s esperado=4/12/4/2 obtenido=%s\n' "base en estado inicial" "$R"; FAIL=$((FAIL + 1))
  fi
else
  printf '  AVISO %-56s mysql no disponible; se queda en %s\n' "restauracion automatica" "$R"
fi

echo
echo "════════════════════════════════════════════════════════════"
printf ' RESULTADO: %d correctas, %d fallidas\n' "$PASS" "$FAIL"
echo "════════════════════════════════════════════════════════════"
rm -f "$JAR"
[ "$FAIL" -eq 0 ] || exit 1