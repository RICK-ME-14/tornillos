@extends('layouts.app')

@section('title', 'Nueva marca')

@section('content')
    <div class="page-head">
        <h1>NUEVA MARCA</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> <a href="{{ route('marcas.index') }}">Marcas</a>
            <span class="sep">/</span> Nueva
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('marcas.store') }}">
            @csrf
            @include('marcas._form')
        </form>
    </div>
@endsection
