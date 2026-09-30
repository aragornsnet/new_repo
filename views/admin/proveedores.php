<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Proveedores';
$subtituloPagina = 'Gestión de proveedores de mercancía';
$paginaActiva = 'proveedores';
$scriptsExtra = [
    BASE_URL . 'public/js/proveedores.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Administrador', 'Almacenero', 'Supervisor'])) {
    redirigir('views/403.php');
}

$puedeEditar = Auth::esAdmin();
?>

<div class="page-header">
    <div>
        <h2 class="page-title">🏭 Proveedores</h2>
        <p class="page-subtitle">Total: <span id="total-prov">—</span> proveedores</p>
    </div>
    <div class="page-actions">
        <?php if ($puedeEditar): ?>
            <button class="btn btn-primary" onclick="Proveedores.abrirNuevo()">
                <i class="bi bi-plus-lg"></i> Nuevo proveedor
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!$puedeEditar): ?>
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle-fill"></i>
        <div>Modo solo lectura. Solo el administrador puede crear o modificar proveedores.</div>
    </div>
<?php endif; ?>

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
        <button class="btn btn-secondary btn-sm" onclick="Proveedores.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar filtros
        </button>
    </div>
</div>

<div class="card">
    <div id="tabla-proveedores">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: CREAR / EDITAR PROVEEDOR                            -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php if ($puedeEditar): ?>
<div class="modal-backdrop" id="modal-proveedor">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-building"></i>
                <span id="modal-titulo">Nuevo proveedor</span>
            </div>
            <button class="modal-close" onclick="Proveedores.cerrarModal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="form-proveedor" class="form" novalidate>
                <input type="hidden" id="prov-id">

                <div class="form-grid">
                    <div class="field span-2">
                        <label for="prov-nombre">Nombre / Razón social <span class="req">*</span></label>
                        <input type="text" id="prov-nombre" maxlength="150" required placeholder="Ej: Distribuidora El Sol">
                    </div>

                    <div class="field">
                        <label for="prov-nit">NIT / Documento</label>
                        <input type="text" id="prov-nit" maxlength="50" placeholder="Ej: J-12345678-9">
                    </div>

                    <div class="field">
                        <label for="prov-tipo">Tipo de persona</label>
                        <select id="prov-tipo">
                            <option value="juridica">Jurídica</option>
                            <option value="fisica">Física</option>
                        </select>
                    </div>

                    <div class="field span-full">
                        <label for="prov-direccion">Dirección</label>
                        <input type="text" id="prov-direccion" maxlength="255" placeholder="Calle, número, ciudad">
                    </div>

                    <div class="field">
                        <label for="prov-telefono">Teléfono</label>
                        <input type="text" id="prov-telefono" maxlength="50" placeholder="+53 7 1234567">
                    </div>

                    <div class="field">
                        <label for="prov-email">Email</label>
                        <input type="email" id="prov-email" maxlength="120" placeholder="contacto@proveedor.com">
                    </div>

                    <div class="field span-full">
                        <label for="prov-contacto">Persona de contacto</label>
                        <input type="text" id="prov-contacto" maxlength="120" placeholder="Ej: Juan Pérez (gerente de ventas)">
                    </div>

                    <div class="field span-full">
                        <label for="prov-notas">Notas</label>
                        <textarea id="prov-notas" maxlength="1000" placeholder="Notas internas sobre el proveedor" style="min-height:80px;"></textarea>
                    </div>

                    <div class="field span-full">
                        <label class="check">
                            <input type="checkbox" id="prov-activo" checked>
                            <span>Proveedor activo</span>
                        </label>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="Proveedores.cerrarModal()">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar-prov" onclick="Proveedores.guardar()">
                <i class="bi bi-check-lg"></i> Guardar
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: VER DETALLE DEL PROVEEDOR                            -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-detalle">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-info-circle"></i> Detalle del proveedor
            </div>
            <button class="modal-close" onclick="Proveedores.cerrarDetalle()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body" id="detalle-contenido">
            <div class="empty-state"><div class="spinner"></div></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>