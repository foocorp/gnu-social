.fake: all clean

TARGETS=util.min.js json2.min.js
UTIL_SOURCES=util.js xbImportNode.js geometa.js

all: $(TARGETS)

clean:
	rm -f $(TARGETS)

util.min.js: $(UTIL_SOURCES)
	cat $+ | yui-compressor --type js > $@

json2.min.js: json2.js
	yui-compressor $+ > $@
