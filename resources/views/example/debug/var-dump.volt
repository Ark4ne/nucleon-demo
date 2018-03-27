{% extends 'layouts/template.volt' %}

{% block stylesheets %}
  {{ super() }}
  <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/styles/darcula.min.css">
{% endblock %}

{% block body %}
  <div class="page-content flex-center">
    <div class="container">
      <div>
        <h4>Dump in .volt</h4>
        <pre class="hl-js"><code class="twig">{{ '{{ dump(var) }}' }}</code></pre>
      </div>
      <div>
        <h4>Dump in .php</h4>
        {% set nl = constant('PHP_EOL') %}
        {% set code ='use Neutrino\Debug\VarDump;'~nl~
          'class Foo {'~nl~
          '  public function bar($var){'~nl~
          '    VarDump::dump($var);'~nl~
          '  }'~nl~
          '}'
        %}
        <pre class="hl-js"><code class="php">{{ code|e }}</code></pre>
      </div>
      <div>
        <h4>Output</h4>
        {{ dump(to_dump) }}
      </div>
    </div>
  </div>
{% endblock %}

{% block javascripts %}
  {{ super() }}
  <script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/highlight.min.js"></script>

  <script>
    $(document).ready(function () {
      $('pre.hl-js > code').each(function (i, block) {
        hljs.highlightBlock(block);
      });
    });
  </script>
{% endblock %}