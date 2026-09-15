@extends('layouts.app')

@section('title', 'Editar cliente')

@section('content')
    <div class="page-head">
        <h1>EDITAR CLIENTE</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> <a href="{{ route('clientes.index') }}">Clientes</a>
            <span class="sep">/</span> Editar
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('clientes.update', $cliente) }}">
            @csrf @method('PUT')
            @include('clientes._form')
        </form>
    </div>
@endsection
