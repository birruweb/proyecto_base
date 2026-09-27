<?php foreach (flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['tipo']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['mensaje']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endforeach; ?>
