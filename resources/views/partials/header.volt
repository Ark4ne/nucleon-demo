{% set uri = router.getRewriteUri() %}

<header>
  <nav>
    <div class="nav-wrapper">
      <a href="#" data-target="mobile-demo" class="sidenav-trigger"><i class="material-icons">menu</i></a>
      <ul class="right">
        {% if (auth.check()) %}
          <li><a href="{{ url('/logout') }}">Logout</a></li>
        {% else %}
          <li {{ uri == '/login' ? 'class="active"' : '' }}>
            <a href="{{ url('/login') }}">Login</a>
          </li>
          <li {{ uri == '/register' ? 'class="active"' : '' }}>
            <a href="{{ url('/register') }}">Register</a>
          </li>
        {% endif %}
      </ul>
    </div>
  </nav>
</header>

{% macro liIsActive(_url, _children) %}
  {% set uri = router.getRewriteUri() %}
  {% if uri === _url %}
    {% return true %}
  {% elseif _children is empty %}
    {% return false %}
  {% else %}
    {% for child in _children %}
      {% if liIsActive(child['url'], child['children'] | default([])) %}
        {% return true %}
      {% endif %}
    {% endfor %}
  {% endif %}
  {% return false %}
{% endmacro %}

{% macro drawLi(name, _url, _children) %}
  {% set isActive = liIsActive(_url, _children) %}

  {% if _children is empty %}
    <li class="bold {{ isActive ? 'active' : '' }}">
      <a {{ not isActive ? 'href="' ~ url(_url) ~ '"' : '' }}>{{ name }}</a>
    </li>
  {% else %}
    <li>
      <ul class="collapsible collapsible-accordion">
        <li class="bold {{ isActive ? 'active' : '' }}">
          <a class="collapsible-header">{{ name }}</a>
          <div class="collapsible-body">
            <ul>
              {% for name, child in _children %}
                {{ drawLi(name, child['url'], child['children'] | default([])) }}
              {% endfor %}
            </ul>
          </div>
        </li>
      </ul>
    </li>
  {% endif %}
{% endmacro %}

<ul class="sidenav sidenav-fixed" id="mobile-demo">
  <li class="logo center-align">
    <a id="logo-container" href="/" class="brand-logo">
      <img src="/img/nucleon.svg" height="57px">
    </a>
  </li>
  <li class="version center-align">
      <span>
        {{ call_user_func('Neutrino\Version::get') }}
      </span>
  </li>
  {{ drawLi('Kernel [HTTP]', '#!', [
    'Home [NoModule]' : ['url' : '/'],
    'Front [Frontend]' : ['url' : '/index'],
    'Back [Backend]' : ['url' : '/back/index/index']
  ]) }}
  {{ drawLi('Kernel [Micro]', '#!', [
    '/api' : ['url' : '/example/api/index']
  ]) }}
  {{ drawLi('Debug', '#!', [
    'Var dump' : ['url' : '/example/debug/var-dump'],
    'Exception' : ['url' : '/example/debug/exception']
  ]) }}
</ul>
