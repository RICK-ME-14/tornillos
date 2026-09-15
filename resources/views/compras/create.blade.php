@extends('layouts.app')

@section('title', 'Nueva compra')

@section('content')
    <div class="page-head">
        <h1>NUEVA COMPRA</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> <a href="{{ route('compras.index') }}">Compras</a>
            <span class="sep">/</span> Nueva
        </div>
    </div>

    @if ($errors->any())
        <div class="flash error"><i class="fa-solid fa-circle-exclamation"></i> {{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('compras.store') }}" id="formCompra">
        @csrf
        <div class="form-card" style="max-width:100%">
            <div class="form-grid">
                <div class="form-group">
                    <label>Proveedor <span style="color:#e74c3c">*</span></label>
                    <select name="proveedor_id" class="form-control" required>
                        <option value="">— Selecciona —</option>
                        @foreach($proveedores as $p)
                            <option value="{{ $p->id }}" {{ old('proveedor_id') == $p->id ? 'selected' : '' }}>{{ $p->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha <span style="color:#e74c3c">*</span></label>
                    <input type="date" name="fecha" class="form-control" value="{{ old('fecha', now()->format('Y-m-d')) }}" required>
                </div>
                <div class="form-group full">
                    <label>Observación</label>
                    <input type="text" name="observacion" class="form-control" value="{{ old('observacion') }}" placeholder="Opcional (N° de factura, guía, etc.)">
                </div>
            </div>

            <h3 style="margin:22px 0 12px;color:#4a5560;font-size:14px"><span class="dot" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--brand);margin-right:6px"></span> Productos a comprar</h3>

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">
                <div class="search-box no-ico" style="flex:1;min-width:220px">
                    <select id="prodSelect" class="form-control">
                        <option value="">Selecciona un producto para agregar...</option>
                        @foreach($productos as $prod)
                            <option value="{{ $prod->id }}"
                                data-nombre="{{ $prod->nombre }}"
                                data-codigo="{{ $prod->codigo }}"
                                data-precio="{{ $prod->precio_compra }}"
                                data-stock="{{ $prod->stock }}">
                                {{ $prod->codigo }} · {{ $prod->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="button" class="btn btn-primary btn-sm" style="width:auto" onclick="addLinea()">
                    <i class="fa-solid fa-plus"></i> Agregar
                </button>
            </div>

            <div class="panel-scroll">
            <table class="table" id="tablaItems">
                <thead>
                    <tr><th>Código</th><th>Producto</th><th style="width:110px">Cantidad</th><th style="width:130px">Precio compra</th><th style="width:120px">Subtotal</th><th style="width:40px"></th></tr>
                </thead>
                <tbody id="items">
                    <tr id="sinItems"><td colspan="6" style="text-align:center;color:#9aa3ab;padding:20px">Aún no has agregado productos.</td></tr>
                </tbody>
            </table>
            </div>

            <div style="display:flex;justify-content:flex-end;margin-top:16px">
                <div class="totales" style="min-width:260px">
                    <div class="r"><span>Subtotal</span><span id="t_sub">S/ 0.00</span></div>
                    <div class="r"><span>IGV ({{ rtrim(rtrim(number_format($empresa->igv ?? 18, 2), '0'), '.') }}%)</span><span id="t_igv">S/ 0.00</span></div>
                    <div class="r total"><span>Total</span><span id="t_total">S/ 0.00</span></div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-success" style="width:auto" id="btnGuardar" disabled>
                    <i class="fa-solid fa-floppy-disk"></i> Registrar compra
                </button>
                <a href="{{ route('compras.index') }}" class="btn btn-light">Cancelar</a>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
const IGV = {{ $empresa ? $empresa->tasaIgv() : 0.18 }};
let idx = 0;
const money = v => 'S/ ' + Number(v).toFixed(2);

function addLinea() {
    const sel = document.getElementById('prodSelect');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;

    // Evitar duplicados
    if (document.querySelector(`tr[data-pid="${opt.value}"]`)) {
        alert('Ese producto ya está en la lista.');
        return;
    }

    document.getElementById('sinItems')?.remove();
    const i = idx++;
    const tr = document.createElement('tr');
    tr.dataset.pid = opt.value;
    tr.innerHTML = `
        <td><span class="badge-soft">${opt.dataset.codigo}</span>
            <input type="hidden" name="items[${i}][producto_id]" value="${opt.value}"></td>
        <td>${opt.dataset.nombre}<br><span style="color:#9aa3ab;font-size:11px">stock actual: ${opt.dataset.stock}</span></td>
        <td><input type="number" min="1" value="1" name="items[${i}][cantidad]" class="form-control lc" style="padding:6px" oninput="calc()"></td>
        <td><input type="number" min="0" step="0.01" value="${Number(opt.dataset.precio).toFixed(2)}" name="items[${i}][precio]" class="form-control lp" style="padding:6px" oninput="calc()"></td>
        <td class="sub"><strong>S/ 0.00</strong></td>
        <td><button type="button" class="btn-icon del" onclick="this.closest('tr').remove();calc()"><i class="fa-solid fa-xmark"></i></button></td>`;
    document.getElementById('items').appendChild(tr);
    sel.value = '';
    calc();
}

function calc() {
    let subtotal = 0;
    document.querySelectorAll('#items tr[data-pid]').forEach(tr => {
        const c = Number(tr.querySelector('.lc').value) || 0;
        const p = Number(tr.querySelector('.lp').value) || 0;
        const s = c * p;
        subtotal += s;
        tr.querySelector('.sub').innerHTML = `<strong>${money(s)}</strong>`;
    });
    const igv = subtotal * IGV;
    document.getElementById('t_sub').textContent = money(subtotal);
    document.getElementById('t_igv').textContent = money(igv);
    document.getElementById('t_total').textContent = money(subtotal + igv);
    document.getElementById('btnGuardar').disabled = document.querySelectorAll('#items tr[data-pid]').length === 0;
}
</script>
@endpush
