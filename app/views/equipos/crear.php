<h1>Crear Equipo</h1>

<form action="<?= BASE_URL ?>?controller=Equipos&action=guardar" method="POST">
    <?= csrfCampo() ?>
    <div class="form-group">
        <label for="nombre">Nombre *</label>
        <input type="text" id="nombre" name="nombre" value="<?= isset($old['nombre']) ? e($old['nombre']) : '' ?>" required>
    </div>

    <div class="form-group">
        <label for="ciudad">Ciudad *</label>
        <input type="text" id="ciudad" name="ciudad" value="<?= isset($old['ciudad']) ? e($old['ciudad']) : '' ?>" required>
    </div>

    <div class="form-group">
        <label for="fundacion">Año de Fundación</label>
        <input type="number" id="fundacion" name="fundacion" min="<?= FUNDACION_MIN ?>" max="<?= date('Y') ?>" value="<?= isset($old['fundacion']) && $old['fundacion'] !== '' ? e($old['fundacion']) : '' ?>">
    </div>

    <button type="submit" class="btn">Guardar</button>
    <a href="<?= BASE_URL ?>?controller=Equipos&action=index" class="btn btn-secondary">Cancelar</a>
</form>
