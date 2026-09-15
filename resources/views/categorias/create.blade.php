@extends('layouts.app')

@section('title', 'Nueva categoría')

@section('content')
    <div class="page-head">
        <h1>NUEVA CATEGORÍA</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> <a href="{{ route('categorias.index') }}">Categorías</a>
            <span class="sep">/</span> Nueva
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('categorias.store') }}">
            @csrf
            @include('categorias._form')
        </form>
    </div>
@endsection
