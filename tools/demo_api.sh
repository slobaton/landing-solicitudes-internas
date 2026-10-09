#!/usr/bin/env bash
# =============================================================================
# demo_api.sh — Demostración paso a paso de la API de solicitudes.
# Uso (desde proyecto/06_codigo):   bash tools/demo_api.sh
#
# Levanta el servidor PHP de pruebas, inicia sesión como demo.solicitante y
# ejecuta POST y GET contra la base real. Se detiene entre pasos para tomar
# capturas. Las contraseñas se leen de .env y nunca se muestran.
# =============================================================================
set -u
cd "$(dirname "$0")/.."

PHP="/Users/slobaton/Library/Application Support/Herd/bin/php"
MYSQL="/Users/Shared/DBngin/mysql/8.0.33/bin/mysql"
BASE="http://127.0.0.1:8080"

env_get() { grep -E "^$1=" .env | head -1 | cut -d= -f2-; }
USUARIO="$(env_get DEMO_SOLICITANTE_USUARIO)"
PASSWORD="$(env_get DEMO_SOLICITANTE_PASSWORD)"

TMP="$(mktemp -d)"
COOKIES="$TMP/cookies.txt"
trap 'kill $SERVER_PID 2>/dev/null; rm -rf "$TMP"' EXIT

titulo() { printf '\n\033[1;36m==== %s ====\033[0m\n' "$1"; }
pausa()  { printf '\n\033[33m📸 Toma la captura y presiona Enter para continuar...\033[0m'; read -r _; }

# Ejecuta una petición y muestra: método, ruta, código HTTP y JSON ordenado.
peticion() {
  local metodo="$1" ruta="$2" cuerpo="${3:-}"
  local args=(-s -o "$TMP/resp.json" -w '%{http_code}' -X "$metodo" -b "$COOKIES" -c "$COOKIES"
              -H 'Accept: application/json')
  [ -n "${CSRF:-}" ] && args+=(-H "X-CSRF-Token: $CSRF")
  if [ -n "$cuerpo" ]; then
    args+=(-H 'Content-Type: application/json' --data "$cuerpo")
    printf '\033[1m%s %s\033[0m\n' "$metodo" "$ruta"
    printf '%s' "$cuerpo" | python3 -m json.tool --no-ensure-ascii
  else
    printf '\033[1m%s %s\033[0m\n' "$metodo" "$ruta"
  fi
  CODIGO="$(curl "${args[@]}" "$BASE$ruta")"
  printf '\n\033[1;32mHTTP %s\033[0m\n' "$CODIGO"
  python3 -m json.tool --no-ensure-ascii < "$TMP/resp.json"
}

titulo "Iniciando servidor PHP en $BASE"
"$PHP" -S 127.0.0.1:8080 -t public > "$TMP/server.log" 2>&1 &
SERVER_PID=$!
sleep 1

titulo "0. Login como $USUARIO"
LOGIN_JSON="$(python3 -c 'import json,sys; print(json.dumps({"nombre_usuario": sys.argv[1], "password": sys.argv[2]}))' "$USUARIO" "$PASSWORD")"
CODIGO="$(curl -s -o "$TMP/resp.json" -w '%{http_code}' -c "$COOKIES" -H 'Content-Type: application/json' --data "$LOGIN_JSON" "$BASE/api/v1/auth/login")"
printf 'POST /api/v1/auth/login   {"nombre_usuario": "%s", "password": "********"}\n' "$USUARIO"
printf '\033[1;32mHTTP %s\033[0m\n' "$CODIGO"
CSRF="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["data"]["csrf_token"])' "$TMP/resp.json")"
python3 -c 'import json,sys; d=json.load(open(sys.argv[1])); d["data"]["csrf_token"]="(oculto)"; print(json.dumps(d,indent=4,ensure_ascii=False))' "$TMP/resp.json"
pausa

titulo "1. POST /api/v1/solicitudes — registrar (INSERT)"
peticion POST /api/v1/solicitudes '{"titulo":"Cambio de chapa en la puerta de Almacén","descripcion":"La chapa de la puerta principal del almacén está dañada y no cierra.","area_destino_id":2,"tipo_solicitud_id":3,"prioridad_id":3}'
NUEVO_ID="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["data"]["id"])' "$TMP/resp.json" 2>/dev/null || echo "")"
pausa

titulo "2. POST /api/v1/solicitudes — datos inválidos (validación)"
peticion POST /api/v1/solicitudes '{"titulo":"","area_destino_id":999,"tipo_solicitud_id":"abc","prioridad_id":9}'
pausa

titulo "3. GET /api/v1/solicitudes — mis solicitudes (SELECT)"
peticion GET /api/v1/solicitudes
pausa

if [ -n "$NUEVO_ID" ]; then
  titulo "4. GET /api/v1/solicitudes/$NUEVO_ID — detalle con historial (SELECT)"
  peticion GET "/api/v1/solicitudes/$NUEVO_ID"
  pausa

  version_actual() { python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["data"]["version"])' "$TMP/resp.json"; }

  titulo "5. PUT /api/v1/solicitudes/$NUEVO_ID — editar (UPDATE)"
  V="$(version_actual)"
  peticion PUT "/api/v1/solicitudes/$NUEVO_ID" "{\"titulo\":\"Cambio de chapa y cerradura en la puerta de Almacén\",\"descripcion\":\"La chapa de la puerta principal del almacén está dañada y no cierra. Se necesita cambiar también la cerradura.\",\"tipo_solicitud_id\":3,\"version\":$V}"
  pausa

  titulo "6. DELETE /api/v1/solicitudes/$NUEVO_ID — cancelar sin motivo (validación)"
  V="$(version_actual)"
  peticion DELETE "/api/v1/solicitudes/$NUEVO_ID" "{\"motivo\":\"\",\"version\":$V}"
  pausa

  titulo "7. DELETE /api/v1/solicitudes/$NUEVO_ID — cancelar con motivo (UPDATE, no borra)"
  peticion DELETE "/api/v1/solicitudes/$NUEVO_ID" "{\"motivo\":\"Se resolvió con el personal de turno, ya no se necesita.\",\"version\":$V}"
  pausa

  titulo "8. PUT /api/v1/solicitudes/$NUEVO_ID — editar una solicitud cancelada (regla de negocio)"
  V="$(version_actual)"
  peticion PUT "/api/v1/solicitudes/$NUEVO_ID" "{\"titulo\":\"Intento de edición\",\"descripcion\":\"No debería permitirse.\",\"tipo_solicitud_id\":3,\"version\":$V}"
  pausa
fi

titulo "9. Verificación directa en MySQL (base sis_solicitudes)"
"$MYSQL" -uroot -S /tmp/mysql_3306.sock --default-character-set=utf8mb4 -t sis_solicitudes -e "
SELECT s.id, s.numero, s.titulo, e.codigo AS estado, p.codigo AS prioridad,
       a.codigo AS area, u.nombre_usuario AS solicitante, s.fecha_registro, s.fecha_limite
  FROM sol_solicitud s
  JOIN cat_estado e    ON e.id = s.estado_id
  JOIN cat_prioridad p ON p.id = s.prioridad_id
  JOIN org_area a      ON a.id = s.area_destino_id
  JOIN seg_usuario u   ON u.id = s.solicitante_id
 ORDER BY s.id;
SELECT h.id, h.solicitud_id, h.tipo_evento, ea.codigo AS estado_anterior, en.codigo AS estado_nuevo,
       h.motivo, h.fecha
  FROM sol_historial h
  LEFT JOIN cat_estado ea ON ea.id = h.estado_anterior_id
  LEFT JOIN cat_estado en ON en.id = h.estado_nuevo_id
 ORDER BY h.id;"
pausa

titulo "Fin de la demostración"
