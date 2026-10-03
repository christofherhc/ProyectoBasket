<h1>Editar Equipo</h1>

<form action="<?= BASE_URL ?>?controller=Equipos&action=actualizar" method="POST">
    <input type="hidden" name="id" value="<?= htmlspecialchars((string)$equipo['id']) ?>">

    <div class="form-group">
        <label for="nombre">Nombre *</label>
        <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($old['nombre'] ?? $equipo['nombre']) ?>" required>
    </div>

    <div class="form-group">
        <label for="ciudad">Ciudad *</label>
        <input type="text" id="ciudad" name="ciudad" value="<?= htmlspecialchars($old['ciudad'] ?? $equipo['ciudad']) ?>" required>
    </div>

    <div class="form-group">
        <label for="fundacion">Año de Fundación</label>
        <input type="number" id="fundacion" name="fundacion" min="1800" max="<?= date('Y') ?>" value="<?= htmlspecialchars((string)($old['fundacion'] ?? $equipo['fundacion'] ?? '')) ?>">
    </div>

    <button type="submit" class="btn">Actualizar</button>
    <a href="<?= BASE_URL ?>?controller=Equipos&action=index" class="btn btn-secondary">Cancelar</a>
</form>
