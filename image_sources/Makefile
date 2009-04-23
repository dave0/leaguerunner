# Uses rasterizer from the Batik project (libbatik-java package in Debian Lenny)
RASTERIZER=/usr/bin/rasterizer

png:
	$(RASTERIZER) -m image/png -w 20 -h 20 -d ../src/image/icons *.svg

.PHONY: png
