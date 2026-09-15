@extends('layouts.app')

@section('title', 'Nuevo producto')

@section('content')
    <div class="page-head">
        <h1>NUEVO PRODUCTO</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> <a href="{{ route('productos.index') }}">Productos</a>
            <span class="sep">/</span> Nuevo
        </div>
    </div>

    <div class="form-card" style="max-width:820px">
        <form method="POST" action="{{ route('productos.store') }}" enctype="multipart/form-data">
            @csrf
            @include('productos._form')
        </form>
    </div>
@endsection
