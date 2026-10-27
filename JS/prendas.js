// Se carga como módulo: <script type="module" src="js/prendas-agregar.js"></script>
// Requiere jQuery y auth.js cargados antes (para protegerPagina y el botón de salir).
import * as imgly from "https://esm.sh/@imgly/background-removal@1.5.5";
 
const quitarFondo = imgly.removeBackground || imgly.default;
 
const API = "../BACKEND/api";          // mismo ajuste de ruta que en auth.js
const MAX_LADO = 1600;      // se reduce la foto antes de procesarla: más rápido y pesa menos
 
const $form = $("#frmPrenda");
const $drop = $("#drop");
const $btn = $form.find("button[type=submit]");
const input = document.getElementById("imagen");
const preview = document.getElementById("preview");
const estado = document.getElementById("estado");
const textoInicial = estado.textContent;
 
let imagenFinal = null;   // Blob que se enviará al servidor
let ultimaURL = null;
let turno = 0;            // evita que una foto vieja pise a una nueva
 
protegerPagina();         // si no hay sesión, manda al login
 
function mensaje(texto) {
  $("#msg").text(texto);
}
 
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
 
// ---- Selección y arrastre ----
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
 
// ---- Guardar ----
$form.on("submit", function (event) {
  event.preventDefault();
  mensaje("");
 
  if (!imagenFinal) {
    mensaje("Sube la foto de la prenda.");
    return;
  }
 
  const datos = new FormData(this);
  datos.set("imagen", imagenFinal, imagenFinal.type === "image/png" ? "prenda.png" : (imagenFinal.name || "prenda"));
 
  $btn.prop("disabled", true);
 
  $.ajax({
    url: `${API}/servicio.php?agregarPrenda`,
    method: "POST",
    data: datos,
    processData: false,   // necesario para enviar archivos con FormData
    contentType: false,
    dataType: "json"
  })
    .done(respuesta => {
      if (respuesta.ok) {
        location.href = "prendas.html";
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