( function( wp ) {
    var el = wp.element.createElement;
    var registerBlockType = wp.blocks.registerBlockType;

    registerBlockType( 'newstler/boletin', {
        title: 'Newstler — Boletín',
        icon: 'media-document',
        category: 'widgets',
        attributes: {},
        edit: function( props ) {
            return el( 'div', { className: 'newstler-editor-placeholder' },
                el( 'p', {}, 'Newstler: controles activos en frontend.'),
                el( 'div', { id: 'newstler-boletin-root' } )
            );
        },
        save: function() {
            return null;
        }
    } );
} )( window.wp );
