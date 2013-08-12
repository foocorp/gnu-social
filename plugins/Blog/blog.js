(function() {
    var origInit = SN.Init.NoticeFormSetup;
    SN.Init.NoticeFormSetup = function(form) {
        origInit(form);
        var content = form.find("#blog-entry-content");
        if (content.length > 0) {
            content.tinymce({
                script_url : window._tinymce_path,
                // General options
                theme : "advanced",
                plugins : "paste,fullscreen,autoresize,autolink,inlinepopups,tabfocus",
                theme_advanced_buttons1 : "bold,italic,strikethrough,|,undo,redo,|,link,unlink,image",
                theme_advanced_buttons2 : "",
                theme_advanced_buttons3 : "",
                add_form_submit_trigger : false,
                theme_advanced_resizing : true,
                tabfocus_elements: ":prev,:next",
                setup: function(ed) {

                    form.find('.submit:first').click(function() {
                        tinymce.triggerSave();
                    });

                    form.find('input[type=file]').change(function() {
                        var img = '<img src="'+window._tinymce_placeholder+'" class="placeholder" width="320" height="240">';
                        var html = tinyMCE.activeEditor.getContent();
                        ed.setContent(html + img);
                    });
                }
            });
        }
    };
})();