<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Gestión de Usuarios';
$subtituloPagina = 'Administrar usuarios del sistema';
$paginaActiva = 'usuarios';
$scriptsExtra = [
    BASE_URL . 'public/js/usuarios.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::esAdmin()) {
    redirigir('views/403.php');
}

$roles = Database::fetchAll("SELECT id, nombre FROM roles ORDER BY id");
$puntos = Database::fetchAll("SELECT id, nombre FROM puntos_venta WHERE activo = 1 ORDER BY nombre");
?>

<div class="page-header">
    <div>
        <h2 class="page-title">👥 Usuarios del sistema</h2>
        <p class="page-subtitle">Total: <span id="total-usuarios">—</span> usuarios</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="Usuarios.abrirFormNuevo()">
            <i class="bi bi-person-plus-fill"></i> Nuevo usuario
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Nombre o correo...">
            </div>
        </div>
        <div class="field">
            <label>Rol</label>
            <select id="filtro-rol">
                <option value="">Todos los roles</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= (int) $r['id'] ?>"><?= h($r['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Punto de venta</label>
            <select id="filtro-pv">
                <option value="">Todos los PV</option>
                <?php foreach ($puntos as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"><?= h($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
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
        <button class="btn btn-secondary btn-sm" onclick="Usuarios.limpiarFiltros()">
            <i class="bi bi-x-circle"></i> Limpiar filtros
        </button>
    </div>
</div>

<div class="card">
    <div id="tabla-usuarios">
        <div class="empty-state">
            <div class="spinner spinner-lg"></div>
            <p class="mt-3 text-muted">Cargando usuarios...</p>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="modal-usuario">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-person-fill"></i>
                <span id="modal-titulo">Nuevo usuario</span>
            </div>
            <button class="modal-close" onclick="Usuarios.cerrarModal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="form-usuario" class="form" novalidate>
                <input type="hidden" id="usuario-id" value="">

                <div class="form-grid">
                    <div class="field span-full">
                        <label for="u-nombre">Nombre completo <span class="req">*</span></label>
                        <input type="text" id="u-nombre" required maxlength="100">
                    </div>

                    <div class="field span-full">
                        <label for="u-email">Correo electrónico <span class="req">*</span></label>
                        <input type="email" id="u-email" required maxlength="120">
                    </div>

                    <div class="field">
                        <label for="u-rol">Rol <span class="req">*</span></label>
                        <select id="u-rol" required>
                            <option value="">Selecciona un rol</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= (int) $r['id'] ?>"><?= h($r['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field" id="field-pv" style="display:none;">
                        <label for="u-pv">Punto de venta <span class="req">*</span></label>
                        <select id="u-pv">
                            <option value="">Selecciona PV</option>
                            <?php foreach ($puntos as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= h($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field span-full">
                        <label class="check">
                            <input type="checkbox" id="u-activo" checked>
                            <span>Usuario activo (puede iniciar sesión)</span>
                        </label>
                    </div>
                </div>

                <div id="seccion-password">
                    <hr style="margin: var(--space-4) 0; border: none; border-top: 1px solid var(--border);">

                    <div class="form-grid">
                        <div class="field">
                            <label for="u-password">Contraseña <span class="req">*</span></label>
                            <div class="input-group">
                                <input type="password" id="u-password" autocomplete="new-password">
                                <button type="button" class="btn-toggle-pass" onclick="Password.toggle('u-password', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                            <div class="strength-text" id="strength-text"></div>
                            <ul class="req-list" id="req-list">
                                <li data-req="len"><i class="bi bi-circle"></i> Mínimo <?= Config::int('pass_longitud_min', 8) ?> caracteres</li>
                                <li data-req="mayus"><i class="bi bi-circle"></i> Al menos una mayúscula</li>
                                <li data-req="minus"><i class="bi bi-circle"></i> Al menos una minúscula</li>
                                <li data-req="num"><i class="bi bi-circle"></i> Al menos un número</li>
                                <li data-req="simbolo"><i class="bi bi-circle"></i> Al menos un símbolo</li>
                            </ul>
                        </div>

                        <div class="field">
                            <label for="u-password2">Confirmar contraseña <span class="req">*</span></label>
                            <div class="input-group">
                                <input type="password" id="u-password2" autocomplete="new-password">
                                <button type="button" class="btn-toggle-pass" onclick="Password.toggle('u-password2', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="match-msg" id="match-msg"></div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="Usuarios.cerrarModal()">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar" onclick="Usuarios.guardar()">
                <i class="bi bi-check-lg"></i> Guardar
            </button>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="modal-reset">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-key-fill"></i> Resetear contraseña</div>
            <button class="modal-close" onclick="Usuarios.cerrarReset()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <p class="text-muted mb-3">Usuario: <strong id="reset-nombre"></strong></p>
            <input type="hidden" id="reset-id">
            <div class="field mb-3">
                <label for="reset-password">Nueva contraseña <span class="req">*</span></label>
                <div class="input-group">
                    <input type="password" id="reset-password" autocomplete="new-password">
                    <button type="button" class="btn-toggle-pass" onclick="Password.toggle('reset-password', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="reset-strength-fill"></div></div>
                <div class="strength-text" id="reset-strength-text"></div>
            </div>
            <div class="field">
                <label for="reset-password2">Confirmar <span class="req">*</span></label>
                <div class="input-group">
                    <input type="password" id="reset-password2" autocomplete="new-password">
                    <button type="button" class="btn-toggle-pass" onclick="Password.toggle('reset-password2', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="match-msg" id="reset-match-msg"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="Usuarios.cerrarReset()">Cancelar</button>
            <button class="btn btn-danger" id="btn-reset" onclick="Usuarios.confirmarReset()">
                <i class="bi bi-key"></i> Resetear
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>