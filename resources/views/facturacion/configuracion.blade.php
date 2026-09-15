@extends('layouts.app')

@section('title', 'Facturación Electrónica')

@section('content')
    <div class="page-head">
        <h1>FACTURACIÓN ELECTRÓNICA</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Configuración <span class="sep">/</span> Facturación Electrónica
        </div>
    </div>

    {{-- ============ BANNER DE ESTADO ============ --}}
    <div class="fe-banner">
        <div class="fe-banner-ico"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        <div class="fe-banner-body">
            <div class="fe-banner-title">
                Facturación Electrónica
                <span class="fe-flag">🇵🇪 Perú</span>
            </div>
            <p class="fe-banner-sub">
                Emisión de comprobantes electrónicos ante <strong>SUNAT</strong> · UBL 2.1 ·
                Boletas, facturas y notas de crédito.
            </p>
            <div class="fe-pills">
                @if($cfg->habilitado)
                    <span class="pill ok"><i class="fa-solid fa-circle-check"></i> Habilitada</span>
                @else
                    <span class="pill err"><i class="fa-solid fa-circle-xmark"></i> Deshabilitada</span>
                @endif
                <span class="pill {{ $cfg->driver === 'none' ? 'warn' : 'ok' }}">Driver: {{ $cfg->driver }}</span>
                <span class="pill {{ $cfg->entorno === 'produccion' ? 'ok' : 'warn' }}">Modo: {{ $cfg->entorno }}</span>
                @if($cfg->certificadoExiste())
                    <span class="pill ok"><i class="fa-solid fa-shield-halved"></i> Certificado OK</span>
                @else
                    <span class="pill err"><i class="fa-solid fa-triangle-exclamation"></i> Certificado no encontrado</span>
                @endif
            </div>
        </div>
        <div class="fe-banner-side">
            <span class="fe-sunat">SUNAT</span>
            <span class="fe-sunat-sub">Comprobantes de Pago Electrónicos</span>
            <button type="button" class="btn btn-light fe-btn-test" onclick="probarConexionSunat(this)">
                <i class="fa-solid fa-bolt"></i> Probar conexión con SUNAT
            </button>
            <div id="fe-test-result" class="fe-test-result"></div>
        </div>
    </div>

    <form method="POST" action="{{ route('facturacion.config.update') }}" class="fe-form">
        @csrf @method('PUT')

        {{-- ============ ESTADO Y MODO ============ --}}
        <div class="form-card fe-section">
            <div class="fe-section-head">
                <div class="fe-section-ico blue"><i class="fa-solid fa-bolt"></i></div>
                <div>
                    <h3>Estado y modo</h3>
                    <p>Activación, forma de emisión y entorno de SUNAT.</p>
                </div>
            </div>

            <label class="fe-switch-row">
                <input type="checkbox" name="habilitado" value="1" {{ $cfg->habilitado ? 'checked' : '' }}>
                <span>
                    <strong>Habilitar facturación electrónica</strong>
                    <small>Si está desactivada, las ventas no generan comprobante ante SUNAT.</small>
                </span>
            </label>

            <label class="fe-switch-row">
                <input type="checkbox" name="emitir_automatico" value="1" {{ $cfg->emitir_automatico ? 'checked' : '' }}>
                <span>
                    <strong>Emitir automáticamente al cerrar la venta</strong>
                    <small>Cada boleta o factura se envía apenas se registra en el POS.</small>
                </span>
            </label>

            <div class="form-grid" style="margin-top:16px">
                <div class="form-group">
                    <label>Driver de emisión</label>
                    <select name="driver" class="form-control">
                        <option value="none" {{ $cfg->driver === 'none' ? 'selected' : '' }}>Ninguno (no emite, deja pendiente)</option>
                        <option value="greenter" {{ $cfg->driver === 'greenter' ? 'selected' : '' }}>Greenter (SUNAT · UBL 2.1)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Entorno SUNAT</label>
                    <select name="entorno" class="form-control">
                        <option value="beta" {{ $cfg->entorno === 'beta' ? 'selected' : '' }}>Beta (homologación / pruebas)</option>
                        <option value="produccion" {{ $cfg->entorno === 'produccion' ? 'selected' : '' }}>Producción</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Declaración de boletas</label>
                    <select name="modo_boleta" class="form-control">
                        <option value="individual" {{ $cfg->modo_boleta === 'individual' ? 'selected' : '' }}>Individual (cada boleta se envía a SUNAT al emitirse)</option>
                        <option value="resumen" {{ $cfg->modo_boleta === 'resumen' ? 'selected' : '' }}>Por Resumen Diario (RC) — recomendado para producción</option>
                    </select>
                    <div class="help">En modo Resumen, las boletas se numeran y se declaran juntas una vez al día. Las facturas siempre se envían individualmente.</div>
                </div>
            </div>
        </div>

        {{-- ============ DATOS DEL EMISOR ============ --}}
        <div class="form-card fe-section">
            <div class="fe-section-head">
                <div class="fe-section-ico teal"><i class="fa-solid fa-building"></i></div>
                <div>
                    <h3>Datos del emisor</h3>
                    <p>Aparecen en el comprobante electrónico.</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>RUC <span style="color:#e74c3c">*</span></label>
                    <input type="text" name="ruc" class="form-control" value="{{ old('ruc', $cfg->ruc) }}" maxlength="11" placeholder="20000000001">
                    @error('ruc') <div class="invalid-msg">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label>Razón social <span style="color:#e74c3c">*</span></label>
                    <input type="text" name="razon_social" class="form-control" value="{{ old('razon_social', $cfg->razon_social) }}">
                </div>
                <div class="form-group">
                    <label>Nombre comercial</label>
                    <input type="text" name="nombre_comercial" class="form-control" value="{{ old('nombre_comercial', $cfg->nombre_comercial) }}">
                </div>
                <div class="form-group">
                    <label>Dirección fiscal</label>
                    <input type="text" name="direccion_fiscal" class="form-control" value="{{ old('direccion_fiscal', $cfg->direccion_fiscal) }}">
                </div>
                <div class="form-group">
                    <label>Ubigeo</label>
                    <input type="text" name="ubigeo" class="form-control" value="{{ old('ubigeo', $cfg->ubigeo) }}" placeholder="150101">
                    <div class="help">Código de 6 dígitos (departamento+provincia+distrito).</div>
                </div>
                <div class="form-group">
                    <label>Departamento</label>
                    <input type="text" name="departamento" class="form-control" value="{{ old('departamento', $cfg->departamento) }}">
                </div>
                <div class="form-group">
                    <label>Provincia</label>
                    <input type="text" name="provincia" class="form-control" value="{{ old('provincia', $cfg->provincia) }}">
                </div>
                <div class="form-group">
                    <label>Distrito</label>
                    <input type="text" name="distrito" class="form-control" value="{{ old('distrito', $cfg->distrito) }}">
                </div>
                <div class="form-group">
                    <label>Serie de Boleta</label>
                    <input type="text" name="serie_boleta" class="form-control" value="{{ old('serie_boleta', $cfg->serie_boleta) }}" maxlength="4" placeholder="B001">
                </div>
                <div class="form-group">
                    <label>Serie de Factura</label>
                    <input type="text" name="serie_factura" class="form-control" value="{{ old('serie_factura', $cfg->serie_factura) }}" maxlength="4" placeholder="F001">
                </div>
                <div class="form-group">
                    <label>Serie N. Crédito (Boleta)</label>
                    <input type="text" name="serie_nota_credito" class="form-control" value="{{ old('serie_nota_credito', $cfg->serie_nota_credito) }}" maxlength="4" placeholder="BC01">
                </div>
                <div class="form-group">
                    <label>Serie N. Crédito (Factura)</label>
                    <input type="text" name="serie_nc_factura" class="form-control" value="{{ old('serie_nc_factura', $cfg->serie_nc_factura) }}" maxlength="4" placeholder="FC01">
                </div>
            </div>
        </div>

        {{-- ============ CREDENCIALES SUNAT ============ --}}
        <div class="form-card fe-section">
            <div class="fe-section-head">
                <div class="fe-section-ico amber"><i class="fa-solid fa-key"></i></div>
                <div>
                    <h3>Credenciales SUNAT</h3>
                    <p>Clave SOL y certificado digital.</p>
                </div>
            </div>

            <div class="fe-note">
                <i class="fa-solid fa-circle-info"></i>
                En <strong>beta</strong> puedes usar RUC <strong>20000000001</strong> con usuario y clave <strong>MODDATOS</strong>.
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Usuario Clave SOL</label>
                    <input type="text" name="sol_user" class="form-control" value="{{ old('sol_user', $cfg->sol_user) }}" autocomplete="off" placeholder="MODDATOS">
                </div>
                <div class="form-group">
                    <label>Clave SOL</label>
                    <input type="password" name="sol_pass" class="form-control" value="" autocomplete="new-password"
                           placeholder="{{ $cfg->sol_pass ? '•••••••• (guardada)' : 'Ingresa la clave SOL' }}">
                    <div class="help">Déjalo vacío para conservar la clave actual.</div>
                </div>
                <div class="form-group full">
                    <label>Ruta del certificado (.pem)</label>
                    <input type="text" name="certificado_ruta" class="form-control" value="{{ old('certificado_ruta', $cfg->certificado_ruta) }}"
                           placeholder="C:\SAAS\saas-ventas-inventario\storage\facturacion\pe\certificate.pem">
                    @if($cfg->certificado_ruta && ! $cfg->certificadoExiste())
                        <div class="invalid-msg"><i class="fa-solid fa-triangle-exclamation"></i> No se encontró el certificado en la ruta indicada.</div>
                    @elseif($cfg->certificadoExiste())
                        <div class="help" style="color:#1e9e5a"><i class="fa-solid fa-circle-check"></i> Certificado encontrado.</div>
                    @endif
                </div>
                <div class="form-group">
                    <label>Clave del certificado</label>
                    <input type="password" name="certificado_pass" class="form-control" value="" autocomplete="new-password"
                           placeholder="{{ $cfg->certificado_pass ? '•••••••• (guardada)' : 'Opcional' }}">
                    <div class="help">Déjalo vacío para conservar la actual.</div>
                </div>
            </div>
        </div>

        <div class="fe-actions">
            <a href="{{ route('dashboard') }}" class="btn btn-light" style="width:auto"><i class="fa-solid fa-arrow-left"></i> Volver</a>
            <button type="submit" class="btn btn-primary" style="width:auto"><i class="fa-solid fa-circle-check"></i> Guardar configuración</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
function probarConexionSunat(btn) {
    const box = document.getElementById('fe-test-result');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Probando…';
    box.className = 'fe-test-result';
    box.textContent = '';

    fetch('{{ route('facturacion.probar') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json().then(d => ({ ok: r.ok, d })))
    .then(({ ok, d }) => {
        box.className = 'fe-test-result ' + (d.exito ? 'ok' : 'err');
        box.innerHTML = (d.exito ? '<i class="fa-solid fa-circle-check"></i> ' : '<i class="fa-solid fa-circle-xmark"></i> ') + (d.mensaje || '');
    })
    .catch(() => {
        box.className = 'fe-test-result err';
        box.textContent = 'No se pudo contactar al servidor.';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = original;
    });
}
</script>
@endpush
