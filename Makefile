GETTEXT_KEYWORDS= \
	--keyword=__ --keyword=_x:1,2c --keyword=_e \
	--keyword=esc_html__ --keyword=esc_html_x:1,2c --keyword=esc_html_e \
	--keyword=esc_attr__ --keyword=esc_attr_x:1,2c --keyword=esc_attr_e

build:
	# Translations
	xgettext --from-code=utf-8 ${GETTEXT_KEYWORDS} -o languages/media-download.pot *.php

	# Zipball
	rm -f media-download.zip
	zip -r -9 media-download.zip lib languages *.php *.css *.js readme.txt
deploy: build
	scp media-download.zip root@codeseekah.com:/home/soulseekah/www/media-download.zip
	echo https://codeseekah.com/media-download.zip
test:
	vendor/bin/phpunit --coverage-text
