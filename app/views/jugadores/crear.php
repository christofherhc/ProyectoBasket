<h1>Crear Jugador</h1>

<form action="<?= BASE_URL ?>?controller=Jugadores&action=guardar" method="POST">
    <div class="form-group">
        <label for="nombre">Nombre *</label>
        <input type="text" id="nombre" name="nombre" value="<?= isset($old['nombre']) ? htmlspecialchars($old['nombre']) : '' ?>" required>
    </div>

    <div class="form-group">
        <label for="numero">Número de camiseta</label>
        <input type="number" id="numero" name="numero" min="0" max="99" value="<?= isset($old['numero']) && $old['numero'] !== '' ? htmlspecialchars((string)$old['numero']) : '' ?>">
    </div>

    <div class="form-group">
        <label for="posicion">Posición</label>
        <input type="text" id="posicion" name="posicion" placeholder="Base, Escolta, Alero, Ala-Pívot, Pívot" value="<?= isset($old['posicion']) ? htmlspecialchars($old['posicion']) : '' ?>">
    </div>

    <div class="form-group">
        <label for="equipo_id">Equipo *</label>
        <select id="equipo_id" name="equipo_id" required>
            <option value="">-- Seleccionar equipo --</option>
            <?php foreach ($equipos as $equipo): ?>
                <option value="<?= $equipo['id'] ?>" <?= isset($old['equipo_id']) && (int)$old['equipo_id'] === (int)$equipo['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($equipo['nombre']) ?> (<?= htmlspecialchars($equipo['ciudad']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="btn">Guardar</button>
    <a href="<?= BASE_URL ?>?controller=Jugadores&action=index" class="btn btn-secondary">Cancelar</a>
</form>
