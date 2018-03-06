## Startup
```
cd docker
docker-compose up --build -d mysql
# uncomment ENTRYPOINT line in docker/gerrit/Dockerfile
docker-compose up gerrit
# Ctrl+C when initialized
# commend back the ENTRYPOINT line
docker-compose up --build -d gerrit
# wait until app starts
docker logs labcr-gerrit
docker-compose up --build -d
```

1. Create new `labcr` project with initial empty commit.
1. Go to `All-Projects` Access tab and remove `Read` persmission for
   Anonymous users. Add `Read` permission for Registered users. 
1. Create an account for students:
    ```
    username: student
    email: student@student.agh.edu.pl
    ```
1. Go to Preferences / HTTP password and generate one that does not contain
`/` or `+` characters.
1. Put it in `get-change.php` constant.
1. Adjust the rest of constants in this script.
1. Run `php get-change.php` once to see if it works.
