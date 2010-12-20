.fake: all clean

TARGETS=util.min.js

all: $(TARGETS)

clean:
	rm -f $(TARGETS)

util.min.js: util.js
	yui-compressor $< -o $@
