ADMIN_GUIDE=admin-guide
DEVEL_GUIDE=devel-guide

all:
	@echo No can do.  Read the Makefile.

$(ADMIN_GUIDE): $(ADMIN_GUIDE)-html-multiple

$(DEVEL_GUIDE): $(DEVEL_GUIDE)-html-multiple

	
$(ADMIN_GUIDE)-html-single:
	xsltproc -o $(ADMIN_GUIDE).html /usr/share/sgml/docbook/xsl-stylesheets/html/docbook.xsl $(ADMIN_GUIDE).xml

$(ADMIN_GUIDE)-html-multiple:
	if [ ! -d $(ADMIN_GUIDE) ] ; then mkdir $(ADMIN_GUIDE); fi
	cd $(ADMIN_GUIDE); xsltproc /usr/share/sgml/docbook/xsl-stylesheets/html/chunk.xsl ../$(ADMIN_GUIDE).xml


$(DEVEL_GUIDE)-html-single:
	xsltproc -o $(DEVEL_GUIDE).html /usr/share/sgml/docbook/xsl-stylesheets/html/docbook.xsl $(DEVEL_GUIDE).xml

$(DEVEL_GUIDE)-html-multiple:
	if [ ! -d $(DEVEL_GUIDE) ] ; then mkdir $(DEVEL_GUIDE); fi
	cd $(DEVEL_GUIDE); xsltproc /usr/share/sgml/docbook/xsl-stylesheets/html/chunk.xsl ../$(DEVEL_GUIDE).xml

