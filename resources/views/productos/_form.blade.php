<div class="form-grid">
    <div class="form-group">
        <label>Código <span style="color:#e74c3c">*</span></label>
        <input type="text" name="codigo" class="form-control @error('codigo') invalid @enderror"
               value="{{ old('codigo', $producto->codigo) }}" placeholder="P0001" required>
        @error('codigo') <div class="invalid-msg">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label>Unidad <span style="color:#e74c3c">*</span></label>
        <select name="unidad" class="form-control @error('unidad') invalid @enderror">
            @foreach(\App\Models\Producto::UNIDADES as $val => [$txt, $cod])
                <option value="{{ $val }}" {{ old('unidad', $producto->unidad) === $val ? 'selected' : '' }}>{{ $txt }}</option>
            @endforeach
        </select>
        <div class="help">Se declara ante SUNAT con el código del catálogo 03.</div>
        @error('unidad') <div class="invalid-msg">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label>Afectación IGV <span style="color:#e74c3c">*</span></label>
        <select name="tipo_afectacion_igv" class="form-control @error('tipo_afectacion_igv') invalid @enderror">
            @foreach(\App\Models\Producto::AFECTACIONES as $val => $txt)
                <option value="{{ $val }}"
                    {{ (string) old('tipo_afectacion_igv', $producto->tipo_afectacion_igv ?? '10') === (string) $val ? 'selected' : '' }}>
                    {{ $txt }}
                </option>
            @endforeach
        </select>
        <div class="help">Determina si el producto paga IGV en el comprobante electrónico.</div>
        @error('tipo_afectacion_igv') <div class="invalid-msg">{{ $message }}</div> @enderror
    </div>

    <div class="form-group full">
        <label>Nombre <span style="color:#e74c3c">*</span></label>
        <input type="text" name="nombre" class="form-control @error('nombre') invalid @enderror"
               value="{{ old('nombre', $producto->nombre) }}" required>
        @error('nombre') <div class="invalid-msg">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label>Categoría</label>
        <select name="categoria_id" class="form-control">
            <option value="">— Sin categoría —</option>
            @foreach($categorias as $cat)
                <option value="{{ $cat->id }}" {{ (string)old('categoria_id', $producto->categoria_id) === (string)$cat->id ? 'selected' : '' }}>
                    {{ $cat->nombre }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>Marca</label>
        <select name="marca_id" class="form-control">
            <option value="">— Sin marca —</option>
            @foreach($marcas as $marca)
                <option value="{{ $marca->id }}" {{ (string)old('marca_id', $producto->marca_id) === (string)$marca->id ? 'selected' : '' }}>
                    {{ $marca->nombre }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>Precio de compra (S/) <span style="color:#e74c3c">*</span></label>
        <input type="number" step="0.01" min="0" name="precio_compra"
               class="form-control @error('precio_compra') invalid @enderror"
               value="{{ old('precio_compra', $producto->precio_compra ?? 0) }}" required>
        @error('precio_compra') <div class="invalid-msg">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label>Precio de venta (S/) <span style="color:#e74c3c">*</span></label>
        <input type="number" step="0.01" min="0" name="precio_venta"
               class="form-control @error('precio_venta') invalid @enderror"
               value="{{ old('precio_venta', $producto->precio_venta ?? 0) }}" required>
        @error('precio_venta') <div class="invalid-msg">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label>Stock actual <span style="color:#e74c3c">*</span></label>
        <input type="number" min="0" name="stock"
               class="form-control @error('stock') invalid @enderror"
               value="{{ old('stock', $producto->stock ?? 0) }}" required>
        @error('stock') <div class="invalid-msg">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label>Stock mínimo <span style="color:#e74c3c">*</span></label>
        <input type="number" min="0" name="stock_minimo"
               class="form-control @error('stock_minimo') invalid @enderror"
               value="{{ old('stock_minimo', $producto->stock_minimo ?? 5) }}" required>
        <div class="help">Se avisará cuando el stock llegue a este valor.</div>
    </div>

    <div class="form-group full">
        <label>Descripción</label>
        <textarea name="descripcion" class="form-control" placeholder="Opcional">{{ old('descripcion', $producto->descripcion) }}</textarea>
    </div>

    <div class="form-group full">
        <label>Imagen del producto</label>
        @if($producto->imagen ?? false)
            <div style="margin-bottom:8px">
                <img src="{{ asset('storage/'.$producto->imagen) }}" class="thumb" style="width:64px;height:64px" alt="">
            </div>
        @endif
        <input type="file" name="imagen" accept="image/*" class="form-control @error('imagen') invalid @enderror">
        <div class="help">JPG o PNG, máx 2 MB. Requiere haber ejecutado <code>php artisan storage:link</code>.</div>
        @error('imagen') <div class="invalid-msg">{{ $message }}</div> @enderror
    </div>

    <div class="form-group full">
        <div class="checkbox-row" style="margin:0">
            <input type="checkbox" name="activo" id="activo" value="1"
                   {{ old('activo', $producto->activo ?? true) ? 'checked' : '' }}>
            <label for="activo" style="margin:0">Producto activo (visible en ventas)</label>
        </div>
    </div>
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary" style="width:auto">
        <i class="fa-solid fa-floppy-disk"></i> Guardar
    </button>
    <a href="{{ route('productos.index') }}" class="btn btn-light">Cancelar</a>
</div>
