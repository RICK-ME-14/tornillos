@extends('layouts.app')

@section('title', 'Datos de la Empresa')

@section('content')
    <div class="page-head">
        <h1>DATOS DE LA EMPRESA</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Configuración <span class="sep">/</span> Empresa
        </div>
    </div>

    <div class="form-card" style="max-width:820px">
        <form method="POST" action="{{ route('configuracion.empresa.update') }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="form-grid">
                <div class="form-group full">
                    <label>Nombre / Razón social <span style="color:#e74c3c">*</span></label>
                    <input type="text" name="nombre" class="form-control @error('nombre') invalid @enderror"
                           value="{{ old('nombre', $empresaCfg->nombre) }}" required>
                    @error('nombre') <div class="invalid-msg">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label>RUC</label>
                    <input type="text" name="ruc" class="form-control" value="{{ old('ruc', $empresaCfg->ruc) }}">
                </div>

                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" class="form-control" value="{{ old('telefono', $empresaCfg->telefono) }}">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control @error('email') invalid @enderror" value="{{ old('email', $empresaCfg->email) }}">
                    @error('email') <div class="invalid-msg">{{ $message }}</div> @enderror
                </div>

                <div class="form-group full">
                    <label>Dirección</label>
                    <input type="text" name="direccion" class="form-control" value="{{ old('direccion', $empresaCfg->direccion) }}">
                </div>

                <div class="form-group">
                    <label>Símbolo de moneda <span style="color:#e74c3c">*</span></label>
                    <input type="text" name="moneda" class="form-control" value="{{ old('moneda', $empresaCfg->moneda) }}" maxlength="5" required>
                    <div class="help">Ej: S/, $, €, Bs</div>
                </div>

                <div class="form-group">
                    <label>IGV / Impuesto (%) <span style="color:#e74c3c">*</span></label>
                    <input type="number" name="igv" step="0.01" min="0" max="100"
                           class="form-control @error('igv') invalid @enderror" value="{{ old('igv', $empresaCfg->igv) }}" required>
                    <div class="help">Se aplica en ventas y compras.</div>
                    @error('igv') <div class="invalid-msg">{{ $message }}</div> @enderror
                </div>

                <div class="form-group full">
                    <label>Logo</label>
                    @if($empresaCfg->logo)
                        <div style="margin-bottom:8px">
                            <img src="{{ asset('storage/'.$empresaCfg->logo) }}" class="thumb" style="width:80px;height:80px" alt="Logo">
                        </div>
                    @endif
                    <input type="file" name="logo" accept="image/*" class="form-control @error('logo') invalid @enderror">
                    <div class="help">JPG o PNG, máx 2 MB. Requiere <code>php artisan storage:link</code>.</div>
                    @error('logo') <div class="invalid-msg">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" style="width:auto"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection
