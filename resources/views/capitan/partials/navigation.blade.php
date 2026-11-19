@foreach(['INICIO'=>['panel'=>['home','Panel principal']], 'ORGANIZACIÓN DEL COMEDOR'=>['servicios'=>['utensils','Servicios'],'zonas'=>['map','Gestión de zonas'],'mesas'=>['layers','Gestión de mesas'],'asignaciones'=>['users','Asignación de meseros']], 'SUPERVISIÓN'=>['comedor'=>['activity','Comedor en vivo'],'incidencias'=>['alert','Incidencias']]] as $heading=>$items)
<div class="nav-section"><span>{{ $heading }}</span>
@foreach($items as $key=>$item)
<a class="nav-link {{ request('modulo','panel') === $key && request()->routeIs('capitan.index') ? 'active' : '' }}" href="{{ route('capitan.index',['modulo'=>$key]) }}"><x-icon :name="$item[0]"/><b>{{ $item[1] }}</b></a>
@endforeach
</div>
@endforeach
