@extends('layouts.app')

@section('title', 'Ajustes de stock')

@section('content')
    <div class="page-head">
        <h1>AJUSTES DE STOCK</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Inventario <span class="sep">/</span> Ajustes
        </div>
    </div>

    @if ($errors->any())
        <div class="flash error"><i class="fa-solid fa-circle-exclamation"></i> {{ $errors->first() }}</div>
    @endif

    <div class="grid-2">
        {{-- Formulario --}}
        <div class="form-card" style="max-width:100%">
            <h3 style="margin:0 0 16px;color:#4a5560;font-size:15px"><i class="fa-solid fa-sliders" style="color:var(--brand)"></i> Nuevo ajuste</h3>
            <form method="POST" action="{{ route('inventario.ajustes.guardar') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Producto <span style="color:#e74c3c">*</span></label>
                        <select name="producto_id" id="producto_id" class="form-control" required onchange="mostrarStock()">
                            <option value="">— Selecciona —</option>
                            @foreach($productos as $p)
                                <option value="{{ $p->id }}" data-stock="{{ $p->stock }}" data-unidad="{{ $p->unidad }}"
                                    {{ old('producto_id') == $p->id ? 'selected' : '' }}>
                                    {{ $p->codigo }} · {{ $p->nombre }}
                                </option>
                            @endforeach
                        </select>
                        <div class="help" id="stockActual">Stock actual: —</div>
                    </div>

                    <div class="form-group">
                        <label>Tipo de ajuste <span style="color:#e74c3c">*</span></label>
                        <select name="modo" class="form-control">
                            <option value="set">Fijar stock exacto (conteo físico)</option>
                            <option value="add">Sumar al stock (entrada)</option>
                            <option value="sub">Restar del stock (merma/robo)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Cantidad <span style="color:#e74c3c">*</span></label>
                        <input type="number" name="cantidad" min="0" class="form-control" value="{{ old('cantidad', 0) }}" required>
                    </div>

                    <div class="form-group full">
                        <label>Motivo <span style="color:#e74c3c">*</span></label>
                        <select name="motivo" class="form-control">
                            <option value="CONTEO_FISICO">Conteo físico</option>
                            <option value="MERMA">Merma / vencimiento</option>
                            <option value="ROBO">Robo / pérdida</option>
                            <option value="DEVOLUCION">Devolución</option>
                            <option value="CORRECCION">Corrección de error</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" style="width:auto"><i class="fa-solid fa-floppy-disk"></i> Aplicar ajuste</button>
                </div>
            </form>
        </div>

        {{-- Últimos ajustes --}}
        <div class="panel">
            <h3><span class="dot"></span> Últimos ajustes</h3>
            <div class="panel-scroll">
            <table class="table">
                <thead><tr><th>Fecha</th><th>Producto</th><th>Motivo</th><th>Antes</th><th>Después</th></tr></thead>
                <tbody>
                @forelse($ultimos as $m)
                    <tr>
                        <td>{{ $m->created_at->format('d/m H:i') }}</td>
                        <td>{{ $m->producto->nombre ?? '—' }}</td>
                        <td><span class="badge-soft">{{ $m->motivo }}</span></td>
                        <td>{{ $m->stock_anterior }}</td>
                        <td><strong>{{ $m->stock_nuevo }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:#9aa3ab;padding:20px">Sin ajustes aún.</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
function mostrarStock() {
    const sel = document.getElementById('producto_id');
    const opt = sel.options[sel.selectedIndex];
    const box = document.getElementById('stockActual');
    if (opt && opt.value) {
        box.innerHTML = `Stock actual: <strong>${opt.dataset.stock} ${opt.dataset.unidad}</strong>`;
    } else {
        box.textContent = 'Stock actual: —';
    }
}
mostrarStock();
</script>
@endpush
