// Esta misma página sirve para tres cosas:
//   closetix-crear-outfit.html          -> crear un outfit nuevo
//   closetix-crear-outfit.html?id=12    -> ver el outfit 12 (bloqueado)
//   ...?id=12 + botón "Editar outfit"   -> modificarlo
// Requiere jQuery, Fabric.js 5 y auth.js cargados antes.
// API, protegerPagina() y el botón #btnSalir vienen de auth.js (no los declares otra vez).
const RAIZ = "../";            // prefijo para llegar a las imágenes. Si la página está en PAGES/, usa "../"
const PAGINA = "subir-outfits.html";
const ANCHO = 800;          // tamaño lógico del lienzo (se guarda en canvas_width / canvas_height)
const ALTO = 1000;
const LADO_INICIAL = 320;   // tamaño con el que entra cada prenda al lienzo
const MAX_PRENDAS = 30;
const FONDOS = ["#FFC1CC", "#FFFAF3", "#FFF0DF"];

const idOutfit = new URLSearchParams(location.search).get("id");
let modo = idOutfit ? "ver" : "nuevo";   // "nuevo" | "ver" | "editar"
let nombreOutfit = "";

protegerPagina();           // si no hay sesión, manda al login

// ---------------- Lienzo (Fabric.js) ----------------
// Las posiciones se guardan en el tamaño lógico 800x1000; en pantalla se escala con zoom.
const canvas = new fabric.Canvas("lienzo", {
  preserveObjectStacking: true,   // una prenda seleccionada no salta al frente
  selection: false                // sin selección múltiple: cada prenda se mueve por separado
});

fabric.Object.prototype.set({
  transparentCorners: false,
  cornerStyle: "circle",
  cornerSize: 14,
  cornerColor: "#9F2D2D",
  cornerStrokeColor: "#3B1414",
  borderColor: "#3B1414",
  padding: 6
});

let enLienzo = {};          // id de prenda -> objeto del lienzo

function ajustarLienzo() {
  const disponible = $("#stage").innerWidth() - 28;
  const topePorAlto = window.innerHeight * 0.8 * (ANCHO / ALTO);   // que el lienzo quepa en pantalla
  const ancho = Math.max(240, Math.min(disponible, topePorAlto));
  const escala = ancho / ANCHO;
  canvas.setDimensions({ width: ancho, height: ALTO * escala });
  canvas.setZoom(escala);
}

function mensaje(texto) {
  $("#msg").text(texto);
}

// Actualiza botones, contador, mensaje de vacío y marcas del selector
function actualizarEstado() {
  const objetos = canvas.getObjects();

  enLienzo = {};
  objetos.forEach(o => { enLienzo[o.clothingId] = o; });

  $("#vacio").prop("hidden", objetos.length > 0);
  $("#contador").text(objetos.length + (objetos.length === 1 ? " prenda" : " prendas"));
  $(".needsel").prop("disabled", canvas.getActiveObjects().length === 0);
  $(".pick").each(function () {
    $(this).toggleClass("used", !!enLienzo[$(this).data("id")]);
  });
}

canvas.on({
  "object:added": actualizarEstado,
  "object:removed": actualizarEstado,
  "selection:created": actualizarEstado,
  "selection:updated": actualizarEstado,
  "selection:cleared": actualizarEstado
});

$(window).on("resize", ajustarLienzo);

// ---------------- Modos: nuevo / ver / editar ----------------
const $form = $("#frmOutfit");

function aplicarModo() {
  const ver = modo === "ver";

  $("#card").toggleClass("view", ver);
  $(".picker").prop("hidden", ver);
  $("#herramientas").prop("hidden", ver);
  $form.find("input, select, textarea").prop("disabled", ver);

  // En modo ver las prendas no se pueden tocar
  canvas.discardActiveObject();
  canvas.skipTargetFind = ver;
  canvas.forEachObject(o => o.set({ selectable: !ver, evented: !ver }));
  canvas.requestRenderAll();

  $("#btnGuardar").prop("hidden", ver).text(modo === "editar" ? "Guardar cambios" : "Guardar outfit");
  $("#btnEditar").prop("hidden", !ver);

  if (modo === "editar") {
    $("#btnCancelar").text("Cancelar").attr("href", `${PAGINA}?id=${idOutfit}`);
  } else if (ver) {
    $("#btnCancelar").text("Volver").attr("href", "outfits.html");
  } else {
    $("#btnCancelar").text("Cancelar").attr("href", "outfits.html");
  }

  const textos = {
    nuevo:  ["Crear outfit", "Elige tus prendas y acomódalas en el lienzo."],
    ver:    [nombreOutfit || "Mi outfit", "Así quedó tu outfit."],
    editar: ["Editar outfit", "Mueve, cambia o agrega prendas y guarda."]
  };
  $("#titulo").text(textos[modo][0]);
  $("#subtitulo").text(textos[modo][1]);
  document.title = `${textos[modo][0]} | Closetix`;

  ajustarLienzo();   // el espacio disponible cambia entre un modo y otro
  actualizarEstado();
}

$("#btnEditar").on("click", function () {
  modo = "editar";
  mensaje("");
  aplicarModo();
});

// ---------------- Cargar un outfit existente ----------------
function cargarImagen(it) {
  return new Promise(function (resolve) {
    fabric.Image.fromURL(RAIZ + it.imagen, function (img, error) {
      resolve(error || !img.width ? null : { img: img, it: it });
    }, { crossOrigin: "anonymous" });
  });
}

async function dibujarOutfit(o) {
  // Si el lienzo guardado tenía otro tamaño, se reescalan las posiciones
  const f = ANCHO / (o.canvas_width || ANCHO);

  // Se cargan todas y se agregan en orden (z_index) para respetar qué va al frente
  const cargadas = await Promise.all(o.items.map(cargarImagen));
  let fallidas = 0;

  cargadas.forEach(function (c) {
    if (!c) { fallidas++; return; }

    c.img.set({
      left: c.it.x * f,
      top: c.it.y * f,
      scaleX: c.it.sx * f,
      scaleY: c.it.sy * f,
      angle: c.it.angle,
      lockUniScaling: true,
      clothingId: c.it.prenda
    });
    c.img.setControlsVisibility({ mt: false, mb: false, ml: false, mr: false });
    canvas.add(c.img);
  });

  if (fallidas) {
    mensaje(`${fallidas} ${fallidas === 1 ? "prenda no se pudo cargar" : "prendas no se pudieron cargar"}.`);
  }
}

function cargarOutfit() {
  $.post(`${API}/servicio.php?outfit`, { id: idOutfit }, async function (respuesta) {
    if (!respuesta.ok) {
      if (respuesta.mensaje === "Debes iniciar sesión.") {
        location.href = "login.html";
        return;
      }
      mensaje(respuesta.mensaje);
      $("#btnEditar").prop("hidden", true);
      return;
    }

    const o = respuesta.outfit;
    nombreOutfit = o.nombre;

    $("#nombre").val(o.nombre);
    $form.find("[name=ocasion]").filter(`[value="${o.ocasion}"]`).prop("checked", true);
    $("#temporada").val(o.temporada);
    $("#notas").val(o.notas);
    $form.find("[name=favorita]").prop("checked", !!o.es_favorito);

    await dibujarOutfit(o);
    aplicarModo();
  }, "json").fail(function () {
    mensaje("No se pudo conectar con el servidor. Intenta de nuevo.");
    $("#btnEditar").prop("hidden", true);
  });
}

// ---------------- Poner prendas en el lienzo ----------------
function agregarAlLienzo(p) {
  // Una prenda solo puede estar una vez: si ya está, se selecciona
  if (enLienzo[p.id]) {
    canvas.setActiveObject(enLienzo[p.id]);
    canvas.requestRenderAll();
    return;
  }
  if (canvas.getObjects().length >= MAX_PRENDAS) {
    mensaje(`Un outfit puede tener máximo ${MAX_PRENDAS} prendas.`);
    return;
  }
  mensaje("");

  fabric.Image.fromURL(RAIZ + p.imagen, function (img, error) {
    if (error || !img.width) {
      mensaje("No se pudo cargar la imagen de esa prenda.");
      return;
    }

    const k = LADO_INICIAL / Math.max(img.width, img.height);
    const sacudida = () => (Math.random() - 0.5) * 60;   // para que no se encimen exactamente

    img.set({
      left: (ANCHO - img.width * k) / 2 + sacudida(),
      top: (ALTO - img.height * k) / 2 + sacudida(),
      scaleX: k,
      scaleY: k,
      lockUniScaling: true,
      clothingId: p.id
    });
    img.setControlsVisibility({ mt: false, mb: false, ml: false, mr: false });   // solo escala proporcional

    canvas.add(img);
    canvas.setActiveObject(img);
    canvas.requestRenderAll();
  }, { crossOrigin: "anonymous" });
}

// ---------------- Herramientas ----------------
$("#btnFrente").on("click", function () {
  canvas.getActiveObjects().forEach(o => canvas.bringToFront(o));
  canvas.requestRenderAll();
});

$("#btnAtras").on("click", function () {
  canvas.getActiveObjects().forEach(o => canvas.sendToBack(o));
  canvas.requestRenderAll();
});

function quitarSeleccion() {
  if (modo === "ver") return;
  const seleccion = canvas.getActiveObjects();
  if (!seleccion.length) return;
  canvas.discardActiveObject();
  seleccion.forEach(o => canvas.remove(o));
  canvas.requestRenderAll();
}

$("#btnQuitar").on("click", quitarSeleccion);

$("#btnVaciar").on("click", function () {
  if (canvas.getObjects().length && confirm("¿Quitar todas las prendas del lienzo?")) {
    canvas.discardActiveObject();
    canvas.getObjects().slice().forEach(o => canvas.remove(o));
    canvas.requestRenderAll();
  }
});

// Suprimir / Borrar quita la prenda seleccionada (sin interferir al escribir en el formulario)
$(document).on("keydown", function (e) {
  if (["INPUT", "TEXTAREA", "SELECT"].includes(e.target.tagName)) return;
  if (e.key === "Delete" || e.key === "Backspace") {
    e.preventDefault();
    quitarSeleccion();
  }
});

// ---------------- Selector de prendas ----------------
const $pgrid = $("#pgrid");
const $pvacio = $("#pvacio");
let categoria = 0;
let turnoPrendas = 0;

function dibujarSelector(prendas) {
  $pgrid.empty();

  prendas.forEach(function (p, i) {
    const $img = $("<img>").attr("src", RAIZ + p.imagen).attr("alt", p.nombre).attr("loading", "lazy");

    $("<button>")
      .attr({ type: "button", title: p.nombre, "aria-label": "Agregar " + p.nombre })
      .addClass("pick")
      .data("id", p.id)
      .css("background", FONDOS[i % FONDOS.length])
      .append($img)
      .on("click", () => agregarAlLienzo(p))
      .appendTo($pgrid);
  });

  $pvacio.prop("hidden", prendas.length > 0);
  actualizarEstado();
}

function cargarPrendas() {
  const miTurno = ++turnoPrendas;

  // Reutiliza el mismo método que la página de prendas
  $.post(`${API}/servicio.php?prendas`, { categoria: categoria }, function (respuesta) {
    if (miTurno !== turnoPrendas) return;

    if (!respuesta.ok) {
      if (respuesta.mensaje === "Debes iniciar sesión.") {
        location.href = "login.html";
        return;
      }
      mensaje(respuesta.mensaje);
      return;
    }
    dibujarSelector(respuesta.prendas);
  }, "json").fail(function () {
    if (miTurno !== turnoPrendas) return;
    mensaje("No se pudieron cargar tus prendas.");
  });
}

$("#pchips").on("click", ".chip", function () {
  categoria = parseInt($(this).data("cat"), 10);
  $("#pchips .chip").attr("aria-pressed", "false");
  $(this).attr("aria-pressed", "true");
  cargarPrendas();
});

// ---------------- Guardar (crear o editar) ----------------
const redondear = (n, d) => Math.round(n * 10 ** d) / 10 ** d;

$form.on("submit", async function (event) {
  event.preventDefault();
  mensaje("");

  if (modo === "ver") return;

  if (canvas.getObjects().length === 0) {
    mensaje("Agrega al menos una prenda al lienzo.");
    return;
  }

  const $btn = $("#btnGuardar").prop("disabled", true);

  try {
    // Sin selección activa, para que las posiciones sean absolutas y no se dibujen los controles
    canvas.discardActiveObject();
    canvas.requestRenderAll();

    // Una fila por prenda: los nombres coinciden con las propiedades de Fabric
    const items = canvas.getObjects().map(function (o, i) {
      return {
        prenda: o.clothingId,
        x: redondear(o.left, 2),
        y: redondear(o.top, 2),
        sx: redondear(o.scaleX, 3),
        sy: redondear(o.scaleY, 3),
        angle: redondear(((o.angle % 360) + 360) % 360, 2),
        z: i                         // orden de apilamiento: 0 = al fondo
      };
    });

    // Vista previa en PNG transparente, siempre de 800x1000 sin importar el tamaño en pantalla
    const dataURL = canvas.toDataURL({ format: "png", multiplier: ANCHO / canvas.getWidth() });
    const preview = await (await fetch(dataURL)).blob();

    const datos = new FormData(this);
    datos.set("items", JSON.stringify(items));
    datos.set("canvas_width", ANCHO);
    datos.set("canvas_height", ALTO);
    datos.set("preview", preview, "preview.png");

    let metodo = "agregarOutfit";
    if (modo === "editar") {
      metodo = "editarOutfit";
      datos.set("id", idOutfit);
    }

    $.ajax({
      url: `${API}/servicio.php?${metodo}`,
      method: "POST",
      data: datos,
      processData: false,
      contentType: false,
      dataType: "json"
    })
      .done(function (respuesta) {
        if (respuesta.ok) {
          // Al editar se regresa a la ficha; al crear, a la lista
          location.href = modo === "editar" ? `${PAGINA}?id=${idOutfit}` : "outfits.html";
        } else {
          mensaje(respuesta.mensaje);
          $btn.prop("disabled", false);
        }
      })
      .fail(function () {
        mensaje("No se pudo conectar con el servidor. Intenta de nuevo.");
        $btn.prop("disabled", false);
      });
  } catch (error) {
    console.error(error);
    mensaje("No se pudo generar la vista previa del outfit.");
    $btn.prop("disabled", false);
  }
});

// ---------------- Inicio ----------------
aplicarModo();
cargarPrendas();
if (idOutfit) cargarOutfit();
