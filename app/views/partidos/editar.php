<h1>Editar Partido</h1>

<form action="<?= BASE_URL ?>?controller=Partidos&action=actualizar" method="POST">
    <input type="hidden" name="id" value="<?= htmlspecialchars((string)$partido['id']) ?>">

    <div class="form-group">
        <label for="equipo_local_id">Equipo Local *</label>
        <select id="equipo_local_id" name="equipo_local_id" required>
            <?php foreach ($equipos as $e): ?>
                <option value="<?= $e['id'] ?>" <?= ((int)($old['equipo_local_id'] ?? $partido['equipo_local_id']))===(int)$e['id']?'selected':'' ?>>
                    <?= htmlspecialchars($e['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="equipo_visitante_id">Equipo Visitante *</label>
        <select id="equipo_visitante_id" name="equipo_visitante_id" required>
            <?php foreach ($equipos as $e): ?>
                <option value="<?= $e['id'] ?>" <?= ((int)($old['equipo_visitante_id'] ?? $partido['equipo_visitante_id']))===(int)$e['id']?'selected':'' ?>>
                    <?= htmlspecialchars($e['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="puntos_local">Puntos Local *</label>
        <input type="number" id="puntos_local" name="puntos_local" min="0" value="<?= htmlspecialchars((string)($old['puntos_local'] ?? $partido['puntos_local'])) ?>" required>
    </div>

    <div class="form-group">
        <label for="puntos_visitante">Puntos Visitante *</label>
        <input type="number" id="puntos_visitante" name="puntos_visitante" min="0" value="<?= htmlspecialchars((string)($old['puntos_visitante'] ?? $partido['puntos_visitante'])) ?>" required>
    </div>

    <div class="form-group">
        <label for="fecha">Fecha y Hora *</label>
        <input type="datetime-local" id="fecha" name="fecha" value="<?= htmlspecialchars(substr($old['fecha'] ?? $partido['fecha'], 0, 16)) ?>" required>
    </div>

    <button type="submit" class="btn">Actualizar</button>
    <a href="<?= BASE_URL ?>?controller=Partidos&action=index" class="btn btn-secondary">Cancelar</a>
</form>
