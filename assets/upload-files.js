window.Zao_WC_Upload_Files = window.Zao_WC_Upload_Files || {};

( function( window, document, $, app, dropzone, undefined ) {
    'use strict';

    app.cache = function() {
        app.initDropzone();
    };

    app.initDropzone = function() {
        dropzone.autoDiscover = false;

        $( '.wc-dropzone' ).each( function( i, v ) {
            let dz         = $( this );
            let target_key = dz.attr( 'id' ).replace( 'wc-dropzone-', '' );

            dz.dropzone(
                {
                    url: dz.attr( 'action' ),
                    addRemoveLinks : true,
                    init: function () {

                        let thisDropzone = this;
                        thisDropzone.on( 'removedfile', app.removeFile );
                        thisDropzone.on( 'sending'    , app.blockUi );
                        thisDropzone.on( 'success'    , app.setThumb );

                        if ( wcUploadedFiles.hasOwnProperty( target_key )  ) {
                            $.each( wcUploadedFiles[ target_key ], function ( name, data ) {

                                var mockFile = { name: name, size: data.size };
                                thisDropzone.emit("addedfile", mockFile);
                                thisDropzone.options.thumbnail.call(thisDropzone, mockFile, data.link);
                                // Make sure that there is no progress bar, etc...
                                thisDropzone.emit("complete", mockFile);
                            } );
                        }
                    }
                }
            );
        } );

    };

    app.removeFile = function( file ) {
        let data = {
            action : 'wc_dropzone_remove_file',
            filename : file.name,
            order_id : $( 'input[name="order_id"]', $( this.element ) ).val(),
            item_key : $( 'input[name="item_key"]', $( this.element ) ).val()
        }

        $.post( woocommerce_params.ajax_url, data, function( response ) {
            console.log( response );
        } );
    };

    app.blockUi = function() {
        $( '.wc-dropzone' ).block({
            message: "One at a time please 😁",
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
    }

    app.setThumb = function( file ) {
        let response = JSON.parse( file.xhr.response );
        let dz = this;

        if ( response.success ) {
            dz.emit( 'thumbnail', file, response.data.link );
        }

        $( '.wc-dropzone' ).unblock();
    }

	app.init = function() {
        app.cache();
	};

	$( app.init );

}( window, document, jQuery, window.Zao_WC_Upload_Files, Dropzone ) );
