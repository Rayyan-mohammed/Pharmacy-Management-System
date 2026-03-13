<?php
// Entry shim: keep root URL working while the app lives under /public
header('Location: ./public/index.php', true, 302);
exit();
