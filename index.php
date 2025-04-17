<?php
/**
 * index.php (Raíz del proyecto)
 * Redirige a la página principal de la aplicación dentro de la carpeta views.
 */

// Asegurarse que no haya salida antes del header
ob_start();

// Redirigir al index dentro de la carpeta views
header("Location: views/index.php");

// Terminar el script para asegurar que la redirección ocurra
exit;

// ob_end_flush(); // Opcional si necesitas output buffering por alguna razón
?>