<h1>Iniciar Sesión</h1>

<form action="<?= BASE_URL ?>?controller=Auth&action=login" method="POST">
    <?= csrfCampo() ?>
    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autocomplete="email">
    </div>

    <div class="form-group">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>

    <button type="submit" class="btn">Ingresar</button>
</form>

<div class="actions">
    <p>¿No tienes cuenta? <a href="<?= BASE_URL ?>?controller=Auth&action=register">Regístrate aquí</a></p>
</div>
