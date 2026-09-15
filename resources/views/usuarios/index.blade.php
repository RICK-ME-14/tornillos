@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
    <div class="page-head">
        <h1>USUARIOS</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Configuración <span class="sep">/</span> Usuarios
        </div>
    </div>

    <div class="toolbar">
        <form method="GET" action="{{ route('usuarios.index') }}" class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" value="{{ $q }}" placeholder="Buscar por nombre o correo...">
        </form>
        <div class="spacer"></div>
        <a href="{{ route('usuarios.create') }}" class="btn btn-success btn-sm">
            <i class="fa-solid fa-user-plus"></i> Nuevo usuario
        </a>
    </div>

    <div class="panel">
        <div class="panel-scroll">
        <table class="table">
            <thead>
                <tr><th>Usuario</th><th>Correo</th><th>Teléfono</th><th>Rol</th><th>Estado</th><th style="width:110px">Acciones</th></tr>
            </thead>
            <tbody>
            @forelse($usuarios as $u)
                @php
                    $rolClase = $u->rol === 'admin' ? 'purple' : ($u->rol === 'gerente' ? 'blue' : 'lime');
                @endphp
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <span class="thumb" style="border-radius:50%;background:var(--brand);color:#fff">{{ strtoupper(substr($u->name,0,1)) }}</span>
                            <strong>{{ $u->name }}</strong>
                            @if($u->id === auth()->id())<span class="badge-soft">Tú</span>@endif
                        </div>
                    </td>
                    <td>{{ $u->email }}</td>
                    <td>{{ $u->telefono ?: '—' }}</td>
                    <td><span class="badge-soft">{{ $roles[$u->rol] ?? $u->rol }}</span></td>
                    <td><span class="pill {{ $u->activo ? 'ok' : 'warn' }}">{{ $u->activo ? 'Activo' : 'Inactivo' }}</span></td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('usuarios.edit', $u) }}" class="btn-icon edit" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            @if($u->id !== auth()->id())
                                <form method="POST" action="{{ route('usuarios.destroy', $u) }}"
                                      onsubmit="return confirm('¿Eliminar al usuario {{ $u->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn-icon del" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#9aa3ab;padding:24px">No hay usuarios.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        {{ $usuarios->links() }}
    </div>
@endsection
