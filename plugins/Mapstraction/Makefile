.fake: clean all

TARGETS=usermap-mxn-openlayers.min.js

CORE=js/mxn.js js/mxn.core.js
USERMAP=usermap.js

all: $(TARGETS)

clean:
	rm -f $(TARGETS)

usermap-mxn-openlayers.min.js: $(CORE) js/mxn.openlayers.core.js $(USERMAP)
	cat $+ | yui-compressor -o $@ --type=js
