PHPDOC = phpdoc
PHP = php

DOC_OPTIONS = -ed docs/examples -o HTML:frames:earthli -ti Dormio -dn dormio

all: build

build: api_docs check
	@echo "Ready to install"

install:
	@echo "DESTDIR: ${DESTDIR}"
	@echo "No installer yet"

dev_docs: classes tests/example_tests.php
	${PHPDOC} ${DOC_OPTIONS} -pp -d docs,$< -t $@
  
api_docs: classes tests/example_tests.php
	${PHPDOC} ${DOC_OPTIONS} -d docs,$< -t $@

remote-docs: api_docs
	rsync -r $< tris@tfconsulting.com.au:~/public_html/dormio/
  
check: tests/all_tests.php tests/example_tests.php
  
tests/%.php: .FORCE
	${PHP} $@
  
dormio-%.tar.gz:
	git archive $* | gzip > $@
  
.FORCE:

clean:
	rm -rf api_docs dev_docs