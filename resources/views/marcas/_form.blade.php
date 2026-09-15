<div class="form-grid">
    <div class="form-group full">
        <label>Nombre <span style="color:#e74c3c">*</span></label>
        <input type="text" name="nombre" class="form-control @error('nombre') invalid @enderror"
               value="{{ old('nombre', $marca->nombre ?? '') }}" required>
        @error('nombre') <div class="invalid-msg">{{ $message }}</div> @enderror
    </div>

    <div class="form-group full">
        <div class="checkbox-row" style="margin:0">
            <input type="checkbox" name="activo" id="activo" value="1"
                   {{ old('activo', $marca->activo ?? true) ? 'checked' : '' }}>
            <label for="activo" style="margin:0">Marca activa</label>
        </div>
    </div>
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary" style="width:auto">
        <i class="fa-solid fa-floppy-disk"></i> Guardar
    </button>
    <a href="{{ route('marcas.index') }}" class="btn btn-light">Cancelar</a>
</div>
