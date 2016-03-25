WEB?=/var/www/html/OsmHiCheck

PHP_FILES=index.html uploads
CSS_FILES=css/stylesheet.css css/leaflet
JS_FILES=js/jquery.jeditable.js js/jquery-1.11.2.min.js js/OsmHiCheck.js js/leaflet
IMAGE_FILES=images/screen.png

install: 
	rsync -vap $(PHP_FILES) $(WEB)/
	rsync -vap $(IMAGE_FILES) $(WEB)/images
	rsync -vap $(CSS_FILES) $(WEB)/css
	rsync -vap $(JS_FILES) $(WEB)/js
	make -C tables
	make -C map
	make -C gp

