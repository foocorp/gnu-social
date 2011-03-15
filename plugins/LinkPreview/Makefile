.fake: all clean

TARGETS=linkpreview.min.js

all: $(TARGETS)

clean:
	rm -f $(TARGETS)

linkpreview.min.js: linkpreview.js
	yui-compressor $< -o $@
