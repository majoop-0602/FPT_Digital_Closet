// Requiere jQuery y auth.js cargados antes (protegerPagina y el botón de salir).
// API viene de auth.js (no la declares otra vez: los scripts normales comparten el mismo ámbito).
const RAIZ = "../";       // prefijo para llegar a las imágenes desde esta página. Si está en PAGES/, usa "../"

const FONDOS = ["#FFC1CC", "#FFFAF3", "#FFF0DF"];   // colores que se alternan detrás de cada prenda

const $grid = $("#grid");
const $count = $("#count");
const $empty = $("#empty");
const $marca = $("#marca");
const $temporada = $("#temporada");
const $color = $("#color");

let categoria = 0;     // 0 = todas
let turno = 0;         // evita que una respuesta lenta pise a una más nueva
let espera = null;

protegerPagina();      // si no hay sesión, manda al login

function dibujar(prendas) {
  $grid.empty();

  prendas.forEach(function (p, i) {
    const $img = $("<img>")
      .attr("src", RAIZ + p.imagen)
      .attr("alt", p.nombre)
      .attr("loading", "lazy");

    $("<a>")
      .addClass("item")
      .attr("href", "#")
      .attr("data-id", p.id)
      .css({ background: FONDOS[i % FONDOS.length], animationDelay: Math.min(i, 12) * 0.04 + "s" })
      .append($img)
      .appendTo($grid);
  });

  $count.text(prendas.length + (prendas.length === 1 ? " prenda" : " prendas"));
  $empty.css("display", prendas.length ? "none" : "block");
}

function cargar() {
  const miTurno = ++turno;

  $grid.attr("aria-busy", "true");

  $.post(`${API}/servicio.php?prendas`, {
    categoria: categoria,
    marca: $marca.val(),
    temporada: $temporada.val(),
    color: $color.val()
  }, function (respuesta) {
    if (miTurno !== turno) return;

    $grid.attr("aria-busy", "false");

    if (!respuesta.ok) {
      if (respuesta.mensaje === "Debes iniciar sesión.") {
        location.href = "closetix-login.html";
        return;
      }
      $count.text(respuesta.mensaje);
      return;
    }
    dibujar(respuesta.prendas);
  }, "json").fail(function () {
    if (miTurno !== turno) return;
    $grid.attr("aria-busy", "false");
    $count.text("No se pudo conectar con el servidor.");
  });
}

// ---- Categorías ----
$("#chips").on("click", ".chip", function () {
  categoria = parseInt($(this).data("cat"), 10);
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
$color.on("change", cargar);

// La marca espera un momento después de escribir para no consultar con cada letra
$marca.on("input", function () {
  clearTimeout(espera);
  espera = setTimeout(cargar, 300);
});

$("#clear").on("click", function () {
  $marca.val("");
  $temporada.val("");
  $color.val("");
  cargar();
});

cargar();