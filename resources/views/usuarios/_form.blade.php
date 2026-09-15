<div class="form-grid">
    <div class="form-group">
        <label>Nombre completo <span style="color:#e74c3c">*</span></label>
        <input type="text" name="name" class="form-control @error('name') invalid @enderror"
               value="{{ old('name', $usuario->name) }}" required>
        @error('name') <div class="invalid-msg">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label>Correo electrónico <span style="color:#e74c3c">*</span></label>
        <input type="email" name="email" class="form-control @error('email') invalid @enderror"
               value="{{ old('email', $usuario->email) }}" required>
        @error('email') <div class="invalid-msg">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label>Rol <span style="color:#e74c3c">*</span></label>
        <select name="rol" class="form-control">
            @foreach($roles as $val => $txt)
                <option value="{{ $val }}" {{ old('rol', $usuario->rol) === $val ? 'selected' : '' }}>{{ $txt }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>Teléfono</label>
        <input type="text" name="telefono" class="form-control" value="{{ old('telefono', $usuario->telefono) }}">
    </div>

    <div class="form-group">
        <label>Contraseña @if($usuario->exists)<span class="help" style="display:inline">(dejar en blanco para no cambiar)</span>@else<span style="color:#e74c3c">*</span>@endif</label>
        <input type="password" name="password" class="form-control @error('password') invalid @enderror"
               {{ $usuario->exists ? '' : 'required' }} placeholder="••••••••">
        @error('password') <div class="invalid-msg">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label>Confirmar contraseña</label>
        <input type="password" name="password_confirmation" class="form-control" placeholder="••••••••"
               {{ $usuario->exists ? '' : 'required' }}>
    </div>

    <div class="form-group full">
        <div class="checkbox-row" style="margin:0">
            <input type="checkbox" name="activo" id="activo" value="1" {{ old('activo', $usuario->activo ?? true) ? 'checked' : '' }}>
            <label for="activo" style="margin:0">Usuario activo (puede iniciar sesión)</label>
        </div>
    </div>
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary" style="width:auto"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
    <a href="{{ route('usuarios.index') }}" class="btn btn-light">Cancelar</a>
</div>
