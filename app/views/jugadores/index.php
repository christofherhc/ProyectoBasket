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
                    <td><?= htmlspecialchars((string)$jugador['id']) ?></td>
                    <td><?= htmlspecialchars($jugador['nombre']) ?></td>
                    <td><?= htmlspecialchars($jugador['numero'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($jugador['posicion'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($jugador['nombre_equipo'] ?? '-') ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>?controller=Jugadores&action=editar&id=<?= $jugador['id'] ?>" class="btn btn-secondary">Editar</a>
                        <a href="<?= BASE_URL ?>?controller=Jugadores&action=eliminar&id=<?= $jugador['id'] ?>" class="btn btn-danger" onclick="return confirm('¿Eliminar este jugador?');">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
