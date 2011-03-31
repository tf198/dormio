PHPDOC = phpdoc
PHP = php

DOC_OPTIONS = -ed docs/examples -o HTML:frames:earthli -ti Dormio -dn dormio

all: build

build: classes/Phorms docs/api
	@echo "Ready to install"

install: build
	@echo "DESTDIR: ${DESTDIR}"
	@echo "No installer yet"

docs/dev: classes/dormio tests/example_tests.php
	${PHPDOC} ${DOC_OPTIONS} -pp -d docs,$< -t $@
  
docs/api: classes/dormio tests/example_tests.php
	${PHPDOC} ${DOC_OPTIONS} -d docs,$< -t $@

remote-docs: docs/api
	rsync -r $< tris@tfconsulting.com.au:~/public_html/dormio/
  
classes/Phorms: vendor/phorms/src
	rsync -r $</Phorms classes/
  
vendor/phorms/src:
	git submodule update --init vendor/phorms
  
check: tests/all_tests.php tests/example_tests.php
  
tests/%.php: .FORCE
	${PHP} $@
  
dormio-%.tar.gz:
	git archive $* | gzip > $@
  
.FORCE:

clean:
	rm -rf docs/api docs/dev