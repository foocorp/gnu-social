function increasePhotoSize() {
    $('.photoingallery, .albumingallery').each(function(index) {
            this.height *= 1.1;
            this.width *= 1.1;
        });
    return false;
}

function decreasePhotoSize() {
    $('.photoingallery, .albumingallery').each(function(index) {
            this.height /= 1.1;
            this.width /= 1.1;
        });
    return false;
}
