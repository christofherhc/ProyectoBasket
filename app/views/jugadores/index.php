<h1>Listado de Jugadores</h1>

<div class="actions">
    <a href="<?= BASE_URL ?>?controller=Jugadores&action=crear" class="btn">+ Nuevo Jugador</a>
</div>

<?php if (empty($jugadores)): ?>
    <p class="text-center">No hay jugadores registrados aún.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Número</th>
                <th>Posición</th>
                <th>Equipo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jugadores as $jugador): ?>
                <tr>
                    <td><?= e($jugador['id']) ?></td>
                    <td><?= e($jugador['nombre']) ?></td>
                    <td><?= e($jugador['numero'] ?? '-') ?></td>
                    <td><?= e($jugador['posicion'] ?? '-') ?></td>
                    <td><?= e($jugador['nombre_equipo'] ?? '-') ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>?controller=Jugadores&action=editar&id=<?= $jugador['id'] ?>" class="btn btn-secondary">Editar</a>
                                                <form action="<?= BASE_URL ?>?controller=Jugadores&action=eliminar" method="POST" class="inline-form"
                              onsubmit="return confirm('¿Eliminar este jugador?');">
                            <?= csrfCampo() ?>
                            <input type="hidden" name="id" value="<?= $jugador['id'] ?>">
                            <button type="submit" class="btn btn-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
