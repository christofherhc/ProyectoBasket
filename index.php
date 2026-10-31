<?php

/**
 * Punto de entrada desde la raíz del proyecto.
 *
 * Permite entrar a la aplicación con la URL habitual de XAMPP:
 *
 *     http://localhost/ProyectoBasket/
 *
 * sin tener que escribir /public/. El archivo no duplica lógica: delega en
 * public/index.php, que sigue siendo el front controller real y el punto de
 * entrada cuando el DocumentRoot apunta a public/.
 *
 * El .htaccess de esta carpeta se encarga de que solo este archivo y los
 * recursos estáticos de public/ sean accesibles; app/, database/, doc/,
 * vault/, tests/ y .git/ siguen devolviendo 403.
 */

require __DIR__ . '/public/index.php';
