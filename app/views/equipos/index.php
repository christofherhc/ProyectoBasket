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
                    <td><?= e($equipo['id']) ?></td>
                    <td><?= e($equipo['nombre']) ?></td>
                    <td><?= e($equipo['ciudad']) ?></td>
                    <td><?= e($equipo['fundacion'] ?? '-') ?></td>
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
