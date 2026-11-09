document.addEventListener("DOMContentLoaded", function () {
    const boton = document.getElementById("menuMovilToggle");
    const barra = document.querySelector(".sidebar");
    const capa = document.getElementById("sidebarOverlay");
    if (!boton || !barra || !capa) return;

    const consultaMovil = window.matchMedia("(max-width: 800px)");

    function abrir() {
        barra.classList.add("show");
        capa.hidden = false;
        document.body.classList.add("menu-movil-abierto");
        boton.setAttribute("aria-expanded", "true");
    }

    function cerrar() {
        barra.classList.remove("show");
        capa.hidden = true;
        document.body.classList.remove("menu-movil-abierto");
        boton.setAttribute("aria-expanded", "false");
    }

    boton.addEventListener("click", function () {
        if (barra.classList.contains("show")) cerrar(); else abrir();
    });
    capa.addEventListener("click", cerrar);
    barra.querySelectorAll("a").forEach(enlace => enlace.addEventListener("click", function () {
        if (consultaMovil.matches) cerrar();
    }));
    document.addEventListener("keydown", evento => { if (evento.key === "Escape" && barra.classList.contains("show")) cerrar(); });
    consultaMovil.addEventListener?.("change", evento => { if (!evento.matches) cerrar(); });
});
