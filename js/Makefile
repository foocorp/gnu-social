.fake: all clean

TARGETS=util.min.js extlib/json2.min.js extlib/jquery.cookie.min.js

all: $(TARGETS)

clean:
	rm -f $(TARGETS)

extlib/json2.min.js: extlib/json2.js
	yui-compressor $+ > $@

extlib/jquery.cookie.min.js: extlib/jquery.cookie.js
	yui-compressor $+ > $@
