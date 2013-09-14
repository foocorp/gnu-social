var Bookmark = {

    // Special XHR that sends in some code to be run
    // when the full bookmark form gets loaded
    BookmarkXHR: function(form)
    {
        SN.U.FormXHR(form, Bookmark.InitBookmarkForm);
        return false;
    },

    // Special initialization function just for the
    // second step in the bookmarking workflow
    InitBookmarkForm: function() {
        SN.Init.CheckBoxes();
        $('fieldset fieldset label').inFieldLabels({ fadeOpacity:0 });
        SN.Init.NoticeFormSetup($('#form_new_bookmark'));
    }
}

$(document).ready(function() {

    // Stop normal live event stuff
    $(document).off("click", "form.ajax");
    $(document).off("submit", "form.ajax input[type=submit]");

    // Make the bookmark submit super special
    $(document).on('submit', '#form_initial_bookmark', function (e) {
        Bookmark.BookmarkXHR($(this));
        e.stopPropagation();
        return false;
    });

    // Restore live event stuff to other forms & submit buttons
    SN.Init.AjaxForms();

});
