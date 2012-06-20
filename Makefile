PHPDOC = phpdoc
PHPUNIT=  phpunit
PHP = php
VERSION = 0.8.1

#DOC_OPTIONS = -ed docs/examples -o HTML:frames:earthli -ti Dormio -dn Dormio
DOC_OPTIONS = 

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
  
check: tests/data/entities.sql docs/examples/entities.sql
	${PHPUNIT}

%/entities.sql: %/entities.php
	${PHP} tools/generate_sql.php $< > $@
  
clean:
	rm -rf docs/api docs/dev

.FORCE:

.PRECIOUS: docs/api docs/dev
