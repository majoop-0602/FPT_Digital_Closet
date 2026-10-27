<?php
error_reporting(E_ALL);

ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL & ~E_DEPRECATED);

header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Allow: GET, POST, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
  http_response_code(200);
  exit;
}

if (isset($_GET["PING"])) {
  exit;
}

date_default_timezone_set("America/Matamoros");

if (isset($_GET["DATETIME"])) {
  echo date("Y-m-d H:i:s");
  exit;
}


// ------------------------------------------------------
// ------------------------------------------------------
// Debajo de este comentario irá la configuración a la BD
// y las funciones del servicio para la aplicación móvil.

require "conexion.php";
require "enviarCorreo.php";

$con = new Conexion(array(
  "tipo"       => "mysql",
  "servidor"   => "localhost",
  "bd"         => "armario_digital",
  "usuario"    => "root",
  "contrasena" => ""
));

session_start();

// Responde siempre en JSON y termina la ejecución.
function responder($ok, $mensaje = "", $extra = array()) {
  header("Content-Type: application/json; charset=utf-8");
  echo json_encode(array_merge(array("ok" => $ok, "mensaje" => $mensaje), $extra));
  exit;
}
 
 
// ------------------------------------------------------
// REGISTRO
// ------------------------------------------------------
if (isset($_GET["registrarUsuario"])) {
 
  $nombre   = trim($_POST["usuario"] ?? "");
  $correo   = strtolower(trim($_POST["correo"] ?? ""));
  $password = $_POST["password"] ?? "";
 
  // Validaciones (el servidor nunca debe confiar solo en el navegador)
  if (strlen($nombre) < 3 || strlen($nombre) > 30) {
    responder(false, "El usuario debe tener entre 3 y 30 caracteres.");
  }
  if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    responder(false, "Escribe un correo válido.");
  }
  if (strlen($password) < 8) {
    responder(false, "La contraseña debe tener al menos 8 caracteres.");
  }
 
  // ¿Usuario o correo ya existen?
  $select = $con->select("users", "id");
  $select->where("name", "=", $nombre);
  if (count($select->execute())) {
    responder(false, "Ese usuario ya está en uso.");
  }
 
  $select = $con->select("users", "id");
  $select->where("email", "=", $correo);
  if (count($select->execute())) {
    responder(false, "Ese correo ya está registrado.");
  }
 
  // Nunca se guarda la contraseña: solo su hash
  $hash = password_hash($password, PASSWORD_DEFAULT);
 
  try {
    $insert = $con->insert("users", "name, email, password_hash");
    $insert->value($nombre);
    $insert->value($correo);
    $insert->value($hash);
    $insert->execute();
 
    $idUsuario = $con->lastInsertId();
  } catch (Exception $e) {
    // Por ejemplo, si el índice UNIQUE de la BD detecta un duplicado simultáneo
    responder(false, "No se pudo crear la cuenta. Intenta con otros datos.");
  }
 
  if (is_numeric($idUsuario) && $idUsuario > 0) {
    // Inicia sesión automáticamente después de registrarse
    session_regenerate_id(true);
    $_SESSION["user_id"]   = (int)$idUsuario;
    $_SESSION["user_name"] = $nombre;
 
    responder(true, "Cuenta creada correctamente.");
  }
 
  responder(false, "No se pudo crear la cuenta.");
}
 
 
// ------------------------------------------------------
// LOGIN
// ------------------------------------------------------
elseif (isset($_GET["iniciarSesion"])) {
 
  $nombre   = trim($_POST["usuario"] ?? "");
  $password = $_POST["password"] ?? "";
 
  if ($nombre === "" || $password === "") {
    responder(false, "Escribe tu usuario y tu contraseña.");
  }
 
  $select = $con->select("users", "id, name, password_hash");
  $select->where("name", "=", $nombre);
  $filas = $select->execute();
 
  // Mismo mensaje si falla el usuario o la contraseña, para no revelar cuál existe
  if (count($filas) && password_verify($password, $filas[0]["password_hash"])) {
 
    session_regenerate_id(true);
    $_SESSION["user_id"]   = (int)$filas[0]["id"];
    $_SESSION["user_name"] = $filas[0]["name"];
 
    responder(true, "Bienvenid@.");
  }
 
  responder(false, "Usuario o contraseña incorrectos.");
}
 
 
// ------------------------------------------------------
// SESIÓN (para proteger las páginas internas)
// ------------------------------------------------------
elseif (isset($_GET["sesionActual"])) {
 
  if (isset($_SESSION["user_id"])) {
    responder(true, "", array("usuario" => $_SESSION["user_name"]));
  }
  responder(false, "Sin sesión.");
}
 
elseif (isset($_GET["cerrarSesion"])) {
 
  $_SESSION = array();
  session_destroy();
  responder(true, "Sesión cerrada.");
}



?>

