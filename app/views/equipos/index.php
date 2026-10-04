<h1>Listado de Equipos</h1>

<div class="actions">
    <a href="<?= BASE_URL ?>?controller=Equipos&action=crear" class="btn">+ Nuevo Equipo</a>
</div>

<?php if (empty($equipos)): ?>
    <p class="text-center">No hay equipos registrados aún.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Ciudad</th>
                <th>Año de Fundación</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($equipos as $equipo): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$equipo['id']) ?></td>
                    <td><?= htmlspecialchars($equipo['nombre']) ?></td>
                    <td><?= htmlspecialchars($equipo['ciudad']) ?></td>
                    <td><?= htmlspecialchars($equipo['fundacion'] ?? '-') ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>?controller=Equipos&action=editar&id=<?= $equipo['id'] ?>" class="btn btn-secondary">Editar</a>
                                                <form action="<?= BASE_URL ?>?controller=Equipos&action=eliminar" method="POST" class="inline-form"
                              onsubmit="return confirm('¿Eliminar este equipo?');">
                            <?= csrfCampo() ?>
                            <input type="hidden" name="id" value="<?= $equipo['id'] ?>">
                            <button type="submit" class="btn btn-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
