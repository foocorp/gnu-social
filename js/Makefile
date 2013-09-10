.fake: all clean

TARGETS=util.min.js extlib/json2.min.js
UTIL_SOURCES=util.js xbImportNode.js geometa.js

all: $(TARGETS)

clean:
	rm -f $(TARGETS)

util.min.js: $(UTIL_SOURCES)
	cat $+ | yui-compressor --type js > $@

extlib/json2.min.js: extlib/json2.js
	yui-compressor $+ > $@
