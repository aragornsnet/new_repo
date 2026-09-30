<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Clientes Mayoristas';
$subtituloPagina = 'Gestión de clientes para facturación';
$paginaActiva = 'clientes';
$scriptsExtra = [
    BASE_URL . 'public/js/clientes.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Administrador', 'Supervisor', 'Vendedor'])) {
    redirigir('views/403.php');
}

$puedeEditar = Auth::esAdmin() || Auth::esSupervisor() || Auth::esVendedor();
?>

<div class="page-header">
    <div>
        <h2 class="page-title">👥 Clientes mayoristas</h2>
        <p class="page-subtitle">Total: <span id="total-clientes">—</span> clientes</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="Clientes.abrirNuevo()">
            <i class="bi bi-plus-lg"></i> Nuevo cliente
        </button>
    </div>
</div>

<?php if (!$puedeEditar): ?>
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle-fill"></i>
        <div>Modo solo lectura.</div>
    </div>
<?php endif; ?>

<!-- Filtros -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Nombre, NIT, contacto o email...">
            </div>
        </div>
        <div class="field">
            <label>Estado</label>
            <select id="filtro-estado">
                <option value="">Todos</option>
                <option value="activos">Solo activos</option>
                <option value="inactivos">Solo inactivos</option>
            </select>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-secondary btn-sm" onclick="Clientes.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar filtros
        </button>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-people-fill"></i> Clientes
            <span class="badge badge-neutral ml-2" id="total-clientes-2">—</span>
        </div>
    </div>
    <div id="tabla-clientes">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: CREAR / EDITAR CLIENTE                              -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-cliente">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-person-badge"></i>
                <span id="modal-titulo">Nuevo cliente</span>
            </div>
            <button class="modal-close" onclick="Clientes.cerrarModal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="form-cliente" class="form" novalidate>
                <input type="hidden" id="cli-id">

                <div class="form-grid">
                    <div class="field span-2">
                        <label for="cli-nombre">Nombre / Razón social <span class="req">*</span></label>
                        <input type="text" id="cli-nombre" maxlength="150" required placeholder="Ej: Comercial XYZ S.A.">
                    </div>

                    <div class="field">
                        <label for="cli-nit">NIT / Documento</label>
                        <input type="text" id="cli-nit" maxlength="50" placeholder="Ej: J-12345678-9">
                    </div>

                    <div class="field">
                        <label for="cli-tipo">Tipo de persona</label>
                        <select id="cli-tipo">
                            <option value="juridica">Jurídica</option>
                            <option value="fisica">Física</option>
                        </select>
                    </div>

                    <div class="field span-full">
                        <label for="cli-direccion">Dirección</label>
                        <input type="text" id="cli-direccion" maxlength="255" placeholder="Calle, número, ciudad">
                    </div>

                    <div class="field">
                        <label for="cli-telefono">Teléfono</label>
                        <input type="text" id="cli-telefono" maxlength="50">
                    </div>

                    <div class="field">
                        <label for="cli-email">Email</label>
                        <input type="email" id="cli-email" maxlength="120">
                    </div>

                    <div class="field span-full">
                        <label for="cli-contacto">Persona de contacto</label>
                        <input type="text" id="cli-contacto" maxlength="120">
                    </div>

                    <div class="field span-full">
                        <label for="cli-notas">Notas</label>
                        <textarea id="cli-notas" maxlength="1000" style="min-height:80px;"></textarea>
                    </div>

                    <div class="field span-full">
                        <label class="check">
                            <input type="checkbox" id="cli-activo" checked>
                            <span>Cliente activo</span>
                        </label>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="Clientes.cerrarModal()">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar-cli" onclick="Clientes.guardar()">
                <i class="bi bi-check-lg"></i> Guardar
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: VER DETALLE DEL CLIENTE                              -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-detalle-cliente">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-info-circle"></i> Detalle del cliente
            </div>
            <button class="modal-close" onclick="Clientes.cerrarDetalle()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body" id="detalle-contenido">
            <div class="empty-state"><div class="spinner"></div></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>