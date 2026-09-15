<div class="form-grid">
    <div class="form-group full">
        <label>Nombre / Razón social <span style="color:#e74c3c">*</span></label>
        <input type="text" name="nombre" class="form-control @error('nombre') invalid @enderror"
               value="{{ old('nombre', $cliente->nombre) }}" required>
        @error('nombre') <div class="invalid-msg">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label>Tipo de documento</label>
        <select name="tipo_documento" class="form-control">
            @foreach(['DNI'=>'DNI','RUC'=>'RUC','CE'=>'Carné de extranjería','PASAPORTE'=>'Pasaporte'] as $v=>$t)
                <option value="{{ $v }}" {{ old('tipo_documento', $cliente->tipo_documento) === $v ? 'selected' : '' }}>{{ $t }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>N° de documento</label>
        <input type="text" name="numero_documento" class="form-control" value="{{ old('numero_documento', $cliente->numero_documento) }}">
    </div>

    <div class="form-group">
        <label>Teléfono</label>
        <input type="text" name="telefono" class="form-control" value="{{ old('telefono', $cliente->telefono) }}">
    </div>

    <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control @error('email') invalid @enderror" value="{{ old('email', $cliente->email) }}">
        @error('email') <div class="invalid-msg">{{ $message }}</div> @enderror
    </div>

    <div class="form-group full">
        <label>Dirección</label>
        <input type="text" name="direccion" class="form-control" value="{{ old('direccion', $cliente->direccion) }}">
    </div>

    <div class="form-group full">
        <div class="checkbox-row" style="margin:0">
            <input type="checkbox" name="activo" id="activo" value="1" {{ old('activo', $cliente->activo ?? true) ? 'checked' : '' }}>
            <label for="activo" style="margin:0">Cliente activo</label>
        </div>
    </div>
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary" style="width:auto"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
    <a href="{{ route('clientes.index') }}" class="btn btn-light">Cancelar</a>
</div>
