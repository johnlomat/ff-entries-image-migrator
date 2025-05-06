/**
 * Fluent Forms Entries Image Migrator Admin JS
 */
(function($) {
    'use strict';

    /**
     * Image Processor Admin functionality
     */
    var FfeimAdmin = {
        /**
         * Processing state flag
         */
        isProcessing: false,

        /**
         * Initialize the admin functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            $('#ffeim-start-process').on('click', this.startProcess);
        },

        /**
         * Start the image processing
         */
        startProcess: function(e) {
            e.preventDefault();
            
            if (FfeimAdmin.isProcessing) {
                return;
            }
            
            FfeimAdmin.isProcessing = true;
            var $button = $(this);
            var $status = $('#ffeim-status-container');
            var batchSize = $('#ffeim-batch-size').val();
            
            // Validate batch size
            batchSize = parseInt(batchSize);
            if (isNaN(batchSize) || batchSize < 10) {
                batchSize = 10;
            } else if (batchSize > 200) {
                batchSize = 200;
            }
            
            // Save batch size preference
            FfeimAdmin.saveBatchSize(batchSize);
            
            $button.prop('disabled', true).text(ffeim_vars.starting_text);
            $status.show();
            
            // Start the process
            FfeimAdmin.initiateProcessing(batchSize);
        },

        /**
         * Save the batch size preference
         */
        saveBatchSize: function(batchSize) {
            $.post(ajaxurl, {
                action: 'ff_save_batch_size',
                batch_size: batchSize,
                nonce: ffeim_vars.nonce
            });
        },

        /**
         * Initiate the processing via AJAX
         */
        initiateProcessing: function(batchSize) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ff_process_images_start',
                    form_id: ffeim_vars.form_id,
                    batch_size: batchSize,
                    nonce: ffeim_vars.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.ffeim-status-text p').text(ffeim_vars.processing_started_text);
                        $('.ffeim-status-counts .ffeim-total-count .ffeim-count').text(response.data.total);
                        $('.ffeim-progress-bar-inner').css('width', '0%');
                        
                        // Start checking status and processing batches
                        FfeimAdmin.processNextBatch(0, batchSize);
                    } else {
                        $('#ffeim-start-process').prop('disabled', false).text(ffeim_vars.process_button_text);
                        $('.ffeim-status-text p').text(ffeim_vars.error_text + ' ' + response.data.message);
                        FfeimAdmin.isProcessing = false;
                    }
                },
                error: function() {
                    $('#ffeim-start-process').prop('disabled', false).text(ffeim_vars.process_button_text);
                    $('.ffeim-status-text p').text(ffeim_vars.connection_error_text);
                    FfeimAdmin.isProcessing = false;
                }
            });
        },

        /**
         * Process the next batch of images
         */
        processNextBatch: function(offset, batchSize) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ff_process_images_batch',
                    form_id: ffeim_vars.form_id,
                    offset: offset,
                    batch_size: batchSize,
                    nonce: ffeim_vars.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FfeimAdmin.updateProgressUI(response.data);
                        
                        if (response.data.is_completed) {
                            FfeimAdmin.completeProcess();
                        } else {
                            // Process next batch
                            FfeimAdmin.processNextBatch(response.data.next_offset, batchSize);
                        }
                    } else {
                        $('.ffeim-status-text p').text(ffeim_vars.error_text + ' ' + response.data.message);
                        $('#ffeim-start-process').prop('disabled', false).text(ffeim_vars.process_button_text);
                        FfeimAdmin.isProcessing = false;
                    }
                },
                error: function() {
                    $('.ffeim-status-text p').text(ffeim_vars.connection_error_text);
                    $('#ffeim-start-process').prop('disabled', false).text(ffeim_vars.process_button_text);
                    FfeimAdmin.isProcessing = false;
                }
            });
        },

        /**
         * Update the UI progress indicators
         */
        updateProgressUI: function(data) {
            var progress = Math.floor((data.processed / data.total) * 100);
            $('.ffeim-progress-bar-inner').css('width', progress + '%');
            $('.ffeim-status-counts .ffeim-processed-count .ffeim-count').text(data.processed);
            $('.ffeim-status-text p').text(ffeim_vars.processing_text + ' ' + progress + '% ' + ffeim_vars.complete_text);
        },

        /**
         * Complete the processing
         */
        completeProcess: function() {
            $('.ffeim-progress-bar-inner').css('width', '100%');
            $('.ffeim-status-text p').text(ffeim_vars.complete_message);
            $('#ffeim-start-process').prop('disabled', false).text(ffeim_vars.process_button_text);
            FfeimAdmin.isProcessing = false;
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        FfeimAdmin.init();
    });

})(jQuery);
