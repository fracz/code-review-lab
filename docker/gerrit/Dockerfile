FROM gerritcodereview/gerrit

RUN git config -f /var/gerrit/etc/gerrit.config database.type "mysql" \
    && git config -f /var/gerrit/etc/gerrit.config database.hostname "mysql" \
    && git config -f /var/gerrit/etc/gerrit.config database.database "reviewdb" \
    && git config -f /var/gerrit/etc/gerrit.config database.username "gerrit" \
    && git config -f /var/gerrit/etc/gerrit.config database.password "ala123"

#ENTRYPOINT java -jar /var/gerrit/bin/gerrit.war init -d /var/gerrit
