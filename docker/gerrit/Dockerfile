FROM gerritcodereview/gerrit

COPY gerrit.config /var/gerrit/etc/gerrit.confg

USER gerrit
RUN java -jar /var/gerrit/bin/gerrit.war init --batch -d /var/gerrit
