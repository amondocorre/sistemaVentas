<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Error general</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    h1 { color: #c00; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 4px; }
  </style>
</head>
<body>
  <h1>Ocurrió un error en el servidor</h1>
  <p><strong>Mensaje:</strong> <?php echo isset($message) ? $message : 'Error general'; ?></p>
  <?php if (isset($heading)): ?>
    <p><strong>Detalle:</strong> <?php echo $heading; ?></p>
  <?php endif; ?>
</body>
</html>