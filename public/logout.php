<?php
require_once '../app/init.php';
session_destroy();
header('Location: index.php');
exit();
