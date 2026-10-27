
document.addEventListener("DOMContentLoaded", async () => {
    const elementoNombre = document.getElementById("nombreUsuario");

    try {
        const respuesta = await fetch(
            "../BACKEND/api/servicio.php?sesionActual",
            {
                method: "GET",
                credentials: "same-origin"
            }
        );

        const resultado = await respuesta.json();

        if (resultado.ok) {
            elementoNombre.textContent = resultado.usuario;
        } else {
            // Si no hay sesión, regresar al login
            window.location.href = "login.html";
        }

    } catch (error) {
        console.error("Error al consultar la sesión:", error);
    }
});