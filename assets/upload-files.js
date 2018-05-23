window.Zao_WC_Upload_Files = window.Zao_WC_Upload_Files || {};

( function( window, document, $, app, dropzone, undefined ) {
    'use strict';

    app.cache = function() {
        app.initDropzone();
    };

    app.initDropzone = function() {
        dropzone.autoDiscover = false;

        $( '.wc-dropzone' ).each( function( i, v ) {
            let dz = $( this );
            console.log( dz.attr( 'action' ) );
            dz.dropzone(
                {
                    url: dz.attr( 'action' ),
                    addRemoveLinks : true,
                    init: function () {
                        let thisDropzone = this;
                        thisDropzone.on( 'removedfile', app.removeFile );
                        $.each( wcUploadedFiles, function (name, data) {
                            var mockFile = { name: name, size: data.size };
                            thisDropzone.emit("addedfile", mockFile);
                            thisDropzone.options.thumbnail.call(thisDropzone, mockFile, data.link);
                            // Make sure that there is no progress bar, etc...
                            thisDropzone.emit("complete", mockFile);
                        });

                    }
                }
            );
        } );

    };

    app.removeFile = function( file ) {

        let data = {
            action : 'wc_dropzone_remove_file',
            filename : file.name
        }

        $.post( woocommerce_params.ajax_url, data, function( response ) {
            console.log( response );
        } );
    };

	app.init = function() {
        app.cache();
	};

	$( app.init );

}( window, document, jQuery, window.Zao_WC_Upload_Files, Dropzone ) );
