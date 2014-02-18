all:
	cp -r * /var/www/html

start:
	sudo systemctl start httpd.service
