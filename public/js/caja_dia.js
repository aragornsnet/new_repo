/**
 * IPV - Caja del día
 */

const CajaDia = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    async function cargarCatalogos() {
        const res = await Api.get('api/turnos.php?accion=catalogos');
        if (!res.success) return;

        const { puntos_venta } = res.data;
        document.getElementById('filtro-pv').innerHTML = '<option value="">Todos los PV</option>' +
            puntos_venta.map(p => `<option value="${p.id}">${App.escapeHtml(p.nombre)}</option>`).join('');
    }

    async function cargar() {
        const desde = document.getElementById('filtro-desde').value;
        const hasta = document.getElementById('filtro-hasta').value;
        const pvId = document.getElementById('filtro-pv').value;

        const params = new URLSearchParams();
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);
        if (pvId) params.append('pv_id', pvId);

        document.getElementById('tabla-vendedor').innerHTML = '<div class="empty-state"><div class="spinner"></div></div>';
        document.getElementById('tabla-pv').innerHTML = '<div class="empty-state"><div class="spinner"></div></div>';

        const res = await Api.get(`api/caja_dia.php?${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        const { por_vendedor, por_pv, totales } = res.data;

        // Totales
        document.getElementById('tot-num').textContent = totales.num_ventas || 0;
        document.getElementById('tot-efectivo').textContent = App.formatMoney(totales.total_efectivo);
        document.getElementById('tot-transf').textContent = App.formatMoney(totales.total_transferencia);
        document.getElementById('tot-general').textContent = App.formatMoney(totales.total_general);

        renderPorVendedor(por_vendedor);
        renderPorPV(por_pv);
    }

    function renderPorVendedor(lista) {
        const cont = document.getElementById('tabla-vendedor');

        const conVentas = lista.filter(v => parseInt(v.num_ventas) > 0);

        if (!conVentas.length) {
            cont.innerHTML = `<div class="empty-state"><i class="bi bi-inbox"></i><p>Sin ventas en el rango seleccionado</p></div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Vendedor</th>
                            <th>Punto de venta</th>
                            <th class="text-right">Num. ventas</th>
                            <th class="text-right">Efectivo</th>
                            <th class="text-right">Transferencias</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${conVentas.map(v => `
                            <tr>
                                <td><strong>${App.escapeHtml(v.vendedor)}</strong></td>
                                <td class="text-muted">${App.escapeHtml(v.pv)}</td>
                                <td class="text-right">${v.num_ventas}</td>
                                <td class="text-right text-success">${App.formatMoney(v.total_efectivo)}</td>
                                <td class="text-right text-info">${App.formatMoney(v.total_transferencia)}</td>
                                <td class="text-right"><strong>${App.formatMoney(v.total_general)}</strong></td>
                            </tr>
                        `).join('')}
                    </tbody>
                    <tfoot style="background:var(--surface-2);font-weight:700;">
                        <tr>
                            <td colspan="2">TOTAL</td>
                            <td class="text-right">${conVentas.reduce((a, b) => a + parseInt(b.num_ventas), 0)}</td>
                            <td class="text-right text-success">${App.formatMoney(conVentas.reduce((a, b) => a + parseFloat(b.total_efectivo), 0))}</td>
                            <td class="text-right text-info">${App.formatMoney(conVentas.reduce((a, b) => a + parseFloat(b.total_transferencia), 0))}</td>
                            <td class="text-right">${App.formatMoney(conVentas.reduce((a, b) => a + parseFloat(b.total_general), 0))}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        `;
    }

    function renderPorPV(lista) {
        const cont = document.getElementById('tabla-pv');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state"><i class="bi bi-shop"></i><p>Sin puntos de venta</p></div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Punto de venta</th>
                            <th class="text-right">Num. ventas</th>
                            <th class="text-right">Efectivo</th>
                            <th class="text-right">Transferencias</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(p => `
                            <tr>
                                <td><strong>${App.escapeHtml(p.pv)}</strong></td>
                                <td class="text-right">${p.num_ventas}</td>
                                <td class="text-right text-success">${App.formatMoney(p.total_efectivo)}</td>
                                <td class="text-right text-info">${App.formatMoney(p.total_transferencia)}</td>
                                <td class="text-right"><strong>${App.formatMoney(p.total_general)}</strong></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    // Atajos de fecha
    function hoy() {
        const hoy = new Date().toISOString().split('T')[0];
        document.getElementById('filtro-desde').value = hoy;
        document.getElementById('filtro-hasta').value = hoy;
        cargar();
    }

    function ayer() {
        const ayer = new Date();
        ayer.setDate(ayer.getDate() - 1);
        const str = ayer.toISOString().split('T')[0];
        document.getElementById('filtro-desde').value = str;
        document.getElementById('filtro-hasta').value = str;
        cargar();
    }

    function semana() {
        const hoy = new Date();
        const hace7 = new Date();
        hace7.setDate(hoy.getDate() - 6);
        document.getElementById('filtro-desde').value = hace7.toISOString().split('T')[0];
        document.getElementById('filtro-hasta').value = hoy.toISOString().split('T')[0];
        cargar();
    }

    function mes() {
        const hoy = new Date();
        const inicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        document.getElementById('filtro-desde').value = inicio.toISOString().split('T')[0];
        document.getElementById('filtro-hasta').value = hoy.toISOString().split('T')[0];
        cargar();
    }

    function exportar() {
        Toast.info('Exportando...', 'Descargando CSV');
        const desde = document.getElementById('filtro-desde').value;
        const hasta = document.getElementById('filtro-hasta').value;
        const pvId = document.getElementById('filtro-pv').value;
        const params = new URLSearchParams();
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);
        if (pvId) params.append('pv_id', pvId);
        window.location.href = BASE_URL + 'api/caja_dia.php?accion=exportar&' + params;
    }

    document.addEventListener('DOMContentLoaded', () => {
        cargarCatalogos().then(cargar);
    });

    return { cargar, hoy, ayer, semana, mes, exportar };
})();

window.CajaDia = CajaDia;