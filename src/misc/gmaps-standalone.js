// Standalone mode specific functions
// Anything not (C)opyright Google is (C)opyright follower@myrealbox.com

function _initStandAlone() {
    //
    // Initialises standalone mode.
    //

    // Call the standard Gmaps routine
    _createMap();

    // Enable off-domain file retrieval
    _hookXmlHttpRequestFactory();

    _loadExternalXmlFile(_getInitialUrl());
}

function _getInitialUrl() {
    //
    // Extracts the initial url.
    //
    // Note: This method is a little crass, but allows for easy passing
    //       of arguments to target scripts.
    //
    //       We could use something like `getArgs()` from:
    //
    //         <http://www.oreilly.com/catalog/jscript3/chapter/ch13.html>
    //
    //       I hereby state any parameter before the "url" parameter we'll
    //       assume is for us, otherwise we'll include it as part of the URL.
    //
 //   var urlOffset = document.location.search.indexOf("url=") + "url=".length;
//    return document.location.search.substring(urlOffset);
    return 'http://testing.ocua.ca/leaguerunner/gmaps/allfields';
}

// var URL_GETFILE_CGI = "getrawfile.cgi?url=";
var URL_GETFILE_CGI = "http://testing.ocua.ca/leaguerunner/gmaps/getfile/";

function _hookXmlHttpRequestFactory() {
    //
    // Enables off-domain file retrieval by sending all requests
    // via our on-domain file retrieval helper script.
    //
    // Note: Mozilla based browsers can handle wrapping just the `open`
    // method but IE requires us to wrap the whole object, so
    // in order to have only one code version we just wrap the whole
    // object for everyone.
    //

    _XMLHttp._create = _XMLHttp.create;

    _XMLHttp.create = function () {
        return new _XmlHttpRequestEx2(this._create());
        }
}

function _XmlHttpRequestEx2(proxee) {
    //
    // Allows off-site document retrieval with assistance of
    // a script on the host server.
    //
    this._proxee = proxee;
}

_XmlHttpRequestEx2.prototype.open = function (method, url) {
    //
    //
    //
    if (url[0] == "/") {
        // Patch Gmaps urls
        url = "http://maps.google.com" + url;
    }
    url = URL_GETFILE_CGI + url;
    return this._proxee.open(method, url);
}
    
_XmlHttpRequestEx2.prototype.send = function (content) {
    //
    //
    //
    
    // TODO: Can we do this automatically?
    this._proxee.onreadystatechange = _wrapCallback(this);
    return this._proxee.send(content);
}

_wrapCallback = function (_this) {
    //
    // Wrap the caller supplied callback so we prepare items
    // before calling it proper.
    //
    return function () {
        _this.readyState = _this._proxee.readyState;
        if (_this.readyState == 4) { // TODO: Use constant.
            _this.responseXML = _this._proxee.responseXML;
            _this.responseText = _this._proxee.responseText;
            }
        _this.onreadystatechange();
        }
}
        

function _loadExternalXmlFile(externalUrl) {
    //
    // Loads the specified external XML file into the map view.
    //
    
    var  _getter = _XMLHttp.create();

    _getter.open("GET", externalUrl);

    _getter.onreadystatechange=function() {
      if (_getter.readyState==4) {
           _load(_getter.responseText);
      }
    }

    _getter.send(null); // Whoops, IE *needs* this to be last, Moz is lenient.
}

_mGoogleCopy += '&nbsp;<span style="color:white;background:red;font-weight:bold;">&nbsp;(Note: <a href="/leaguerunner/misc/gmaps-disclaimer.html">Standalone mode</a> is not an authorised service.)&nbsp;</span>'



