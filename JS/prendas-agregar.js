// Esta misma página sirve para tres cosas:
//   closetix-agregar-prenda.html          -> agregar una prenda nueva
//   closetix-agregar-prenda.html?id=12    -> ver la prenda 12 (campos bloqueados)
//   ...?id=12 + botón "Editar prenda"     -> editarla
// Se carga como módulo: <script type="module" src="js/prendas-agregar.js"></script>
// Requiere jQuery y auth.js cargados antes (protegerPagina y el botón de salir).
import * as imgly from "https://esm.sh/@imgly/background-removal@1.5.5";
 
const quitarFondo = imgly.removeBackground || imgly.default;
 
const API = "../BACKEND/api";          // mismo ajuste de ruta que en auth.js
const RAIZ = "../";            // prefijo para llegar a las imágenes. Si la página está en PAGES/, usa "../"
const PAGINA = "subir-prendas.html";
const MAX_LADO = 1600;      // se reduce la foto antes de procesarla: más rápido y pesa menos
 
const $form = $("#frmPrenda");
const $drop = $("#drop");
const $btn = $("#btnGuardar");
const input = document.getElementById("imagen");
const preview = document.getElementById("preview");
const estado = document.getElementById("estado");
const textoInicial = estado.textContent;
 
const idPrenda = new URLSearchParams(location.search).get("id");
let modo = idPrenda ? "ver" : "nuevo";   // "nuevo" | "ver" | "editar"
let nombrePrenda = "";
 
let imagenFinal = null;   // Blob nuevo que se enviará (null = no se eligió foto nueva)
let ultimaURL = null;
let turno = 0;            // evita que una foto vieja pise a una nueva
 
protegerPagina();         // si no hay sesión, manda al login
 
function mensaje(texto) {
  $("#msg").text(texto);
}
 
// ---------------- Modos: nuevo / ver / editar ----------------
function aplicarModo() {
  const ver = modo === "ver";
 
  $form.toggleClass("view", ver);
  $drop.toggleClass("locked", ver);
  $form.find("input, select, textarea").prop("disabled", ver);
  $("#imagen").prop("required", modo === "nuevo");   // al editar, la foto es opcional
 
  $("#btnGuardar").prop("hidden", ver).text(modo === "editar" ? "Guardar cambios" : "Guardar prenda");
  $("#btnEditar").prop("hidden", !ver);
 
  if (modo === "editar") {
    $("#btnCancelar").text("Cancelar").attr("href", `${PAGINA}?id=${idPrenda}`);
  } else if (ver) {
    $("#btnCancelar").text("Volver").attr("href", "prendas.html");
  } else {
    $("#btnCancelar").text("Cancelar").attr("href", "prendas.html");
  }
 
  const textos = {
    nuevo:  ["Agregar prenda", "Sube una foto y completa los datos de tu prenda.", textoInicial],
    ver:    [nombrePrenda || "Mi prenda", "Estos son los datos que registraste.", ""],
    editar: ["Editar prenda", "Cambia lo que necesites y guarda.", "Si eliges otra foto, también se le quitará el fondo."]
  };
  $("#titulo").text(textos[modo][0]);
  $("#subtitulo").text(textos[modo][1]);
  estado.textContent = textos[modo][2];
  document.title = `${textos[modo][0]} | Closetix`;
}
 
$("#btnEditar").on("click", function () {
  modo = "editar";
  mensaje("");
  aplicarModo();
});
 
// ---------------- Cargar la prenda existente ----------------
function cargarPrenda() {
  $.post(`${API}/servicio.php?prenda`, { id: idPrenda }, function (respuesta) {
    if (!respuesta.ok) {
      if (respuesta.mensaje === "Debes iniciar sesión.") {
        location.href = "login.html";
        return;
      }
      mensaje(respuesta.mensaje);
      $("#btnEditar").prop("hidden", true);
      return;
    }
 
    const p = respuesta.prenda;
    nombrePrenda = p.nombre;
 
    $form.find("[name=categoria]").filter(`[value="${p.categoria}"]`).prop("checked", true);
    $("#nombre").val(p.nombre);
    $("#marca").val(p.marca);
    $("#color").val(p.color);
    $("#talla").val(p.talla);
    $("#temporada").val(p.temporada);
    $("#material").val(p.material);
    $("#notas").val(p.notas);
    $form.find("[name=favorita]").prop("checked", !!p.es_favorita);
 
    preview.src = RAIZ + p.imagen;
    $drop.addClass("has");
 
    aplicarModo();
  }, "json").fail(function () {
    mensaje("No se pudo conectar con el servidor. Intenta de nuevo.");
    $("#btnEditar").prop("hidden", true);
  });
}
 
// ---------------- Foto: vista previa + quitar fondo ----------------
function mostrarPreview(blob) {
  if (ultimaURL) URL.revokeObjectURL(ultimaURL);
  ultimaURL = URL.createObjectURL(blob);
  preview.src = ultimaURL;
  $drop.addClass("has");
}
 
// Reduce la imagen a un máximo de MAX_LADO px por lado
async function reducir(file) {
  const bmp = await createImageBitmap(file);
  const escala = Math.min(1, MAX_LADO / Math.max(bmp.width, bmp.height));
  const canvas = document.createElement("canvas");
  canvas.width = Math.round(bmp.width * escala);
  canvas.height = Math.round(bmp.height * escala);
  canvas.getContext("2d").drawImage(bmp, 0, 0, canvas.width, canvas.height);
  bmp.close();
  return new Promise((res, rej) =>
    canvas.toBlob(b => (b ? res(b) : rej(new Error("No se pudo reducir la imagen"))), "image/jpeg", 0.92)
  );
}
 
async function procesar(file) {
  if (!file || !file.type.startsWith("image/")) {
    mensaje("Elige un archivo de imagen.");
    return;
  }
 
  const miTurno = ++turno;
  mensaje("");
  imagenFinal = null;
  mostrarPreview(file);
  $drop.addClass("busy");
  $btn.prop("disabled", true);
  estado.textContent = "Quitando el fondo… la primera vez descarga el modelo y puede tardar.";
 
  try {
    const reducida = await reducir(file);
    const resultado = await quitarFondo(reducida, {
      progress: (key, actual, total) => {
        if (miTurno === turno && key.startsWith("fetch") && total) {
          estado.textContent = `Preparando el modelo… ${Math.round((actual / total) * 100)}%`;
        }
      }
    });
 
    if (miTurno !== turno) return;
    imagenFinal = resultado;             // PNG con fondo transparente
    mostrarPreview(resultado);
    estado.textContent = "Listo, fondo eliminado.";
  } catch (error) {
    console.error(error);
    if (miTurno !== turno) return;
    imagenFinal = file;                  // si falla, se usa la imagen original
    estado.textContent = "No se pudo quitar el fondo; se guardará la imagen original.";
  } finally {
    if (miTurno === turno) {
      $drop.removeClass("busy");
      $btn.prop("disabled", false);
    }
  }
}
 
input.addEventListener("change", () => procesar(input.files[0]));
 
["dragenter", "dragover"].forEach(t =>
  $drop[0].addEventListener(t, e => { e.preventDefault(); $drop.addClass("over"); }));
["dragleave", "drop"].forEach(t =>
  $drop[0].addEventListener(t, e => { e.preventDefault(); $drop.removeClass("over"); }));
$drop[0].addEventListener("drop", e => {
  if (e.dataTransfer.files.length) {
    input.files = e.dataTransfer.files;
    procesar(input.files[0]);
  }
});
 
// ---------------- Guardar (agregar o editar) ----------------
$form.on("submit", function (event) {
  event.preventDefault();
  mensaje("");
 
  if (modo === "ver") return;
 
  if (modo === "nuevo" && !imagenFinal) {
    mensaje("Sube la foto de la prenda.");
    return;
  }
 
  const datos = new FormData(this);
 
  if (imagenFinal) {
    datos.set("imagen", imagenFinal, imagenFinal.type === "image/png" ? "prenda.png" : (imagenFinal.name || "prenda"));
  } else {
    datos.delete("imagen");              // al editar sin foto nueva, se conserva la actual
  }
 
  let metodo = "agregarPrenda";
  if (modo === "editar") {
    metodo = "editarPrenda";
    datos.set("id", idPrenda);
  }
 
  $btn.prop("disabled", true);
 
  $.ajax({
    url: `${API}/servicio.php?${metodo}`,
    method: "POST",
    data: datos,
    processData: false,   // necesario para enviar archivos con FormData
    contentType: false,
    dataType: "json"
  })
    .done(respuesta => {
      if (respuesta.ok) {
        // Al editar se regresa a la ficha; al agregar, a la lista
        location.href = modo === "editar" ? `${PAGINA}?id=${idPrenda}` : "prendas.html";
      } else {
        mensaje(respuesta.mensaje);
        $btn.prop("disabled", false);
      }
    })
    .fail(() => {
      mensaje("No se pudo conectar con el servidor. Intenta de nuevo.");
      $btn.prop("disabled", false);
    });
});
 
// ---------------- Inicio ----------------
aplicarModo();
if (idPrenda) cargarPrenda();
 