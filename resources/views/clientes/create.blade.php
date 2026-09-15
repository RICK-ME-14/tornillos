@extends('layouts.app')

@section('title', 'Nuevo cliente')

@section('content')
    <div class="page-head">
        <h1>NUEVO CLIENTE</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> <a href="{{ route('clientes.index') }}">Clientes</a>
            <span class="sep">/</span> Nuevo
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('clientes.store') }}">
            @csrf
            @include('clientes._form')
        </form>
    </div>
@endsection
