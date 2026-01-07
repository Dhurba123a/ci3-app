###################
STEP - 1 : Executing the Github Actions For Testing
###################

So, As of now, i've created the .git/workflows/github-actions-demo.yml file with below content

```
name: GitHub Actions Demo
run-name: ${{ github.actor }} is testing out GitHub Actions
on: [push]

jobs:
  Explore-GitHub-Actions:
    runs-on: ubuntu-latest

    steps:
      - run: echo "The job was automatically triggered by a ${{ github.event_name }} event."
      - run: echo "This job is running on a ${{ runner.os }} server hosted by GitHub."
      - run: echo "Branch: ${{ github.ref }} | Repo: ${{ github.repository }}"

      - name: Check out repository code
        uses: actions/checkout@v4

      - run: echo "Repository has been cloned to the runner."

      - name: List files in the repository
        run: |
          ls -la ${{ github.workspace }}

      - run: echo "Job status: ${{ job.status }}"
```

###############
STEP -2 : Deploying production leve code
###############
So, Below is the code which will run on Github Actions

```
name: GitHub Actions Demo
run-name: ${{ github.actor }} is testing out GitHub Actions

on:
  push:
    branches:
      - main

jobs:
  Explore-GitHub-Actions:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout Repository
        uses: actions/checkout@v4

      - name: CI Docker Build Validation
        run: docker build -t ci3-app-test .

      - name: Deploy to EC2 (Docker Compose)
        uses: appleboy/ssh-action@v1.0.3
        with:
          host: ${{ secrets.EC2_HOST }}
          username: ${{ secrets.EC2_USER }}
          key: ${{ secrets.EC2_SSH_KEY }}
          script: |
            cd /home/ubuntu/ci3-app
            git pull origin main
            docker compose down
            docker compose up -d --build

```
##############
Checking the updates, From dev-server branch
##############
