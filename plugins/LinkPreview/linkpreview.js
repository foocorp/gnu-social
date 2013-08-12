/**
 * (c) 2010 StatusNet, Inc.
 */

(function() {
    /**
     * Quickie wrapper around ooembed JSON lookup
     */
    var oEmbed = {
        api: 'https://noembed.com/embed',
        width: 100,
        height: 75,
        cache: {},
        callbacks: {},

        /**
         * Do a cached oEmbed lookup for the given URL.
         *
         * @param {String} url
         * @param {function} callback
         */
        lookup: function(url, callback)
        {
            if (typeof oEmbed.cache[url] == "object") {
                // We already have a successful lookup.
                callback(oEmbed.cache[url]);
            } else if (typeof oEmbed.callbacks[url] == "undefined") {
                // No lookup yet... Start it!
                oEmbed.callbacks[url] = [callback];

                oEmbed.rawLookup(url, function(data) {
                    oEmbed.cache[url] = data;
                    var callbacks = oEmbed.callbacks[url];
                    oEmbed.callbacks[url] = undefined;
                    for (var i = 0; i < callbacks.length; i++) {
                        callbacks[i](data);
                    }
                });
            } else {
                // A lookup is in progress.
                oEmbed.callbacks[url].push(callback);
            }
        },

        /**
         * Do an oEmbed lookup for the given URL.
         *
         * @fixme proxy through ourselves if possible?
         * @fixme use the global thumbnail size settings
         *
         * @param {String} url
         * @param {function} callback
         */
        rawLookup: function(url, callback)
        {
            var params = {
                url: url,
                format: 'json',
                maxwidth: oEmbed.width,
                maxheight: oEmbed.height,
                token: $('#token').val()
            };
            $.ajax({
                url: oEmbed.api,
                data: params,
                dataType: 'json',
                success: function(data, xhr) {
                    callback(data);
                },
                error: function(xhr, textStatus, errorThrown) {
                    callback(null);
                }
            });
        }
    };

    SN.Init.LinkPreview = function(params) {
        if (params.api) oEmbed.api = params.api;
        if (params.width) oEmbed.width = params.width;
        if (params.height) oEmbed.height = params.height;
    }

    // Piggyback on the counter update...
    var origCounter = SN.U.Counter;
    SN.U.Counter = function(form) {
        var preview = form.data('LinkPreview');
        if (preview) {
            preview.previewLinks(form.find('.notice_data-text:first').val());
        }
        return origCounter(form);
    }

    // Customize notice form init...
    var origSetup = SN.Init.NoticeFormSetup;
    SN.Init.NoticeFormSetup = function(form) {
        origSetup(form);

        form
            .bind('reset', function() {
                LinkPreview.clear();
            });

        var LinkPreview = {
            links: [],
            state: [],
            refresh: [],

            /**
             * Find URL links from the source text that may be interesting.
             *
             * @param {String} text
             * @return {Array} list of URLs
             */
            findLinks: function (text)
            {
                // @fixme match this to core code
                var re = /(?:^| )(https?:\/\/.+?\/.+?)(?= |$)/mg;
                var links = [];
                var matches;
                while ((matches = re.exec(text)) !== null) {
                    links.push(matches[1]);
                }
                return links;
            },

            ensureArea: function() {
                if (form.find('.link-preview').length < 1) {
                    form.append('<div class="notice-status link-preview thumbnails"></div>');
                }
            },

            /**
             * Start looking up info for a link preview...
             * May start async data loads.
             *
             * @param {number} col: column number to insert preview into
             */
            prepLinkPreview: function(col)
            {
                var id = 'link-preview-' + col;
                var url = LinkPreview.links[col];
                LinkPreview.refresh[col] = false;
                LinkPreview.markLoading(col);

                oEmbed.lookup(url, function(data) {
                    var thumb = null;
                    var width = 100;
                    if (data && typeof data.thumbnail_url == "string") {
                        thumb = data.thumbnail_url;
                        if (typeof data.thumbnail_width !== "undefined") {
                            if (data.thumbnail_width < width) {
                                width = data.thumbnail_width;
                            }
                        }
                    } else if (data && data.type == 'photo' && typeof data.url == "string") {
                        thumb = data.url;
                        if (typeof data.width !== "undefined") {
                            if (data.width < width) {
                                width = data.width;
                            }
                        }
                    }

                    if (thumb) {
                        LinkPreview.ensureArea();
                        var link = $('<span class="inline-attachment"><a><img/></a></span>');
                        link.find('a')
                                .attr('href', url)
                                .attr('target', '_blank')
                                .last()
                            .find('img')
                                .attr('src', thumb)
                                .attr('width', width)
                                .attr('title', data.title || data.url || url);
                        form.find('.' + id)
                            .empty()
                            .append(link);
                    } else {
                        // No thumbnail available or error retriving it.
                        LinkPreview.clearLink(col);
                    }

                    if (LinkPreview.refresh[col]) {
                        // Darn user has typed more characters.
                        // Go fetch another link!
                        LinkPreview.prepLinkPreview(col);
                    } else {
                        LinkPreview.markDone(col);
                    }
                });
            },

            /**
             * Update the live preview section with links found in the given text.
             * May start async data loads.
             *
             * @param {String} text: free-form input text
             */
            previewLinks: function(text)
            {
                var i;
                var old = LinkPreview.links;
                var links = LinkPreview.findLinks(text);
                LinkPreview.links = links;

                // Check for existing common elements...
                for (i = 0; i < old.length && i < links.length; i++) {
                    if (links[i] != old[i]) {
                        if (LinkPreview.state[i] == "loading") {
                            // Slate this column for a refresh when this one's done.
                            LinkPreview.refresh[i] = true;
                        } else {
                            // Change an existing entry!
                            LinkPreview.prepLinkPreview(i);
                        }
                    }
                }
                if (links.length > old.length) {
                    // Adding new entries, whee!
                    for (i = old.length; i < links.length; i++) {
                        LinkPreview.addPreviewArea(i);
                        LinkPreview.prepLinkPreview(i);
                    }
                } else if (old.length > links.length) {
                    // Remove preview entries for links that have been removed.
                    for (i = links.length; i < old.length; i++) {
                        LinkPreview.clearLink(i);
                    }
                }
                if (links.length == 0) {
                    LinkPreview.clear();
                }
            },

            addPreviewArea: function(col) {
                LinkPreview.ensureArea();
                var id = 'link-preview-' + col;
                if (form.find('.' + id).length < 1) {
                    form.find('.link-preview').append('<span class="' + id + '"></span>');
                }
            },

            clearLink: function(col) {
                var id = 'link-preview-' + col;
                form.find('.' + id).html('');
            },

            markLoading: function(col) {
                LinkPreview.state[col] = "loading";
                var id = 'link-preview-' + col;
                form.find('.' + id).attr('style', 'opacity: 0.5');
            },

            markDone: function(col) {
                LinkPreview.state[col] = "done";
                var id = 'link-preview-' + col;
                form.find('.' + id).removeAttr('style');
            },

            /**
             * Clear out any link preview data.
             */
            clear: function() {
                LinkPreview.links = [];
                form.find('.link-preview').remove();
            }
        };
        form.data('LinkPreview', LinkPreview);
    }
})();
