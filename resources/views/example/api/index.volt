{% extends 'layouts/template.volt' %}

{% block stylesheets %}
  {{ super() }}
  <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/styles/darcula.min.css">
{% endblock %}

{% block body %}
  <div class="page-content flex-center">
    <div class="container">
      <div data-call="/api/index">
        <p>Call : <kbd>/api/index</kbd>
          <button type="button" class="recall">ReCall</button>
        </p>
        <pre></pre>
      </div>
      <div data-call="/api/test">
        <p>Call : <kbd>/api/test</kbd>
          <button type="button" class="recall">ReCall</button>
        </p>
        <pre></pre>
      </div>
      <div data-call="/api/no-found">
        <p>Call : <kbd>/api/no-found</kbd>
          <button type="button" class="recall">ReCall</button>
        </p>
        <pre></pre>
      </div>
      <div data-call="/api/exception">
        <p>Call : <kbd>/api/exception</kbd>
          <button type="button" class="recall">ReCall</button>
          <small>example in debug mode</small>
        </p>
        <pre></pre>
      </div>
    </div>
  </div>

  <div id="preloader" class="preloader-wrapper small hide">
    <div class="spinner-layer spinner-red-only">
      <div class="circle-clipper left">
        <div class="circle"></div>
      </div>
      <div class="gap-patch">
        <div class="circle"></div>
      </div>
      <div class="circle-clipper right">
        <div class="circle"></div>
      </div>
    </div>
  </div>
{% endblock %}

{% block javascripts %}
  {{ super() }}
  <script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/highlight.min.js"></script>

  <script type="text/javascript">
    $(document).ready(function () {
      function apiCall($container) {
        $container.find('pre').empty();
        $container.find('button').attr('disabled', true).addClass('disabled');

        $container.append($("#preloader").clone().addClass('active').removeClass('hide'));

        $.ajax({
          url: $container.data('call')
        }).always(function () {
          $container.find('.preloader-wrapper').remove();
          $container.find('button').attr('disabled', false).removeClass('disabled');
        }).done(function (data) {
          $container.find('pre').append($('<code class="json">').text(JSON.stringify(data, null, 2)))
          hljs.highlightBlock($container.find('pre code').get(0));
        }).fail(function (xhr) {
          $container.find('pre').append($('<code class="json">').text(JSON.stringify(xhr.responseJSON, null, 2)))
          hljs.highlightBlock($container.find('pre code').get(0));
        })
      }

      $('[data-call]').each(function () {
        var $container = $(this);

        var call = apiCall.bind(null, $container);

        $container.find('button').on('click', call);

        call();
      });
    })
  </script>
{% endblock %}