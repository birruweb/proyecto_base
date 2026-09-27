<?php
use App\Core\View;
?>
<div class="card auth-card">
    <div class="card-body p-4 p-sm-5">
        <div class="text-center mb-4">
            <div class="auth-logo"><i class="bi bi-grid-1x2-fill"></i></div>
            <h1 class="h4 mb-1"><?= e(env('APP_NAME', 'Proyecto Base')) ?></h1>
            <p class="text-body-secondary small mb-0">Inicia sesión para continuar</p>
        </div>

        <?php View::parcial('partials/flash') ?>

        <form method="post" action="<?= url('login') ?>">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label" for="usuario">Usuario</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="usuario" name="usuario" value="<?= e(old('usuario')) ?>"
                           autocomplete="username" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label" for="password">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password"
                           autocomplete="current-password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2">Entrar</button>
        </form>
    </div>
</div>
