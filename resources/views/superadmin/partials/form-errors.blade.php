@if($errors->any())<div class="sa-alert sa-error" role="alert"><div><strong>No se pudo completar la operación</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
<input type="hidden" name="_sa_drawer" value="{{ $drawerId }}">
