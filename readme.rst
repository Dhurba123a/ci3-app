###################
Executing the Github Actions For Testing
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
