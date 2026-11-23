// Requiere jQuery y auth.js cargados antes (API, protegerPagina y el botón #btnSalir vienen de auth.js).
const RAIZ = "../";   // prefijo para llegar a las imágenes desde esta página. Si está en PAGES/, usa "../"

const FONDOS = ["#FFC1CC", "#FFFAF3", "#FFF0DF"];
const TEMPORADAS = {
  "primavera": "Primavera",
  "verano": "Verano",
  "otono": "Otoño",
  "invierno": "Invierno",
  "todo-el-ano": "Todo el año"
};

const $grid = $("#grid");
const $count = $("#count");
const $empty = $("#empty");
const $buscar = $("#buscar");
const $temporada = $("#temporada");
const $orden = $("#orden");
const $favs = $("#favs");

let ocasion = 0;     // 0 = todas
let turno = 0;       // evita que una respuesta lenta pise a una más nueva
let espera = null;

protegerPagina();    // si no hay sesión, manda al login

function dibujar(outfits) {
  $grid.empty();

  outfits.forEach(function (o, i) {
    const $thumb = $("<div>")
      .addClass("thumb")
      .css("background", FONDOS[i % FONDOS.length])
      .append($("<img>").attr({ src: RAIZ + o.imagen, alt: o.nombre, loading: "lazy" }));

    if (o.es_favorito) {
      $thumb.append($("<span>").addClass("cstar"));
    }

    const $tags = $("<div>").addClass("tags")
      .append($("<span>").addClass("tag").text(o.ocasion_nombre))
      .append($("<span>").addClass("tag s").text(TEMPORADAS[o.temporada] || ""));

    const $info = $("<div>").addClass("info")
      .append($("<h3>").text(o.nombre))
      .append($tags);

    $("<a>")
      .addClass("outfit")
      .attr("href", `subir-outfits.html?id=${o.id}`)
      .css("animationDelay", Math.min(i, 12) * 0.04 + "s")
      .append($thumb, $info)
      .appendTo($grid);
  });

  $count.text(outfits.length + (outfits.length === 1 ? " outfit" : " outfits"));
  $empty.css("display", outfits.length ? "none" : "block");
}

function cargar() {
  const miTurno = ++turno;

  $grid.attr("aria-busy", "true");

  $.post(`${API}/servicio.php?outfits`, {
    ocasion: ocasion,
    buscar: $buscar.val(),
    temporada: $temporada.val(),
    orden: $orden.val(),
    favoritos: $favs.prop("checked") ? 1 : 0
  }, function (respuesta) {
    if (miTurno !== turno) return;

    $grid.attr("aria-busy", "false");

    if (!respuesta.ok) {
      if (respuesta.mensaje === "Debes iniciar sesión.") {
        location.href = "login.html";
        return;
      }
      $count.text(respuesta.mensaje);
      return;
    }
    dibujar(respuesta.outfits);
  }, "json").fail(function () {
    if (miTurno !== turno) return;
    $grid.attr("aria-busy", "false");
    $count.text("No se pudo conectar con el servidor.");
  });
}

// ---- Ocasiones ----
$("#chips").on("click", ".chip", function () {
  ocasion = parseInt($(this).data("oca"), 10);
  $(".chip").attr("aria-pressed", "false");
  $(this).attr("aria-pressed", "true");
  cargar();
});

// ---- Más filtros ----
$("#more").on("click", function () {
  const abierto = $("#panel").toggleClass("open").hasClass("open");
  $(this).attr("aria-expanded", abierto ? "true" : "false");
});

$temporada.on("change", cargar);
$orden.on("change", cargar);
$favs.on("change", cargar);

// La búsqueda espera un momento después de escribir para no consultar con cada letra
$buscar.on("input", function () {
  clearTimeout(espera);
  espera = setTimeout(cargar, 300);
});

$("#clear").on("click", function () {
  $buscar.val("");
  $temporada.val("");
  $orden.val("nuevos");
  $favs.prop("checked", false);
  cargar();
});

cargar();