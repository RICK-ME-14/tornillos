@extends('layouts.app')

@section('title', 'Editar producto')

@section('content')
    <div class="page-head">
        <h1>EDITAR PRODUCTO</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> <a href="{{ route('productos.index') }}">Productos</a>
            <span class="sep">/</span> Editar
        </div>
    </div>

    <div class="form-card" style="max-width:820px">
        <form method="POST" action="{{ route('productos.update', $producto) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('productos._form')
        </form>
    </div>
@endsection
