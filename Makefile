all: mcstat minecraft_users_

mcstat: mcstat.php mcstat_program.php
	@echo '== Building mcstat =='
	echo '#!/usr/bin/env php' > mcstat
	sed -e '$$d' < mcstat.php >> mcstat
	sed -e '1,3d' < mcstat_program.php >> mcstat
	chmod 755 mcstat

minecraft_users_: mcstat.php minecraft_users_program.php
	@echo '== Building minecraft_users_ =='
	echo '#!/usr/bin/env php' > minecraft_users_
	sed -e '$$d' < mcstat.php >> minecraft_users_
	sed -e '1,3d' < minecraft_users_program.php >> minecraft_users_
	chmod 755 minecraft_users_

clean:
	rm -f mcstat minecraft_users_

test: test/testrunner.sh all
	./test/testrunner.sh

.PHONY: all clean test
