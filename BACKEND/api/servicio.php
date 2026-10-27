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

 
// ------------------------------------------------------
// PRENDAS: agregar
// ------------------------------------------------------
elseif (isset($_GET["agregarPrenda"])) {
 
  // Solo usuarios con sesión iniciada
  if (!isset($_SESSION["user_id"])) {
    responder(false, "Debes iniciar sesión.");
  }
  $idUsuario = (int)$_SESSION["user_id"];
 
  $idCategoria = (int)($_POST["categoria"] ?? 0);
  $nombre      = trim($_POST["nombre"] ?? "");
  $marca       = trim($_POST["marca"] ?? "");
  $color       = trim($_POST["color"] ?? "");
  $talla       = trim($_POST["talla"] ?? "");
  $temporadaIn = $_POST["temporada"] ?? "";
  $material    = trim($_POST["material"] ?? "");
  $notas       = trim($_POST["notas"] ?? "");
  $favorita    = isset($_POST["favorita"]) ? 1 : 0;
 
  // El formulario manda "otono" y "todo-el-ano"; la BD guarda el enum exacto
  $temporadas = array(
    "primavera"   => "Primavera",
    "verano"      => "Verano",
    "otono"       => "Otoño",
    "invierno"    => "Invierno",
    "todo-el-ano" => "Todo_anio"
  );
 
  if ($nombre === "" || mb_strlen($nombre) > 100) {
    responder(false, "Escribe el nombre de la prenda (máximo 100 caracteres).");
  }
  if ($color === "" || mb_strlen($color) > 30) {
    responder(false, "Elige un color.");
  }
  if (!isset($temporadas[$temporadaIn])) {
    responder(false, "Elige una temporada válida.");
  }
  $temporada = $temporadas[$temporadaIn];
 
  // Ajusta estos límites al tamaño real de tus columnas
  $marca    = mb_substr($marca, 0, 60);
  $talla    = mb_substr($talla, 0, 10);
  $material = mb_substr($material, 0, 100);
  $notas    = mb_substr($notas, 0, 500);
 
  // La categoría debe existir en la tabla categories
  $select = $con->select("categories", "id");
  $select->where("id", "=", $idCategoria);
  if (!count($select->execute())) {
    responder(false, "Elige una categoría válida.");
  }
 
  // ----- Imagen -----
  if (!isset($_FILES["imagen"]) || $_FILES["imagen"]["error"] !== UPLOAD_ERR_OK) {
    responder(false, "No se recibió la imagen (revisa que no pese demasiado).");
  }
  if ($_FILES["imagen"]["size"] > 5 * 1024 * 1024) {
    responder(false, "La imagen no debe pesar más de 5 MB.");
  }
 
  // Se revisa el contenido real del archivo, no la extensión que manda el navegador
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime  = finfo_file($finfo, $_FILES["imagen"]["tmp_name"]);
  finfo_close($finfo);
 
  $extensiones = array("image/png" => "png", "image/jpeg" => "jpg", "image/webp" => "webp");
  if (!isset($extensiones[$mime])) {
    responder(false, "Solo se permiten imágenes PNG, JPG o WEBP.");
  }
 
  // Nombre aleatorio: nunca se usa el nombre original del archivo
  $carpeta = dirname(__DIR__) . "/../IMAGES/prendas";
  if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true)) {
    responder(false, "No se pudo preparar la carpeta de imágenes.");
  }
 
  $archivo = bin2hex(random_bytes(16)) . "." . $extensiones[$mime];
  if (!move_uploaded_file($_FILES["imagen"]["tmp_name"], $carpeta . "/" . $archivo)) {
    responder(false, "No se pudo guardar la imagen.");
  }
  $rutaImagen = "IMAGES/prendas/" . $archivo; // ruta relativa a la raíz del proyecto
 
  // ----- Insert -----
  try {
    $insert = $con->insert("clothing_items", "user_id, category_id, name, brand, color, size, season, material, notes, image_path, is_favorite");
    $insert->value($idUsuario);
    $insert->value($idCategoria);
    $insert->value($nombre);
    $insert->value($marca);
    $insert->value($color);
    $insert->value($talla);
    $insert->value($temporada);
    $insert->value($material);
    $insert->value($notas);
    $insert->value($rutaImagen);
    $insert->value($favorita);
    $insert->execute();
 
    $idPrenda = $con->lastInsertId();
  } catch (Exception $e) {
    unlink($carpeta . "/" . $archivo); // no dejar imágenes huérfanas
    responder(false, "No se pudo guardar la prenda.");
  }
 
  if (is_numeric($idPrenda) && $idPrenda > 0) {
    responder(true, "Prenda agregada.", array("id" => (int)$idPrenda));
  }
 
  unlink($carpeta . "/" . $archivo);
  responder(false, "No se pudo guardar la prenda.");
}


?>

