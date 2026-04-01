<?php
/**
 * Front Controller (Router simple)
 * - Lee ?ruta=controlador/metodo
 * - Carga controllers/<Controlador>Controller.php
 * - Ejecuta el método solicitado
 *
 * + Añadimos:
 * - session_start()
 * - Guard de autenticación: solo permite auth/* sin sesión
 */

session_start();

// Ruta por defecto: si ya estás logueado irá a proceso/formulario,
// si NO estás logueado lo redirigimos abajo a auth/login.
$ruta = isset($_GET['ruta']) ? $_GET['ruta'] : 'proceso/formulario';
$ruta = trim($ruta, "/");

// Si viene vacío por alguna razón
if ($ruta === '') {
    $ruta = 'proceso/formulario';
}

// Partimos "controlador/metodo"
$partes = explode('/', $ruta);
$ctrlBase = $partes[0] ?? 'proceso';
$metodo   = $partes[1] ?? 'formulario';

/**
 * GUARD de autenticación
 * - Permitimos libremente solo rutas auth/*
 * - Todo lo demás requiere sesión (auth)
 */
$esAuth = ($ctrlBase === 'auth');

if (!$esAuth && empty($_SESSION['auth'])) {
    // Si no hay sesión, forzamos login
    $ctrlBase = 'auth';
    $metodo   = 'login';
}

/**
 * Construimos el nombre de la clase controlador:
 *   proceso -> ProcesoController
 *   audio   -> AudioController
 *   auth    -> AuthController
 */
$controlador = ucfirst($ctrlBase) . 'Controller';

// Ruta del archivo del controlador
$archivoControlador = __DIR__ . '/controllers/' . $controlador . '.php';

// Validamos que exista el archivo
if (!file_exists($archivoControlador)) {
    http_response_code(404);
    exit("Controlador no encontrado: " . htmlspecialchars($controlador));
}

// Cargamos el controlador
require_once $archivoControlador;

// Validamos que exista la clase
if (!class_exists($controlador)) {
    http_response_code(500);
    exit("Clase de controlador no encontrada: " . htmlspecialchars($controlador));
}

// Creamos instancia
$controladorInstancia = new $controlador();

// Validamos que exista el método
if (!method_exists($controladorInstancia, $metodo)) {
    http_response_code(404);
    exit("Método no encontrado: " . htmlspecialchars($controlador . '::' . $metodo));
}

// Ejecutamos método
$controladorInstancia->$metodo();
