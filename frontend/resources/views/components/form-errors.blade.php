@if($errors->any())
    <div class="form-error-summary" role="alert">
        <strong>Data belum dapat disimpan</strong>
        <ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    </div>
@endif
