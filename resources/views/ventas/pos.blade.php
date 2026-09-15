@extends('layouts.app')

@section('title', 'Punto de Venta')

@section('content')
    <div class="page-head">
        <h1>PUNTO DE VENTA</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Ventas <span class="sep">/</span> POS
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

        {{-- ===== Derecha: carrito ===== --}}
        <div class="cart">
            <div class="cart-head">
                <i class="fa-solid fa-cart-shopping"></i> Carrito
                <span class="count" id="count">0</span>
            </div>

            <div class="cart-items" id="items">
                <div class="cart-empty" id="empty">
                    <i class="fa-solid fa-basket-shopping" style="font-size:34px;display:block;margin-bottom:10px;opacity:.5"></i>
                    Agrega productos para empezar
                </div>
            </div>

            <div class="cart-foot">
                <div class="fields">
                    <div class="full">
                        <label>Cliente</label>
                        <select id="cliente_id">
                            <option value="">Cliente varios</option>
                            @foreach($clientes as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Comprobante</label>
                        <select id="tipo_comprobante">
                            <option value="TICKET">Ticket</option>
                            <option value="BOLETA">Boleta</option>
                            <option value="FACTURA">Factura</option>
                        </select>
                    </div>
                    <div>
                        <label>Descuento (S/)</label>
                        <input type="number" id="descuento" min="0" step="0.01" value="0">
                    </div>
                    <div class="full">
                        <label>Método de pago</label>
                        <input type="hidden" id="metodo_pago" value="EFECTIVO">
                        <div class="pay-chips">
                            <div class="pay-chip active" data-val="EFECTIVO"><i class="fa-solid fa-money-bill-wave"></i> Efectivo</div>
                            <div class="pay-chip" data-val="TARJETA"><i class="fa-regular fa-credit-card"></i> Tarjeta</div>
                            <div class="pay-chip" data-val="YAPE"><i class="fa-solid fa-mobile-screen-button"></i> Yape</div>
                            <div class="pay-chip" data-val="TRANSFERENCIA"><i class="fa-solid fa-building-columns"></i> Transferencia</div>
                        </div>
                    </div>
                    <div class="full" id="cashWrap">
                        <label>Efectivo recibido (S/)</label>
                        <input type="number" id="efectivo" min="0" step="0.01" placeholder="0.00">
                        <div class="vuelto-row"><span>Vuelto</span><span id="vuelto">S/ 0.00</span></div>
                    </div>
                </div>

                <div class="totales">
                    <div class="r"><span>Subtotal</span><span id="t_sub">S/ 0.00</span></div>
                    <div class="r"><span>Descuento</span><span id="t_desc">S/ 0.00</span></div>
                    <div class="r"><span>IGV ({{ rtrim(rtrim(number_format($empresa->igv ?? 18, 2), '0'), '.') }}%)</span><span id="t_igv">S/ 0.00</span></div>
                    <div class="r total"><span>Total</span><span id="t_total">S/ 0.00</span></div>
                </div>

                <button class="btn-cobrar" id="cobrar" disabled>
                    <i class="fa-solid fa-circle-check"></i> Cobrar
                    <span id="btn_total"></span>
                </button>
                <div class="pos-msg" id="msg"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
const IGV = {{ $empresa ? $empresa->tasaIgv() : 0.18 }};
const URL_BUSCAR = "{{ route('ventas.buscar') }}";
const URL_STORE  = "{{ route('ventas.store') }}";
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

let cart = [];   // {id, nombre, precio, stock, cantidad}
const money = v => 'S/ ' + Number(v).toFixed(2);
const round2 = v => Math.round((v + Number.EPSILON) * 100) / 100;

/* Etiqueta de afectación IGV (vacía cuando el producto es gravado) */
const AFEC_TXT = {'20':'Exonerado', '30':'Inafecto'};

/* Paleta para el avatar del producto (color estable por nombre) */
const AVA = ['#dd8c07','#119084','#6247d8','#2073b4','#c2456b','#0f8579','#e0930f','#4a5cc4'];
function avaColor(txt){
    let h = 0;
    for (let i = 0; i < txt.length; i++) h = (h * 31 + txt.charCodeAt(i)) % AVA.length;
    return AVA[h];
}
function inicial(txt){ return (txt.trim()[0] || '?').toUpperCase(); }

/* ---- Buscar productos ---- */
const grid = document.getElementById('grid');
let timer = null;

async function buscar(q = '') {
    const res = await fetch(`${URL_BUSCAR}?q=${encodeURIComponent(q)}`, {headers:{'X-Requested-With':'XMLHttpRequest'}});
    const data = await res.json();
    grid.innerHTML = data.length ? '' : '<div style="color:#9aa3ab;padding:24px;grid-column:1/-1;text-align:center">Sin resultados.</div>';
    data.forEach(p => {
        const sin = p.stock <= 0;
        const min = p.stock_minimo ?? 5;
        const stkClass = sin ? 'out' : (p.stock <= min ? 'low' : 'ok');
        const el = document.createElement('div');
        el.className = 'pos-prod' + (sin ? ' sinstock' : '');
        el.innerHTML = `
            <div class="pp-top">
                <div class="pp-ava" style="background:${avaColor(p.nombre)}">${inicial(p.nombre)}</div>
                <span class="stk ${stkClass}">${sin ? 'Sin stock' : 'stock ' + p.stock}</span>
            </div>
            <div class="code">${p.codigo}</div>
            <div class="name">${p.nombre}</div>
            <div class="pp-bot">
                <div class="price">${money(p.precio_venta)}</div>
                <span class="pp-add"><i class="fa-solid fa-plus"></i></span>
            </div>`;
        if (!sin) el.onclick = () => addItem(p);
        grid.appendChild(el);
    });
}

document.getElementById('buscador').addEventListener('input', e => {
    clearTimeout(timer);
    timer = setTimeout(() => buscar(e.target.value.trim()), 250);
});

/* ---- Método de pago (chips) ---- */
function toggleCash() {
    const esEfectivo = document.getElementById('metodo_pago').value === 'EFECTIVO';
    document.getElementById('cashWrap').style.display = esEfectivo ? 'block' : 'none';
}
document.querySelectorAll('.pay-chip').forEach(chip => {
    chip.addEventListener('click', () => {
        document.querySelectorAll('.pay-chip').forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        document.getElementById('metodo_pago').value = chip.dataset.val;
        toggleCash();
        render();
    });
});

/* ---- Carrito ---- */
function addItem(p) {
    const found = cart.find(i => i.id === p.id);
    if (found) {
        if (found.cantidad < p.stock) found.cantidad++;
    } else {
        cart.push({id:p.id, nombre:p.nombre, precio:Number(p.precio_venta), stock:p.stock,
                   afectacion:(p.tipo_afectacion_igv || '10'), cantidad:1});
    }
    render();
}
function setQty(id, val) {
    const it = cart.find(i => i.id === id);
    if (!it) return;
    val = Math.max(1, Math.min(val, it.stock));
    it.cantidad = val;
    render();
}
function remove(id) { cart = cart.filter(i => i.id !== id); render(); }

function render() {
    const box = document.getElementById('items');
    const empty = document.getElementById('empty');
    box.querySelectorAll('.cart-row').forEach(n => n.remove());

    if (cart.length === 0) {
        empty.style.display = 'block';
    } else {
        empty.style.display = 'none';
        cart.forEach(it => {
            const row = document.createElement('div');
            row.className = 'cart-row';
            row.innerHTML = `
                <div class="info"><div class="n">${it.nombre}</div><div class="p">${money(it.precio)} c/u${
                    AFEC_TXT[it.afectacion] ? ' · <span style="color:#7a5cf0">'+AFEC_TXT[it.afectacion]+'</span>' : ''}</div></div>
                <div class="qty">
                    <button type="button" onclick="setQty(${it.id}, ${it.cantidad-1})">−</button>
                    <input type="number" value="${it.cantidad}" min="1" max="${it.stock}"
                           onchange="setQty(${it.id}, parseInt(this.value)||1)">
                    <button type="button" onclick="setQty(${it.id}, ${it.cantidad+1})">+</button>
                </div>
                <div class="line">${money(it.precio*it.cantidad)}</div>
                <button type="button" class="rm" onclick="remove(${it.id})"><i class="fa-solid fa-xmark"></i></button>`;
            box.appendChild(row);
        });
    }

    const subtotal = cart.reduce((s,i) => s + i.precio*i.cantidad, 0);
    let desc = Math.max(0, Number(document.getElementById('descuento').value) || 0);
    desc = Math.min(desc, subtotal);
    const base = round2(subtotal - desc);

    // El descuento se prorratea sobre todo, pero solo lo gravado paga IGV.
    const factor = subtotal > 0 ? base / subtotal : 1;
    const baseGravada = cart.reduce(
        (s,i) => s + (i.afectacion === '10' ? round2(i.precio*i.cantidad*factor) : 0), 0);
    const igv = round2(baseGravada * IGV);
    const total = round2(base + igv);

    document.getElementById('count').textContent = cart.reduce((s,i)=>s+i.cantidad,0);
    document.getElementById('t_sub').textContent = money(subtotal);
    document.getElementById('t_desc').textContent = money(desc);
    document.getElementById('t_igv').textContent = money(igv);
    document.getElementById('t_total').textContent = money(total);
    document.getElementById('btn_total').textContent = money(total);

    // Efectivo recibido / vuelto
    const esEfectivo = document.getElementById('metodo_pago').value === 'EFECTIVO';
    const efecStr = document.getElementById('efectivo').value;
    const efec = Number(efecStr) || 0;
    const vuelto = (esEfectivo && efecStr !== '' && efec >= total) ? (efec - total) : 0;
    document.getElementById('vuelto').textContent = money(vuelto);
    const efectivoInsuficiente = esEfectivo && efecStr !== '' && efec < total;

    document.getElementById('cobrar').disabled = cart.length === 0 || efectivoInsuficiente;
}
document.getElementById('descuento').addEventListener('input', render);
document.getElementById('efectivo').addEventListener('input', render);

/* ---- Cobrar ---- */
document.getElementById('cobrar').addEventListener('click', async () => {
    const btn = document.getElementById('cobrar');
    const msg = document.getElementById('msg');
    msg.className = 'pos-msg';
    btn.disabled = true;

    const metodoSel = document.getElementById('metodo_pago').value;
    const efecVal = document.getElementById('efectivo').value;
    const payload = {
        cliente_id: document.getElementById('cliente_id').value || null,
        tipo_comprobante: document.getElementById('tipo_comprobante').value,
        metodo_pago: metodoSel,
        descuento: Number(document.getElementById('descuento').value) || 0,
        efectivo_recibido: (metodoSel === 'EFECTIVO' && efecVal !== '') ? Number(efecVal) : null,
        items: cart.map(i => ({producto_id:i.id, cantidad:i.cantidad})),
    };

    try {
        const res = await fetch(URL_STORE, {
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!res.ok) {
            msg.textContent = data.message || 'No se pudo registrar la venta.';
            msg.className = 'pos-msg err';
            btn.disabled = false;
            return;
        }
        window.location = data.redirect;
    } catch (err) {
        msg.textContent = 'Error de conexión. Intenta de nuevo.';
        msg.className = 'pos-msg err';
        btn.disabled = false;
    }
});

/* Init */
toggleCash();
buscar('');
</script>
@endpush
