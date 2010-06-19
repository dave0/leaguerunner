var h2count = 0;
var h3count = new Array();
var asciistart  = 64;
$(document).ready(function() {
	$("h2, h3").each(function(i) {
	    var current = $(this);
	    var linkId;
	    if(current.attr("tagName") == 'H2') {
		h2count++;
		linkId = unescape('%' + (asciistart + h2count).toString(16));
	    } else if (current.attr("tagName") == 'H3') {
	        if( h2count == 0) {
			h2count = 1;
		}
	    	if( h2count in h3count ) {
			h3count[h2count]++;
		} else {
			h3count[h2count] = 1;
		}
		linkId = unescape('%' + (asciistart + h2count).toString(16)) + '.' + h3count[h2count];
	    }
	    current.attr("id", linkId);
	    current.text(linkId + '. ' + current.text());
	    $("#toc").append("<a id='link" + i + "' href='#" + linkId + "' title='" + current.attr("tagName") + "'>" + 
		current.html() + "</a>");
	});
});
