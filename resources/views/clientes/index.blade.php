@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
    <div class="page-head">
        <h1>CLIENTES</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Personas <span class="sep">/</span> Clientes
        </div>
    </div>

    <div class="toolbar">
        <form method="GET" action="{{ route('clientes.index') }}" class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" value="{{ $q }}" placeholder="Buscar por nombre o documento...">
        </form>
        <div class="spacer"></div>
        <a href="{{ route('clientes.create') }}" class="btn btn-success btn-sm">
            <i class="fa-solid fa-plus"></i> Nuevo cliente
        </a>
    </div>

    <div class="panel">
        <div class="panel-scroll">
        <table class="table">
            <thead>
                <tr><th>Nombre</th><th>Documento</th><th>Teléfono</th><th>Email</th><th>Estado</th><th style="width:110px">Acciones</th></tr>
            </thead>
            <tbody>
            @forelse($clientes as $c)
                <tr>
                    <td><strong>{{ $c->nombre }}</strong></td>
                    <td><span class="badge-soft">{{ $c->tipo_documento }}</span> {{ $c->numero_documento ?: '—' }}</td>
                    <td>{{ $c->telefono ?: '—' }}</td>
                    <td>{{ $c->email ?: '—' }}</td>
                    <td><span class="pill {{ $c->activo ? 'ok' : 'warn' }}">{{ $c->activo ? 'Activo' : 'Inactivo' }}</span></td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('clientes.edit', $c) }}" class="btn-icon edit" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" action="{{ route('clientes.destroy', $c) }}"
                                  onsubmit="return confirm('¿Eliminar el cliente {{ $c->nombre }}?')">
                                @csrf @method('DELETE')
                                <button class="btn-icon del" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#9aa3ab;padding:24px">No hay clientes registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        {{ $clientes->links() }}
    </div>
@endsection
