<?php session_start() ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/css/output.css" rel="stylesheet">
    <title>Comprar</title>
</head>

<body>
    <?php require '../vendor/autoload.php';

    if (!\App\Tablas\Usuario::esta_logueado()) {
        return redirigir_login();
    }

    $cuponCodigo = obtener_get('cupon');
    $cuponDescartar = obtener_get('cuponDescartar');
    $cupon = false;
    $carrito = unserialize(carrito());

    if (isset($cuponCodigo) && $cuponCodigo != '') {
        $pdo = conectar();
        $sentCupon = $pdo->prepare("SELECT * FROM cupones WHERE lower(unaccent(codigo)) LIKE lower(unaccent(:codigo))");
        $sentCupon->execute([':codigo' => $cuponCodigo]);
        $cupon = $sentCupon ? $sentCupon->fetch() : $cupon;

        if ($cupon) {
            if ($cupon['caducidad'] < date('Y-m-d')) {
                $cupon = false;
                $_SESSION['error'] = 'El cupón ha expirado';
            }
        } else if (isset($cuponDescartar) ) {
            $_SESSION['exito'] = 'Eliminaste el cupón';
            $cupon = false;
        } else {
            $_SESSION['error'] = 'El cupón no existe';
        }
    }

    if (obtener_post('_testigo') !== null) {
        $pdo = conectar();
        $ids_art_carrito = implode(', ', $carrito->getIds());
        $where = "WHERE id IN (" . $ids_art_carrito . ")";
        $sent = $pdo->prepare("SELECT *
                                FROM articulos
                                $where");
        $sent->execute();
        $res = $sent->fetchAll(PDO::FETCH_ASSOC);

        foreach ($res as $fila) {
            if ($fila['stock'] < $carrito->getLinea($fila['id'])->getCantidad()) {
                $_SESSION['error'] = 'No hay existencias suficientes para crear la factura.';
                return volver();
            }
        }
        // Crear factura
        $usuario = \App\Tablas\Usuario::logueado();
        $usuario_id = $usuario->id;
        $pdo->beginTransaction();
        $sent = $pdo->prepare('INSERT INTO facturas (usuario_id)
                               VALUES (:usuario_id)
                               RETURNING id');
        $sent->execute([':usuario_id' => $usuario_id]);
        $factura_id = $sent->fetchColumn();
        $lineas = $carrito->getLineas();
        $values = [];
        $execute = [':f' => $factura_id];
        $i = 1;

        foreach ($lineas as $id => $linea) {
            $values[] = "(:a$i, :f, :c$i)";
            $execute[":a$i"] = $id;
            $execute[":c$i"] = $linea->getCantidad();
            $i++;
        }

        $values = implode(', ', $values);
        $sent = $pdo->prepare("INSERT INTO articulos_facturas (articulo_id, factura_id, cantidad)
                               VALUES $values");
        $sent->execute($execute);
        foreach ($lineas as $id => $linea) {
            $cantidad = $linea->getCantidad();
            $sent = $pdo->prepare('UPDATE articulos
                                      SET stock = stock - :cantidad
                                    WHERE id = :id');
            $sent->execute([':id' => $id, ':cantidad' => $cantidad]);
        }

        if ($cupon) {
            $cuponId = $cupon['id'];
            $sentFactura = $pdo->prepare("UPDATE facturas SET cupon_id = :cuponId WHERE id = :id");
            $sentFactura->execute([":id" => $factura_id, ":cuponId" => $cuponId]);
        }

        $pdo->commit();
        $_SESSION['exito'] = 'La factura se ha creado correctamente.';
        unset($_SESSION['carrito']);
        return volver();
    }

    ?>

    <div class="container mx-auto">
        <?php require '../src/_menu.php' ?>
        <?php require '../src/_alerts.php' ?>
        <div class="overflow-y-auto py-4 px-3 bg-gray-50 rounded dark:bg-gray-800">

            <form action="" method="get">
                <label for="cupon">Cupon de descuento: </label>
                <input type="text" name="cupon" id="cupon" class="rounded-lg" value="<?= isset($cupon) ? $cupon['codigo'] : '' ?>">
                <button type="submit" href="" class="mx-auto focus:outline-none text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-900">Aplicar </button>
            </form>
            <form action="" method="get">
                <input type="hidden" name="cuponDescartar" id="cupon" class="rounded-lg" value="false">
                <button type="submit" href="" class="mx-auto focus:outline-none text-white bg-red-700 hover:bg-red-800 focus:ring-4 focus:ring-red-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-900">Descartar cupón</button>
            </form>

            <table class="mx-auto text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                    <th scope="col" class="py-3 px-6">Código</th>
                    <th scope="col" class="py-3 px-6">Descripción</th>
                    <th scope="col" class="py-3 px-6">Cantidad</th>
                    <th scope="col" class="py-3 px-6">Precio</th>
                    <th scope="col" class="py-3 px-6">Importe</th>
                </thead>
                <tbody>
                    <?php $total = 0 ?>
                    <?php foreach ($carrito->getLineas() as $id => $linea) : ?>
                        <?php
                        $articulo = $linea->getArticulo();
                        $codigo = $articulo->getCodigo();
                        $cantidad = $linea->getCantidad();
                        $precio = $articulo->getPrecio();
                        $importe = $cantidad * $precio;
                        $total += $importe;
                        ?>
                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                            <td class="py-4 px-6"><?= $articulo->getCodigo() ?></td>
                            <td class="py-4 px-6"><?= $articulo->getDescripcion() ?></td>
                            <td class="py-4 px-6 text-center"><?= $cantidad ?></td>
                            <td class="py-4 px-6 text-center">
                                <?= dinero($precio) ?>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <?= dinero($importe) ?>
                            </td>
                        </tr>
                    <?php endforeach;

                    if (isset($cupon)) {
                        $subtotal = $total;
                        $total = $total - $total * $cupon['descuento'];
                    }

                    ?>

                </tbody>
                <tfoot>
                    <?php
                    if ($cupon) : ?>
                        <tr>
                            <td colspan="3"></td>
                            <td class="text-center font-semibold">Cupón aplicado: </td>
                            <td class="text-center font-semibold text-red-600"> -<?= dinero($subtotal - $total) ?> </td>
                        </tr>
                    <?php endif ?>

                    <tr>
                        <td colspan="3"></td>
                        <td class="text-center font-semibold">TOTAL:</td>
                        <td class="text-center font-semibold"><?= dinero($total) ?></td>
                    </tr>
                </tfoot>
            </table>
            <form action="" method="POST" class="mx-auto flex mt-4">
                <input type="hidden" name="_testigo" value="1">
                <button type="submit" href="" class="mx-auto focus:outline-none text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-900">Realizar pedido</button>
            </form>
        </div>
    </div>
    <script src="/js/flowbite/flowbite.js"></script>
</body>

</html>