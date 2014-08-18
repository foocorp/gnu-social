.fake: all clean

all: realtimeupdate.min.js

clean:
	rm -f js/realtimeupdate.min.js

realtimeupdate.min.js: js/realtimeupdate.js
	yui-compressor js/realtimeupdate.js > js/realtimeupdate.min.js
