<?php

namespace App\Tablas;

use PDO;

class Cupon extends Modelo
{
    protected static string $tabla = 'cupones';

    public $id;
    private $codigo;
    private $descuento;
    private $caducidad;
    private $cupon;

    public function __construct(array $campos)
    {
        $this->id = $campos['id'];
        $this->codigo = $campos['codigo'];
        $this->descuento = $campos['descuento'];
        $this->caducidad = $campos['caducidad'];
        $this->cupon = $campos['cupon'];
    }

    public static function existe(int $id, ?PDO $pdo = null): bool
    {
        $pdo = $pdo ?? conectar();
        return static::obtener($id, $pdo) !== null;
    }

    public function getCodigo()
    {
        return $this->codigo;
    }

    public function getDescuento()
    {
        return $this->descuento;
    }

    public function getCaducidad()
    {
        return $this->caducidad;
    }

    public function getCupon()
    {
        return $this->cupon;
    }

    public static function getCuponNombre($id, ?PDO $pdo = null)
    {
        $pdo = $pdo ?? conectar();
        $sent = $pdo -> prepare("SELECT cupon FROM cupones WHERE id = :id");
        $sent -> execute([':id' => $id]);
        $nombre = $sent->fetchColumn();
        return $nombre;
    }
}
