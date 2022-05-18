start:
	docker-compose up -d --remove-orphans

stop:
	docker-compose stop

init:
	bin/console init-db
	bin/console twitter:followings
	bin/console sniper:snipe

wallet-import:
	bin/console sniper:import

wallet-snipe:
	bin/console sniper:snipe

twitter-import:
	bin/console twitter:import
	bin/console twitter:followings

twitter-followings:
	bin/console twitter:followings
