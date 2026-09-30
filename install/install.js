/**
 * IPV - Instalador
 * Scripts generales
 */

// Confirmación al salir si hay formulario a medias (opcional)
// (evitamos por simplicidad; el usuario puede recargar sin problema)

// Auto-focus en el primer input de cada paso
document.addEventListener('DOMContentLoaded', () => {
    const primerInput = document.querySelector('.card form input:not([type=checkbox]):not([type=hidden])');
    if (primerInput) {
        // Comentado para no ser intrusivo:
        // primerInput.focus();
    }
});