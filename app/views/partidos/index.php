<h1>Listado de Partidos</h1>

<div class="actions">
    <a href="<?= BASE_URL ?>?controller=Partidos&action=crear" class="btn">+ Registrar Partido</a>
</div>

<?php if (empty($partidos)): ?>
    <p class="text-center">No hay partidos registrados.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Local</th>
                <th>Visitante</th>
                <th>Marcador</th>
                <th>Resultado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($partidos as $p): ?>
                <?php
                    $pl = (int)$p['puntos_local'];
                    $pv = (int)$p['puntos_visitante'];
                    if ($pl > $pv) {
                        $res = 'Gana Local';
                    } elseif ($pv > $pl) {
                        $res = 'Gana Visitante';
                    } else {
                        $res = 'Empate';
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars((string)$p['id']) ?></td>
                    <td><?= htmlspecialchars($p['fecha']) ?></td>
                    <td><?= htmlspecialchars($p['nombre_local']) ?></td>
                    <td><?= htmlspecialchars($p['nombre_visitante']) ?></td>
                    <td><?= $pl ?> - <?= $pv ?></td>
                    <td><?= $res ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>?controller=Partidos&action=editar&id=<?= $p['id'] ?>" class="btn btn-secondary">Editar</a>
                                                <form action="<?= BASE_URL ?>?controller=Partidos&action=eliminar" method="POST" class="inline-form"
                              onsubmit="return confirm('¿Eliminar este partido?');">
                            <?= csrfCampo() ?>
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>