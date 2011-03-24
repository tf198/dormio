PHPDOC = phpdoc
PHP = php

OPTIONS = -ed examples -o HTML:frames:earthli -ti Dormio

all: check docs

docs: dev_docs api_docs

dev_docs: classes tests/example_tests.php
	rm -rf $@
	${PHPDOC} ${OPTIONS} -pp -d $< -t $@
  
api_docs: classes tests/example_tests.php .FORCE
	rm -rf $@
	${PHPDOC} ${OPTIONS} -d $< -t $@

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