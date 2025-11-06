/**
 * Product R2 File Selector
 *
 * Handles R2 file selection for WooCommerce products
 */

(function($) {
    'use strict';

    var ProductR2Selector = {
        currentFolderPath: '',
        uploadFolderPath: '',
        selectedFile: null,
        currentRow: null, // Track which row we're working with
        folderStructure: {}, // Cache of folder structure
        autocompleteSelectedIndex: -1,

        init: function() {
            this.injectButtons();
            this.bindEvents();
            this.observeNewRows();
            this.loadFolderStructure();
        },

        injectButtons: function() {
            var self = this;

            // Try to inject buttons, with retries if needed
            var attempts = 0;
            var maxAttempts = 10;

            function tryInject() {
                // Try multiple selectors to find the downloadable files table
                var $rows = $('.woocommerce_downloadable_files tbody tr');

                if ($rows.length === 0) {
                    $rows = $('#downloadable_product_files tbody tr');
                }

                if ($rows.length === 0) {
                    $rows = $('.downloadable_files tbody tr');
                }

                if ($rows.length === 0) {
                    $rows = $('table.widefat.wc_input_table tbody tr');
                }

                if ($rows.length === 0) {
                    // Last resort - find any table in downloadable_files options group
                    $rows = $('.options_group.show_if_downloadable table tbody tr');
                }


                if ($rows.length > 0) {
                    $rows.each(function() {
                        self.injectButtonsIntoRow($(this));
                    });
                } else {
                    attempts++;
                    if (attempts < maxAttempts) {
                        setTimeout(tryInject, 500);
                    }
                }
            }

            // Start trying after a short delay
            setTimeout(tryInject, 300);
        },

        injectButtonsIntoRow: function($row) {
            // Check if buttons already exist
            if ($row.find('.cfr2wc-choose-r2-file').length > 0) {
                return;
            }

            // Try multiple selectors for the WooCommerce choose file button
            var $chooseFileBtn = $row.find('.upload_file_button');

            if ($chooseFileBtn.length === 0) {
                $chooseFileBtn = $row.find('a.button');
            }

            if ($chooseFileBtn.length === 0) {
                // Try to find any suitable location - look for the file URL input
                var $fileInput = $row.find('input.input_text[type="text"]').first();
                if ($fileInput.length > 0) {
                    var $cell = $fileInput.closest('td');

                    // Create our R2 buttons with nowrap
                    var $r2Buttons = $('<div class="cfr2wc-row-buttons" style="margin-top: 5px; white-space: nowrap;"></div>');

                    var $chooseBtn = $('<button type="button" class="button cfr2wc-choose-r2-file">' +
                        '<span class="dashicons dashicons-cloud"></span> ' +
                        cfr2wcProduct.strings.choose_r2 +
                        '</button>');

                    var $uploadBtn = $('<button type="button" class="button cfr2wc-upload-r2-file">' +
                        '<span class="dashicons dashicons-upload"></span> ' +
                        cfr2wcProduct.strings.upload_r2 +
                        '</button>');

                    $r2Buttons.append($chooseBtn).append(' ').append($uploadBtn);
                    $cell.append($r2Buttons);
                }
                return;
            }

            $chooseFileBtn.hide();

            // Create our R2 buttons with nowrap
            var $r2Buttons = $('<span class="cfr2wc-row-buttons" style="white-space: nowrap;"></span>');

            var $chooseBtn = $('<button type="button" class="button cfr2wc-choose-r2-file">' +
                '<span class="dashicons dashicons-cloud"></span> ' +
                cfr2wcProduct.strings.choose_r2 +
                '</button>');

            var $uploadBtn = $('<button type="button" class="button cfr2wc-upload-r2-file">' +
                '<span class="dashicons dashicons-upload"></span> ' +
                cfr2wcProduct.strings.upload_r2 +
                '</button>');

            $r2Buttons.append($chooseBtn).append(' ').append($uploadBtn);

            // Insert after the hidden Choose file button
            $chooseFileBtn.after($r2Buttons);
        },

        observeNewRows: function() {
            var self = this;

            // Watch for new rows being added by WooCommerce
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length) {
                        $(mutation.addedNodes).each(function() {
                            if ($(this).is('tr')) {
                                self.injectButtonsIntoRow($(this));
                            }
                        });
                    }
                });
            });

            var $tbody = $('.woocommerce_downloadable_files tbody');
            if ($tbody.length === 0) {
                $tbody = $('.downloadable_files tbody');
            }

            if ($tbody.length) {
                observer.observe($tbody[0], {
                    childList: true,
                    subtree: false
                });
            } else {
            }
        },

        bindEvents: function() {
            var self = this;

            // Choose from R2 button (delegated for dynamic rows)
            $(document).on('click', '.cfr2wc-choose-r2-file', function(e) {
                e.preventDefault();
                self.currentRow = $(this).closest('tr');
                self.openModal();
            });

            // Upload to R2 button (delegated for dynamic rows)
            $(document).on('click', '.cfr2wc-upload-r2-file', function(e) {
                e.preventDefault();
                self.currentRow = $(this).closest('tr');
                self.openUploadModal();
            });

            // Modal close buttons
            $('.cfr2wc-modal-close, .cfr2wc-modal-cancel').on('click', function() {
                self.closeModal();
            });

            $('.cfr2wc-upload-modal-close').on('click', function() {
                self.closeUploadModal();
            });

            // Breadcrumb navigation - clicking items navigates to that level
            $(document).on('click', '#cfr2wc-breadcrumb .cfr2wc-breadcrumb-item', function() {
                var path = $(this).data('path');
                self.navigateToFolder(path, 'choose');
            });

            $(document).on('click', '#cfr2wc-upload-breadcrumb .cfr2wc-breadcrumb-item', function() {
                var path = $(this).data('path');
                self.navigateToFolder(path, 'upload');
            });

            // Breadcrumb input - Choose modal
            $(document).on('focus', '#cfr2wc-breadcrumb-input', function() {
                self.handleAutocomplete('', 'choose');
            }).on('input', '#cfr2wc-breadcrumb-input', function() {
                self.handleAutocomplete($(this).val(), 'choose');
            }).on('keydown', '#cfr2wc-breadcrumb-input', function(e) {
                self.handleBreadcrumbKeydown(e, 'choose');
            }).on('blur', '#cfr2wc-breadcrumb-input', function() {
                setTimeout(function() {
                    $('#cfr2wc-autocomplete-dropdown').hide();
                }, 200);
            });

            // Breadcrumb input - Upload modal
            $(document).on('focus', '#cfr2wc-upload-breadcrumb-input', function() {
                self.handleAutocomplete('', 'upload');
            }).on('input', '#cfr2wc-upload-breadcrumb-input', function() {
                self.handleAutocomplete($(this).val(), 'upload');
            }).on('keydown', '#cfr2wc-upload-breadcrumb-input', function(e) {
                self.handleBreadcrumbKeydown(e, 'upload');
            }).on('blur', '#cfr2wc-upload-breadcrumb-input', function() {
                setTimeout(function() {
                    $('#cfr2wc-upload-autocomplete-dropdown').hide();
                }, 200);
            });

            // Autocomplete item selection
            $(document).on('click', '.cfr2wc-autocomplete-item', function() {
                var folder = $(this).data('folder');
                var modalType = $(this).closest('.cfr2wc-modal').attr('id') === 'cfr2wc-modal' ? 'choose' : 'upload';
                self.selectAutocompleteFolder(folder, modalType);
            });

            // Add file button
            $('.cfr2wc-add-file-btn').on('click', function() {
                self.addFileToProduct();
            });

            // Upload: file input
            $('#cfr2wc-file-input').on('change', function() {
                if (this.files.length > 0) {
                    self.uploadFile(this.files[0]);
                }
            });

            // Upload: drop zone
            $('#cfr2wc-drop-zone').on('click', function() {
                $('#cfr2wc-file-input').click();
            }).on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('drag-over');
            }).on('dragleave', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
            }).on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    self.uploadFile(files[0]);
                }
            });
        },

        openModal: function() {
            $('#cfr2wc-modal').fadeIn(200);

            // Reload folder structure to ensure it's fresh
            if (Object.keys(this.folderStructure).length === 0) {
                this.loadFolderStructure();
            }

            this.renderBreadcrumb('choose');
            this.initFileSelect2();
        },

        closeModal: function() {
            $('#cfr2wc-modal').fadeOut(200);
            $('#cfr2wc-file-select').val(null).trigger('change');
            this.selectedFile = null;
            $('#cfr2wc-file-preview').hide();
            $('.cfr2wc-add-file-btn').prop('disabled', true);
        },

        openUploadModal: function() {
            $('#cfr2wc-upload-modal').fadeIn(200);
            $('#cfr2wc-file-input').val('');
            $('#cfr2wc-upload-progress').hide();
            $('#cfr2wc-drop-zone').show();

            // Reload folder structure to ensure it's fresh
            if (Object.keys(this.folderStructure).length === 0) {
                this.loadFolderStructure();
            }

            this.renderBreadcrumb('upload');
        },

        closeUploadModal: function() {
            $('#cfr2wc-upload-modal').fadeOut(200);
        },

        loadFolderTree: function() {
            var self = this;

            $.post(cfr2wcProduct.ajax_url, {
                action: 'cfr2wc_get_folder_tree',
                nonce: cfr2wcProduct.nonce
            }, function(response) {
                if (response.success) {
                    // Clear existing tree content first (except root)
                    $('#cfr2wc-folder-tree').find('.cfr2wc-folder-item').not('.cfr2wc-folder-root').remove();
                    $('#cfr2wc-folder-tree').find('.cfr2wc-folder-children').remove();

                    self.renderFolderTree(response.data.tree);
                }
            });
        },

        renderFolderTree: function(tree, parentPath, parentElement) {
            var self = this;
            parentPath = parentPath || '';

            // Set parent element default, but ensure we don't re-add to root when recursing
            if (!parentElement) {
                parentElement = $('#cfr2wc-folder-tree');
            }

            $.each(tree, function(folderName, children) {
                var folderPath = parentPath ? parentPath + '/' + folderName : folderName;
                var hasChildren = Object.keys(children).length > 0;

                var $folder = $('<div>')
                    .addClass('cfr2wc-folder-item')
                    .attr('data-folder-path', folderPath)
                    .html(
                        '<span class="dashicons dashicons-category"></span>' +
                        '<span class="cfr2wc-folder-name">' + folderName + '</span>' +
                        (hasChildren ? '<span class="dashicons dashicons-arrow-right-alt2 cfr2wc-folder-toggle"></span>' : '')
                    );

                if (hasChildren) {
                    var $children = $('<div>').addClass('cfr2wc-folder-children').hide();
                    self.renderFolderTree(children, folderPath, $children);
                    $folder.append($children);

                    // Toggle children
                    $folder.find('.cfr2wc-folder-toggle').on('click', function(e) {
                        e.stopPropagation();
                        $children.slideToggle(200);
                        $(this).toggleClass('expanded');
                    });
                }

                parentElement.append($folder);
            });
        },

        selectFolder: function(folderPath) {
            this.currentFolderPath = folderPath;

            // Update UI
            $('.cfr2wc-folder-item').removeClass('active');
            $('.cfr2wc-folder-item[data-folder-path="' + folderPath + '"]').addClass('active');
            $('#cfr2wc-current-folder').text('/' + folderPath || '/');

            // Reload file selector
            $('#cfr2wc-file-select').val(null).trigger('change');
            this.selectedFile = null;
            $('#cfr2wc-file-preview').hide();
            $('.cfr2wc-add-file-btn').prop('disabled', true);
        },

        initFileSelect2: function() {
            var self = this;

            $('#cfr2wc-file-select').select2({
                ajax: {
                    url: cfr2wcProduct.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'cfr2wc_search_files',
                            nonce: cfr2wcProduct.nonce,
                            search: params.term || '',
                            folder_path: self.currentFolderPath
                        };
                    },
                    processResults: function(data) {
                        if (!data.success) {
                            return { results: [] };
                        }

                        return {
                            results: data.data.files.map(function(file) {
                                return {
                                    id: file.object_key,
                                    text: file.file_name + ' (' + file.file_size_formatted + ')',
                                    file: file
                                };
                            })
                        };
                    },
                    cache: true
                },
                placeholder: cfr2wcProduct.strings.select_file,
                minimumInputLength: 0,
                width: '100%'
            });

            $('#cfr2wc-file-select').on('select2:select', function(e) {
                self.selectedFile = e.params.data.file;
                self.showFilePreview(self.selectedFile);
                $('.cfr2wc-add-file-btn').prop('disabled', false);
            });
        },

        showFilePreview: function(file) {
            $('#cfr2wc-preview-name').text(file.file_name);
            $('#cfr2wc-preview-size').text(file.file_size_formatted);
            $('#cfr2wc-preview-path').text(file.object_key);
            $('#cfr2wc-file-preview').fadeIn(200);
        },

        addFileToProduct: function() {
            if (!this.selectedFile) {
                alert(cfr2wcProduct.strings.select_file);
                return;
            }

            if (!this.currentRow) {
                alert('Could not find file row');
                return;
            }

            // Build shortcode
            var shortcode = '[cloudflare_r2 object="' + this.selectedFile.object_key + '"]';


            // Try multiple selectors for the file NAME input (first field)
            var $fileNameInput = this.currentRow.find('input.file_name');

            if ($fileNameInput.length === 0) {
                $fileNameInput = this.currentRow.find('input[name*="[name]"]');
            }

            if ($fileNameInput.length === 0) {
                $fileNameInput = this.currentRow.find('input[type="text"]').first();
            }

            // Try multiple selectors for the file URL input (second field)
            var $fileUrlInput = this.currentRow.find('input.file_url');

            if ($fileUrlInput.length === 0) {
                $fileUrlInput = this.currentRow.find('input[name*="[file]"]');
            }

            if ($fileUrlInput.length === 0) {
                $fileUrlInput = this.currentRow.find('input[type="text"]').eq(1);
            }


            // Determine file name to use
            var fileName;
            if (cfr2wcProduct.use_generic_download_name) {
                // Use generic "Download" name if setting is enabled
                fileName = 'Download';
            } else {
                // Trim extension from filename
                fileName = this.selectedFile.file_name;
                var lastDotIndex = fileName.lastIndexOf('.');
                if (lastDotIndex > 0) {
                    fileName = fileName.substring(0, lastDotIndex);
                }
            }

            if ($fileNameInput.length > 0) {
                $fileNameInput.val(fileName);
            }

            if ($fileUrlInput.length > 0) {
                $fileUrlInput.val(shortcode);
            }

            // Trigger change event so WooCommerce knows the fields changed
            $fileUrlInput.trigger('change');
            $fileNameInput.trigger('change');

            // Close modal
            this.closeModal();

            // Reset current row
            this.currentRow = null;
        },

        uploadFile: function(file) {
            var self = this;

            // Show progress
            $('#cfr2wc-drop-zone').hide();
            $('#cfr2wc-upload-progress').show();
            $('.cfr2wc-upload-status').text(cfr2wcProduct.strings.uploading);

            // Get folder path from breadcrumb navigation
            var folderPath = self.uploadFolderPath || '';

            var formData = new FormData();
            formData.append('action', 'cfr2wc_upload_to_r2');
            formData.append('nonce', cfr2wcProduct.nonce);
            formData.append('file', file);
            formData.append('folder_path', folderPath);

            $.ajax({
                url: cfr2wcProduct.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percent = (e.loaded / e.total) * 100;
                            $('.cfr2wc-progress-fill').css('width', percent + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        $('.cfr2wc-upload-status').text(cfr2wcProduct.strings.upload_success);

                        // Build shortcode
                        var shortcode = '[cloudflare_r2 object="' + response.data.object_key + '"]';

                        // Insert into the current row's fields
                        if (self.currentRow) {
                            // Try multiple selectors for the file NAME input (first field)
                            var $fileNameInput = self.currentRow.find('input.file_name');
                            if ($fileNameInput.length === 0) {
                                $fileNameInput = self.currentRow.find('input[name*="[name]"]');
                            }
                            if ($fileNameInput.length === 0) {
                                $fileNameInput = self.currentRow.find('input[type="text"]').first();
                            }

                            // Try multiple selectors for the file URL input (second field)
                            var $fileUrlInput = self.currentRow.find('input.file_url');
                            if ($fileUrlInput.length === 0) {
                                $fileUrlInput = self.currentRow.find('input[name*="[file]"]');
                            }
                            if ($fileUrlInput.length === 0) {
                                $fileUrlInput = self.currentRow.find('input[type="text"]').eq(1);
                            }

                            // Determine file name to use
                            var fileName;
                            if (cfr2wcProduct.use_generic_download_name) {
                                // Use generic "Download" name if setting is enabled
                                fileName = 'Download';
                            } else {
                                // Trim extension from filename
                                fileName = response.data.file_name;
                                var lastDotIndex = fileName.lastIndexOf('.');
                                if (lastDotIndex > 0) {
                                    fileName = fileName.substring(0, lastDotIndex);
                                }
                            }

                            if ($fileNameInput.length > 0) {
                                $fileNameInput.val(fileName);
                            }
                            if ($fileUrlInput.length > 0) {
                                $fileUrlInput.val(shortcode);
                            }

                            // Trigger change events
                            $fileUrlInput.trigger('change');
                            $fileNameInput.trigger('change');
                        }

                        // Close modal after delay
                        setTimeout(function() {
                            self.closeUploadModal();
                            self.currentRow = null;
                        }, 1000);
                    } else {
                        $('.cfr2wc-upload-status').text(response.data.message || cfr2wcProduct.strings.upload_error);
                        setTimeout(function() {
                            $('#cfr2wc-upload-progress').hide();
                            $('#cfr2wc-drop-zone').show();
                        }, 2000);
                    }
                },
                error: function() {
                    $('.cfr2wc-upload-status').text(cfr2wcProduct.strings.upload_error);
                    setTimeout(function() {
                        $('#cfr2wc-upload-progress').hide();
                        $('#cfr2wc-drop-zone').show();
                    }, 2000);
                }
            });
        },

        syncR2Files: function() {
            var self = this;
            var $btn = $('.cfr2wc-refresh-tree');
            var originalHtml = $btn.html();

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: rotation 1s infinite linear;"></span>');

            $.post(cfr2wcProduct.ajax_url, {
                action: 'cfr2wc_sync_r2_files',
                nonce: cfr2wcProduct.nonce
            }, function(response) {
                if (response.success) {
                    // Reload folder tree instead of entire page
                    self.loadFolderTree();

                    // Clear file selector and reset selection
                    $('#cfr2wc-file-select').val(null).trigger('change');
                    self.selectedFile = null;
                    $('#cfr2wc-file-preview').hide();
                    $('.cfr2wc-add-file-btn').prop('disabled', true);
                } else {
                    alert(response.data.message || 'Sync failed');
                }
            }).always(function() {
                $btn.prop('disabled', false).html(originalHtml);
            });
        },

        // ========== Breadcrumb Navigation System ==========

        loadFolderStructure: function() {
            var self = this;


            $.post(cfr2wcProduct.ajax_url, {
                action: 'cfr2wc_get_folder_tree',
                nonce: cfr2wcProduct.nonce
            }, function(response) {
                if (response.success && response.data && response.data.tree) {
                    self.folderStructure = self.buildFolderMap(response.data.tree);
                } else {
                    console.error('CFR2WC: Failed to load folder tree:', response);
                }
            }).fail(function(xhr, status, error) {
                console.error('CFR2WC: AJAX error loading folder tree:', error);
            });
        },

        buildFolderMap: function(tree) {
            var map = {};

            // Tree format is: {folderName: {subFolder: {}, ...}, ...}
            function traverse(obj, parentPath) {
                // Get all folder names at this level
                var folderNames = Object.keys(obj);

                if (folderNames.length > 0) {
                    // Initialize parent path in map if not exists
                    if (!map[parentPath]) {
                        map[parentPath] = [];
                    }

                    // Add all folders at this level to parent's list
                    folderNames.forEach(function(folderName) {
                        if (map[parentPath].indexOf(folderName) === -1) {
                            map[parentPath].push(folderName);
                        }

                        // Build current path
                        var currentPath = parentPath ? parentPath + '/' + folderName : folderName;

                        // Recursively process subfolders
                        if (obj[folderName] && typeof obj[folderName] === 'object') {
                            traverse(obj[folderName], currentPath);
                        }
                    });
                }
            }

            if (tree && typeof tree === 'object') {
                traverse(tree, '');
            }

            return map;
        },

        renderBreadcrumb: function(modalType) {
            var $breadcrumb = modalType === 'choose' ? $('#cfr2wc-breadcrumb') : $('#cfr2wc-upload-breadcrumb');
            var path = modalType === 'choose' ? this.currentFolderPath : this.uploadFolderPath;
            var inputId = modalType === 'choose' ? 'cfr2wc-breadcrumb-input' : 'cfr2wc-upload-breadcrumb-input';
            var dropdownId = modalType === 'choose' ? 'cfr2wc-autocomplete-dropdown' : 'cfr2wc-upload-autocomplete-dropdown';

            // Clear everything except the input container
            $breadcrumb.find('.cfr2wc-breadcrumb-item').remove();

            // Always add root
            var $root = $('<span class="cfr2wc-breadcrumb-item cfr2wc-breadcrumb-root" data-path="">' +
                '<span class="dashicons dashicons-admin-home"></span> Root</span>');

            $breadcrumb.prepend($root);

            // Add path segments
            if (path) {
                var segments = path.split('/').filter(function(s) { return s.length > 0; });
                var builtPath = '';

                segments.forEach(function(segment, index) {
                    builtPath += (builtPath ? '/' : '') + segment;
                    var $item = $('<span class="cfr2wc-breadcrumb-item" data-path="' + builtPath + '">' + segment + '</span>');
                    $root.after($item);
                    $root = $item; // Update reference for next iteration
                });
            }

            // Ensure input container exists and is at the end
            var $inputContainer = $breadcrumb.find('.cfr2wc-breadcrumb-input-container');
            if ($inputContainer.length === 0) {
                var inputHtml = '<div class="cfr2wc-breadcrumb-input-container">' +
                    '<input type="text" id="' + inputId + '" class="cfr2wc-breadcrumb-input" placeholder="folder..." autocomplete="off">' +
                    '<div class="cfr2wc-autocomplete-dropdown" id="' + dropdownId + '" style="display: none;"></div>' +
                    '</div>';
                $breadcrumb.append(inputHtml);
            }
        },

        navigateToFolder: function(path, modalType) {
            if (modalType === 'choose') {
                this.currentFolderPath = path;
                this.renderBreadcrumb('choose');
                this.initFileSelect2();
            } else {
                this.uploadFolderPath = path;
                this.renderBreadcrumb('upload');
            }
        },

        handleAutocomplete: function(query, modalType) {
            var currentPath = modalType === 'choose' ? this.currentFolderPath : this.uploadFolderPath;
            var folders = this.folderStructure[currentPath] || [];


            var $dropdown = modalType === 'choose' ?
                $('#cfr2wc-autocomplete-dropdown') :
                $('#cfr2wc-upload-autocomplete-dropdown');


            if (!query) {
                // Show all folders in current path
                this.renderAutocomplete(folders, folders, $dropdown);
                return;
            }

            // Filter folders based on query (zsh-style matching)
            var matches = folders.filter(function(folder) {
                return folder.toLowerCase().indexOf(query.toLowerCase()) === 0 ||
                       folder.toLowerCase().indexOf(query.toLowerCase()) > -1;
            });

            // Sort by relevance (starts with query first)
            matches.sort(function(a, b) {
                var aStarts = a.toLowerCase().indexOf(query.toLowerCase()) === 0;
                var bStarts = b.toLowerCase().indexOf(query.toLowerCase()) === 0;

                if (aStarts && !bStarts) return -1;
                if (!aStarts && bStarts) return 1;
                return a.localeCompare(b);
            });

            this.renderAutocomplete(matches, folders, $dropdown, query);
        },

        renderAutocomplete: function(matches, allFolders, $dropdown, query) {
            $dropdown.empty();

            if (matches.length === 0) {
                $dropdown.html('<div class="cfr2wc-autocomplete-empty">No folders found. Press Enter to create "' + query + '"</div>');
                $dropdown.show();
                return;
            }

            matches.forEach(function(folder) {
                var displayText = folder;

                // Highlight matching part if query exists
                if (query) {
                    var index = folder.toLowerCase().indexOf(query.toLowerCase());
                    if (index > -1) {
                        var before = folder.substring(0, index);
                        var match = folder.substring(index, index + query.length);
                        var after = folder.substring(index + query.length);
                        displayText = before + '<span class="cfr2wc-folder-match">' + match + '</span>' + after;
                    }
                }

                var $item = $('<div class="cfr2wc-autocomplete-item" data-folder="' + folder + '">' + displayText + '</div>');
                $dropdown.append($item);
            });

            $dropdown.show();
            this.autocompleteSelectedIndex = -1;
        },

        handleBreadcrumbKeydown: function(e, modalType) {
            var $dropdown = modalType === 'choose' ?
                $('#cfr2wc-autocomplete-dropdown') :
                $('#cfr2wc-upload-autocomplete-dropdown');

            var $items = $dropdown.find('.cfr2wc-autocomplete-item');

            // Enter key
            if (e.keyCode === 13) {
                e.preventDefault();

                if (this.autocompleteSelectedIndex >= 0 && $items.length > 0) {
                    // Select highlighted item
                    var folder = $items.eq(this.autocompleteSelectedIndex).data('folder');
                    this.selectAutocompleteFolder(folder, modalType);
                } else {
                    // Create new folder with typed name
                    var $input = modalType === 'choose' ?
                        $('#cfr2wc-breadcrumb-input') :
                        $('#cfr2wc-upload-breadcrumb-input');
                    var folderName = $input.val().trim();

                    if (folderName) {
                        this.selectAutocompleteFolder(folderName, modalType);
                    }
                }
                return;
            }

            // Escape key - hide dropdown and clear input
            if (e.keyCode === 27) {
                e.preventDefault();
                var $input = modalType === 'choose' ? $('#cfr2wc-breadcrumb-input') : $('#cfr2wc-upload-breadcrumb-input');
                var $dropdown = modalType === 'choose' ? $('#cfr2wc-autocomplete-dropdown') : $('#cfr2wc-upload-autocomplete-dropdown');
                $input.val('');
                $dropdown.hide();
                this.autocompleteSelectedIndex = -1;
                return;
            }

            // Tab key - select first match
            if (e.keyCode === 9 && $items.length > 0) {
                e.preventDefault();
                var folder = $items.eq(0).data('folder');
                this.selectAutocompleteFolder(folder, modalType);
                return;
            }

            // Arrow down
            if (e.keyCode === 40) {
                e.preventDefault();
                if ($items.length > 0) {
                    this.autocompleteSelectedIndex = Math.min(this.autocompleteSelectedIndex + 1, $items.length - 1);
                    this.updateAutocompleteSelection($items);
                }
                return;
            }

            // Arrow up
            if (e.keyCode === 38) {
                e.preventDefault();
                if ($items.length > 0) {
                    this.autocompleteSelectedIndex = Math.max(this.autocompleteSelectedIndex - 1, 0);
                    this.updateAutocompleteSelection($items);
                }
                return;
            }
        },

        updateAutocompleteSelection: function($items) {
            $items.removeClass('selected');

            if (this.autocompleteSelectedIndex >= 0) {
                $items.eq(this.autocompleteSelectedIndex).addClass('selected');
            }
        },

        selectAutocompleteFolder: function(folderName, modalType) {
            var currentPath = modalType === 'choose' ? this.currentFolderPath : this.uploadFolderPath;
            var newPath = currentPath ? currentPath + '/' + folderName : folderName;

            // Navigate to new path
            this.navigateToFolder(newPath, modalType);

            // Clear input and hide dropdown
            var $input = modalType === 'choose' ? $('#cfr2wc-breadcrumb-input') : $('#cfr2wc-upload-breadcrumb-input');
            var $dropdown = modalType === 'choose' ? $('#cfr2wc-autocomplete-dropdown') : $('#cfr2wc-upload-autocomplete-dropdown');

            $input.val('');
            $dropdown.hide();
            this.autocompleteSelectedIndex = -1;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {

        // Check if we're on a product edit page
        var isProductPage = $('body').hasClass('post-type-product') ||
                           $('body').hasClass('posttype-product') ||
                           $('#product-type').length > 0;


        if (isProductPage) {
            ProductR2Selector.init();
        } else {
        }
    });

})(jQuery);
