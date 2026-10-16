<h1>Editar Jugador</h1>

<form action="<?= BASE_URL ?>?controller=Jugadores&action=actualizar" method="POST">
    <?= csrfCampo() ?>
    <input type="hidden" name="id" value="<?= htmlspecialchars((string)$jugador['id']) ?>">

    <div class="form-group">
        <label for="nombre">Nombre *</label>
        <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($old['nombre'] ?? $jugador['nombre']) ?>" required>
    </div>

    <div class="form-group">
        <label for="numero">Número de camiseta</label>
        <input type="number" id="numero" name="numero" min="0" max="99" value="<?= htmlspecialchars((string)($old['numero'] ?? $jugador['numero'] ?? '')) ?>">
    </div>

    <div class="form-group">
        <label for="posicion">Posición</label>
        <select id="posicion" name="posicion">
            <option value="">-- Sin posición --</option>
            <?php foreach (POSICIONES as $pos): ?>
                <option value="<?= e($pos) ?>" <?= ($old['posicion'] ?? $jugador['posicion']) === $pos ? 'selected' : '' ?>>
                    <?= e($pos) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="equipo_id">Equipo *</label>
        <select id="equipo_id" name="equipo_id" required>
            <option value="">-- Seleccionar equipo --</option>
            <?php foreach ($equipos as $equipo): ?>
                <option value="<?= $equipo['id'] ?>" <?= ((int)($old['equipo_id'] ?? $jugador['equipo_id'])) === (int)$equipo['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($equipo['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="btn">Actualizar</button>
    <a href="<?= BASE_URL ?>?controller=Jugadores&action=index" class="btn btn-secondary">Cancelar</a>
</form>
