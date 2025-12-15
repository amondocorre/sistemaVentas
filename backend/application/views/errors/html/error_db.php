<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Error de base de datos</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    h1 { color: #c00; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
  </style>
</head>
<body>
  <h1>Ocurrió un error en la base de datos</h1>
  <p>Se produjo un error al procesar la solicitud.</p>

  <?php if (defined('ENVIRONMENT') && ENVIRONMENT !== 'production') : ?>
    <h3>Detalles</h3>
    <?php if (isset($message)) : ?>
      <pre><?php echo htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8'); ?></pre>
    <?php endif; ?>
  <?php endif; ?>
</body>
</html>
