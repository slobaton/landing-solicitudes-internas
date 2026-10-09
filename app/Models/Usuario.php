<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Usuario autenticado (seg_usuario + area + roles). Nunca expone password_hash.
 */
final class Usuario
{
    /**
     * @param array{id: int, codigo: string, nombre: string} $area
     * @param list<string>                                    $roles
     */
    public function __construct(
        public readonly int $id,
        public readonly string $nombreUsuario,
        public readonly string $nombres,
        public readonly string $apellidos,
        public readonly string $correo,
        public readonly bool $activo,
        public readonly array $area,
        public readonly array $roles,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string>         $roles
     */
    public static function fromRow(array $row, array $roles): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['nombre_usuario'],
            (string) $row['nombres'],
            (string) $row['apellidos'],
            (string) $row['correo'],
            (int) $row['activo'] === 1,
            [
                'id' => (int) $row['area_id'],
                'codigo' => (string) $row['area_codigo'],
                'nombre' => (string) $row['area_nombre'],
            ],
            $roles,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nombre_usuario' => $this->nombreUsuario,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'correo' => $this->correo,
            'area' => $this->area,
            'roles' => $this->roles,
        ];
    }
}
