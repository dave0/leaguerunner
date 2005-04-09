// These are (slightly) edited from original Google Maps page (versions 5 & 6).

_m = null; // Main app
_sf = 'hl=en'; // Locale?

function _load(xml, doc) {
    _m.loadXML(xml, doc);
}

function _createMap() {
    _m = new _MapsApplication(document.getElementById('map'),
                              document.getElementById('panel'),
                              document.getElementById('metapanel'),
                              document.getElementById('linktopage'),
                              document.getElementById('toggle'));
    
    _m.loadMap(null);
}

_mSiteName = 'Google Maps (Unofficial Standalone Viewer)';
