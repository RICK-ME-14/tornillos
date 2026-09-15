@extends('layouts.app')

@section('title', 'Categorías')

@section('content')
    <div class="page-head">
        <h1>CATEGORÍAS</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Inventario <span class="sep">/</span> Categorías
        </div>
    </div>

    <div class="toolbar">
        <form method="GET" action="{{ route('categorias.index') }}" class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" value="{{ $q }}" placeholder="Buscar categoría...">
        </form>
        <div class="spacer"></div>
        <a href="{{ route('categorias.create') }}" class="btn btn-success btn-sm">
            <i class="fa-solid fa-plus"></i> Nueva categoría
        </a>
    </div>

    <div class="panel">
        <div class="panel-scroll">
        <table class="table">
            <thead>
                <tr><th>Nombre</th><th>Descripción</th><th>Productos</th><th>Estado</th><th style="width:110px">Acciones</th></tr>
            </thead>
            <tbody>
            @forelse($categorias as $cat)
                <tr>
                    <td><strong>{{ $cat->nombre }}</strong></td>
                    <td>{{ $cat->descripcion ?: '—' }}</td>
                    <td><span class="badge-soft">{{ $cat->productos_count }}</span></td>
                    <td>
                        <span class="pill {{ $cat->activo ? 'ok' : 'warn' }}">
                            {{ $cat->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('categorias.edit', $cat) }}" class="btn-icon edit" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <form method="POST" action="{{ route('categorias.destroy', $cat) }}"
                                  onsubmit="return confirm('¿Eliminar la categoría {{ $cat->nombre }}?')">
                                @csrf @method('DELETE')
                                <button class="btn-icon del" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;color:#9aa3ab;padding:24px">No hay categorías registradas.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        {{ $categorias->links() }}
    </div>
@endsection
