.fake: all clean

all: realtimeupdate.min.js

clean:
	rm -f realtimeupdate.min.js

realtimeupdate.min.js: realtimeupdate.js
	yui-compressor realtimeupdate.js > realtimeupdate.min.js
