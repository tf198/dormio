PHPDOC = phpdoc
PHPUNIT=  phpunit
PHP = php
VERSION = 0.8.1

TESTS = tests/all_tests.php tests/example_tests.php

DOC_OPTIONS = -ed docs/examples -o HTML:frames:earthli -ti Dormio -dn Dormio

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

docs/dev: classes/Dormio check
	${PHPDOC} ${DOC_OPTIONS} -pp -d docs,$< -t $@
  
docs/api: vendor/Dormio check
	${PHPDOC} ${DOC_OPTIONS} -d docs,$< -t $@

remote-docs: docs/api
	rsync -r $< tris@tfconsulting.com.au:~/public_html/dormio/
  
check:
	${PHPUNIT}
  
clean:
	rm -rf docs/api docs/dev

.FORCE:

.PRECIOUS: docs/api docs/dev
