PHPDOC = phpdoc
PHP = php
VERSION = 0.3.1

TESTS = tests/all_tests.php tests/example_tests.php tests/bantam_tests.php

DOC_OPTIONS = -ed docs/examples -o HTML:frames:earthli -ti Dormio -dn dormio

all: build

build: classes/Phorms api-docs
	@echo "Ready to install"

../dormio-${VERSION}.tar.gz: build
	tar zcvf $@ -C .. dormio-${VERSION} --exclude-vcs --exclude vendor

install: build
	install -d ${DESTDIR}/usr/share/php/dormio
	cp -a classes tests docs ${DESTDIR}/usr/share/php/dormio/

	install -d $(DESTDIR)/usr/share/doc/php-dormio
	cp -a api-docs ${DESTDIR}/usr/share/doc/php-dormio/

dev-docs: classes/dormio tests/example_tests.php
	${PHPDOC} ${DOC_OPTIONS} -pp -d docs,$< -t $@
  
api-docs: classes/dormio tests/example_tests.php
	${PHPDOC} ${DOC_OPTIONS} -d docs,$< -t $@

remote-docs: api-docs
	rsync -r $< tris@tfconsulting.com.au:~/public_html/dormio/
  
classes/Phorms: vendor/phorms/src
	rsync -r $</Phorms classes/
  
vendor/phorms/src:
	git submodule update --init vendor/phorms
  
check: ${TESTS}
  
tests/%.php: .FORCE
	${PHP} $@
  
clean:
	rm -rf api-docs dev-docs classes/Phorms

.FORCE:

.PRECIOUS: docs/api docs/dev classes/Phorms
