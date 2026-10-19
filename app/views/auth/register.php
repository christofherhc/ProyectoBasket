<h1>Registrarse</h1>

<form action="<?= BASE_URL ?>?controller=Auth&action=guardarRegistro" method="POST">
    <?= csrfCampo() ?>
    <div class="form-group">
        <label for="nombre">Nombre completo</label>
        <input type="text" id="nombre" name="nombre" value="<?= isset($old['nombre']) ? e($old['nombre']) : '' ?>" required>
    </div>

    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= isset($old['email']) ? e($old['email']) : '' ?>" required>
    </div>

    <div class="form-group">
        <label for="password">Contraseña (mín. 6, máx. 72 bytes)</label>
        <input type="password" id="password" name="password" required minlength="6" maxlength="72">
    </div>

    <div class="form-group">
        <label for="confirmar">Confirmar contraseña</label>
        <input type="password" id="confirmar" name="confirmar" required minlength="6" maxlength="72">
    </div>

    <button type="submit" class="btn">Registrarse</button>
</form>

<div class="actions">
    <p>¿Ya tienes cuenta? <a href="<?= BASE_URL ?>?controller=Auth&action=index">Inicia sesión aquí</a></p>
</div>
