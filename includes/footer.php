<?php $appVersion=trim((string)@file_get_contents(__DIR__.'/../VERSION')); ?>
</main><footer><img src="assets/logo-control-ganado.png" alt=""><span>Sistema de Gestión y Control de Ganado<?= $appVersion!==''?' · v'.htmlspecialchars($appVersion):'' ?></span></footer></body></html>
