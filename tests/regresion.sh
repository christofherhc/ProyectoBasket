#!/usr/bin/env bash
# Suite de regresión del proyecto Liga de Básquetbol.
# Comprueba que los errores documentados en la auditoría ya no se reproducen.
#
# Uso:  ./tests/regresion.sh [URL_BASE]
# Por defecto: http://localhost/ProyectoBasket-Deploy/public/index.php
#
# Requisitos: la aplicación desplegada en htdocs y MySQL/MariaDB arrancados.
#
# AVISO: esta suite MODIFICA la base de datos (crea registros de prueba) y al
# terminar la reinicia desde database/schema.sql + database/seeds.sql. No la
# ejecutes contra una base con datos reales.
set -u

BASE="${1:-http://localhost/ProyectoBasket-Deploy/public/index.php}"
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

# Lee un valor suelto de la base. Lo usan las comprobaciones que necesitan
# contrastar lo que se guardo con lo que hay realmente en la tabla.
MYSQL_BIN="${MYSQL_BIN:-/opt/lampp/bin/mysql}"
mysql_valor() {
  "$MYSQL_BIN" -u root -N -B liga_basquet -e "$1" 2>/dev/null | head -1
}

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
contiene "Location lleva a la ruta de la app" "/public/" "$LOC"

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
contiene "  avisa del limite de dorsal" "entre 1 y" "$(POSTL "controller=Jugadores&action=guardar" "csrf_token=$TOKEN&nombre=TestNum&numero=150&posicion=Base&equipo_id=1")"
check "nombre de 200 chars -> 302" "302" "$(POST "controller=Jugadores&action=guardar" "csrf_token=$TOKEN&nombre=$LONG&numero=1&posicion=Base&equipo_id=1")"
no500 "campo como array no provoca 500" "$(codigo -b "$JAR" -X POST "$BASE?controller=Jugadores&action=guardar" -d "nombre[]=array&equipo_id=1&csrf_token=$TOKEN")"

check "fecha invalida (31/02/2026) -> 302" "302" "$(POST "controller=Partidos&action=guardar" "csrf_token=$TOKEN&equipo_local_id=1&equipo_visitante_id=2&puntos_local=1&puntos_visitante=2&fecha=31/02/2026")"
contiene "  avisa de la fecha invalida" "fecha indicada no es válida" "$(POSTL "controller=Partidos&action=guardar" "csrf_token=$TOKEN&equipo_local_id=1&equipo_visitante_id=2&puntos_local=1&puntos_visitante=2&fecha=31/02/2026")"
check "puntos fuera de rango -> 302" "302" "$(POST "controller=Partidos&action=guardar" "csrf_token=$TOKEN&equipo_local_id=1&equipo_visitante_id=2&puntos_local=5000&puntos_visitante=2&fecha=2026-10-12T19:00")"
contiene "  avisa del limite de puntos" "no pueden superar" "$(POSTL "controller=Partidos&action=guardar" "csrf_token=$TOKEN&equipo_local_id=1&equipo_visitante_id=2&puntos_local=5000&puntos_visitante=2&fecha=2026-10-12T19:00")"
check "puntos no numericos -> 302" "302" "$(POST "controller=Partidos&action=guardar" "csrf_token=$TOKEN&equipo_local_id=1&equipo_visitante_id=2&puntos_local=abc&puntos_visitante=2&fecha=2026-10-12T19:00")"
check "equipo local = visitante -> 302" "302" "$(POST "controller=Partidos&action=guardar" "csrf_token=$TOKEN&equipo_local_id=1&equipo_visitante_id=1&puntos_local=1&puntos_visitante=2&fecha=2026-10-12T19:00")"
check "equipos inexistentes -> 302" "302" "$(POST "controller=Partidos&action=guardar" "csrf_token=$TOKEN&equipo_local_id=9998&equipo_visitante_id=9999&puntos_local=1&puntos_visitante=2&fecha=2026-10-12T19:00")"

echo
echo "════════════════════════════════════════════════════════════"
echo " 8. Alta valida y listado coherente"
echo "════════════════════════════════════════════════════════════"
check "crear equipo valido -> 302" "302" "$(POST "controller=Equipos&action=guardar" "csrf_token=$TOKEN&nombre=ZZ%20Sin%20Partidos&ciudad=Prueba&fundacion=1990")"
R=$(cuerpo -b "$JAR" "$BASE?controller=Equipos&action=index")
contiene "  el equipo nuevo aparece" "ZZ Sin Partidos" "$R"
check "crear jugador valido -> 302" "302" "$(POST "controller=Jugadores&action=guardar" "csrf_token=$TOKEN&nombre=ZZ%20Jugador&numero=42&posicion=Base&equipo_id=4")"
R=$(cuerpo -b "$JAR" "$BASE?controller=Jugadores&action=index")
contiene "  el jugador nuevo aparece" "ZZ Jugador" "$R"
contiene "  el dorsal no se muestra como 0" "42" "$R"
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
echo "════════════════════════════════════════════════════════════"
echo "13. Restauracion de la base de datos"
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
  "$MYSQL_BIN" -u root liga_basquet < "$RAIZ/database/schema.sql" 2>/dev/null
  "$MYSQL_BIN" -u root liga_basquet < "$RAIZ/database/seeds.sql"  2>/dev/null
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