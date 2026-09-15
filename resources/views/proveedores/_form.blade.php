<div class="form-grid">
    <div class="form-group full">
        <label>Nombre / Razón social <span style="color:#e74c3c">*</span></label>
        <input type="text" name="nombre" class="form-control @error('nombre') invalid @enderror"
               value="{{ old('nombre', $proveedor->nombre) }}" required>
        @error('nombre') <div class="invalid-msg">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label>RUC</label>
        <input type="text" name="ruc" class="form-control" value="{{ old('ruc', $proveedor->ruc) }}">
    </div>

    <div class="form-group">
        <label>Persona de contacto</label>
        <input type="text" name="contacto" class="form-control" value="{{ old('contacto', $proveedor->contacto) }}">
    </div>

    <div class="form-group">
        <label>Teléfono</label>
        <input type="text" name="telefono" class="form-control" value="{{ old('telefono', $proveedor->telefono) }}">
    </div>

    <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control @error('email') invalid @enderror" value="{{ old('email', $proveedor->email) }}">
        @error('email') <div class="invalid-msg">{{ $message }}</div> @enderror
    </div>

    <div class="form-group full">
        <label>Dirección</label>
        <input type="text" name="direccion" class="form-control" value="{{ old('direccion', $proveedor->direccion) }}">
    </div>

    <div class="form-group full">
        <div class="checkbox-row" style="margin:0">
            <input type="checkbox" name="activo" id="activo" value="1" {{ old('activo', $proveedor->activo ?? true) ? 'checked' : '' }}>
            <label for="activo" style="margin:0">Proveedor activo</label>
        </div>
    </div>
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary" style="width:auto"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
    <a href="{{ route('proveedores.index') }}" class="btn btn-light">Cancelar</a>
</div>
