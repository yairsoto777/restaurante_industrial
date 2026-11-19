@foreach(['INICIO'=>['panel'=>['home','Panel principal']], 'GESTIÓN COMERCIAL'=>['solicitudes'=>['building','Solicitudes de empresas'],'clientes'=>['group','Gestión de clientes'],'sedes'=>['map','Gestión de sedes']], 'ADMINISTRACIÓN'=>['usuarios'=>['users','Usuarios del sistema'],'configuracion'=>['layers','Configuración general']]] as $group=>$items)
<div class="nav-section"><span>{{ $group }}</span>
@foreach($items as $key=>[$icon,$label])
<a class="nav-link {{ request('modulo','panel')===$key && (request()->routeIs('superadmin.*') || request()->routeIs('dashboard')) ? 'active' : '' }}" href="{{ route('superadmin.index',['modulo'=>$key]) }}"><x-icon :name="$icon"/><b>{{ $label }}</b></a>
@endforeach
</div>
@endforeach
