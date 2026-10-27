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
  "servidor"   => "185.232.14.52",
  "bd"         => "u760464709_dam_refu4a_bd",
  "usuario"    => "u760464709_dam_refu4a_usr",
  "contrasena" => "p[hy+n=V5#q"
));





if(isset($_GET["iniciarSesion"])){

  $select = $con->select("usuarios", "id" );
  $select->where("usuario", "=",$_POST["usuario"]);
  $select->where_and("contrasena", "=",$_POST["contrasena"]);

  if(count($select->execute())){
    echo "correcto";
  }
  else{
      echo "error";
    }

}

elseif (isset($_GET["padrinos"])) { 
    $select = $con->select("padrinos", "*");
    $select->orderby("idPadrino DESC");
    $select->limit(10);

    header('Content-Type: application/json');
    echo json_encode($select->execute());
}
elseif (isset($_GET["eliminarPadrino"])){
  $delete = $con->delete("padrinos");
  $delete->where("idPadrino", "=", $_POST["idPadrino"]);
  if ($delete->execute()){
    echo "correcto";
  }
  else{
    echo "error";
  }  
  
}
elseif (isset($_GET["agregarPadrinos"])){
  $nombrePadrino = $_POST["nombrePadrino"]; 
  $sexo = $_POST["sexo"];
  $telefono = $_POST["telefono"];
  $correoElectronico = $_POST["correoElectronico"];

  $insert = $con->insert("padrinos", "nombrePadrino, sexo, telefono, correoElectronico");
  $insert->value("$nombrePadrino");
  $insert->value("$sexo");
  $insert->value("$telefono");
  $insert->value("$correoElectronico");
  $insert->execute();

  $idPadrino = $con ->lastInsertId();

  if (is_numeric($idPadrino)){
    echo $idPadrino;
  }
  else{
    echo "0";
  }
 
}
elseif (isset($_GET["modificarPadrinos"])){
  $idPadrino = $_POST["idPadrino"];
  $nombrePadrino = $_POST["nombrePadrino"]; 
  $sexo = $_POST["sexo"];
  $telefono = $_POST["telefono"];
  $correoElectronico = $_POST["correoElectronico"];

  $update = $con->update("padrinos");
  $update->set("nombrePadrino", $nombrePadrino);
  $update->set("sexo", $sexo);
  $update->set("telefono", $telefono);
  $update->set("correoElectronico", $correoElectronico);
  $update->where("idPadrino", "=", $idPadrino); 

  if ($update->execute()){
   echo $idPadrino; 
    } else {
        echo "0";
    }
}
elseif (isset($_GET["mascotas"])) {
    $select = $con->select("mascotas", "*");
    $select->orderby("idMascota DESC");
    $select->limit(10);
    header("Content-Type: application/json");
    echo json_encode($select->execute());



}
elseif (isset($_GET["agregarMascota"])) {

  $insert = $con->insert(
    "mascotas",
    "nombre, tipo_mascota, sexo, raza, peso, condiciones"
  );

  $insert->value($_POST["nombre"]);
  $insert->value($_POST["tipo_mascota"]);
  $insert->value($_POST["sexo"]);
  $insert->value($_POST["raza"]);
  $insert->value($_POST["peso"]);
  $insert->value($_POST["condiciones"]);
  $insert->execute();

  $id = $con->lastInsertId();

  if (is_numeric($id)) {
    echo $id;
  } else {
    echo "0";
  }
}
elseif (isset($_GET["modificarMascota"])) {

  $update = $con->update("mascotas");

  $update->set("nombre", $_POST["nombre"]);
  $update->set("tipo_mascota", $_POST["tipo_mascota"]);
  $update->set("sexo", $_POST["sexo"]);
  $update->set("raza", $_POST["raza"]);
  $update->set("peso", $_POST["peso"]);
  $update->set("condiciones", $_POST["condiciones"]);

  $update->where("idMascota", "=", $_POST["idMascota"]);

  if ($update->execute()) {
    echo $_POST["idMascota"]; 
  } else {
    echo "0";
  }
}
elseif (isset($_GET["eliminarMascota"])) {

  $delete = $con->delete("mascotas");
  $delete->where("idMascota", "=", $_POST["idMascota"]);

  if ($delete->execute()) {
    echo "correcto";
  } else {
    echo "error";
  }
}
elseif (isset($_GET["apoyo"])) {
  $select = $con->select("apoyos", "apoyos.idApoyo AS idApoyo, apoyos.idMascota AS idMascota, mascotas.nombre AS nombreMascota, apoyos.idPadrino AS idPadrino, padrinos.nombrePadrino AS nombrePadrino, monto, causa");
  $select->innerjoin("mascotas USING(idMascota)");
  $select->innerjoin("padrinos USING(idPadrino)");
  $select->orderby("apoyos.idApoyo DESC");
  $select->limit(10);

  header("Content-Type: application/json");
  echo json_encode($select->execute());
}
elseif (isset($_GET["opcionMascota"])) {
  $select = $con->select("mascotas", "idMascota AS value, nombre AS label");
  $select->orderby("nombre ASC");
  $select->limit(10);

  header("Content-Type: application/json");
  echo json_encode($select->execute());
}
elseif (isset($_GET["opcionPadrino"])) {
  $select = $con->select("padrinos", "idPadrino AS value, nombrePadrino AS label");
  $select->orderby("nombrePadrino ASC");
  $select->limit(10);

  header("Content-Type: application/json");
  echo json_encode($select->execute());
}
elseif (isset($_GET["eliminarApoyo"])) {
  $delete = $con->delete("apoyos");
  $delete->where("idApoyo", "=", $_POST['idApoyo']);
  if ($delete->execute()){
    echo "correcto"; 
 }
 else{
  echo "error";
 }
}
elseif (isset($_GET["agregarApoyo"])) {
  $insert = $con->insert("apoyos", "idMascota, idPadrino, monto, causa");
  $insert->value($_POST["mascota"]);
  $insert->value($_POST["padrino"]);
  $insert->value($_POST["monto"]);
  $insert->value($_POST["causa"]);
  $insert->execute();

  $idApoyo = $con->lastInsertId();

  if (is_numeric($idApoyo)){
    echo $idApoyo; 
 }
 else{
  echo "0";
 }
}
elseif (isset($_GET["modificarApoyo"])) {
  $update = $con->update("apoyos");
  $update->set("idMascota",$_POST["mascota"]);
  $update->set("idPadrino",$_POST["padrino"]);
  $update->set("monto", $_POST["monto"]);
  $update->set("causa",$_POST["causa"]);
  $update->where("idApoyo", "=", $_POST["idApoyo"]);

  if ($update->execute()){
    echo "correcto"; 
 }
 else{
  echo "error";
 }
}
elseif (isset($_GET["usuarios"])) { 
    $select = $con->select("usuarios", "*");
    $select->orderby("id DESC");
    $select->limit(10);

    header('Content-Type: application/json');
    echo json_encode($select->execute());
}
elseif (isset($_GET["eliminarUsuario"])){
  $delete = $con->delete("usuarios");
  $delete->where("id", "=", $_POST["id"]);
  if ($delete->execute()){
    echo "correcto";
  }
  else{
    echo "error";
  }  
  
}
elseif (isset($_GET["modificarUsuario"])){
  $id = $_POST["id"];
  $usuario = $_POST["usuario"]; 
  $contrasena = $_POST["contrasena"];

  $update = $con->update("usuarios");
  $update->set("usuario", $usuario);
  $update->set("contrasena", $contrasena);
  $update->where("id", "=", $id); 

  if ($update->execute()){
   echo $id; 
    } else {
        echo "0";
    }
}
elseif (isset($_GET["agregarUsuario"])){
  $usuario = $_POST["usuario"]; 
  $contrasena = $_POST["contrasena"];

  $insert = $con->insert("usuarios", "usuario, contrasena");
  $insert->value("$usuario");
  $insert->value("$contrasena");
  $insert->execute();

  $id = $con ->lastInsertId();

  if (is_numeric($id)){
    echo $id;
  }
  else{
    echo "0";
  }
 
}
?>

