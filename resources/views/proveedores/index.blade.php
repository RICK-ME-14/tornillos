@extends('layouts.app')

@section('title', 'Proveedores')

@section('content')
    <div class="page-head">
        <h1>PROVEEDORES</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Compras <span class="sep">/</span> Proveedores
        </div>
    </div>

    <div class="toolbar">
        <form method="GET" action="{{ route('proveedores.index') }}" class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" value="{{ $q }}" placeholder="Buscar por nombre o RUC...">
        </form>
        <div class="spacer"></div>
        <a href="{{ route('proveedores.create') }}" class="btn btn-success btn-sm">
            <i class="fa-solid fa-plus"></i> Nuevo proveedor
        </a>
    </div>

    <div class="panel">
        <div class="panel-scroll">
        <table class="table">
            <thead>
                <tr><th>Nombre</th><th>RUC</th><th>Contacto</th><th>Teléfono</th><th>Estado</th><th style="width:110px">Acciones</th></tr>
            </thead>
            <tbody>
            @forelse($proveedores as $p)
                <tr>
                    <td><strong>{{ $p->nombre }}</strong></td>
                    <td>{{ $p->ruc ?: '—' }}</td>
                    <td>{{ $p->contacto ?: '—' }}</td>
                    <td>{{ $p->telefono ?: '—' }}</td>
                    <td><span class="pill {{ $p->activo ? 'ok' : 'warn' }}">{{ $p->activo ? 'Activo' : 'Inactivo' }}</span></td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('proveedores.edit', $p) }}" class="btn-icon edit" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" action="{{ route('proveedores.destroy', $p) }}"
                                  onsubmit="return confirm('¿Eliminar el proveedor {{ $p->nombre }}?')">
                                @csrf @method('DELETE')
                                <button class="btn-icon del" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#9aa3ab;padding:24px">No hay proveedores registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        {{ $proveedores->links() }}
    </div>
@endsection
