@extends('layouts.app')

@section('title', 'Editar categoría')

@section('content')
    <div class="page-head">
        <h1>EDITAR CATEGORÍA</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> <a href="{{ route('categorias.index') }}">Categorías</a>
            <span class="sep">/</span> Editar
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('categorias.update', $categoria) }}">
            @csrf @method('PUT')
            @include('categorias._form')
        </form>
    </div>
@endsection
