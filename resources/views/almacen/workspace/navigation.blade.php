@foreach([
    'INICIO'=>[['dashboard','home','Panel principal']],
    'ABASTECIMIENTO'=>[['orders.suppliers.index','users','Proveedores'],['warehouse.ingredients.index','box','Catálogo de insumos'],['orders.index','calendar','Pedidos de insumos'],['receptions.index','box','Recepción de insumos']],
    'ALMACÉN'=>[['inventory.index','warehouse','Inventario de insumos'],['warehouse.locations.index','warehouse','Ubicaciones de almacén'],['warehouse.movements','layers','Movimientos de inventario'],['warehouse.alerts','alert','Alertas de existencias']],
] as $section=>$links)
    <div class="nav-section"><span>{{ $section }}</span>
        @foreach($links as [$route,$icon,$label])
            @php($active = request()->routeIs($route) || ($route === 'orders.index' && request()->routeIs('orders.create','orders.show')) || ($route === 'receptions.index' && request()->routeIs('receptions.review.*','receptions.orders.show')))
            <a class="nav-link {{ $active ? 'active' : '' }}" href="{{ route($route) }}" @if($active) aria-current="page" @endif><x-icon :name="$icon"/><span>{{ $label }}</span></a>
        @endforeach
    </div>
@endforeach
