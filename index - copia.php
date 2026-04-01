<?php
$ruta = isset($_GET['ruta']) ? $_GET['ruta'] : 'proceso/formulario';
$partes = explode('/', $ruta);

$controlador = ucfirst($partes[0]) . 'Controller';
$metodo = $partes[1] ?? 'formulario';

require_once __DIR__ . '/controllers/' . $controlador . '.php';

$controladorInstancia = new $controlador();
$controladorInstancia->$metodo();
