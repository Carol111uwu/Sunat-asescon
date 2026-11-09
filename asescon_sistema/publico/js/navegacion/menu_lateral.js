document.addEventListener("DOMContentLoaded", function () {
    const botones = document.querySelectorAll(".dropdown-btn");
    botones.forEach(function (boton) {
        boton.addEventListener("click", function () {
            const idObjetivo = boton.getAttribute("data-target");
            const submenu = document.getElementById(idObjetivo);
            if (!submenu) return;
            submenu.classList.toggle("open");
            boton.classList.toggle("closed");
            const flecha = boton.querySelector(".arrow");
            if (flecha) flecha.textContent = submenu.classList.contains("open") ? "⌃" : "⌄";
        });
    });

    const menu = document.querySelector(".sidebar .menu");
    const activo = document.querySelector(".sidebar .menu .active");
    if (menu && activo) {
        requestAnimationFrame(function () {
            activo.scrollIntoView({ block: "nearest", inline: "nearest" });
        });
    }
});
