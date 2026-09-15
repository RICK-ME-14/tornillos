@if (session('success'))
    <div class="flash success">
        <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
    </div>
@endif
@if (session('warning'))
    <div class="flash warning">
        <i class="fa-solid fa-triangle-exclamation"></i> {{ session('warning') }}
    </div>
@endif
@if (session('error'))
    <div class="flash error">
        <i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}
    </div>
@endif
