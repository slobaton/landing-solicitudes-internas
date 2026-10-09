<?php

declare(strict_types=1);

/**
 * Suite de pruebas de la API (CP-API-05; ampliada en CP-FRONT-06 con
 * GET /estadisticas y GET /catalogos de la landing). Recorre todos los endpoints de
 * routes/api.php contra un servidor en marcha y comprueba que cada ruta esté
 * documentada en docs/openapi.yaml.
 *
 * Uso:
 *   php -S 127.0.0.1:8080 -t public      (en otra terminal)
 *   php tests/api_test.php [URL_BASE]     (por defecto http://127.0.0.1:8080)
 *
 * Credenciales: DEMO_SOLICITANTE_* y DEMO_JEFE_* desde .env (nunca se imprimen).
 * Resultado: salida estándar y tests/resultados/resultado_AAAAMMDD_HHMMSS.txt.
 * Código de salida: 0 si todo pasa, 1 si alguna prueba falla.
 *
 * DATOS QUE CREA CADA EJECUCIÓN (siempre a través de la API, nunca DELETE de filas):
 *   - sol_solicitud: 1 fila nueva de demo.solicitante (área TI, tipo 1, prioridad BAJA)
 *     que termina en estado CANCELADA (version 2, fecha_cierre).
 *   - sol_historial: 3 filas para esa solicitud (REGISTRO, EDICION, CAMBIO_ESTADO T03).
 *   - sol_secuencia: +1 en el año en curso.
 *   - aud_auditoria: 1 LOGIN_FALLIDO (demo.jefe) y 2 LOGIN (demo.solicitante y demo.jefe).
 *   - seg_usuario.ultimo_acceso_en de ambos usuarios demo.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$raiz = dirname(__DIR__);

// --------------------------------------------------------------------------
// Utilidades
// --------------------------------------------------------------------------

/** @return array<string, string> */
function leerEnv(string $ruta): array
{
    $env = [];
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
        $l = trim($l);
        if ($l === '' || $l[0] === '#' || !str_contains($l, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $l, 2);
        $env[trim($k)] = trim(trim($v), "\"'");
    }
    return $env;
}

/** Cliente HTTP con su propia cookie de sesión. */
final class Cliente
{
    private ?string $sesion = null;
    public ?string $csrf = null;

    public function __construct(private string $base)
    {
    }

    /**
     * @param array<string, string> $cabeceras
     * @return array{status: int, headers: array<string, string>, json: mixed, raw: string}
     */
    public function pedir(string $metodo, string $ruta, mixed $cuerpo = null, array $cabeceras = [], bool $conCsrf = false): array
    {
        $h = ['Accept: application/json'];
        if ($cuerpo !== null) {
            $h[] = 'Content-Type: application/json';
        }
        if ($this->sesion !== null) {
            $h[] = 'Cookie: SIS_SESSID=' . $this->sesion;
        }
        if ($conCsrf && $this->csrf !== null) {
            $h[] = 'X-CSRF-Token: ' . $this->csrf;
        }
        foreach ($cabeceras as $k => $v) {
            $h[] = "$k: $v";
        }

        $ch = curl_init($this->base . $ruta);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_TIMEOUT => 15,
        ]);
        if ($cuerpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($cuerpo) ? $cuerpo : json_encode($cuerpo, JSON_UNESCAPED_UNICODE));
        }
        $resp = curl_exec($ch);
        if ($resp === false) {
            throw new RuntimeException('No se pudo conectar con ' . $this->base . ': ' . curl_error($ch));
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $tam = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $cabecerasResp = [];
        foreach (explode("\r\n", substr($resp, 0, $tam)) as $linea) {
            if (str_contains($linea, ':')) {
                [$k, $v] = explode(':', $linea, 2);
                $k = strtolower(trim($k));
                $v = trim($v);
                $cabecerasResp[$k] = $v;
                if ($k === 'set-cookie' && preg_match('/^SIS_SESSID=([^;]*)/', $v, $m)) {
                    $this->sesion = ($m[1] === 'deleted' || $m[1] === '') ? null : $m[1];
                }
            }
        }
        $raw = substr($resp, $tam);

        return ['status' => $status, 'headers' => $cabecerasResp, 'json' => json_decode($raw, true), 'raw' => $raw];
    }
}

$resultados = [];
$fallos = 0;
$n = 0;

/** @param array<string, mixed>|null $r */
function comprobar(string $descripcion, bool $condicion, ?array $r = null): void
{
    global $resultados, $fallos, $n;
    $n++;
    $linea = ($condicion ? 'ok' : 'not ok') . " $n - $descripcion";
    if (!$condicion && $r !== null) {
        $linea .= ' # obtenido: HTTP ' . $r['status'] . ' ' . substr((string) $r['raw'], 0, 300);
    }
    if (!$condicion) {
        $fallos++;
    }
    $resultados[] = $linea;
    echo $linea, "\n";
}

function seccion(string $titulo): void
{
    global $resultados;
    $resultados[] = "# $titulo";
    echo "# $titulo\n";
}

function codigoError(array $r): ?string
{
    return $r['json']['error']['codigo'] ?? null;
}

// --------------------------------------------------------------------------
// Preparación
// --------------------------------------------------------------------------

$env = leerEnv($raiz . '/.env');
foreach (['DEMO_SOLICITANTE_USUARIO', 'DEMO_SOLICITANTE_PASSWORD', 'DEMO_JEFE_USUARIO', 'DEMO_JEFE_PASSWORD'] as $k) {
    if (($env[$k] ?? '') === '') {
        fwrite(STDERR, "Falta $k en .env\n");
        exit(1);
    }
}

date_default_timezone_set($env['APP_TIMEZONE'] ?? 'UTC');
$inicio = date('Y-m-d H:i:s');
echo "# Suite API solicitudes — $inicio — $base\n";
$resultados[] = "# Suite API solicitudes — $inicio — $base";

// --------------------------------------------------------------------------
// 0. Rutas documentadas en OpenAPI
// --------------------------------------------------------------------------
seccion('OpenAPI: cada ruta de routes/api.php está documentada');

$rutas = [];
preg_match_all("/\\\$router->(get|post|put|delete)\\('([^']+)'/", (string) file_get_contents($raiz . '/routes/api.php'), $m, PREG_SET_ORDER);
foreach ($m as $x) {
    $rutas[] = [$x[1], preg_replace('#^/api/v1#', '', $x[2])];
}

$spec = (string) file_get_contents($raiz . '/docs/openapi.yaml');
comprobar('openapi.yaml no contiene tabuladores', !str_contains($spec, "\t"));
comprobar('openapi.yaml declara openapi: 3.1.0', str_starts_with($spec, "openapi: 3.1.0\n"));
$documentadas = [];
$actual = null;
foreach (explode("\n", $spec) as $linea) {
    if (preg_match('#^  (/\S*):$#', $linea, $mm)) {
        $actual = $mm[1];
    } elseif ($actual !== null && preg_match('#^    (get|post|put|delete):$#', $linea, $mm)) {
        $documentadas[] = $mm[1] . ' ' . $actual;
    } elseif (preg_match('#^\S#', $linea)) {
        $actual = null;
    }
}
foreach ($rutas as [$metodo, $ruta]) {
    comprobar(strtoupper($metodo) . " $ruta documentada", in_array("$metodo $ruta", $documentadas, true));
}
comprobar('No hay operaciones documentadas sin ruta implementada', count($documentadas) === count($rutas));

// --------------------------------------------------------------------------
// 1. Sistema y rutas
// --------------------------------------------------------------------------
seccion('Sistema');
$anonimo = new Cliente($base);

$r = $anonimo->pedir('GET', '/api/v1/health');
comprobar('GET /health -> 200 y base_datos ok', $r['status'] === 200 && ($r['json']['data']['base_datos'] ?? null) === 'ok', $r);

$r = $anonimo->pedir('GET', '/api/v1/no-existe');
comprobar('Ruta inexistente -> 404 RUTA_NO_ENCONTRADA', $r['status'] === 404 && codigoError($r) === 'RUTA_NO_ENCONTRADA', $r);

$r = $anonimo->pedir('DELETE', '/api/v1/health');
comprobar('DELETE /health -> 405 con Allow: GET', $r['status'] === 405 && ($r['headers']['allow'] ?? '') === 'GET', $r);

// --------------------------------------------------------------------------
// 2. Autenticación
// --------------------------------------------------------------------------
seccion('Autenticación');

$r = $anonimo->pedir('GET', '/api/v1/auth/me');
comprobar('GET /auth/me sin sesión -> 401 NO_AUTENTICADO', $r['status'] === 401 && codigoError($r) === 'NO_AUTENTICADO', $r);

$r = $anonimo->pedir('POST', '/api/v1/auth/login', '{malformado');
comprobar('POST /auth/login con JSON mal formado -> 400 JSON_INVALIDO', $r['status'] === 400 && codigoError($r) === 'JSON_INVALIDO', $r);

$r = $anonimo->pedir('POST', '/api/v1/auth/login', new stdClass());
comprobar('POST /auth/login {} -> 422 VALIDACION con detalles', $r['status'] === 422 && isset($r['json']['error']['detalles']['nombre_usuario'], $r['json']['error']['detalles']['password']), $r);

$r = $anonimo->pedir('POST', '/api/v1/auth/login', ['nombre_usuario' => $env['DEMO_JEFE_USUARIO'], 'password' => 'incorrecta-' . bin2hex(random_bytes(4))]);
comprobar('POST /auth/login con contraseña errónea -> 401 CREDENCIALES_INVALIDAS', $r['status'] === 401 && codigoError($r) === 'CREDENCIALES_INVALIDAS', $r);

$sol = new Cliente($base);
$r = $sol->pedir('POST', '/api/v1/auth/login', ['nombre_usuario' => $env['DEMO_SOLICITANTE_USUARIO'], 'password' => $env['DEMO_SOLICITANTE_PASSWORD']]);
$sol->csrf = $r['json']['data']['csrf_token'] ?? null;
comprobar('POST /auth/login solicitante -> 200, cookie HttpOnly y csrf_token',
    $r['status'] === 200 && $sol->csrf !== null && str_contains($r['headers']['set-cookie'] ?? '', 'HttpOnly')
    && !isset($r['json']['data']['usuario']['password_hash']), $r);

$jefe = new Cliente($base);
$r = $jefe->pedir('POST', '/api/v1/auth/login', ['nombre_usuario' => $env['DEMO_JEFE_USUARIO'], 'password' => $env['DEMO_JEFE_PASSWORD']]);
$jefe->csrf = $r['json']['data']['csrf_token'] ?? null;
comprobar('POST /auth/login jefe -> 200 con rol JEFE_AREA', $r['status'] === 200 && in_array('JEFE_AREA', $r['json']['data']['usuario']['roles'] ?? [], true), $r);

$r = $sol->pedir('GET', '/api/v1/auth/me');
comprobar('GET /auth/me con sesión -> 200 y mismo csrf_token', $r['status'] === 200 && ($r['json']['data']['csrf_token'] ?? null) === $sol->csrf, $r);

// --------------------------------------------------------------------------
// 2b. Landing: estadísticas (público) y catálogos (con sesión) — CP-FRONT-06
// --------------------------------------------------------------------------
seccion('Landing: estadísticas y catálogos');

/** @return array<string, mixed> data de GET /estadisticas (o [] si falla) */
function estadisticas(Cliente $c): array
{
    $r = $c->pedir('GET', '/api/v1/estadisticas');
    return $r['status'] === 200 ? ($r['json']['data'] ?? []) : [];
}

$r = $anonimo->pedir('GET', '/api/v1/estadisticas');
$e = $r['json']['data'] ?? [];
$sumaEstados = array_sum(array_column($e['por_estado'] ?? [], 'total'));
$porCodigo = array_column($e['por_estado'] ?? [], 'total', 'codigo');
comprobar('GET /estadisticas sin sesión -> 200, sin cookie de sesión',
    $r['status'] === 200 && !isset($r['headers']['set-cookie']), $r);
comprobar('GET /estadisticas: solo conteos enteros (total, registradas, en_proceso, atendidas_cerradas, por_estado, generado_en)',
    array_keys($e) === ['total', 'registradas', 'en_proceso', 'atendidas_cerradas', 'por_estado', 'generado_en']
    && is_int($e['total']) && is_int($e['registradas']) && is_int($e['en_proceso']) && is_int($e['atendidas_cerradas'])
    && count($e['por_estado']) === 7
    && array_filter($e['por_estado'], static fn ($f) => array_keys($f) !== ['codigo', 'nombre', 'total']) === [], $r);
comprobar('GET /estadisticas: total = suma de por_estado y agregados coherentes',
    $e['total'] === $sumaEstados
    && $e['registradas'] === ($porCodigo['REGISTRADA'] ?? -1)
    && $e['en_proceso'] === ($porCodigo['ASIGNADA'] ?? 0) + ($porCodigo['EN_PROCESO'] ?? 0)
    && $e['atendidas_cerradas'] === ($porCodigo['ATENDIDA'] ?? 0) + ($porCodigo['CERRADA'] ?? 0), $r);
$r = $anonimo->pedir('POST', '/api/v1/estadisticas', new stdClass());
comprobar('POST /estadisticas -> 405 METODO_NO_PERMITIDO', $r['status'] === 405 && codigoError($r) === 'METODO_NO_PERMITIDO', $r);
$estAntes = $e;

$r = $anonimo->pedir('GET', '/api/v1/catalogos');
comprobar('GET /catalogos sin sesión -> 401 NO_AUTENTICADO', $r['status'] === 401 && codigoError($r) === 'NO_AUTENTICADO', $r);

$r = $sol->pedir('GET', '/api/v1/catalogos');
$cat = $r['json']['data'] ?? [];
$columnas = static fn (array $lista, array $esperadas): bool =>
    array_filter($lista, static fn ($f) => array_keys($f) !== $esperadas) === [];
comprobar('GET /catalogos con sesión -> 200 con areas, tipos_solicitud y prioridades no vacíos',
    $r['status'] === 200 && array_keys($cat) === ['areas', 'tipos_solicitud', 'prioridades']
    && $cat['areas'] !== [] && $cat['tipos_solicitud'] !== [] && $cat['prioridades'] !== [], $r);
comprobar('GET /catalogos: columnas mínimas (sin jefe_usuario_id ni activo)',
    $columnas($cat['areas'] ?? [], ['id', 'codigo', 'nombre'])
    && $columnas($cat['tipos_solicitud'] ?? [], ['id', 'nombre', 'descripcion'])
    && $columnas($cat['prioridades'] ?? [], ['id', 'codigo', 'nombre', 'horas_max_atencion'])
    && !str_contains($r['raw'], 'jefe_usuario_id'), $r);
comprobar('GET /catalogos: prioridades en orden BAJA, MEDIA, ALTA, URGENTE con horas enteras',
    array_column($cat['prioridades'] ?? [], 'codigo') === ['BAJA', 'MEDIA', 'ALTA', 'URGENTE']
    && array_filter($cat['prioridades'] ?? [], static fn ($p) => !is_int($p['horas_max_atencion'])) === [], $r);
$r = $sol->pedir('PUT', '/api/v1/catalogos', new stdClass(), [], true);
comprobar('PUT /catalogos -> 405 METODO_NO_PERMITIDO', $r['status'] === 405 && codigoError($r) === 'METODO_NO_PERMITIDO', $r);

// --------------------------------------------------------------------------
// 3. Registrar y consultar
// --------------------------------------------------------------------------
seccion('Solicitudes: registrar y consultar');

$valida = [
    'titulo' => 'Prueba automática ' . date('Y-m-d H:i:s'),
    'descripcion' => 'Solicitud creada por tests/api_test.php; termina cancelada.',
    'area_destino_id' => 1,
    'tipo_solicitud_id' => 1,
    'prioridad_id' => 1,
];

$r = $anonimo->pedir('POST', '/api/v1/solicitudes', $valida);
comprobar('POST /solicitudes sin sesión -> 401', $r['status'] === 401, $r);

$r = $sol->pedir('POST', '/api/v1/solicitudes', $valida);
comprobar('POST /solicitudes sin X-CSRF-Token -> 403 CSRF_INVALIDO', $r['status'] === 403 && codigoError($r) === 'CSRF_INVALIDO', $r);

$r = $sol->pedir('POST', '/api/v1/solicitudes', ['titulo' => '  ', 'area_destino_id' => 'abc', 'prioridad_id' => 9], [], true);
comprobar('POST /solicitudes inválida -> 422 con errores por campo',
    $r['status'] === 422 && count($r['json']['error']['detalles'] ?? []) === 5, $r);

$r = $sol->pedir('POST', '/api/v1/solicitudes', ['area_destino_id' => 999] + $valida, [], true);
comprobar('POST /solicitudes con área inexistente -> 422', $r['status'] === 422 && isset($r['json']['error']['detalles']['area_destino_id']), $r);

$r = $sol->pedir('POST', '/api/v1/solicitudes', $valida + ['estado_id' => 5, 'numero' => '1999-00001'], [], true);
$s = $r['json']['data'] ?? [];
$id = (int) ($s['id'] ?? 0);
comprobar('POST /solicitudes válida -> 201, Location, número AAAA-NNNNN, REGISTRADA, version 0, historial REGISTRO',
    $r['status'] === 201
    && ($r['headers']['location'] ?? '') === "/api/v1/solicitudes/$id"
    && preg_match('/^' . date('Y') . '-[0-9]{5}$/', (string) ($s['numero'] ?? '')) === 1
    && ($s['estado']['codigo'] ?? null) === 'REGISTRADA'
    && ($s['version'] ?? null) === 0
    && ($s['historial'][0]['tipo_evento'] ?? null) === 'REGISTRO', $r);
comprobar('fecha_limite posterior a fecha_registro', ($s['fecha_limite'] ?? '') > ($s['fecha_registro'] ?? 'z'), $r);
$numero = (string) ($s['numero'] ?? '');
$e = estadisticas($anonimo);
comprobar('GET /estadisticas tras registrar: total y registradas +1',
    ($e['total'] ?? -1) === $estAntes['total'] + 1 && ($e['registradas'] ?? -1) === $estAntes['registradas'] + 1);

$r = $sol->pedir('GET', '/api/v1/solicitudes?q=' . rawurlencode($numero));
comprobar('GET /solicitudes?q=<número> -> 200 con la solicitud creada',
    $r['status'] === 200 && ($r['json']['meta']['total'] ?? 0) === 1 && ($r['json']['data'][0]['id'] ?? 0) === $id, $r);

$r = $sol->pedir('GET', '/api/v1/solicitudes?estado=REGISTRADA&por_pagina=1&pagina=1');
comprobar('GET /solicitudes con filtros y paginación -> 200 y meta coherente',
    $r['status'] === 200 && ($r['json']['meta']['por_pagina'] ?? 0) === 1 && count($r['json']['data'] ?? []) <= 1, $r);

$r = $sol->pedir('GET', '/api/v1/solicitudes?estado=FOO&por_pagina=0');
comprobar('GET /solicitudes con parámetros inválidos -> 400 PARAMETRO_INVALIDO', $r['status'] === 400 && codigoError($r) === 'PARAMETRO_INVALIDO', $r);

$r = $jefe->pedir('GET', '/api/v1/solicitudes?q=' . rawurlencode($numero));
comprobar('GET /solicitudes del jefe no incluye solicitudes ajenas', $r['status'] === 200 && ($r['json']['meta']['total'] ?? -1) === 0, $r);

$r = $sol->pedir('GET', "/api/v1/solicitudes/$id");
comprobar('GET /solicitudes/{id} propia -> 200 con historial', $r['status'] === 200 && count($r['json']['data']['historial'] ?? []) === 1, $r);

$r = $jefe->pedir('GET', "/api/v1/solicitudes/$id");
comprobar('GET /solicitudes/{id} ajena -> 403 SIN_PERMISO', $r['status'] === 403 && codigoError($r) === 'SIN_PERMISO', $r);

$r = $sol->pedir('GET', '/api/v1/solicitudes/999999999');
comprobar('GET /solicitudes/{id} inexistente -> 404 NO_ENCONTRADO', $r['status'] === 404 && codigoError($r) === 'NO_ENCONTRADO', $r);

$r = $sol->pedir('GET', '/api/v1/solicitudes/abc');
comprobar('GET /solicitudes/abc -> 400 PARAMETRO_INVALIDO', $r['status'] === 400 && codigoError($r) === 'PARAMETRO_INVALIDO', $r);

// --------------------------------------------------------------------------
// 4. Editar y cancelar
// --------------------------------------------------------------------------
seccion('Solicitudes: editar y cancelar');

$edicion = [
    'titulo' => $valida['titulo'] . ' (editada)',
    'descripcion' => $valida['descripcion'],
    'tipo_solicitud_id' => 1,
    'version' => 0,
];

$r = $anonimo->pedir('PUT', "/api/v1/solicitudes/$id", $edicion);
comprobar('PUT sin sesión -> 401', $r['status'] === 401, $r);

$r = $sol->pedir('PUT', "/api/v1/solicitudes/$id", $edicion);
comprobar('PUT sin X-CSRF-Token -> 403 CSRF_INVALIDO', $r['status'] === 403 && codigoError($r) === 'CSRF_INVALIDO', $r);

$r = $jefe->pedir('PUT', "/api/v1/solicitudes/$id", $edicion, [], true);
comprobar('PUT de solicitud ajena -> 403 SIN_PERMISO', $r['status'] === 403 && codigoError($r) === 'SIN_PERMISO', $r);

$r = $sol->pedir('PUT', '/api/v1/solicitudes/999999999', $edicion, [], true);
comprobar('PUT de solicitud inexistente -> 404', $r['status'] === 404, $r);

$r = $sol->pedir('PUT', "/api/v1/solicitudes/$id", ['area_destino_id' => 2] + $edicion, [], true);
comprobar('PUT cambiando área destino -> 422', $r['status'] === 422 && isset($r['json']['error']['detalles']['area_destino_id']), $r);

$r = $sol->pedir('PUT', "/api/v1/solicitudes/$id", $edicion + ['area_destino_id' => 1, 'prioridad_id' => 1], [], true);
$s = $r['json']['data'] ?? [];
$ultimo = end($s['historial']) ?: [];
comprobar('PUT válido -> 200, version 1, historial EDICION con antes/después del título',
    $r['status'] === 200 && ($s['version'] ?? null) === 1 && ($ultimo['tipo_evento'] ?? null) === 'EDICION'
    && ($ultimo['detalle']['titulo']['despues'] ?? null) === $edicion['titulo'], $r);

$r = $sol->pedir('PUT', "/api/v1/solicitudes/$id", ['titulo' => 'Versión vieja'] + $edicion, [], true);
comprobar('PUT con version desactualizada -> 409 CONFLICTO_VERSION', $r['status'] === 409 && codigoError($r) === 'CONFLICTO_VERSION', $r);

$r = $sol->pedir('DELETE', "/api/v1/solicitudes/$id", ['version' => 1], [], true);
comprobar('DELETE sin motivo -> 422', $r['status'] === 422 && isset($r['json']['error']['detalles']['motivo']), $r);

$r = $jefe->pedir('DELETE', "/api/v1/solicitudes/$id", ['motivo' => 'Ajena', 'version' => 1], [], true);
comprobar('DELETE de solicitud ajena -> 403 SIN_PERMISO', $r['status'] === 403 && codigoError($r) === 'SIN_PERMISO', $r);

$r = $sol->pedir('DELETE', "/api/v1/solicitudes/$id", ['motivo' => 'Versión vieja', 'version' => 0], [], true);
comprobar('DELETE con version desactualizada -> 409 CONFLICTO_VERSION', $r['status'] === 409 && codigoError($r) === 'CONFLICTO_VERSION', $r);

$r = $sol->pedir('DELETE', "/api/v1/solicitudes/$id", ['motivo' => 'Cancelada por la suite de pruebas', 'version' => 1], [], true);
$s = $r['json']['data'] ?? [];
$ultimo = end($s['historial']) ?: [];
comprobar('DELETE válido -> 200, CANCELADA, fecha_cierre, version 2, historial CAMBIO_ESTADO con motivo',
    $r['status'] === 200 && ($s['estado']['codigo'] ?? null) === 'CANCELADA' && ($s['fecha_cierre'] ?? null) !== null
    && ($s['version'] ?? null) === 2 && ($ultimo['tipo_evento'] ?? null) === 'CAMBIO_ESTADO'
    && ($ultimo['motivo'] ?? null) === 'Cancelada por la suite de pruebas', $r);
$e = estadisticas($anonimo);
comprobar('GET /estadisticas tras cancelar: registradas vuelve al valor inicial y total queda +1 (no se borra)',
    ($e['registradas'] ?? -1) === $estAntes['registradas'] && ($e['total'] ?? -1) === $estAntes['total'] + 1);

$r = $sol->pedir('PUT', "/api/v1/solicitudes/$id", ['version' => 2] + $edicion, [], true);
comprobar('PUT sobre cancelada -> 409 ESTADO_NO_PERMITE', $r['status'] === 409 && codigoError($r) === 'ESTADO_NO_PERMITE', $r);

$r = $sol->pedir('DELETE', "/api/v1/solicitudes/$id", ['motivo' => 'Otra vez', 'version' => 2], [], true);
comprobar('DELETE sobre cancelada -> 409 ESTADO_NO_PERMITE', $r['status'] === 409 && codigoError($r) === 'ESTADO_NO_PERMITE', $r);

$r = $sol->pedir('GET', "/api/v1/solicitudes/$id");
comprobar('La solicitud cancelada sigue existiendo (GET -> 200, no se borra)', $r['status'] === 200 && count($r['json']['data']['historial'] ?? []) === 3, $r);

// --------------------------------------------------------------------------
// 5. Cierre de sesión
// --------------------------------------------------------------------------
seccion('Cierre de sesión');

$r = $sol->pedir('POST', '/api/v1/auth/logout', null, ['X-CSRF-Token' => 'token-invalido']);
comprobar('POST /auth/logout con token inválido -> 403 CSRF_INVALIDO', $r['status'] === 403 && codigoError($r) === 'CSRF_INVALIDO', $r);

$r = $sol->pedir('POST', '/api/v1/auth/logout', null, [], true);
comprobar('POST /auth/logout válido -> 200', $r['status'] === 200, $r);

$r = $sol->pedir('GET', '/api/v1/auth/me');
comprobar('GET /auth/me tras logout -> 401', $r['status'] === 401, $r);

$r = $jefe->pedir('POST', '/api/v1/auth/logout', null, [], true);
comprobar('POST /auth/logout del jefe -> 200', $r['status'] === 200, $r);

// --------------------------------------------------------------------------
// Resumen y guardado
// --------------------------------------------------------------------------
$resumen = sprintf('# Resultado: %d pruebas, %d correctas, %d fallidas. Solicitud creada: id %d (%s), cancelada.', $n, $n - $fallos, $fallos, $id, $numero);
echo $resumen, "\n";
$resultados[] = $resumen;

$dir = __DIR__ . '/resultados';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
$archivo = $dir . '/resultado_' . date('Ymd_His') . '.txt';
file_put_contents($archivo, implode("\n", $resultados) . "\n");
echo "# Guardado en tests/resultados/" . basename($archivo) . "\n";

exit($fallos === 0 ? 0 : 1);
