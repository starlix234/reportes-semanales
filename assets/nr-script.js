/* assets/nr-script.js
   Versión limpia: filtra por categorías (AJAX), prepara el form de export y abre la plantilla imprimible.
   Depende de nr_ajax.ajax_url y nr_ajax.nonce que inyecta wp_localize_script en PHP.
*/
(function($){
    'use strict';

    // Calcula base del plugin (para abrir exports/printable.php)
    function getPluginBaseUrl() {
        // admin-ajax.php -> /wp-admin/admin-ajax.php
        // plugin base: /wp-content/plugins/noticias-reporte/
        var ajax = (typeof nr_ajax !== 'undefined' && nr_ajax.ajax_url) ? nr_ajax.ajax_url : '/wp-admin/admin-ajax.php';
        return ajax.replace('/wp-admin/admin-ajax.php', '/wp-content/plugins/noticias-reporte/');
    }

    function fetchPostsByCats(cats) {
        $('#nr-results').html('<p>Cargando...</p>');
        $.post(nr_ajax.ajax_url, {
            action: 'nr_get_posts',
            nonce: nr_ajax.nonce,
            cats: cats
        }, function(resp){
            if (resp && resp.success && resp.data && resp.data.html) {
                $('#nr-results').html(resp.data.html);
            } else {
                $('#nr-results').html('<p>Error al obtener noticias.</p>');
                console.warn('nr_get_posts: respuesta inesperada', resp);
            }
        }, 'json')
        .fail(function(jqXHR, textStatus, errorThrown){
            $('#nr-results').html('<p>Error de red al obtener noticias.</p>');
            console.error('AJAX nr_get_posts fallo:', textStatus, errorThrown);
        });
    }

    function injectCatsIntoExportForm($form) {
        // eliminar inputs previos
        $form.find('input[name="cats[]"]').remove();

        var cats = [];
        $('#nr-filter-form input[name="cats[]"]:checked').each(function(){
            cats.push($(this).val());
        });

        if (cats.length) {
            cats.forEach(function(c){
                $('<input>').attr({ type: 'hidden', name: 'cats[]', value: c }).appendTo($form);
            });
        }
    }

    function openPrintable(cats) {
        var base = getPluginBaseUrl();
        var url = base + 'exports/printable.php';
        if (cats && cats.length) {
            url += '?cats=' + encodeURIComponent(cats.join(','));
        }
        window.open(url, '_blank');
    }

    function bindEvents() {
        // botón filtrar
        $(document).on('click', '#nr-filter-btn', function(e){
            var cats = [];
            $('#nr-filter-form input[name="cats[]"]:checked').each(function(){ cats.push($(this).val()); });
            fetchPostsByCats(cats);
        });

        // submit del form de export: inyectar cats[] antes de enviar
        $(document).on('submit', '#nr-export-form', function(e){
            injectCatsIntoExportForm($(this));
            // el form seguirá y abrirá la descarga
        });

        // botón imprimible
        $(document).on('click', '#btn-printable', function(e){
            var cats = [];
            $('#nr-filter-form input[name="cats[]"]:checked').each(function(){ cats.push($(this).val()); });
            openPrintable(cats);
        });
    }

    // init
    $(function(){
        if (typeof nr_ajax === 'undefined') {
            console.warn('nr_ajax no definido. Asegúrate de que wp_localize_script está encolado correctamente.');
        }
        bindEvents();
    });

})(jQuery);
