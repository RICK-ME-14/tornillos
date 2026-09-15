@extends('layouts.app')

@section('title', 'Punto de Cotización')

@section('content')
    <div class="page-head">
        <h1>PUNTO DE COTIZACIÓN</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Ventas <span class="sep">/</span> Cotizar
        </div>
    </div>

    <div class="pos">
        {{-- ===== Izquierda: productos ===== --}}
        <div class="pos-left">
            <div class="pos-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="buscador" placeholder="Buscar producto por nombre o código..." autofocus autocomplete="off">
            </div>
            <div class="pos-grid" id="grid"></div>
        </div>

        {{-- ===== Derecha: la propuesta ===== --}}
        <div class="cart">
            <div class="cart-head">
                <i class="fa-solid fa-file-invoice"></i> Cotización
                <span class="count" id="count">0</span>
            </div>

            <div class="cart-items" id="items">
                <div class="cart-empty" id="empty">
                    <i class="fa-solid fa-file-circle-plus" style="font-size:34px;display:block;margin-bottom:10px;opacity:.5"></i>
                    Agrega productos para cotizar
                </div>
            </div>

            <div class="cart-foot">
                <div class="fields">
                    <div class="full">
                        <label>Cliente</label>
                        <select id="cliente_id">
                            <option value="">Sin cliente asignado</option>
                            @foreach($clientes as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Válida hasta</label>
                        <input type="date" id="valida_hasta"
                               value="{{ now()->addDays($dias)->toDateString() }}"
                               min="{{ today()->toDateString() }}">
                    </div>
                    <div>
                        <label>Descuento (S/)</label>
                        <input type="number" id="descuento" min="0" step="0.01" value="0">
                    </div>
                    <div class="full">
                        <label>Observaciones</label>
                        <input type="text" id="observacion" maxlength="500"
                               placeholder="Condiciones de entrega, forma de pago…">
                    </div>
                </div>

                <div class="totales">
                    <div class="r"><span>Subtotal</span><span id="t_sub">S/ 0.00</span></div>
                    <div class="r"><span>Descuento</span><span id="t_desc">S/ 0.00</span></div>
                    <div class="r"><span>IGV ({{ rtrim(rtrim(number_format($empresa->igv ?? 18, 2), '0'), '.') }}%)</span><span id="t_igv">S/ 0.00</span></div>
                    <div class="r total"><span>Total</span><span id="t_total">S/ 0.00</span></div>
                </div>

                <button class="btn-cobrar" id="guardar" disabled>
                    <i class="fa-solid fa-file-arrow-down"></i> Generar cotización
                    <span id="btn_total"></span>
                </button>
                <div class="pos-msg" id="msg"></div>

                <p class="cot-nota">
                    <i class="fa-solid fa-circle-info"></i>
                    Una cotización no descuenta stock. El inventario se mueve solo
                    al convertirla en venta.
                </p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
const IGV = {{ $empresa ? $empresa->tasaIgv() : 0.18 }};
const URL_BUSCAR = "{{ route('ventas.buscar') }}";
const URL_STORE  = "{{ route('cotizaciones.store') }}";
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

let cart = [];
const money = v => 'S/ ' + Number(v).toFixed(2);
const round2 = v => Math.round((v + Number.EPSILON) * 100) / 100;

const AFEC_TXT = {'20':'Exonerado', '30':'Inafecto'};
const AVA = ['#dd8c07','#119084','#6247d8','#2073b4','#c2456b','#0f8579','#e0930f','#4a5cc4'];
function avaColor(txt){ let h=0; for(let i=0;i<txt.length;i++) h=(h*31+txt.charCodeAt(i))%AVA.length; return AVA[h]; }
function inicial(txt){ return (txt.trim()[0] || '?').toUpperCase(); }

/* ---- Buscar productos ---- */
const grid = document.getElementById('grid');
let timer = null;

async function buscar(q = '') {
    const res = await fetch(`${URL_BUSCAR}?q=${encodeURIComponent(q)}`, {headers:{'X-Requested-With':'XMLHttpRequest'}});
    const data = await res.json();
    grid.innerHTML = data.length ? '' : '<div style="color:#9aa3ab;padding:24px;grid-column:1/-1;text-align:center">Sin resultados.</div>';
    data.forEach(p => {
        /* Se puede cotizar aunque no haya stock: es una propuesta, no una
           entrega. El stock se valida al convertir en venta. */
        const min = p.stock_minimo ?? 5;
        const stkClass = p.stock <= 0 ? 'out' : (p.stock <= min ? 'low' : 'ok');
        const el = document.createElement('div');
        el.className = 'pos-prod';
        el.innerHTML = `
            <div class="pp-top">
                <div class="pp-ava" style="background:${avaColor(p.nombre)}">${inicial(p.nombre)}</div>
                <span class="stk ${stkClass}">${p.stock <= 0 ? 'Sin stock' : 'stock ' + p.stock}</span>
            </div>
            <div class="code">${p.codigo}</div>
            <div class="name">${p.nombre}</div>
            <div class="pp-bot">
                <div class="price">${money(p.precio_venta)}</div>
                <span class="pp-add"><i class="fa-solid fa-plus"></i></span>
            </div>`;
        el.onclick = () => addItem(p);
        grid.appendChild(el);
    });
}

document.getElementById('buscador').addEventListener('input', e => {
    clearTimeout(timer);
    timer = setTimeout(() => buscar(e.target.value.trim()), 250);
});

/* ---- Líneas ---- */
function addItem(p) {
    const found = cart.find(i => i.id === p.id);
    if (found) found.cantidad++;
    else cart.push({id:p.id, nombre:p.nombre, precio:Number(p.precio_venta),
                    afectacion:(p.tipo_afectacion_igv || '10'), cantidad:1});
    render();
}
function setQty(id, val) {
    const it = cart.find(i => i.id === id);
    if (!it) return;
    it.cantidad = Math.max(1, val || 1);
    render();
}
function remove(id) { cart = cart.filter(i => i.id !== id); render(); }

function render() {
    const box = document.getElementById('items');
    const empty = document.getElementById('empty');
    box.querySelectorAll('.cart-row').forEach(n => n.remove());

    empty.style.display = cart.length === 0 ? 'block' : 'none';
    cart.forEach(it => {
        const row = document.createElement('div');
        row.className = 'cart-row';
        row.innerHTML = `
            <div class="info"><div class="n">${it.nombre}</div><div class="p">${money(it.precio)} c/u${
                AFEC_TXT[it.afectacion] ? ' · <span style="color:#7a5cf0">'+AFEC_TXT[it.afectacion]+'</span>' : ''}</div></div>
            <div class="qty">
                <button type="button" onclick="setQty(${it.id}, ${it.cantidad-1})">−</button>
                <input type="number" value="${it.cantidad}" min="1"
                       onchange="setQty(${it.id}, parseInt(this.value)||1)">
                <button type="button" onclick="setQty(${it.id}, ${it.cantidad+1})">+</button>
            </div>
            <div class="line">${money(it.precio*it.cantidad)}</div>
            <button type="button" class="rm" onclick="remove(${it.id})"><i class="fa-solid fa-xmark"></i></button>`;
        box.appendChild(row);
    });

    const subtotal = cart.reduce((s,i) => s + i.precio*i.cantidad, 0);
    let desc = Math.max(0, Number(document.getElementById('descuento').value) || 0);
    desc = Math.min(desc, subtotal);
    const base = round2(subtotal - desc);
    const factor = subtotal > 0 ? base / subtotal : 1;
    const baseGravada = cart.reduce((s,i) => s + (i.afectacion === '10' ? round2(i.precio*i.cantidad*factor) : 0), 0);
    const igv = round2(baseGravada * IGV);
    const total = round2(base + igv);

    document.getElementById('count').textContent = cart.reduce((s,i)=>s+i.cantidad,0);
    document.getElementById('t_sub').textContent = money(subtotal);
    document.getElementById('t_desc').textContent = money(desc);
    document.getElementById('t_igv').textContent = money(igv);
    document.getElementById('t_total').textContent = money(total);
    document.getElementById('btn_total').textContent = money(total);
    document.getElementById('guardar').disabled = cart.length === 0;
}
document.getElementById('descuento').addEventListener('input', render);

/* ---- Guardar ---- */
document.getElementById('guardar').addEventListener('click', async () => {
    const btn = document.getElementById('guardar');
    const msg = document.getElementById('msg');
    btn.disabled = true;
    msg.className = 'pos-msg';
    msg.textContent = '';

    const payload = {
        cliente_id: document.getElementById('cliente_id').value || null,
        valida_hasta: document.getElementById('valida_hasta').value,
        descuento: Number(document.getElementById('descuento').value) || 0,
        observacion: document.getElementById('observacion').value || null,
        items: cart.map(i => ({producto_id: i.id, cantidad: i.cantidad})),
    };

    try {
        const res = await fetch(URL_STORE, {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF,
                      'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json'},
            body: JSON.stringify(payload),
        });
        const d = await res.json();
        if (!res.ok) throw new Error(d.message || 'No se pudo generar la cotización.');
        window.location = d.redirect;
    } catch (e) {
        msg.className = 'pos-msg err';
        msg.textContent = e.message;
        btn.disabled = false;
    }
});

buscar();
</script>
@endpush
