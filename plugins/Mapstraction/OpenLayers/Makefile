.fake: clean all

TARGET=OpenLayers.js
SOURCEDIR=OpenLayers-2.10/
HERE=`pwd`

all: $(TARGET)

clean:
	rm -f $(TARGET)

$(TARGET): statusnet.cfg
	cp -f statusnet.cfg $(SOURCEDIR)/build/statusnet.cfg
	(cd $(SOURCEDIR)/build && ./build.py statusnet.cfg)
	yui-compressor $(SOURCEDIR)/build/OpenLayers.js -o $(TARGET)
