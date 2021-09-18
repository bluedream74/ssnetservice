<aside class="main-sidebar {{ config('adminlte.classes_sidebar', 'sidebar-dark-primary elevation-4') }}">

    {{-- Sidebar brand logo --}}
    @if(config('adminlte.logo_img_xl'))
        @include('adminlte::partials.common.brand-logo-xl')
    @else
        @include('adminlte::partials.common.brand-logo-xs')
    @endif

    {{-- Sidebar menu --}}
    <div class="sidebar">
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column {{ config('adminlte.classes_sidebar_nav', '') }}"
                data-widget="treeview" role="menu"
                @if(config('adminlte.sidebar_nav_animation_speed') != 300)
                    data-animation-speed="{{ config('adminlte.sidebar_nav_animation_speed') }}"
                @endif
                @if(!config('adminlte.sidebar_nav_accordion'))
                    data-accordion="false"
                @endif>
                {{-- Configured sidebar links --}}
                @foreach ($adminlte->menu() as $item)
                    @if (strpos(Request::path(), 'admin') !== false) 
                        @auth('admin')
                            @if (strpos($item['role'], 'admin') !== false)
                                @include('adminlte::partials.sidebar.menu-item', $item)
                            @endif
                        @endauth
                    @endif

                    @if (strpos(Request::path(), 'artist') !== false) 
                        @auth('tutor')
                            @if (strpos($item['role'], 'tutor') !== false)
                                @include('adminlte::partials.sidebar.menu-item', $item)
                            @endif
                        @endauth
                    @endif
                @endforeach
            </ul>
        </nav>
    </div>

</aside>
