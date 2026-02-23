(function( $ ) {
    'use strict';

    function buildFilterForm() {
        return `
            <div class="newstler-controls">
                <label for="newstler-categories">
                    <strong>Categorías:</strong>
                    <select id="newstler-categories" class="newstler-select-categories" multiple></select>
                </label>
                <label for="newstler-date-start">
                    <strong>Fecha inicio:</strong>
                    <input type="date" id="newstler-date-start" />
                </label>
                <label for="newstler-date-end">
                    <strong>Fecha fin:</strong>
                    <input type="date" id="newstler-date-end" />
                </label>
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button id="newstler-fetch" class="button button-primary">Aplicar filtros</button>
                    <button id="newstler-download" class="button" style="display:none;">Descargar reporte</button>
                </div>
            </div>
            <div id="newstler-results" class="newstler-results"></div>
        `;
    }

    // Pinta las categorías en el select
    function populateCategories( $root, data ) {
        var $sel = $root.find( '#newstler-categories' );
        $sel.empty();

        if ( !Array.isArray(data) || data.length === 0 ) {
            $sel.append('<option value="">(sin categorías)</option>');
            return;
        }

        data.sort(function(a,b){
            return (a.name || '').localeCompare(b.name || '');
        });

        data.forEach(function(cat){
            var id = cat.id;
            var name = cat.name || ('#' + id);
            $sel.append( '<option value="'+ id +'">'+ name +'</option>' );
        });
    }

    // Manejo de errores específico para categories endpoint
    function handleCategoriesError($root, jqXHR) {
        if ( jqXHR && jqXHR.status && (jqXHR.status === 401 || jqXHR.status === 403) ) {
            $root.find('#newstler-categories').empty().append('<option value="">(requiere login para ver categorías)</option>');
        } else {
            $root.find('#newstler-categories').empty().append('<option>(no categories)</option>');
        }
    }

    // Carga de categorías: preferimos las que PHP ya pasó en newstlerPublic.categories
    function fetchCategories( $root ) {
        console.log('Newstler: fetchCategories start', typeof newstlerPublic !== 'undefined' ? newstlerPublic : '(undefined)');

        if ( typeof newstlerPublic !== 'undefined' && Array.isArray(newstlerPublic.categories) && newstlerPublic.categories.length ) {
            // ya las tenemos desde PHP
            populateCategories( $root, newstlerPublic.categories );
            return;
        }

        // si no llegaron desde PHP, hacemos el fallback al REST core
        try {
            var fallback = window.location.origin + '/wp-json/wp/v2/categories';
            console.warn('Newstler: categories not passed from PHP — using fallback', fallback);
            $.ajax({
                url: fallback,
                method: 'GET',
                dataType: 'json'
            }).done(function(data){
                populateCategories( $root, data );
            }).fail(function(jqXHR, textStatus, err){
                console.error('Newstler: fallback categories fetch failed', textStatus, err, jqXHR);
                handleCategoriesError($root, jqXHR);
            });
        } catch (e) {
            console.error('Newstler: error in fallback fetchCategories', e);
            $root.find('#newstler-categories').append('<option>(no categories)</option>');
        }
    }

    function fetchNews( params ) {
        if ( typeof newstlerPublic === 'undefined' || ! newstlerPublic.restUrl ) {
            var d = $.Deferred();
            d.reject({ message: 'restUrl no definido' });
            return d.promise();
        }

        var url = newstlerPublic.restUrl + '?' + $.param( params );
        return $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json',
            beforeSend: function(xhr){
                if ( typeof newstlerPublic !== 'undefined' && newstlerPublic.nonce ) {
                    xhr.setRequestHeader( 'X-WP-Nonce', newstlerPublic.nonce );
                }
            }
        });
    }

    function fetchReportUrl( params, download ) {
        if ( typeof newstlerPublic === 'undefined' || ! newstlerPublic.reportUrl ) {
            console.error('Newstler: reportUrl not defined');
            return;
        }

        var url = newstlerPublic.reportUrl + '?' + $.param( params );
        if ( download ) {
            url += '&download=1';
            window.open(url, '_blank');
            return;
        }
        return $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json',
            beforeSend: function(xhr){
                if ( typeof newstlerPublic !== 'undefined' && newstlerPublic.nonce ) {
                    xhr.setRequestHeader( 'X-WP-Nonce', newstlerPublic.nonce );
                }
            }
        });
    }

    // Render de resultados
    function renderResults( $root, items ) {
        var $results = $root.find('#newstler-results');
        $results.empty();

        if ( !Array.isArray(items) || items.length === 0 ) {
            $results.html('<p>No se encontraron entradas para ese filtro.</p>');
            return;
        }

        items.forEach(function(it){
            var thumb = it.thumbnail ? '<img src="'+ it.thumbnail +'" class="newstler-thumb" alt="" />' : '';
            var safeTitle = it.title || '';
            var safeLink = it.link || '#';
            var safeDate = it.date ? new Date(it.date).toLocaleString() : '';
            var safeAuthor = it.author || '';
            var safeExcerpt = it.excerpt || '';

            var card = '<div class="newstler-card" data-id="'+ (it.id || '') +'">' +
                thumb +
                '<h3><a href="'+ safeLink +'" target="_blank" rel="noopener">'+ safeTitle +'</a></h3>' +
                '<div class="meta">'+ safeDate +' — '+ safeAuthor +'</div>' +
                '<div class="excerpt">'+ safeExcerpt +'</div>' +
                '</div>';
            $results.append(card);
        });
    }

    $( document ).ready(function(){
        console.log('newstlerPublic', typeof newstlerPublic !== 'undefined' ? newstlerPublic : '(undefined)');

        var $root = $( '#newstler-boletin-root' );
        if ( ! $root.length ) {
            return;
        }

        $root.html( buildFilterForm() );

        // cargar categorías (desde PHP o fallback)
        fetchCategories( $root );

        // click aplicar filtros
        $root.on( 'click', '#newstler-fetch', function(e){
            e.preventDefault();

            var $results = $root.find('#newstler-results');
            var cats = $root.find('#newstler-categories').val() || [];
            var start = $root.find('#newstler-date-start').val();
            var end = $root.find('#newstler-date-end').val();

            if ( start && end && start > end ) {
                alert( 'Fecha inicio no puede ser mayor que la fecha final.' );
                return;
            }

            var params = {
                categories: cats.join ? cats.join(',') : cats,
                date_start: start,
                date_end: end,
                per_page: 100
            };

            $results.html('<p>Cargando...</p>');

            fetchNews( params ).done(function(resp){
                $results.empty();
                if ( resp && resp.items && resp.items.length ) {
                    renderResults( $root, resp.items );
                    $root.find('#newstler-download').show().data('params', params);
                } else {
                    $results.html('<p>No se encontraron entradas para ese filtro.</p>');
                    $root.find('#newstler-download').hide();
                }
            }).fail(function(jqXHR, textStatus, err){
                console.error('Newstler: fetchNews fail', textStatus, err, jqXHR);
                var msg = '<p>Error al consultar el servidor.</p>';
                if ( jqXHR && (jqXHR.status === 401 || jqXHR.status === 403) ) {
                    msg = '<p>Acceso no autorizado. Es posible que necesites iniciar sesión.</p>';
                }
                $results.html(msg);
                $root.find('#newstler-download').hide();
            });
        });

        // click descargar
        $root.on( 'click', '#newstler-download', function(e){
            e.preventDefault();
            var params = $(this).data('params') || {};
            fetchReportUrl( params, true );
        });
    });
})( jQuery );