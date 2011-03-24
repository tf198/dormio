PHPDOC = phpdoc
PHP = php

OPTIONS = -ed examples -o HTML:frames:default

all: test docs

dev_docs: classes tests/example_tests.php
	rm -rf $@
	${PHPDOC} ${OPTIONS} -pp -d $< -t $@
  
api_docs: classes tests/example_tests.php .FORCE
	rm -rf $@
	${PHPDOC} ${OPTIONS} -d $< -t $@

tests/%.php: .FORCE
	${PHP} $@
  
check: tests/all_tests.php tests/example_tests.php

.FORCE:

clean:
	rm -rf api_docs dev_docs