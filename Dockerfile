FROM ubuntu:16.04
MAINTAINER Dustin Rue <ruedu@dustinrue.com>

RUN apt-get update && apt-get -y install \
  php-cli \
  php-curl

# add https://github.com/krallin/tini so we can ctrl-c on the cli if we want to stop
# execution of this tool
ENV TINI_VERSION v0.10.0
ADD https://github.com/krallin/tini/releases/download/${TINI_VERSION}/tini /tini
RUN chmod +x /tini
ADD gamedvr-archiver.php usr/local/bin/gamedvr-archiver.php
RUN chmod +x usr/local/bin/gamedvr-archiver.php
VOLUME /destination

ENV GAMERTAG <change me>
ENV GAMEDVR y
ENV SCREENSHOTS y

#ENTRYPOINT ["gamedvr-archiver.php"]
ENTRYPOINT ["/tini", "--", "gamedvr-archiver.php"]
