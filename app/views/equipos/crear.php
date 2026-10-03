<h1>Crear Equipo</h1>

<form action="<?= BASE_URL ?>?controller=Equipos&action=guardar" method="POST">
    <div class="form-group">
        <label for="nombre">Nombre *</label>
        <input type="text" id="nombre" name="nombre" value="<?= isset($old['nombre']) ? htmlspecialchars($old['nombre']) : '' ?>" required>
    </div>

    <div class="form-group">
        <label for="ciudad">Ciudad *</label>
        <input type="text" id="ciudad" name="ciudad" value="<?= isset($old['ciudad']) ? htmlspecialchars($old['ciudad']) : '' ?>" required>
    </div>

    <div class="form-group">
        <label for="fundacion">Año de Fundación</label>
        <input type="number" id="fundacion" name="fundacion" min="1800" max="<?= date('Y') ?>" value="<?= isset($old['fundacion']) && $old['fundacion'] !== '' ? htmlspecialchars((string)$old['fundacion']) : '' ?>">
    </div>

    <button type="submit" class="btn">Guardar</button>
    <a href="<?= BASE_URL ?>?controller=Equipos&action=index" class="btn btn-secondary">Cancelar</a>
</form>
