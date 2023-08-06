<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
    </ul>

    <ul class="navbar-nav ml-auto">
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
            <i class="fa fa-user fa-fw"></i> {{ Auth::guard('admin')->user()->name}} <i class="fa fa-caret-down"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-md dropdown-menu-right">
            <a href="{{route('admin.logout')}}" onclick="event.preventDefault();document.getElementById('logout-form').submit();" class="dropdown-item dropdown-footer"><i class="fas fa-sign-out-alt"></i> ログアウト</a>
            <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" style="display: none;">
            @csrf
            </form>
        </div>
      </li>
      
    </ul>
</nav>
