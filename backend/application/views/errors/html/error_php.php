
<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Error PHP</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    h1 { color: #c00; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 4px; }
  </style>
</head>
<body>
  <h1>Ocurrió un error en el servidor</h1>
  <p><strong>Mensaje:</strong> <?php echo $message; ?></p>
  <p><strong>Severidad:</strong> <?php echo $severity; ?></p>
  <p><strong>Archivo:</strong> <?php echo $filepath; ?></p>
  <p><strong>Línea:</strong> <?php echo $line; ?></p>
</body>
</html>