<?php
// Simple redirect to public folder
header("Location: ./public/index.php");
exit();
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="refresh" content="0;url=./public/index.php">
    <title>Redirecting...</title>
</head>
<body>
    <p>If you are not redirected automatically, <a href="./public/index.php">click here</a>.</p>
    <script>window.location.replace("./public/index.php");</script>
</body>
</html>
