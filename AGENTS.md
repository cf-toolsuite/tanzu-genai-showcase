# AGENTS.md: tanzu-genai-showcase

Rules for coding agents, and people, working in this repo. `README.md` covers what the samples do, `CI.md` the CI
providers, `DEPLOY.md` the Cloud Foundry deploy workflow and `SECRETS.md` the secrets it needs.

## What this repo is

Eight independent sample apps that use the GenAI tile on Tanzu Platform for Cloud Foundry. Each lives in its own
top-level folder, has its own toolchain and its own GitHub Actions workflow, and shares nothing with the others.
Keep a change inside the sample it is about.

| Sample | Stack | CI workflow (run from the sample folder) |
|---|---|---|
| `dotnet-extensions-ai` | .NET 9 (`global.json` SDK 9.0.104) | `dotnet restore && dotnet build --no-restore && dotnet test --no-build` |
| `go-fiber-langchaingo` | Go (version from `go.mod`) | `make build` (or `go build -o ./bin/app ./cmd/server`), `go test ./...` |
| `java-spring-ai-mcp` | Java 21, Maven multi-module (`api`, `server`, `client`) | `mvn -B package` |
| `java-spring-langgraph-mcp-angular` | Java 21 Maven backend + Angular frontend | `backend`: `mvn -B package`; `frontend`: `npm install && npm run build -- --configuration production` |
| `js-langchain-react` | Node 22, React | `npm install && npm run build && npm test` |
| `php-symfony-neuron` | PHP 8.3, Symfony 7 (Flex) | `composer validate --strict && composer install --prefer-dist`, then `vendor/bin/phpunit` if `tests/` exists |
| `py-django-crewai` | Python 3.12, Django + CrewAI; Node 26 frontend | `pip install -r requirements.txt && python manage.py test`; `frontend`: `npm install`, then `./build_frontend.sh` |
| `ruby-sinatra-fastmcp` | Ruby 3.3.6, Sinatra | `bundle install && bundle exec rake test` |

The workflow file for a sample is `.github/workflows/<sample>.yml`. It runs on pushes and PRs that touch that folder,
so CI is the definition of passing: mirror its commands, versions and flags when you check a change locally.

## Rules

- **Work through PRs.** Branch from `main`, open a PR, get the sample's workflow green, and stop. A maintainer merges.
- **Commits** use Conventional Commits with the sample as scope where it helps (`fix(php-symfony-neuron): …`).
  Stage explicit paths only, never `git add -A`. No AI co-author trailer.
- **Never commit secrets.** API keys and Cloud Foundry credentials live in GitHub Actions secrets (`SECRETS.md`,
  `create-gh-secrets.sh`). `.env` files are ignored; only `.env.example` is tracked. Don't commit AI tool output
  (`.claude-flow/`, `.mcp.json`, etc.; see `.gitignore`).
- **Other CI providers mirror GitHub Actions.** Each sample keeps GitLab, Bitbucket and Jenkins configs under
  `<sample>/ci/`. When you change a sample's workflow, run `./ci-sync-tool.sh check` and update those copies too.
- **Deploys are manual.** `deploy-to-tp4cf.yml` (and the `deploy-<sample>-to-tp4cf.yml` wrappers) run only on
  `workflow_dispatch` and push to a real foundation. Never trigger one without the owner's say-so.
- **Docs move with behaviour.** A change to how a sample builds, is configured or is deployed updates that sample's
  `README.md` (and `DEPLOY.md`/`SECRETS.md` when secrets or the deploy flow change) in the same PR.

## Dependencies

- **Renovate, not Dependabot.** Version updates come from Renovate (`renovate.json`, managed from
  `pacphi/mise-dotfiles`; change it there, not here). There is no `.github/dependabot.yml`; Dependabot security alerts
  stay on. Never close a Renovate PR by hand: that tells Renovate to ignore the update.
- **3-day release age.** Take only versions published at least 3 days ago, except the first patched release for an
  advisory (GitHub Advisory DB, RustSec or OSV), recorded in the PR body.
- **Majors are deliberate.** A major, or a 0.x minor bump (except `golang.org/x/*`), is never taken as part of routine
  updates; it gets its own planned PR.
- **Lockfiles:** `go.sum` is tracked. `composer.lock`, `package-lock.json` and `Gemfile.lock` are gitignored in their
  samples, so constraints resolve at install time. Keep constraints within the current major.

## Known issues

- `java-spring-ai-mcp` and `java-spring-langgraph-mcp-angular` don't compile on `main` with JDK 21 (checked
  2026-11-05): `AviationStackClient.java:116` in the first, missing langgraph4j and Spring AI types in the second's
  backend. Fix those before taking dependency updates for either.
