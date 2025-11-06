/**
 * Cloudflare R2 Admin Settings JavaScript
 */

(function($) {
    'use strict';

    var AdminSettings = {
        init: function() {
            this.bindEvents();
            this.toggleCustomDomainField();
            this.toggleCredentialFields();
        },

        bindEvents: function() {
            var self = this;

            // Test connection button
            $(document).on('click', '#cfr2wc-test-connection', this.testConnection.bind(this));

            // Toggle password visibility (if implemented)
            $(document).on('click', '.cfr2wc-toggle-password', this.togglePassword);

            // Toggle custom domain based on public downloads
            $(document).on('change', '#cfr2wc_enable_public_downloads', function() {
                self.toggleCustomDomainField();
            });

            // Toggle credential fields based on storage mode
            $(document).on('change', 'input[name="cfr2wc_credential_storage_mode"]', function() {
                self.toggleCredentialFields();
            });
        },

        toggleCustomDomainField: function() {
            var isPublicEnabled = $('#cfr2wc_enable_public_downloads').is(':checked');
            var $customDomainRow = $('#cfr2wc_custom_domain').closest('tr');

            if (isPublicEnabled) {
                $customDomainRow.show();
            } else {
                $customDomainRow.hide();
            }
        },

        toggleCredentialFields: function() {
            var storageMode = $('input[name="cfr2wc_credential_storage_mode"]:checked').val();
            var $credentialFields = $('.cfr2wc-credential-field').closest('tr');
            var $databaseDesc = $('#storage_mode_database');
            var $constantsDesc = $('#storage_mode_constants');

            if (storageMode === 'constants') {
                // Hide credential input fields
                $credentialFields.hide();
                // Show constants description
                $databaseDesc.hide();
                $constantsDesc.show();
            } else {
                // Show credential input fields
                $credentialFields.show();
                // Show database description
                $databaseDesc.show();
                $constantsDesc.hide();
            }
        },

        testConnection: function(e) {
            e.preventDefault();

            var $button = $(e.currentTarget);
            var $status = $('.cfr2wc-connection-status');

            // Get form values
            var data = {
                action: 'cfr2wc_test_connection',
                nonce: cfr2wcAdmin.nonce,
                endpoint: $('#cfr2wc_endpoint').val(),
                access_key_id: $('#cfr2wc_access_key_id').val(),
                secret_access_key: $('#cfr2wc_secret_access_key').val(),
                bucket_name: $('#cfr2wc_bucket_name').val()
            };

            // Client-side validation
            if (!data.endpoint || !data.access_key_id || !data.secret_access_key || !data.bucket_name) {
                var missingFields = [];
                if (!data.endpoint) missingFields.push('Endpoint');
                if (!data.access_key_id) missingFields.push('Access Key ID');
                if (!data.secret_access_key) missingFields.push('Secret Access Key');
                if (!data.bucket_name) missingFields.push('Bucket Name');

                $status.removeClass('testing success')
                       .addClass('error')
                       .html('<span class="dashicons dashicons-warning"></span> Missing fields: ' + missingFields.join(', '))
                       .show();
                setTimeout(function() {
                    $status.fadeOut();
                }, 5000);
                return;
            }

            // Update UI
            $button.prop('disabled', true).text('Testing...');
            $status.removeClass('success error')
                   .addClass('testing')
                   .html('<span class="dashicons dashicons-update-alt"></span> Testing connection...')
                   .show();

            // Make AJAX request
            $.post(cfr2wcAdmin.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        $status.removeClass('testing')
                               .addClass('success')
                               .html('<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message);
                    } else {
                        $status.removeClass('testing')
                               .addClass('error')
                               .html('<span class="dashicons dashicons-warning"></span> ' + response.data.message);
                    }
                })
                .fail(function() {
                    $status.removeClass('testing')
                           .addClass('error')
                           .html('<span class="dashicons dashicons-warning"></span> Connection test failed');
                })
                .always(function() {
                    $button.prop('disabled', false).text('Test Connection');

                    // Hide status after 5 seconds
                    setTimeout(function() {
                        $status.fadeOut();
                    }, 5000);
                });
        },

        togglePassword: function(e) {
            e.preventDefault();

            var $button = $(e.currentTarget);
            var $input = $button.siblings('input');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $button.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $input.attr('type', 'password');
                $button.find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        AdminSettings.init();
    });

})(jQuery);
