## Startup
```
cd docker
docker-compose up --build -d mysql
# uncomment ENTRYPOINT line in docker/gerrit/Dockerfile
docker-compose up gerrit
# Ctrl+C when initialized
# commend back the ENTRYPOINT line
docker-compose up --build -d gerrit
```
