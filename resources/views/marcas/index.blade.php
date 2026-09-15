@extends('layouts.app')

@section('title', 'Marcas')

@section('content')
    <div class="page-head">
        <h1>MARCAS</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Inventario <span class="sep">/</span> Marcas
        </div>
    </div>

    <div class="toolbar">
        <form method="GET" action="{{ route('marcas.index') }}" class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" value="{{ $q }}" placeholder="Buscar marca...">
        </form>
        <div class="spacer"></div>
        <a href="{{ route('marcas.create') }}" class="btn btn-success btn-sm">
            <i class="fa-solid fa-plus"></i> Nueva marca
        </a>
    </div>

    <div class="panel">
        <div class="panel-scroll">
        <table class="table">
            <thead>
                <tr><th>Nombre</th><th>Productos</th><th>Estado</th><th style="width:110px">Acciones</th></tr>
            </thead>
            <tbody>
            @forelse($marcas as $marca)
                <tr>
                    <td><strong>{{ $marca->nombre }}</strong></td>
                    <td><span class="badge-soft">{{ $marca->productos_count }}</span></td>
                    <td>
                        <span class="pill {{ $marca->activo ? 'ok' : 'warn' }}">
                            {{ $marca->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('marcas.edit', $marca) }}" class="btn-icon edit" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <form method="POST" action="{{ route('marcas.destroy', $marca) }}"
                                  onsubmit="return confirm('¿Eliminar la marca {{ $marca->nombre }}?')">
                                @csrf @method('DELETE')
                                <button class="btn-icon del" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;color:#9aa3ab;padding:24px">No hay marcas registradas.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        {{ $marcas->links() }}
    </div>
@endsection
