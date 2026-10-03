<h1>Estadísticas de la Liga</h1>

<h2>Tabla de Posiciones</h2>

<?php if (empty($stats)): ?>
    <p>No hay datos para calcular estadísticas.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Equipo</th>
                <th>PJ</th>
                <th>PG</th>
                <th>PE</th>
                <th>PP</th>
                <th>GF</th>
                <th>GC</th>
                <th>DIF</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stats as $i => $s): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($s['nombre']) ?></td>
                    <td><?= $s['pj'] ?></td>
                    <td><?= $s['pg'] ?></td>
                    <td><?= $s['pe'] ?></td>
                    <td><?= $s['pp'] ?></td>
                    <td><?= $s['gf'] ?></td>
                    <td><?= $s['gc'] ?></td>
                    <td><?= $s['dif'] >= 0 ? '+' : '' ?><?= $s['dif'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2 style="margin-top:2rem">Últimos Partidos</h2>

<?php if (empty($partidos)): ?>
    <p>No hay partidos registrados.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Local</th>
                <th>Visitante</th>
                <th>Marcador</th>
                <th>Resultado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_slice($partidos, 0, 10) as $p): ?>
                <?php
                    $pl = (int)$p['puntos_local']; $pv = (int)$p['puntos_visitante'];
                    if ($pl>$pv) $r='Gana Local'; elseif($pv>$pl) $r='Gana Visitante'; else $r='Empate';
                ?>
                <tr>
                    <td><?= htmlspecialchars($p['fecha']) ?></td>
                    <td><?= htmlspecialchars($p['nombre_local']) ?></td>
                    <td><?= htmlspecialchars($p['nombre_visitante']) ?></td>
                    <td><?= $pl ?> - <?= $pv ?></td>
                    <td><?= $r ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
