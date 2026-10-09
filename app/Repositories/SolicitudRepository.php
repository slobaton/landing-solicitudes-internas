<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Persistencia de sol_solicitud, sol_secuencia y sol_historial
 * (T-01, PA-05, PA-06). Sin DELETE: las solicitudes no se borran (R-06).
 */
final class SolicitudRepository
{
    private const SELECT_BASE = <<<'SQL'
        SELECT s.id, s.numero, s.titulo, s.descripcion, s.solicitante_id, s.area_destino_id,
               s.tipo_solicitud_id, s.prioridad_id, s.estado_id, s.responsable_id,
               s.fecha_registro, s.fecha_limite, s.fecha_atencion, s.fecha_cierre, s.version,
               a.nombre AS area_nombre, t.nombre AS tipo_nombre,
               p.codigo AS prioridad_codigo, p.nombre AS prioridad_nombre,
               e.codigo AS estado_codigo, e.nombre AS estado_nombre,
               (e.es_final = 0 AND s.fecha_limite < NOW()) AS vencida,
               us.nombres AS sol_nombres, us.apellidos AS sol_apellidos,
               ur.nombres AS resp_nombres, ur.apellidos AS resp_apellidos
          FROM sol_solicitud s
          JOIN org_area a            ON a.id = s.area_destino_id
          JOIN cat_tipo_solicitud t  ON t.id = s.tipo_solicitud_id
          JOIN cat_prioridad p       ON p.id = s.prioridad_id
          JOIN cat_estado e          ON e.id = s.estado_id
          JOIN seg_usuario us        ON us.id = s.solicitante_id
          LEFT JOIN seg_usuario ur   ON ur.id = s.responsable_id
        SQL;

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function enTransaccion(callable $fn): mixed
    {
        return Database::transaction($fn);
    }

    /** Hora actual de la BD (zona de la sesion = APP_TIMEZONE). */
    public function ahora(): string
    {
        return (string) Database::connection()->query('SELECT NOW()')->fetchColumn();
    }

    /**
     * Siguiente valor de la secuencia anual en una sola sentencia atomica
     * (T-01 paso 3). Bloquea la fila del anio hasta el COMMIT.
     */
    public function siguienteNumero(int $anio): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO sol_secuencia (anio, ultimo_valor) VALUES (?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE ultimo_valor = LAST_INSERT_ID(ultimo_valor + 1)'
        );
        $stmt->execute([$anio]);
        // Leer antes del siguiente INSERT con AUTO_INCREMENT, que lo sobrescribe.
        return (int) $pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
    }

    /** @param array{numero: string, titulo: string, descripcion: string, solicitante_id: int, area_destino_id: int, tipo_solicitud_id: int, prioridad_id: int, fecha_registro: string, fecha_limite: string} $d */
    public function insertar(array $d): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO sol_solicitud
                (numero, titulo, descripcion, solicitante_id, area_destino_id, tipo_solicitud_id,
                 prioridad_id, estado_id, responsable_id, fecha_registro, fecha_limite, version)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NULL, ?, ?, 0)'
        );
        $stmt->execute([
            $d['numero'], $d['titulo'], $d['descripcion'], $d['solicitante_id'], $d['area_destino_id'],
            $d['tipo_solicitud_id'], $d['prioridad_id'], $d['fecha_registro'], $d['fecha_limite'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Inserta un evento del historial (solo INSERT, RN-D13).
     *
     * @param array<string, mixed>|null $detalle
     */
    public function insertarHistorial(
        int $solicitudId,
        string $tipoEvento,
        ?int $usuarioId,
        ?string $ip,
        ?int $transicionId = null,
        ?int $estadoAnteriorId = null,
        ?int $estadoNuevoId = null,
        ?int $prioridadNuevaId = null,
        ?string $motivo = null,
        ?array $detalle = null,
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO sol_historial
                (solicitud_id, tipo_evento, usuario_id, transicion_id, estado_anterior_id, estado_nuevo_id,
                 prioridad_nueva_id, motivo, detalle, ip_origen)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $solicitudId, $tipoEvento, $usuarioId, $transicionId, $estadoAnteriorId, $estadoNuevoId,
            $prioridadNuevaId, $motivo,
            $detalle === null ? null : json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $ip === null ? null : substr($ip, 0, 45),
        ]);
    }

    /**
     * Lee y bloquea la fila de la solicitud (SELECT ... FOR UPDATE, DF-06).
     * Debe llamarse dentro de una transaccion.
     *
     * @return array{id: int, solicitante_id: int, area_destino_id: int, tipo_solicitud_id: int, prioridad_id: int, estado_id: int, titulo: string, descripcion: string, version: int}|null
     */
    public function bloquearParaActualizar(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, solicitante_id, area_destino_id, tipo_solicitud_id, prioridad_id, estado_id,
                    titulo, descripcion, version
               FROM sol_solicitud WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        foreach (['id', 'solicitante_id', 'area_destino_id', 'tipo_solicitud_id', 'prioridad_id', 'estado_id', 'version'] as $k) {
            $row[$k] = (int) $row[$k];
        }
        return $row;
    }

    /**
     * Edicion con control optimista (T-04). Devuelve las filas afectadas
     * (0 = la version cambio).
     */
    public function actualizarEdicion(int $id, int $version, string $titulo, string $descripcion, int $tipoId): int
    {
        $stmt = Database::connection()->prepare(
            'UPDATE sol_solicitud
                SET titulo = ?, descripcion = ?, tipo_solicitud_id = ?, version = version + 1
              WHERE id = ? AND version = ?'
        );
        $stmt->execute([$titulo, $descripcion, $tipoId, $id, $version]);
        return $stmt->rowCount();
    }

    /**
     * Transicion activa del rol desde el estado actual hacia el destino
     * (cat_transicion_estado, RC-03). Null si no existe o esta inactiva.
     *
     * @return array{id: int, requiere_motivo: bool}|null
     */
    public function transicionActiva(int $origenId, int $destinoId, int $rolId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, requiere_motivo FROM cat_transicion_estado
              WHERE estado_origen_id = ? AND estado_destino_id = ? AND rol_id = ? AND activa = 1'
        );
        $stmt->execute([$origenId, $destinoId, $rolId]);
        $row = $stmt->fetch();
        return $row === false ? null : ['id' => (int) $row['id'], 'requiere_motivo' => (int) $row['requiere_motivo'] === 1];
    }

    /**
     * Cancelacion = UPDATE a CANCELADA con fecha de cierre (nunca DELETE, R-06).
     * Devuelve las filas afectadas (0 = la version cambio).
     */
    public function cancelar(int $id, int $version): int
    {
        $stmt = Database::connection()->prepare(
            'UPDATE sol_solicitud
                SET estado_id = 7, fecha_cierre = NOW(), version = version + 1
              WHERE id = ? AND version = ?'
        );
        $stmt->execute([$id, $version]);
        return $stmt->rowCount();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(self::SELECT_BASE . ' WHERE s.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> Historial ordenado por id (PA-06). */
    public function historial(int $solicitudId): array
    {
        $stmt = Database::connection()->prepare(<<<'SQL'
            SELECT h.id, h.tipo_evento, h.fecha, h.usuario_id, h.motivo, h.detalle,
                   u.nombres AS usu_nombres, u.apellidos AS usu_apellidos,
                   h.estado_anterior_id, ea.codigo AS estado_anterior_codigo, ea.nombre AS estado_anterior_nombre,
                   h.estado_nuevo_id, en.codigo AS estado_nuevo_codigo, en.nombre AS estado_nuevo_nombre
              FROM sol_historial h
              LEFT JOIN seg_usuario u ON u.id = h.usuario_id
              LEFT JOIN cat_estado ea ON ea.id = h.estado_anterior_id
              LEFT JOIN cat_estado en ON en.id = h.estado_nuevo_id
             WHERE h.solicitud_id = ?
             ORDER BY h.id
            SQL);
        $stmt->execute([$solicitudId]);
        return $stmt->fetchAll();
    }

    /**
     * Mis solicitudes con filtros y paginacion (PA-05, ix_sol_solicitud_solicitante).
     *
     * @param array{estado_id: ?int, prioridad_id: ?int, desde: ?string, hasta_exclusivo: ?string, q: ?string} $f
     * @return array{filas: list<array<string, mixed>>, total: int}
     */
    public function listarDeSolicitante(int $solicitanteId, array $f, int $limite, int $desplazamiento): array
    {
        $where = ['s.solicitante_id = ?'];
        $params = [$solicitanteId];

        if ($f['estado_id'] !== null) {
            $where[] = 's.estado_id = ?';
            $params[] = $f['estado_id'];
        }
        if ($f['prioridad_id'] !== null) {
            $where[] = 's.prioridad_id = ?';
            $params[] = $f['prioridad_id'];
        }
        if ($f['desde'] !== null) {
            $where[] = 's.fecha_registro >= ?';
            $params[] = $f['desde'] . ' 00:00:00';
        }
        if ($f['hasta_exclusivo'] !== null) {
            $where[] = 's.fecha_registro < ?';
            $params[] = $f['hasta_exclusivo'] . ' 00:00:00';
        }
        if ($f['q'] !== null) {
            $patron = '%' . addcslashes($f['q'], '\\%_') . '%';
            $where[] = '(s.titulo LIKE ? OR s.numero LIKE ?)';
            $params[] = $patron;
            $params[] = $patron;
        }

        $condicion = ' WHERE ' . implode(' AND ', $where);
        $pdo = Database::connection();

        $count = $pdo->prepare('SELECT COUNT(*) FROM sol_solicitud s' . $condicion);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $pdo->prepare(self::SELECT_BASE . $condicion . ' ORDER BY s.fecha_registro DESC, s.id DESC LIMIT ? OFFSET ?');
        foreach ($params as $i => $valor) {
            $stmt->bindValue($i + 1, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(count($params) + 1, $limite, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $desplazamiento, PDO::PARAM_INT);
        $stmt->execute();

        return ['filas' => $stmt->fetchAll(), 'total' => $total];
    }
}
