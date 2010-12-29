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

function scalePhotosToSize(size) {
    $('.photoingallery, .albumingallery').each(function(index) {
            if(this.height > this.width) {
                this.width = this.width*size/this.height;
                this.height = size;
            }
            else {
                this.height = this.height*size/this.width;
                this.width = size;
            }
        });
    return false;
}