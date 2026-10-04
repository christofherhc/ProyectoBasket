<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($titulo) ? htmlspecialchars($titulo) . ' - Liga de Básquetbol' : 'Liga de Básquetbol' ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/styles.css">
</head>
<body>
    <header>
        <h1>🏀 Liga de Básquetbol</h1>
    </header>

    <nav>
        <ul>
            <?php if (isset($_SESSION['logueado']) && $_SESSION['logueado']): ?>
                <li><a href="<?= BASE_URL ?>?controller=Equipos&action=index">Equipos</a></li>
                <li><a href="<?= BASE_URL ?>?controller=Jugadores&action=index">Jugadores</a></li>
                <li><a href="<?= BASE_URL ?>?controller=Partidos&action=index">Partidos</a></li>
                <li><a href="<?= BASE_URL ?>?controller=Estadisticas&action=index">Estadísticas</a></li>
                <li>
                    <form action="<?= BASE_URL ?>?controller=Auth&action=logout" method="POST" class="inline-form">
                        <?= csrfCampo() ?>
                        <button type="submit" class="btn-link">Cerrar Sesión (<?= e($_SESSION['nombre']) ?>)</button>
                    </form>
                </li>
            <?php else: ?>
                <li><a href="<?= BASE_URL ?>?controller=Auth&action=index">Iniciar Sesión</a></li>
                <li><a href="<?= BASE_URL ?>?controller=Auth&action=register">Registrarse</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <main>
        <div class="container">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['exito'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['exito']) ?>
                </div>
                <?php unset($_SESSION['exito']); ?>
            <?php endif; ?>

            <?php
            // Cargar vista específica
            // __DIR__ evita depender del directorio de trabajo de PHP.
            $vistaArchivo = __DIR__ . '/../' . $vista . '.php';
            if (is_file($vistaArchivo)) {
                require_once $vistaArchivo;
            } else {
                // No se imprime la ruta del disco: sería información interna.
                echo '<h2>Error: Vista no encontrada</h2>';
            }
            ?>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 Liga de Básquetbol - Proyecto de Aprendizaje</p>
    </footer>
</body>
</html>
