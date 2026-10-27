// Requiere jQuery. La carpeta api/ está al lado de las páginas HTML.
const API = "../BACKEND/api";

function mostrarMensaje(texto) {
  $("#msg").text(texto);
}

function errorDeRed($btn) {
  mostrarMensaje("No se pudo conectar con el servidor. Intenta de nuevo.");
  $btn.prop("disabled", false);
}

// ---------------- LOGIN ----------------
$("#frmLogin").submit(function (event) {
  event.preventDefault();
  mostrarMensaje("");

  const $btn = $(this).find("button[type=submit]").prop("disabled", true);

  $.post(`${API}/servicio.php?iniciarSesion`, $(this).serialize(), function (respuesta) {
    if (respuesta.ok) {
      location.href = "prendas.html";
    } else {
      mostrarMensaje(respuesta.mensaje);
      $btn.prop("disabled", false);
    }
  }, "json").fail(() => errorDeRed($btn));
});

// ---------------- SIGN UP ----------------
$("#frmSignup").submit(function (event) {
  event.preventDefault();
  mostrarMensaje("");

  if ($("#password").val().length < 8) {
    mostrarMensaje("La contraseña debe tener al menos 8 caracteres.");
    return;
  }

  const $btn = $(this).find("button[type=submit]").prop("disabled", true);

  $.post(`${API}/servicio.php?registrarUsuario`, $(this).serialize(), function (respuesta) {
    if (respuesta.ok) {
      location.href = "prendas.html";
    } else {
      mostrarMensaje(respuesta.mensaje);
      $btn.prop("disabled", false);
    }
  }, "json").fail(() => errorDeRed($btn));
});

// ------------- PÁGINAS PROTEGIDAS -------------
// Llama protegerPagina() al cargar closetix-prendas.html, closetix-agregar-prenda.html, etc.
function protegerPagina() {
  $.post(`${API}/servicio.php?sesionActual`, function (respuesta) {
    if (!respuesta.ok) {
      location.href = "login.html";
    }
  }, "json");
}

// Úsalo en el botón "Cerrar sesión": <a id="btnSalir" ...>
$(document).on("click", "#btnSalir", function (event) {
  event.preventDefault();
  $.post(`${API}/servicio.php?cerrarSesion`, function () {
    location.href = "login.html";
  }, "json");
});
