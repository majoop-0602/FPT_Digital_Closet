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
// Funciones de apoyo para las prendas
// ------------------------------------------------------
 
// Carpeta (relativa a la raíz del proyecto) donde se guardan las fotos de las prendas.
// Debe existir o poder crearse, y coincidir con lo que usas para mostrar las imágenes.
define("RUTA_PRENDAS", "IMAGES/prendas");
 
// El formulario manda "otono" y "todo-el-ano"; la BD guarda el enum exacto
function temporadasBD() {
  return array(
    "primavera"   => "Primavera",
    "verano"      => "Verano",
    "otono"       => "Otoño",
    "invierno"    => "Invierno",
    "todo-el-ano" => "Todo_anio"
  );
}
 
// Lee y valida los campos del formulario. Sirve para agregar y para editar.
function leerCamposPrenda($con) {
  $temporadas  = temporadasBD();
  $idCategoria = (int)($_POST["categoria"] ?? 0);
  $nombre      = trim($_POST["nombre"] ?? "");
  $marca       = trim($_POST["marca"] ?? "");
  $color       = trim($_POST["color"] ?? "");
  $talla       = trim($_POST["talla"] ?? "");
  $temporadaIn = $_POST["temporada"] ?? "";
  $material    = trim($_POST["material"] ?? "");
  $notas       = trim($_POST["notas"] ?? "");
 
  if ($nombre === "" || mb_strlen($nombre) > 100) {
    responder(false, "Escribe el nombre de la prenda (máximo 100 caracteres).");
  }
  if ($color === "" || mb_strlen($color) > 30) {
    responder(false, "Elige un color.");
  }
  if (!isset($temporadas[$temporadaIn])) {
    responder(false, "Elige una temporada válida.");
  }
 
  // La categoría debe existir en la tabla categories
  $select = $con->select("categories", "id");
  $select->where("id", "=", $idCategoria);
  if (!count($select->execute())) {
    responder(false, "Elige una categoría válida.");
  }
 
  return array(
    "categoria" => $idCategoria,
    "nombre"    => $nombre,
    "marca"     => mb_substr($marca, 0, 60),      // ajusta los límites al tamaño de tus columnas
    "color"     => $color,
    "talla"     => mb_substr($talla, 0, 10),
    "temporada" => $temporadas[$temporadaIn],
    "material"  => mb_substr($material, 0, 100),
    "notas"     => mb_substr($notas, 0, 500),
    "favorita"  => isset($_POST["favorita"]) ? 1 : 0
  );
}
 
// Valida y guarda la imagen subida. Devuelve la ruta relativa o null si no se mandó imagen.
function guardarImagenPrenda() {
  if (!isset($_FILES["imagen"]) || $_FILES["imagen"]["error"] === UPLOAD_ERR_NO_FILE) {
    return null;
  }
  if ($_FILES["imagen"]["error"] !== UPLOAD_ERR_OK) {
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
 
  $carpeta = dirname(__DIR__) . "/../" . RUTA_PRENDAS;
  if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true)) {
    responder(false, "No se pudo preparar la carpeta de imágenes.");
  }
 
  // Nombre aleatorio: nunca se usa el nombre original del archivo
  $archivo = bin2hex(random_bytes(16)) . "." . $extensiones[$mime];
  if (!move_uploaded_file($_FILES["imagen"]["tmp_name"], $carpeta . "/" . $archivo)) {
    responder(false, "No se pudo guardar la imagen.");
  }
 
  return RUTA_PRENDAS . "/" . $archivo;
}
 
// Borra una imagen de prenda. Solo toca archivos dentro de la carpeta de prendas.
function borrarImagenPrenda($ruta) {
  if (!$ruta) return;
  $archivo = dirname(__DIR__) . "/../" . RUTA_PRENDAS . "/" . basename($ruta);
  if (is_file($archivo)) {
    unlink($archivo);
  }
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
 
  if (!isset($_SESSION["user_id"])) {
    responder(false, "Debes iniciar sesión.");
  }
  $idUsuario = (int)$_SESSION["user_id"];
 
  $c = leerCamposPrenda($con);
 
  $rutaImagen = guardarImagenPrenda();
  if ($rutaImagen === null) {
    responder(false, "Sube la foto de la prenda.");
  }
 
  try {
    $insert = $con->insert("clothing_items", "user_id, category_id, name, brand, color, size, season, material, notes, image_path, is_favorite");
    $insert->value($idUsuario);
    $insert->value($c["categoria"]);
    $insert->value($c["nombre"]);
    $insert->value($c["marca"]);
    $insert->value($c["color"]);
    $insert->value($c["talla"]);
    $insert->value($c["temporada"]);
    $insert->value($c["material"]);
    $insert->value($c["notas"]);
    $insert->value($rutaImagen);
    $insert->value($c["favorita"]);
    $insert->execute();
 
    $idPrenda = $con->lastInsertId();
  } catch (Exception $e) {
    borrarImagenPrenda($rutaImagen);   // no dejar imágenes huérfanas
    responder(false, "No se pudo guardar la prenda.");
  }
 
  if (is_numeric($idPrenda) && $idPrenda > 0) {
    responder(true, "Prenda agregada.", array("id" => (int)$idPrenda));
  }
 
  borrarImagenPrenda($rutaImagen);
  responder(false, "No se pudo guardar la prenda.");
}

// ------------------------------------------------------
// PRENDAS: consultar (solo las del usuario con sesión)
// ------------------------------------------------------
elseif (isset($_GET["prendas"])) {
 
  if (!isset($_SESSION["user_id"])) {
    responder(false, "Debes iniciar sesión.");
  }
  $idUsuario = (int)$_SESSION["user_id"];   // sale de la sesión, nunca del navegador
 
  // Filtros opcionales
  $idCategoria = (int)($_POST["categoria"] ?? 0);   // 0 = todas
  $marca       = trim($_POST["marca"] ?? "");
  $temporadaIn = $_POST["temporada"] ?? "";
  $color       = trim($_POST["color"] ?? "");
 
  $temporadas = array(
    "primavera"   => "Primavera",
    "verano"      => "Verano",
    "otono"       => "Otoño",
    "invierno"    => "Invierno",
    "todo-el-ano" => "Todo_anio"
  );
 
  $select = $con->select("clothing_items", "id, category_id, name, brand, image_path, is_favorite");
  $select->where("user_id", "=", $idUsuario);
 
  if ($idCategoria > 0) {
    $select->where_and("category_id", "=", $idCategoria);
  }
  if ($temporadaIn !== "") {
    if (!isset($temporadas[$temporadaIn])) {
      responder(false, "Temporada no válida.");
    }
    $select->where_and("season", "=", $temporadas[$temporadaIn]);
  }
  if ($color !== "") {
    $select->where_and("color", "=", $color);
  }
  $select->orderby("id DESC");
 
  $filas = $select->execute();
 
  // La marca se filtra aquí para aceptar coincidencias parciales (ej. "zar" encuentra "Zara")
  if ($marca !== "") {
    $filas = array_values(array_filter($filas, function ($fila) use ($marca) {
      return mb_stripos((string)$fila["brand"], $marca) !== false;
    }));
  }
 
  // Solo se devuelve lo que la vista necesita
  $prendas = array();
  foreach ($filas as $fila) {
    $prendas[] = array(
      "id"          => (int)$fila["id"],
      "categoria"   => (int)$fila["category_id"],
      "nombre"      => $fila["name"],
      "imagen"      => $fila["image_path"],
      "es_favorita" => (int)$fila["is_favorite"]
    );
  }
 
  responder(true, "", array("prendas" => $prendas));
}
 
 // ------------------------------------------------------
// PRENDAS: detalle de una prenda
// ------------------------------------------------------
elseif (isset($_GET["prenda"])) {
 
  if (!isset($_SESSION["user_id"])) {
    responder(false, "Debes iniciar sesión.");
  }
  $idUsuario = (int)$_SESSION["user_id"];
  $idPrenda  = (int)($_POST["id"] ?? 0);
 
  // Se filtra por id Y por usuario: nadie puede ver prendas de otra persona cambiando el id
  $select = $con->select("clothing_items", "id, category_id, name, brand, color, size, season, material, notes, image_path, is_favorite");
  $select->where("id", "=", $idPrenda);
  $select->where_and("user_id", "=", $idUsuario);
  $filas = $select->execute();
 
  if (!count($filas)) {
    responder(false, "No encontramos esa prenda.");
  }
 
  $f = $filas[0];
  $temporadaForm = array_flip(temporadasBD());   // "Otoño" -> "otono", para el formulario
 
  responder(true, "", array("prenda" => array(
    "id"          => (int)$f["id"],
    "categoria"   => (int)$f["category_id"],
    "nombre"      => $f["name"],
    "marca"       => $f["brand"],
    "color"       => $f["color"],
    "talla"       => $f["size"],
    "temporada"   => $temporadaForm[$f["season"]] ?? "",
    "material"    => $f["material"],
    "notas"       => $f["notes"],
    "imagen"      => $f["image_path"],
    "es_favorita" => (int)$f["is_favorite"]
  )));
}
 
// ------------------------------------------------------
// PRENDAS: editar
// ------------------------------------------------------
elseif (isset($_GET["editarPrenda"])) {
 
  if (!isset($_SESSION["user_id"])) {
    responder(false, "Debes iniciar sesión.");
  }
  $idUsuario = (int)$_SESSION["user_id"];
  $idPrenda  = (int)($_POST["id"] ?? 0);
 
  // La prenda debe existir y ser de este usuario
  $select = $con->select("clothing_items", "id, image_path");
  $select->where("id", "=", $idPrenda);
  $select->where_and("user_id", "=", $idUsuario);
  $filas = $select->execute();
 
  if (!count($filas)) {
    responder(false, "No encontramos esa prenda.");
  }
  $rutaVieja = $filas[0]["image_path"];
 
  $c = leerCamposPrenda($con);
  $rutaNueva = guardarImagenPrenda();   // null si el usuario no cambió la foto
 
  try {
    $update = $con->update("clothing_items");
    $update->set("category_id", $c["categoria"]);
    $update->set("name", $c["nombre"]);
    $update->set("brand", $c["marca"]);
    $update->set("color", $c["color"]);
    $update->set("size", $c["talla"]);
    $update->set("season", $c["temporada"]);
    $update->set("material", $c["material"]);
    $update->set("notes", $c["notas"]);
    $update->set("is_favorite", $c["favorita"]);
    if ($rutaNueva !== null) {
      $update->set("image_path", $rutaNueva);
    }
    $update->where("id", "=", $idPrenda);
    $update->where_and("user_id", "=", $idUsuario);
    $update->execute();
  } catch (Exception $e) {
    borrarImagenPrenda($rutaNueva);
    responder(false, "No se pudo actualizar la prenda.");
  }
 
  // Si se cambió la foto, se elimina la anterior
  if ($rutaNueva !== null) {
    borrarImagenPrenda($rutaVieja);
  }
 
  responder(true, "Prenda actualizada.");
}
?>

