@extends('layouts.app')

@section('title', 'Editar marca')

@section('content')
    <div class="page-head">
        <h1>EDITAR MARCA</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> <a href="{{ route('marcas.index') }}">Marcas</a>
            <span class="sep">/</span> Editar
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('marcas.update', $marca) }}">
            @csrf @method('PUT')
            @include('marcas._form')
        </form>
    </div>
@endsection
